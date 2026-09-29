<?php
/**
 * /api/bible/{traducao}/{slug-livro}/{capitulo}  GET — capítulo completo
 * /api/bible/{traducao}/{slug-livro}/{capitulo}/{versiculo} GET — versículo único
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') json_fail('Método não suportado.', 405);

$abbr = (string) ($segments[1] ?? '');
$bookSlug = (string) ($segments[2] ?? '');
$chapter = (int) ($segments[3] ?? 0);
$verseNum = isset($segments[4]) ? (int) preg_replace('/\D/', '', $segments[4]) : 0;

if ($abbr === '' || $bookSlug === '' || $chapter < 1) {
    json_fail('Use o formato: /api/bible/{traducao}/{livro}/{capitulo}');
}

$trans = db_one('SELECT id, name, abbreviation, status FROM translations WHERE abbreviation = ?', [$abbr]);
if (!$trans || ($trans['status'] !== 'active' && !is_editor())) {
    json_fail('Tradução não encontrada: ' . $abbr, 404);
}

$book = db_one('SELECT * FROM books WHERE slug = ? OR name = ? LIMIT 1', [$bookSlug, $bookSlug]);
if (!$book) json_fail('Livro não encontrado: ' . $bookSlug, 404);

$chapterRow = db_one(
    'SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?',
    [(int) $book['id'], $chapter]
);
if (!$chapterRow) json_fail('Capítulo inexistente: ' . $book['name'] . ' ' . $chapter, 404);

if ($verseNum > 0) {
    $verse = db_one(
        'SELECT v.verse_number, v.text FROM verses v
         WHERE v.translation_id = ? AND v.chapter_id = ? AND v.verse_number = ?',
        [(int) $trans['id'], (int) $chapterRow['id'], $verseNum]
    );
    if (!$verse) json_fail('Versículo não encontrado: ' . $book['name'] . ' ' . $chapter . ':' . $verseNum, 404);
    json_ok(['reference' => $book['name'] . ' ' . $chapter . ':' . $verseNum, 'text' => $verse['text']]);
}

$verses = db_all(
    'SELECT v.verse_number, v.text
     FROM verses v
     WHERE v.translation_id = ? AND v.chapter_id = ?
     ORDER BY v.verse_number',
    [(int) $trans['id'], (int) $chapterRow['id']]
);

// Navegação: capítulo anterior/seguinte
$prev = db_one(
    'SELECT c2.chapter_number AS n, b2.slug AS slug, b2.name AS name
     FROM chapters c1
     JOIN chapters c2 ON c2.book_id = c1.book_id AND c2.chapter_number = c1.chapter_number - 1
     JOIN books b2 ON b2.id = c2.book_id
     WHERE c1.book_id = ? AND c1.chapter_number = ?',
    [(int) $book['id'], $chapter]
);
$next = db_one(
    'SELECT c2.chapter_number AS n, b2.slug AS slug, b2.name AS name
     FROM chapters c1
     JOIN chapters c2 ON c2.book_id = c1.book_id AND c2.chapter_number = c1.chapter_number + 1
     JOIN books b2 ON b2.id = c2.book_id
     WHERE c1.book_id = ? AND c1.chapter_number = ?',
    [(int) $book['id'], $chapter]
);
// Se é o último capítulo do livro, o seguinte é o cap. 1 do livro seguinte
if (!$next) {
    $next = db_one(
        'SELECT 1 AS n, b2.slug AS slug, b2.name AS name
         FROM books b1 JOIN books b2 ON b2.book_order = b1.book_order + 1
         WHERE b1.id = ?',
        [(int) $book['id']]
    );
}

// Livros/capítulos já registados nesta tradução (para o navegadores)
$prevBook = db_one(
    'SELECT b2.slug, b2.name FROM books b1 JOIN books b2 ON b2.book_order = b1.book_order - 1 WHERE b1.id = ?',
    [(int) $book['id']]
);

json_ok([
    'translation' => ['id' => (int) $trans['id'], 'name' => $trans['name'], 'abbreviation' => $trans['abbreviation']],
    'book'        => ['id' => (int) $book['id'], 'name' => $book['name'], 'slug' => $book['slug'], 'chapters' => (int) $book['chapters_count']],
    'chapter'     => $chapter,
    'verses'      => array_map(fn($v) => ['verse' => (int) $v['verse_number'], 'text' => $v['text']], $verses),
    'verses_count'=> count($verses),
    'nav'         => [
        'prev_chapter' => $prev ? ['chapter' => (int) $prev['n'], 'slug' => $prev['slug'], 'name' => $prev['name']] : null,
        'next_chapter' => $next ? ['chapter' => (int) $next['n'], 'slug' => $next['slug'], 'name' => $next['name']] : null,
        'prev_book'    => $prevBook ? ['slug' => $prevBook['slug'], 'name' => $prevBook['name']] : null,
    ],
]);
