<?php
/**
 * /api/history             GET — histórico do utilizador
 * /api/history/last        GET — última leitura (para "Continuar a leitura")
 * /api/history             POST — regista leitura {translation, book_slug, chapter}
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user   = require_api_login();

if ($method === 'GET' && ($segments[1] ?? '') === 'last') {
    $last = db_one(
        'SELECT h.chapter_number, h.read_at, b.name AS book_name, b.slug, t.abbreviation AS translation
         FROM reading_history h
         JOIN books b ON b.id = h.book_id
         JOIN translations t ON t.id = h.translation_id
         WHERE h.user_id = ?
         ORDER BY h.read_at DESC
         LIMIT 1',
        [$user['id']]
    );
    json_ok(['last' => $last]);
}

if ($method === 'GET') {
    $rows = db_all(
        'SELECT h.chapter_number, h.read_at, b.name AS book_name, b.slug,
                t.abbreviation AS translation
         FROM reading_history h
         JOIN books b ON b.id = h.book_id
         JOIN translations t ON t.id = h.translation_id
         WHERE h.user_id = ?
         ORDER BY h.read_at DESC
         LIMIT 100',
        [$user['id']]
    );
    foreach ($rows as &$r) {
        $r['reference'] = $r['book_name'] . ' ' . $r['chapter_number'];
        $r['read_at_fmt'] = fmt_datetime($r['read_at']);
    }
    unset($r);
    json_ok(['history' => $rows, 'total' => count($rows)]);
}

csrf_check();
if ($method === 'POST') {
    $d = request_data();
    $slug = trim((string) ($d['book_slug'] ?? ''));
    $chapter = (int) ($d['chapter'] ?? 0);
    $abbr = trim((string) ($d['translation'] ?? ''));
    if ($slug === '' || $chapter < 1 || $abbr === '') {
        json_fail('translation, book_slug e chapter são obrigatórios.');
    }
    $book = db_one('SELECT id FROM books WHERE slug = ?', [$slug]);
    $trans = db_one('SELECT id FROM translations WHERE abbreviation = ?', [$abbr]);
    if (!$book || !$trans) json_fail('Livro ou tradução inválidos.', 404);

    db_exec(
        'INSERT INTO reading_history (user_id, translation_id, book_id, chapter_number) VALUES (?, ?, ?, ?)',
        [$user['id'], $trans['id'], $book['id'], $chapter]
    );
    json_ok(null, 'Leitura registada.');
}

json_fail('Método não suportado.', 405);
