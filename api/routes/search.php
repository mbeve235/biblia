<?php
/**
 * /api/search?q=amor&translation=ARC&testament=AT&book=salmos&limit=50
 * Suporta:
 *   - referência: "João 3:16" / "jo 3.16" / "gn1:1"  → devolve o versículo exacto
 *   - frase exacta: "Deus amou o mundo"
 *   - palavras: amor  (todas as palavras têm de existir)
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') json_fail('Método não suportado.', 405);

$q = trim((string) ($_GET['q'] ?? ''));
$transAbbr = trim((string) ($_GET['translation'] ?? ''));
$bookSlug  = trim((string) ($_GET['book'] ?? ''));
$testament = trim((string) ($_GET['testament'] ?? ''));
$limit     = min(200, max(5, (int) ($_GET['limit'] ?? 50)));

if ($q === '') json_fail('Indique um termo de pesquisa (?q=).');
if (mb_strlen($q) > 100) json_fail('Termo de pesquisa demasiado longo.');

// ---------------------------------------------------------------
// 1) Referência directa: "joão 3:16", "jo 3.16", "gn 1 1"
// ---------------------------------------------------------------
if (preg_match('/^([1-3]?\s?[a-zà-ú\.]+\.?)\s*(\d{1,3})\s*[:.\-\s]\s*(\d{1,3})$/iu', $q, $m)) {
    $bookPart = trim(mb_strtolower($m[1]));
    $ch = (int) $m[2];
    $vs = (int) $m[3];

    $book = db_one('SELECT * FROM books WHERE LOWER(abbreviation) = ? LIMIT 1', [$bookPart]);
    if (!$book) $book = db_one('SELECT * FROM books WHERE slug = ? LIMIT 1', [$bookPart]);
    if (!$book) $book = db_one('SELECT * FROM books WHERE LOWER(name) LIKE ? ORDER BY LENGTH(name) LIMIT 1', [$bookPart . '%']);
    if (!$book) $book = db_one('SELECT * FROM books WHERE slug LIKE ? ORDER BY LENGTH(slug) LIMIT 1', [$bookPart . '%']);
    if ($book) {
        $sql = 'SELECT v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
                FROM verses v
                JOIN chapters c ON c.id = v.chapter_id
                JOIN books b ON b.id = v.book_id
                JOIN translations t ON t.id = v.translation_id
                WHERE b.id = ? AND c.chapter_number = ? AND v.verse_number = ? AND t.status = "active"';
        $params = [(int) $book['id'], $ch, $vs];
        if ($transAbbr !== '') { $sql .= ' AND t.abbreviation = ?'; $params[] = $transAbbr; }
        $rows = db_all($sql . ' LIMIT 10', $params);
        if ($rows) {
            json_ok([
                'type' => 'reference',
                'results' => array_map(fn($r) => [
                    'reference'  => $r['book_name'] . ' ' . $r['chapter_number'] . ':' . $r['verse_number'],
                    'book'       => $r['book_name'],
                    'slug'       => $r['slug'],
                    'chapter'    => (int) $r['chapter_number'],
                    'verse'      => (int) $r['verse_number'],
                    'text'       => $r['text'],
                    'translation'=> $r['translation'],
                    'highlight'  => $r['text'],
                ], $rows),
            ]);
        }
    }
    // Se não encontrou a referência, segue para pesquisa textual
}

// ---------------------------------------------------------------
// 2) Pesquisa textual (FULLTEXT com fallback LIKE)
// ---------------------------------------------------------------
$where  = ['t.status = "active"'];
$params = [];

if ($transAbbr !== '') { $where[] = 't.abbreviation = ?'; $params[] = $transAbbr; }
if ($bookSlug !== '')  { $where[] = 'b.slug = ?';         $params[] = $bookSlug; }
if (in_array($testament, ['AT', 'NT'], true)) { $where[] = 'b.testament = ?'; $params[] = $testament; }

$isPhrase = preg_match('/^".+"$/', $q) === 1;
$clean    = $isPhrase ? trim($q, '"') : $q;

$results = [];
$filterSql = implode(' AND ', $where);

// FULLTEXT em modo booleano
$ftQuery = $isPhrase ? '"' . str_replace('"', '', $clean) . '"' : '+' . implode(' +', preg_split('/\s+/u', $clean) ?: []);
$ftQuery = mb_substr($ftQuery, 0, 120);

$sql = 'SELECT v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug,
               t.abbreviation AS translation,
               MATCH(v.search_text) AGAINST (? IN BOOLEAN MODE) AS score
        FROM verses v
        JOIN chapters c ON c.id = v.chapter_id
        JOIN books b ON b.id = v.book_id
        JOIN translations t ON t.id = v.translation_id
        WHERE MATCH(v.search_text) AGAINST (? IN BOOLEAN MODE) AND ' . $filterSql . '
        ORDER BY score DESC
        LIMIT ' . $limit;
try {
    $results = db_all($sql, array_merge([$ftQuery, $ftQuery], $params));
} catch (PDOException $e) {
    $results = [];
}

// Fallback/treino: LIKE para frases curtas ou quando FULLTEXT falha
if (count($results) < 5) {
    $like = '%' . mb_strtolower($clean) . '%';
    $sql2 = 'SELECT v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug,
                    t.abbreviation AS translation, 0 AS score
             FROM verses v
             JOIN chapters c ON c.id = v.chapter_id
             JOIN books b ON b.id = v.book_id
             JOIN translations t ON t.id = v.translation_id
             WHERE LOWER(v.text) LIKE ? AND ' . $filterSql . '
             ORDER BY b.book_order, c.chapter_number, v.verse_number
             LIMIT ' . $limit;
    $likeResults = db_all($sql2, array_merge([$like], $params));
    // Merge sem duplicar
    $seen = [];
    foreach ($results as $r) { $seen[$r['translation'] . $r['book_name'] . $r['chapter_number'] . $r['verse_number']] = true; }
    foreach ($likeResults as $r) {
        $k = $r['translation'] . $r['book_name'] . $r['chapter_number'] . $r['verse_number'];
        if (!isset($seen[$k])) { $results[] = $r; $seen[$k] = true; }
    }
    $results = array_slice($results, 0, $limit);
}

// Destaque do termo no texto (preserva capitalização original)
$words = $isPhrase ? [$clean] : array_filter(preg_split('/\s+/u', $clean) ?: [], fn($w) => mb_strlen($w) >= 3);
foreach ($results as &$r) {
    $hl = e($r['text']);
    foreach ($words as $w) {
        $wEsc = preg_quote($w, '/');
        $hl = preg_replace('/(' . $wEsc . ')/iu', '<mark>$1</mark>', $hl) ?? $hl;
    }
    $r['highlight'] = $hl;
    $r['reference'] = $r['book_name'] . ' ' . $r['chapter_number'] . ':' . $r['verse_number'];
}
unset($r);

// Registar pesquisa (para estatísticas do dashboard)
try {
    $u = current_user();
    db_exec(
        'INSERT INTO search_logs (user_id, translation_id, query, results_count) VALUES (?, (SELECT id FROM translations WHERE abbreviation = ?), ?, ?)',
        [$u['id'] ?? null, $transAbbr !== '' ? $transAbbr : null, mb_substr($q, 0, 190), count($results)]
    );
} catch (PDOException $e) {
    // não bloqueia a pesquisa por falha de log
}

json_ok([
    'type'    => 'text',
    'query'   => $q,
    'results' => array_map(fn($r) => [
        'reference'  => $r['reference'],
        'book'       => $r['book_name'],
        'slug'       => $r['slug'],
        'chapter'    => (int) $r['chapter_number'],
        'verse'      => (int) $r['verse_number'],
        'text'       => $r['text'],
        'highlight'  => $r['highlight'],
        'translation'=> $r['translation'],
    ], $results),
    'total' => count($results),
]);
