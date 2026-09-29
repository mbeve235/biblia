<?php
/**
 * /api/import/validate   POST — valida e gera prévia (upload ou texto colado)
 * /api/import/run        POST — executa um bloco da importação {offset}
 * /api/import/status     GET  — estado da última importação
 */

declare(strict_types=1);

require_once BASE_PATH . '/utils/importador.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $segments[1] ?? '';
$user   = require_editor();

if ($method !== 'POST' || $action === '') {
    json_fail('Endpoint inválido. Use /api/import/validate ou /api/import/run.');
}

csrf_check();

$sessionKey = 'import_session';

// ---------------------------------------------------------------
// VALIDAR — recebe ficheiro ou texto, detecta formato, valida
// ---------------------------------------------------------------
if ($action === 'validate') {
    $raw = '';
    $filename = '';
    $forced = trim((string) ($_POST['format'] ?? '')) ?: null;

    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $err = (int) $_FILES['file']['error'];
        if ($err !== UPLOAD_ERR_OK) json_fail('Falha no upload (código ' . $err . ').');

        $size = (int) $_FILES['file']['size'];
        if ($size < 1) json_fail('Ficheiro vazio.');
        if ($size > UPLOAD_MAX_BYTES) json_fail('Ficheiro demasiado grande (máx. ' . (int) (UPLOAD_MAX_BYTES / 1048576) . ' MB).');

        $ext = strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMPORT_EXT, true)) {
            json_fail('Extensão não permitida ("' . $ext . '"). Use: ' . implode(', ', ALLOWED_IMPORT_EXT));
        }
        $raw = (string) file_get_contents($_FILES['file']['tmp_name']);
        $filename = (string) ($_FILES['file']['name'] ?? '');
    } elseif (isset($_POST['text']) && trim((string) $_POST['text']) !== '') {
        $raw = (string) $_POST['text'];
        $filename = 'texto colado';
    } else {
        json_fail('Envie um ficheiro ou cole o conteúdo no campo de texto.');
    }

    $importer = new Importer();
    try {
        $format = $importer->detectFormat($raw);
        $data = $importer->parse($raw, in_array($forced, ['json', 'csv', 'text'], true) ? $forced : null);
        $report = $importer->validate($data);
    } catch (RuntimeException $e) {
        json_fail('Erro ao interpretar o ficheiro: ' . $e->getMessage());
    }

    if (!$report['ok']) {
        json_ok([
            'detected_format' => $format,
            'valid'   => false,
            'errors'  => $report['errors'],
            'stats'   => $report['stats'],
        ], 'Foram encontrados erros que impedem a importação.');
    }

    // Guarda dados em sessão para a fase de execução
    $_SESSION[$sessionKey] = [
        'data'     => $data,
        'filename' => $filename,
        'format'   => $format,
        'translation_id' => (int) ($_POST['translation_id'] ?? 0),
        'created'  => time(),
    ];

    json_ok([
        'detected_format' => $format,
        'valid'     => true,
        'stats'     => $report['stats'],
        'warnings'  => array_slice($report['warnings'], 0, 30),
        'warnings_total' => count($report['warnings']),
        'preview'   => $report['preview'],
    ], 'Formato detectado: ' . strtoupper($format) . '. Estrutura válida.');
}

// ---------------------------------------------------------------
// RUN — executa blocos de IMPORT_CHUNK versículos por chamada AJAX
// ---------------------------------------------------------------
if ($action === 'run') {
    $sess = $_SESSION[$sessionKey] ?? null;
    if (!$sess || time() - (int) $sess['created'] > 3600) {
        json_fail('Sessão de importação expirada. Valide o ficheiro novamente.', 410);
    }

    $translationId = (int) ($sess['translation_id'] ?? 0);
    $trans = db_one('SELECT id, abbreviation, status FROM translations WHERE id = ?', [$translationId]);
    if (!$trans) json_fail('Tradução não encontrada. Crie a tradução antes de importar.', 404);

    // Cria ou recupera registo de importação
    if (empty($_SESSION[$sessionKey]['import_id'])) {
        $data = $sess['data'];
        $totalVerses = 0;
        $totalChapters = 0;
        foreach ($data['books'] as $b) {
            $totalChapters += count($b['chapters']);
            foreach ($b['chapters'] as $ch) $totalVerses += count($ch['verses']);
        }
        db_exec(
            'INSERT INTO imports (translation_id, user_id, filename, format, status, total_books, total_chapters, total_verses)
             VALUES (?, ?, ?, ?, "running", ?, ?, ?)',
            [$translationId, $user['id'], $sess['filename'], $sess['format'], count($data['books']), $totalChapters, $totalVerses]
        );
        $_SESSION[$sessionKey]['import_id'] = db_last_id();
        $_SESSION[$sessionKey]['accum'] = ['verses' => 0, 'duplicates' => 0, 'errors' => 0];
    }

    $importId  = (int) $_SESSION[$sessionKey]['import_id'];
    $offset    = max(0, (int) (request_data()['offset'] ?? 0));

    $importer = new Importer();
    try {
        $chunkStats = $importer->importChunk($translationId, $sess['data'], $offset);
    } catch (Throwable $e) {
        db_exec('UPDATE imports SET status = "failed" WHERE id = ?', [$importId]);
        log_activity($user['id'], 'import_failed', 'import #' . $importId . ': ' . $e->getMessage());
        json_fail('Erro durante a importação: ' . $e->getMessage(), 500);
    }

    // Acumula estatísticas
    $accum = $_SESSION[$sessionKey]['accum'];
    $accum['verses']     += $chunkStats['verses'];
    $accum['duplicates'] += $chunkStats['duplicates'];
    $accum['errors']     += $chunkStats['errors'];
    $_SESSION[$sessionKey]['accum'] = $accum;

    db_exec(
        'UPDATE imports SET imported_verses = ?, duplicates_count = ?, errors_count = ? WHERE id = ?',
        [$accum['verses'], $accum['duplicates'], $accum['errors'], $importId]
    );

    if (!empty($chunkStats['done'])) {
        // Actualiza contagens globais e estado
        $countVerses = (int) (db_one('SELECT COUNT(*) AS c FROM verses WHERE translation_id = ?', [$translationId])['c'] ?? 0);
        $countBooks  = (int) (db_one('SELECT COUNT(DISTINCT book_id) AS c FROM verses WHERE translation_id = ?', [$translationId])['c'] ?? 0);
        db_exec(
            'UPDATE imports SET status = "completed", finished_at = NOW() WHERE id = ?',
            [$importId]
        );
        db_exec('UPDATE translations SET status = "active" WHERE id = ?', [$translationId]);

        log_activity($user['id'], 'import_completed', 'import #' . $importId . ' — ' . $accum['verses'] . ' versículos novos');

        $warnings = [];
        foreach (db_all('SELECT location, problem FROM import_errors WHERE import_id = ?', [$importId]) as $w) {
            $warnings[] = $w;
        }
        unset($_SESSION[$sessionKey]);

        json_ok([
            'done' => true,
            'report' => [
                'translation'   => $trans['abbreviation'],
                'books'         => $countBooks,
                'verses_total'  => $countVerses,
                'verses_new'    => $accum['verses'],
                'duplicates'    => $accum['duplicates'],
                'errors'        => $accum['errors'],
            ],
        ], 'Importação concluída com sucesso.');
    }

    json_ok([
        'done'      => false,
        'processed' => $chunkStats['processed'] ?? 0,
        'total'     => $chunkStats['total'] ?? 0,
        'verses'    => $accum['verses'],
        'duplicates'=> $accum['duplicates'],
    ]);
}

json_fail('Acção desconhecida: ' . $action, 404);
