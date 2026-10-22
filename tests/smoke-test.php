<?php
/**
 * Teste de fumo (CLI): php tests/smoke-test.php
 * Valida o normalizador de pesquisa e o validador de importação sem precisar de MySQL.
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/reading_plans.php';
require_once __DIR__ . '/../utils/validador.php';

$pass = 0;
$fail = 0;

function check(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ $name\n"; }
    else       { $fail++; echo "  ✗ $name\n"; }
}

// ---------------------------------------------------------------
// 1) Normalização para pesquisa (lógica igual a Importer::toSearchText)
// ---------------------------------------------------------------
echo "1. Normalização de texto para pesquisa\n";
$norm = static function (string $t): string {
    $t = mb_strtolower(trim($t), 'UTF-8');
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
    $t = strtr($t, $map);
    return preg_replace('/\s+/', ' ', $t) ?? $t;
};
check('minúsculas + acentos', $norm('No PRINCÍPIO criou Deus') === 'no principio criou deus');
check('espaços múltiplos', $norm("a   b\t c") === 'a b c');

// ---------------------------------------------------------------
// 2) Validador de importação com índice de livros simulado
// ---------------------------------------------------------------
echo "2. Validador de importação\n";
$booksIndex = [
    'genesis' => ['id' => 1, 'name' => 'Génesis', 'chapters_count' => 50],
    'joao'    => ['id' => 43, 'name' => 'João', 'chapters_count' => 21],
    '1samuel' => ['id' => 9,  'name' => '1 Samuel', 'chapters_count' => 31],
];

$valid = [
    'books' => [
        ['name' => 'Génesis', 'chapters' => [
            ['chapter' => 1, 'verses' => [
                ['verse' => 1, 'text' => 'No princípio criou Deus os céus e a terra.'],
                ['verse' => 2, 'text' => 'E a terra era sem forma e vazia.'],
            ]],
        ]],
        ['name' => 'João', 'chapters' => [
            ['chapter' => 3, 'verses' => [
                ['verse' => 16, 'text' => 'Porque Deus amou o mundo de tal maneira…'],
            ]],
        ]],
    ],
];
$r = validate_import($valid, $booksIndex);
check('estrutura válida → ok', $r['ok'] === true);
check('2 livros detectados', $r['stats']['books'] === 2);
check('3 versículos contados', $r['stats']['verses'] === 3);
check('prévia preenchida (amostra ≤8)', count($r['preview']) === 3);

$unknown = ['books' => [['name' => 'Livro Inventado', 'chapters' => [['chapter' => 1, 'verses' => [['verse' => 1, 'text' => 'abc']]]]]]];
$r = validate_import($unknown, $booksIndex);
check('livro desconhecido → erro bloqueante', $r['ok'] === false && count($r['errors']) === 1);

$problems = ['books' => [['name' => 'João', 'chapters' => [
    ['chapter' => 99, 'verses' => [
        ['verse' => 1, 'text' => ''],                    // texto vazio
        ['verse' => 2, 'text' => 'ab'],                  // texto muito curto
        ['verse' => 2, 'text' => 'duplicado ok'],        // duplicado no ficheiro
        ['verse' => 0, 'text' => 'número inválido'],     // versículo 0
        ['verse' => 5, 'text' => "com\x0Bcontrolo"],     // caractere de controlo
    ]],
]]]];
$r = validate_import($problems, $booksIndex);
check('estrutura com problemas → ok (avisos, não erros)', $r['ok'] === true);
check('aviso: capítulo além do esperado', (bool) array_filter($r['warnings'], fn($w) => str_contains($w['location'], '99')));
check('aviso: texto vazio', (bool) array_filter($r['warnings'], fn($w) => $w['problem'] === 'Texto vazio.'));
check('aviso: versículo duplicado', (bool) array_filter($r['warnings'], fn($w) => str_contains($w['problem'], 'duplicado')));
check('aviso: número inválido', (bool) array_filter($r['warnings'], fn($w) => str_contains($w['problem'], 'inválido')));
check('aviso: caracteres de controlo', (bool) array_filter($r['warnings'], fn($w) => str_contains($w['problem'], 'controlo')));

$empty = ['books' => []];
$r = validate_import($empty, $booksIndex);
check('sem livros → erro', $r['ok'] === false);

// Livro de 1 capítulo por nome numerado
$r = validate_import(['books' => [['name' => '1 Samuel', 'chapters' => [['chapter' => 1, 'verses' => [['verse' => 1, 'text' => 'Texto válido aqui.']]]]]]], $booksIndex);
check('livro com numeral ("1 Samuel") reconhecido', $r['ok'] === true && $r['stats']['books'] === 1);

// ---------------------------------------------------------------
// 3) Parsing do formato dos repositórios GitHub (lista-de-listas)
// ---------------------------------------------------------------
echo "3. Parser JSON (formato GitHub: [{abbrev, chapters:[[v1,v2]]}])\n";
require_once __DIR__ . '/../utils/importador.php';

$fauxIndex = [
    'genesis' => ['id' => 1, 'name' => 'Génesis', 'slug' => 'genesis', 'abbreviation' => 'Gn', 'chapters_count' => 50],
    'gn'      => ['id' => 1, 'name' => 'Génesis', 'slug' => 'genesis', 'abbreviation' => 'Gn', 'chapters_count' => 50],
    'joao'    => ['id' => 43, 'name' => 'João', 'slug' => 'joao', 'abbreviation' => 'Jo', 'chapters_count' => 21],
    'jo'      => ['id' => 43, 'name' => 'João', 'slug' => 'joao', 'abbreviation' => 'Jo', 'chapters_count' => 21],
];
$importer = new Importer($fauxIndex);

$githubJson = json_encode([
    ['abbrev' => 'gn', 'name' => 'Gênesis', 'chapters' => [
        ['No princípio criou Deus os céus e a terra.', 'E a terra era sem forma e vazia.'],
        ['Primeiro versículo do capítulo 2.'],
    ]],
    ['abbrev' => 'jo', 'name' => 'João', 'chapters' => [
        ['No princípio era o Verbo.'],
    ]],
], JSON_UNESCAPED_UNICODE);

$parsed = $importer->parse($githubJson, 'json');
check('2 livros interpretados', count($parsed['books']) === 2);
check('"Gênesis" (pt-BR) canonicalizado para "Génesis"', $parsed['books'][0]['name'] === 'Génesis');
check('abreviação "jo" → João (não Jó)', $parsed['books'][1]['name'] === 'João');
check('numeração implícita de versículos (cap.1 tem 2)', count($parsed['books'][0]['chapters'][0]['verses']) === 2);
check('capítulos implícitos (cap.2 detectado)', $parsed['books'][0]['chapters'][1]['chapter'] === 2);

$r = $importer->validate($parsed);
check('estrutura interpretada passa na validação', $r['ok'] === true);
check('total de versículos = 4', $r['stats']['verses'] === 4);

// ---------------------------------------------------------------
// 4) Ficheiros pt-BR (ex.: pt_ara.json do thiagobodruk/bible)
// ---------------------------------------------------------------
echo "4. Alias pt-BR → pt-PT e desambiguação Jó/João\n";

// Índice fiel ao database.sql (abreviações com acentos: Jó vs Jo)
$dbRows = [
    ['id' => 18, 'name' => 'Job',        'slug' => 'job',        'abbreviation' => 'Jó', 'chapters_count' => 42],
    ['id' => 43, 'name' => 'João',       'slug' => 'joao',       'abbreviation' => 'Jo', 'chapters_count' => 21],
    ['id' => 44, 'name' => 'Actos',      'slug' => 'actos',      'abbreviation' => 'At', 'chapters_count' => 28],
    ['id' => 25, 'name' => 'Lamentações','slug' => 'lamentacoes','abbreviation' => 'Lm', 'chapters_count' => 5],
];
$imp2 = new Importer($dbRows);

$ptbr = json_encode([
    ['abbrev' => 'atos', 'name' => 'Atos',  'chapters' => [['Atos dos apóstolos, primeiro versículo.']]],
    ['abbrev' => 'jó',   'name' => 'Jó',    'chapters' => [['Texto do livro de Job.']]],
    ['abbrev' => 'jo',   'name' => 'João',  'chapters' => [['No princípio era o Verbo.']]],
    ['abbrev' => 'lm',   'name' => 'Lamentações de Jeremias', 'chapters' => [['Como está solitária a cidade.']]],
], JSON_UNESCAPED_UNICODE);

$p2 = $imp2->parse($ptbr, 'json');
check('"Atos" (pt-BR) → Actos', $p2['books'][0]['name'] === 'Actos');
check('"jó" (com acento) → Job', $p2['books'][1]['name'] === 'Job');
check('"jo" (sem acento) → João', $p2['books'][2]['name'] === 'João');
check('"Lamentações de Jeremias" → Lamentações', $p2['books'][3]['name'] === 'Lamentações');
$r = $imp2->validate($p2);
check('ficheiro pt-BR completo valida sem erros', $r['ok'] === true && $r['stats']['books'] === 4);

// ---------------------------------------------------------------
// 5) Rótulos dos dias dos planos de leitura
// ---------------------------------------------------------------
echo "5. Planos de leitura\n";
check('intervalo no mesmo livro', reading_plan_day_label([
    ['book' => 'Salmos', 'chapter' => 1],
    ['book' => 'Salmos', 'chapter' => 2],
    ['book' => 'Salmos', 'chapter' => 3],
]) === 'Salmos 1–3');
check('transição entre livros', reading_plan_day_label([
    ['book' => 'Mateus', 'chapter' => 28],
    ['book' => 'Marcos', 'chapter' => 1],
]) === 'Mateus 28 · Marcos 1');
check('sequência inclui hoje e dias anteriores', reading_streak_days(
    ['2026-10-22', '2026-10-21', '2026-10-20'],
    new DateTimeImmutable('2026-10-22')
) === 3);
check('sequência mantém-se quando hoje ainda não foi lido', reading_streak_days(
    ['2026-10-21', '2026-10-20'],
    new DateTimeImmutable('2026-10-22')
) === 2);
check('sequência termina perante uma falha', reading_streak_days(
    ['2026-10-22', '2026-10-20'],
    new DateTimeImmutable('2026-10-22')
) === 1);

// ---------------------------------------------------------------
echo "\nResultado: $pass passaram, $fail falharam\n";
exit($fail > 0 ? 1 : 0);
