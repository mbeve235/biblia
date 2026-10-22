<?php
/**
 * /api/export/translation/{id}?format=json|csv  — exporta uma tradução (editor/admin)
 * /api/export/database                          — backup SQL completo (admin)
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $segments[1] ?? '';
$user   = require_editor();

if ($method !== 'GET') json_fail('Método não suportado.', 405);

function export_download(string $content, string $filename, string $mime): never
{
    header('Content-Type: ' . $mime . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

// ---------------------------------------------------------------
// Exportar tradução
// ---------------------------------------------------------------
if ($action === 'translation') {
    $id = (int) ($segments[2] ?? 0);
    $format = strtolower($_GET['format'] ?? 'json');
    $trans = db_one('SELECT * FROM translations WHERE id = ?', [$id]);
    if (!$trans) json_fail('Tradução não encontrada.', 404);

    $rows = db_all(
        'SELECT b.name AS book, b.abbreviation AS book_abbr, b.testament, c.chapter_number, v.verse_number, v.text
         FROM verses v
         JOIN chapters c ON c.id = v.chapter_id
         JOIN books b ON b.id = v.book_id
         WHERE v.translation_id = ?
         ORDER BY b.book_order, c.chapter_number, v.verse_number',
        [$id]
    );
    if (!$rows) json_fail('Esta tradução não tem versículos para exportar.');

    $safe = preg_replace('/[^a-z0-9\-]/i', '', str_replace(' ', '-', $trans['abbreviation'])) ?: 'traducao';

    if ($format === 'csv') {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['translation', 'book', 'chapter', 'verse', 'text'], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($out, [$trans['abbreviation'], $r['book'], $r['chapter_number'], $r['verse_number'], $r['text']], ',', '"', '\\');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        log_activity($user['id'], 'export_translation', $trans['abbreviation'] . ' CSV');
        export_download("\xEF\xBB\xBF" . $csv, $safe . '.csv', 'text/csv');
    }

    // JSON agrupado por livro/capítulo
    $books = [];
    foreach ($rows as $r) {
        $bn = $r['book'];
        if (!isset($books[$bn])) {
            $books[$bn] = ['name' => $bn, 'abbreviation' => $r['book_abbr'], 'testament' => $r['testament'], 'chapters' => []];
        }
        $cn = (int) $r['chapter_number'];
        if (!isset($books[$bn]['chapters'][$cn])) {
            $books[$bn]['chapters'][$cn] = ['chapter' => $cn, 'verses' => []];
        }
        $books[$bn]['chapters'][$cn]['verses'][] = ['verse' => (int) $r['verse_number'], 'text' => $r['text']];
    }
    $payload = [
        'translation' => [
            'name'         => $trans['name'],
            'abbreviation' => $trans['abbreviation'],
            'language'     => $trans['language'],
            'description'  => $trans['description'],
            'copyright'    => $trans['copyright'],
            'version'      => $trans['version'],
            'source'       => $trans['source'],
        ],
        'books' => array_values(array_map(fn($b) => ['name' => $b['name'], 'abbreviation' => $b['abbreviation'], 'testament' => $b['testament'], 'chapters' => array_values($b['chapters'])], $books)),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    log_activity($user['id'], 'export_translation', $trans['abbreviation'] . ' JSON');
    export_download($json, $safe . '.json', 'application/json');
}

// ---------------------------------------------------------------
// Backup completo da base de dados (admin)
// ---------------------------------------------------------------
if ($action === 'database') {
    if (!is_admin()) json_fail('Acesso restrito ao administrador.', 403);

    $pdo = db();
    $tables = ['users','password_resets','settings','translations','book_groups','books','chapters','verses','favorites','verse_highlights','notes','reading_history','user_settings','imports','import_errors','search_logs','activity_log'];

    $dump = "-- Bíblia Web — backup gerado em " . date('Y-m-d H:i:s') . "\n";
    $dump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        try {
            $create = db_one('SHOW CREATE TABLE ' . DB_NAME . '.' . $t);
        } catch (Throwable $e) {
            continue;
        }
        if (!$create) continue;
        $dump .= 'DROP TABLE IF EXISTS `' . $t . "`;\n";
        $dump .= $create['Create Table'] . ";\n";

        $stmt = $pdo->query('SELECT * FROM `' . $t . '`');
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) >= 200) {
                $dump .= 'INSERT INTO `' . $t . '` VALUES ' . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) $dump .= 'INSERT INTO `' . $t . '` VALUES ' . implode(",\n", $batch) . ";\n";
        $dump .= "\n";
    }
    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    log_activity($user['id'], 'export_database', 'backup completo');
    export_download($dump, 'biblia_web_backup_' . date('Ymd_His') . '.sql', 'application/sql');
}

json_fail('Endpoint de exportação inválido.', 404);
