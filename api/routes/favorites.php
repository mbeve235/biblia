<?php
/**
 * /api/favorites           GET — lista favoritos do utilizador
 * /api/favorites           POST — adiciona {verse_id}
 * /api/favorites/{id}      DELETE — remove (id = id do favorito ou verse_id)
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user   = require_api_login();

$SEL = 'SELECT f.id, f.verse_id, f.created_at, v.verse_number, v.text,
               c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
        FROM favorites f
        JOIN verses v ON v.id = f.verse_id
        JOIN chapters c ON c.id = v.chapter_id
        JOIN books b ON b.id = v.book_id
        JOIN translations t ON t.id = v.translation_id';

if ($method === 'GET') {
    $rows = db_all($SEL . ' WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 500', [$user['id']]);
    foreach ($rows as &$r) {
        $r['reference'] = $r['book_name'] . ' ' . $r['chapter_number'] . ':' . $r['verse_number'];
        $r['created_at_fmt'] = fmt_datetime($r['created_at']);
    }
    unset($r);
    json_ok(['favorites' => $rows, 'total' => count($rows)]);
}

csrf_check();
$d = request_data();

if ($method === 'POST') {
    $verseId = (int) ($d['verse_id'] ?? 0);
    if ($verseId < 1) json_fail('verse_id é obrigatório.');

    $verse = db_one(
        'SELECT v.id, v.verse_number, c.chapter_number, b.name AS book_name
         FROM verses v JOIN chapters c ON c.id = v.chapter_id JOIN books b ON b.id = v.book_id
         WHERE v.id = ?',
        [$verseId]
    );
    if (!$verse) json_fail('Versículo não encontrado.', 404);

    $exists = db_one('SELECT id FROM favorites WHERE user_id = ? AND verse_id = ?', [$user['id'], $verseId]);
    if ($exists) json_ok(['favorited' => true, 'id' => (int) $exists['id']], 'Já estava nos favoritos.');

    db_exec('INSERT INTO favorites (user_id, verse_id) VALUES (?, ?)', [$user['id'], $verseId]);
    json_ok(['favorited' => true, 'id' => db_last_id()], 'Adicionado aos favoritos.');
}

if ($method === 'DELETE' || ($d['_method'] ?? '') === 'DELETE') {
    $id = (int) ($segments[1] ?? 0);
    if ($id < 1) json_fail('Identificador ausente.');
    // Aceita tanto o id do favorito como o id do versículo
    db_exec('DELETE FROM favorites WHERE user_id = ? AND (id = ? OR verse_id = ?)', [$user['id'], $id, $id]);
    json_ok(['favorited' => false], 'Removido dos favoritos.');
}

json_fail('Método não suportado.', 405);
