<?php
/**
 * /api/notes               GET — notas do utilizador (ou ?verse_id= para uma nota)
 * /api/notes               POST — cria/actualiza {verse_id, note}
 * /api/notes/{id}          DELETE — elimina nota
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user   = require_api_login();

$SEL = 'SELECT n.id, n.verse_id, n.note, n.created_at, n.updated_at,
               v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug,
               t.abbreviation AS translation
        FROM notes n
        JOIN verses v ON v.id = n.verse_id
        JOIN chapters c ON c.id = v.chapter_id
        JOIN books b ON b.id = v.book_id
        JOIN translations t ON t.id = v.translation_id';

if ($method === 'GET') {
    if (isset($_GET['verse_id'])) {
        $note = db_one($SEL . ' WHERE n.user_id = ? AND n.verse_id = ?', [$user['id'], (int) $_GET['verse_id']]);
        json_ok(['note' => $note]);
    }
    $rows = db_all($SEL . ' WHERE n.user_id = ? ORDER BY n.updated_at DESC LIMIT 500', [$user['id']]);
    foreach ($rows as &$r) {
        $r['reference'] = $r['book_name'] . ' ' . $r['chapter_number'] . ':' . $r['verse_number'];
        $r['excerpt'] = excerpt($r['text'], 120);
        $r['updated_at_fmt'] = fmt_datetime($r['updated_at']);
    }
    unset($r);
    json_ok(['notes' => $rows, 'total' => count($rows)]);
}

csrf_check();
$d = request_data();

if ($method === 'POST') {
    $noteText = trim((string) ($d['note'] ?? ''));
    if ($noteText === '') json_fail('O texto da nota não pode estar vazio.');
    if (mb_strlen($noteText) > 5000) json_fail('A nota é demasiado longa (máx. 5000 caracteres).');

    // Actualização directa por id da nota (página Notas)
    $noteId = (int) ($d['id'] ?? 0);
    if ($noteId > 0) {
        db_exec('UPDATE notes SET note = ? WHERE id = ? AND user_id = ?', [$noteText, $noteId, $user['id']]);
        json_ok(['id' => $noteId], 'Nota actualizada.');
    }

    // Criação/actualização por versículo (leitor)
    $verseId = (int) ($d['verse_id'] ?? 0);
    if ($verseId < 1) json_fail('verse_id é obrigatório.');

    $exists = db_one('SELECT id FROM notes WHERE user_id = ? AND verse_id = ?', [$user['id'], $verseId]);
    if ($exists) {
        db_exec('UPDATE notes SET note = ? WHERE id = ?', [$noteText, $exists['id']]);
        json_ok(['id' => (int) $exists['id']], 'Nota actualizada.');
    }
    db_exec('INSERT INTO notes (user_id, verse_id, note) VALUES (?, ?, ?)', [$user['id'], $verseId, $noteText]);
    json_ok(['id' => db_last_id()], 'Nota guardada.');
}

if ($method === 'DELETE' || ($d['_method'] ?? '') === 'DELETE') {
    $id = (int) ($segments[1] ?? 0);
    if ($id < 1) json_fail('Identificador ausente.');
    db_exec('DELETE FROM notes WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    json_ok(null, 'Nota eliminada.');
}

json_fail('Método não suportado.', 405);
