<?php
/**
 * /api/highlights           GET — lista marcações do utilizador
 * /api/highlights           POST — cria/actualiza {verse_id, color}
 * /api/highlights/{verseId} DELETE — remove a marcação
 */
declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = require_api_login();
$colors = ['yellow', 'green', 'blue', 'rose'];

try {
    if ($method === 'GET') {
        $rows = db_all('SELECT id, verse_id, color, updated_at FROM verse_highlights WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1000', [$user['id']]);
        json_ok(['highlights' => $rows, 'total' => count($rows)]);
    }

    csrf_check();
    $data = request_data();

    if ($method === 'POST') {
        $verseId = (int) ($data['verse_id'] ?? 0);
        $color = (string) ($data['color'] ?? '');
        if ($verseId < 1 || !in_array($color, $colors, true)) json_fail('Versículo ou cor inválida.');
        if (!db_one('SELECT id FROM verses WHERE id = ?', [$verseId])) json_fail('Versículo não encontrado.', 404);
        db_exec(
            'INSERT INTO verse_highlights (user_id, verse_id, color) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE color = VALUES(color), updated_at = CURRENT_TIMESTAMP',
            [$user['id'], $verseId, $color]
        );
        json_ok(['verse_id' => $verseId, 'color' => $color], 'Marcação guardada.');
    }

    if ($method === 'DELETE' || ($data['_method'] ?? '') === 'DELETE') {
        $verseId = (int) ($segments[1] ?? 0);
        if ($verseId < 1) json_fail('Identificador ausente.');
        db_exec('DELETE FROM verse_highlights WHERE user_id = ? AND verse_id = ?', [$user['id'], $verseId]);
        json_ok(['verse_id' => $verseId, 'color' => null], 'Marcação removida.');
    }
} catch (PDOException $e) {
    json_fail('As marcações ainda não estão instaladas. Execute a migração 20261022_verse_highlights.sql.', 503);
}

json_fail('Método não suportado.', 405);
