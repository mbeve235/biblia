<?php
/**
 * /api/translations           GET — lista traduções (públicas activas; admin vê todas)
 * /api/translations           POST — criar (admin/editor)
 * /api/translations/{id}      GET|POST|DELETE — detalhe, actualizar, eliminar
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id     = isset($segments[1]) ? (int) preg_replace('/\D/', '', $segments[1]) : null;

if ($method === 'GET') {
    if ($id) {
        $t = db_one('SELECT * FROM translations WHERE id = ?', [$id]);
        if (!$t) json_fail('Tradução não encontrada.', 404);
        if ($t['status'] !== 'active' && !is_editor()) json_fail('Tradução não encontrada.', 404);
        json_ok(['translation' => $t]);
    }

    $user = current_user();
    if ($user && in_array($user['role'], ['admin', 'editor'], true)) {
        $rows = db_all('
            SELECT t.*,
              (SELECT COUNT(DISTINCT b.id) FROM verses v JOIN books b ON b.id = v.book_id WHERE v.translation_id = t.id) AS books,
              (SELECT COUNT(*) FROM verses v WHERE v.translation_id = t.id) AS verses
            FROM translations t ORDER BY t.name');
        json_ok(['translations' => $rows, 'total' => count($rows)]);
    }

    $rows = db_all(
        'SELECT t.id, t.name, t.abbreviation, t.language, t.status,
           (SELECT COUNT(*) FROM verses v WHERE v.translation_id = t.id) AS verses
         FROM translations t WHERE t.status = "active" ORDER BY t.name'
    );
    json_ok(['translations' => $rows, 'total' => count($rows)]);
}

require_editor();
csrf_check();

if ($method === 'POST') {
    $d = request_data();

    // Actualizar existente: /api/translations/{id} via POST _method=DELETE também suportado
    if ($id) {
        $t = db_one('SELECT id FROM translations WHERE id = ?', [$id]);
        if (!$t) json_fail('Tradução não encontrada.', 404);

        if (($d['_method'] ?? '') === 'DELETE') {
            try {
                db_exec('DELETE FROM translations WHERE id = ?', [$id]);
                log_activity(current_user()['id'], 'translation_delete', '#' . $id);
                json_ok(null, 'Tradução eliminada.');
            } catch (PDOException $e) {
                json_fail('Não foi possível eliminar: ' . $e->getMessage(), 500);
            }
        }

        $fields = ['name', 'abbreviation', 'language', 'description', 'copyright', 'version', 'source', 'status'];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $d)) {
                $sets[] = "$f = ?";
                $params[] = is_string($d[$f]) ? trim($d[$f]) : $d[$f];
            }
        }
        if ($sets) {
            $params[] = $id;
            db_exec('UPDATE translations SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            log_activity(current_user()['id'], 'translation_update', '#' . $id);
        }
        json_ok(['id' => $id], 'Tradução actualizada.');
    }

    // Criar nova tradução
    $name = trim((string) ($d['name'] ?? ''));
    $abbr = trim((string) ($d['abbreviation'] ?? ''));
    if ($name === '' || $abbr === '') {
        json_fail('Nome e abreviação são obrigatórios.');
    }
    if (!preg_match('/^[A-Za-z0-9\-]{1,20}$/u', $abbr)) {
        json_fail('A abreviação deve ter 1-20 caracteres alfanuméricos.');
    }
    if (db_one('SELECT id FROM translations WHERE abbreviation = ?', [$abbr])) {
        json_fail('Já existe uma tradução com esta abreviação.');
    }

    db_exec(
        'INSERT INTO translations (name, abbreviation, language, description, copyright, version, source, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $name,
            $abbr,
            trim((string) ($d['language'] ?? 'Português')),
            trim((string) ($d['description'] ?? '')) ?: null,
            trim((string) ($d['copyright'] ?? '')) ?: null,
            trim((string) ($d['version'] ?? '')) ?: null,
            trim((string) ($d['source'] ?? '')) ?: null,
            ($d['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]
    );
    $newId = db_last_id();
    log_activity(current_user()['id'], 'translation_create', $abbr . ' #' . $newId);
    json_ok(['id' => $newId, 'abbreviation' => $abbr], 'Tradução criada com sucesso.');
}

json_fail('Método não suportado.', 405);
