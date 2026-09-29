<?php
/**
 * /api/books             GET — todos os livros agrupados por categoria/testamento
 * /api/books/{id}        GET — detalhe do livro
 * /api/books/{id}/chapters GET — capítulos disponíveis (com texto nesta tradução)
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') json_fail('Método não suportado.', 405);

if (isset($segments[1]) && ctype_digit($segments[1])) {
    $bookId = (int) $segments[1];

    if (($segments[2] ?? '') === 'chapters') {
        $transId = (int) ($_GET['translation'] ?? 0);
        if ($transId > 0) {
            $rows = db_all(
                'SELECT c.chapter_number,
                        EXISTS(SELECT 1 FROM verses v WHERE v.chapter_id = c.id AND v.translation_id = ?) AS has_verses
                 FROM chapters c WHERE c.book_id = ? ORDER BY c.chapter_number',
                [$transId, $bookId]
            );
        } else {
            $rows = db_all('SELECT c.chapter_number, 1 AS has_verses FROM chapters c WHERE c.book_id = ? ORDER BY c.chapter_number', [$bookId]);
        }
        json_ok(['chapters' => array_map(fn($r) => ['chapter' => (int) $r['chapter_number'], 'has_verses' => (bool) $r['has_verses']], $rows)]);
    }

    $book = db_one(
        'SELECT b.*, g.name AS group_name, g.testament AS group_testament
         FROM books b LEFT JOIN book_groups g ON g.id = b.group_id WHERE b.id = ?',
        [$bookId]
    );
    if (!$book) json_fail('Livro não encontrado.', 404);
    json_ok(['book' => $book]);
}

// Lista completa
$groups = db_all('SELECT * FROM book_groups ORDER BY testament, group_order');
$books  = db_all('SELECT id, name, abbreviation, slug, testament, book_order, chapters_count, group_id FROM books ORDER BY book_order');

$byGroup = [];
foreach ($books as $b) {
    $byGroup[(int) $b['group_id']][] = $b;
}

$out = [];
foreach ($groups as $g) {
    $out[] = [
        'group'     => $g['name'],
        'testament' => $g['testament'],
        'books'     => $byGroup[(int) $g['id']] ?? [],
    ];
}
// Livros sem grupo (segurança)
$orphan = array_values(array_filter($books, fn($b) => $b['group_id'] === null));
if ($orphan) {
    $out[] = ['group' => 'Outros', 'testament' => 'AT', 'books' => $orphan];
}

json_ok(['groups' => $out, 'books' => $books, 'total' => count($books)]);
