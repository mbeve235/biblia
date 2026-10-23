<?php
/**
 * Sistema inteligente de importação de traduções.
 * Fases: parse (JSON/CSV/texto) → validar → prévia → importar em blocos → relatório.
 */

declare(strict_types=1);

require_once BASE_PATH . '/utils/validador.php';

class Importer
{
    public const FORMAT_JSON = 'json';
    public const FORMAT_CSV  = 'csv';
    public const FORMAT_TEXT = 'text';

    private array $booksIndex = [];   // índice normalizado (sem acentos) + alias pt-BR/EN
    private array $rawAbbr = [];      // abreviações com acentos preservados ("jó" ≠ "jo")
    private array $rawName = [];      // nomes com acentos preservados

    /**
     * Alias de nomes/abreviações pt-BR e ingleses → chave normalizada do livro na BD.
     * Resolve diferenças pt-PT vs pt-BR (Actos/Atos) e ficheiros em inglês.
     */
    private const ALIASES = [
        // pt-BR → pt-PT
        'atos' => 'actos',
        'lamentacoesdejeremias' => 'lamentacoes',
        'canticodoscanticos' => 'canticos',
        'cantares' => 'canticos',
        'cronicas' => 'cronicas',
        // inglês comum
        'exodus' => 'exodo',
        'leviticus' => 'levitico',
        'numbers' => 'numeros',
        'deuteronomy' => 'deuteronomio',
        'joshua' => 'josue',
        'judges' => 'juizes',
        'ruth' => 'rute',
        '1chronicles' => '1cronicas',
        '2chronicles' => '2cronicas',
        'ezra' => 'esdras',
        'nehemiah' => 'neemias',
        'esther' => 'ester',
        'psalms' => 'salmos',
        'psalm' => 'salmos',
        'proverbs' => 'proverbios',
        'ecclesiastes' => 'eclesiastes',
        'songofsolomon' => 'canticos',
        'songofsongs' => 'canticos',
        'isaiah' => 'isaias',
        'jeremiah' => 'jeremias',
        'lamentations' => 'lamentacoes',
        'ezekiel' => 'ezequiel',
        'hosea' => 'oseias',
        'obadiah' => 'obadias',
        'jonah' => 'jonas',
        'micah' => 'miqueias',
        'nahum' => 'naum',
        'habakkuk' => 'habacuque',
        'zephaniah' => 'sofonias',
        'haggai' => 'ageu',
        'zechariah' => 'zacarias',
        'malachi' => 'malaquias',
        'matthew' => 'mateus',
        'mark' => 'marcos',
        'luke' => 'lucas',
        'john' => 'joao',
        'acts' => 'actos',
        'romans' => 'romanos',
        '1corinthians' => '1corintios',
        '2corinthians' => '2corintios',
        'galatians' => 'galatas',
        'ephesians' => 'efesios',
        'philippians' => 'filipenses',
        'colossians' => 'colossenses',
        '1thessalonians' => '1tessalonicenses',
        '2thessalonians' => '2tessalonicenses',
        '1timothy' => '1timoteo',
        '2timothy' => '2timoteo',
        'titus' => 'tito',
        'hebrews' => 'hebreus',
        'james' => 'tiago',
        '1peter' => '1pedro',
        '2peter' => '2pedro',
        '1john' => '1joao',
        '2john' => '2joao',
        '3john' => '3joao',
        'jude' => 'judas',
        'revelation' => 'apocalipse',
    ];

    /** $booksIndex permite injectar o índice em testes CLI sem base de dados. */
    public function __construct(?array $booksIndex = null)
    {
        if ($booksIndex !== null) {
            $this->buildIndex($booksIndex);
            return;
        }
        try {
            $this->buildIndex(db_all('SELECT id, name, abbreviation, slug, chapters_count FROM books'));
        } catch (Throwable $e) {
            // sem BD (ex.: testes) — índice vazio
        }
    }

    /** Constrói os índices (normalizado + alias + bruto com acentos) a partir das linhas da BD. */
    private function buildIndex(array $rows): void
    {
        foreach ($rows as $b) {
            foreach ([$this->norm($b['name']), $this->norm($b['slug'] ?? ''), $this->norm($b['abbreviation'] ?? '')] as $k) {
                if ($k !== '') $this->booksIndex[$k] = $b;
            }
            $rawA = mb_strtolower(trim((string) ($b['abbreviation'] ?? '')), 'UTF-8');
            $rawN = mb_strtolower(trim((string) ($b['name'] ?? '')), 'UTF-8');
            if ($rawA !== '') $this->rawAbbr[$rawA] = $b;
            if ($rawN !== '') $this->rawName[$rawN] = $b;
        }
        // Alias apontam para a mesma linha do livro canónico
        foreach (self::ALIASES as $alias => $canon) {
            if (isset($this->booksIndex[$canon]) && !isset($this->booksIndex[$alias])) {
                $this->booksIndex[$alias] = $this->booksIndex[$canon];
            }
        }
    }

    /** Normaliza texto para comparação: minúsculas, sem acentos, sem pontuação. */
    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
        $s = strtr($s, $map);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    /** Converte HTML carregado ou caminho para conteúdo textual (com detecção de encoding). */
    public function readSource(string $raw): string
    {
        // Remove BOM
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        // Converte para UTF-8 se necessário
        $enc = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
        }
        return $raw;
    }

    /** Detecta automaticamente o formato do conteúdo. */
    public function detectFormat(string $raw): string
    {
        $t = ltrim($raw);
        if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
            json_decode($t, true);
            if (json_last_error() === JSON_ERROR_NONE) return self::FORMAT_JSON;
        }
        $firstLine = strtok($t, "\n") ?: '';
        if (substr_count($firstLine, ',') >= 4 || stripos($firstLine, 'translation') !== false) {
            return self::FORMAT_CSV;
        }
        return self::FORMAT_TEXT;
    }

    /** Faz parsing do conteúdo e devolve estrutura uniforme de livros/capítulos/versículos. */
    public function parse(string $raw, ?string $forcedFormat = null): array
    {
        $raw = $this->readSource($raw);
        $format = $forcedFormat ?: $this->detectFormat($raw);

        return match ($format) {
            self::FORMAT_JSON => $this->parseJson($raw),
            self::FORMAT_CSV  => $this->parseCsv($raw),
            default           => $this->parseText($raw),
        };
    }

    /** JSON: {translation:{...}, books:[{name,abbreviation,testament,chapters:[{chapter,verses:[{verse,text}]}]}]} */
    private function parseJson(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON inválido: ' . json_last_error_msg());
        }

        // Formato comum nos repositórios GitHub: lista directa de livros
        // [ {"abbrev":"gn", "name":"Gênesis", "chapters":[["v1","v2",...], [...]]}, ... ]
        $isList = isset($data[0]) || (array_keys($data) === range(0, count($data) - 1) && $data !== []);
        if ($isList && !isset($data['books'])) {
            return ['translation' => [], 'books' => $this->normalizeBooks($data)];
        }

        // Aceita também [{...livros...}] dentro de "books"
        $books = $data['books'] ?? null;
        if (!is_array($books)) {
            // Formato alternativo: {Génesis: {1: {1: "texto"}}}
            $alt = $this->parseJsonFlat($data);
            if ($alt) return ['translation' => $data['translation'] ?? [], 'books' => $alt];
            throw new RuntimeException('Estrutura JSON não reconhecida: falta "books".');
        }

        return ['translation' => $data['translation'] ?? [], 'books' => $this->normalizeBooks($books)];
    }

    /** JSON plano: {"Génesis": {"1": {"1": "texto", ...}, ...}} */
    private function parseJsonFlat(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (!is_array($v) || in_array($k, ['translation', 'books'], true)) continue;
            $chapters = [];
            foreach ($v as $ch => $verses) {
                if (!is_array($verses)) continue;
                $vList = [];
                foreach ($verses as $vs => $text) {
                    if (is_string($text)) {
                        $vList[] = ['verse' => (int) $vs, 'text' => $text];
                    } elseif (is_array($text) && isset($text['text'])) {
                        $vList[] = ['verse' => (int) ($text['verse'] ?? $vs), 'text' => (string) $text['text']];
                    }
                }
                if ($vList) $chapters[] = ['chapter' => (int) $ch, 'verses' => $vList];
            }
            if ($chapters) $out[] = ['name' => (string) $k, 'chapters' => $chapters];
        }
        return $out;
    }

    /** CSV: translation,book,chapter,verse,text (com ou sem cabeçalho) */
    private function parseCsv(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        if (!$lines) throw new RuntimeException('Ficheiro CSV vazio.');

        // Detecta separador (vírgula ou ponto-e-vírgula)
        $first = $lines[0];
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $hasHeader = stripos($first, 'translation') !== false || stripos($first, 'book') !== false;
        if ($hasHeader) array_shift($lines);

        $defaultTrans = null;
        $books = []; // name => ['chapters' => [ch => [vs => text]]]
        $order = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') continue;
            $row = str_getcsv($line, $sep, '"', '\\');
            if (count($row) < 5) {
                throw new RuntimeException('Linha ' . ($i + 1) . ' do CSV tem menos de 5 colunas.');
            }
            if (count($row) >= 6) {
                [$trans, $book, $ch, $vs, $heading, $text] = array_map('trim', array_slice($row, 0, 6));
            } else {
                [$trans, $book, $ch, $vs, $text] = array_map('trim', array_slice($row, 0, 5));
                $heading = '';
            }
            if ($trans !== '') $defaultTrans = $trans;
            if ($book === '' || $text === '') {
                throw new RuntimeException('Linha ' . ($i + 1) . ' incompleta (livro ou texto vazio).');
            }
            if (!isset($books[$book])) { $books[$book] = ['chapters' => []]; $order[] = $book; }
            $books[$book]['chapters'][(int) $ch][(int) $vs] = ['text' => $text, 'heading' => $heading];
        }

        $out = [];
        foreach ($order as $name) {
            $chapters = [];
            ksort($books[$name]['chapters']);
            foreach ($books[$name]['chapters'] as $ch => $verses) {
                ksort($verses);
                $vList = [];
                foreach ($verses as $vs => $verseData) $vList[] = ['verse' => $vs, 'heading' => $verseData['heading'], 'text' => $verseData['text']];
                $chapters[] = ['chapter' => $ch, 'verses' => $vList];
            }
            $out[] = ['name' => $name, 'chapters' => $chapters];
        }
        return ['translation' => $defaultTrans ? ['abbreviation' => $defaultTrans] : [], 'books' => $out];
    }

    /** Texto estruturado livre: tenta interpretar padrões comuns. */
    private function parseText(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $books = [];
        $order = [];
        $currentBook = null;
        $currentCh = null;

        $reBookCh = '/^\s*(?:CAP[IÍ]TULO|CAP\.?|C)\s+(\d{1,3})\s*$/iu';
        $reBook   = '/^\s*(?:LIVRO\s+DE\s+|LIVRO\s+)?((?:[1-3]\s?)?[A-ZÀ-Ú][A-Za-zÀ-ú\. ]{1,30})\s*$/u';
        $reInline = '/^\s*((?:[1-3]\s?)?[A-Za-zÀ-ú\. ]{2,30}?)\s+(\d{1,3})\s*[:\.]\s*(\d{1,3})(?:\s*[-–—]\s*(.*))$/u';
        $reVerse  = '/^\s*(\d{1,3})\s+([\s\S]+)$/u';

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') continue;

            // "João 3:16 texto..."
            if (preg_match($reInline, $t, $m)) {
                $bname = $this->matchBookName($m[1]);
                if ($bname) {
                    if (!isset($books[$bname])) { $books[$bname] = ['chapters' => []]; $order[] = $bname; }
                    $books[$bname]['chapters'][(int) $m[2]][(int) $m[3]] = trim($m[4]);
                    $currentBook = $bname; $currentCh = (int) $m[2];
                    continue;
                }
            }
            // "CAPÍTULO 3"
            if (preg_match($reBookCh, $t, $m)) {
                if ($currentBook === null) continue;
                $currentCh = (int) $m[1];
                continue;
            }
            // Nome de livro isolado (só se ainda não houver capítulo corrente OU linha curta sem números)
            if (preg_match($reBook, $t, $m) && !preg_match('/\d/', $t)) {
                $bname = $this->matchBookName($m[1]);
                if ($bname) {
                    if (!isset($books[$bname])) { $books[$bname] = ['chapters' => []]; $order[] = $bname; }
                    $currentBook = $bname;
                    $currentCh = null;
                    continue;
                }
            }
            // "1 No princípio..."
            if (preg_match($reVerse, $t, $m) && $currentBook !== null && $currentCh !== null) {
                $books[$currentBook]['chapters'][$currentCh][(int) $m[1]] = trim($m[2]);
            }
        }

        $out = [];
        foreach ($order as $name) {
            $chapters = [];
            ksort($books[$name]['chapters']);
            foreach ($books[$name]['chapters'] as $ch => $verses) {
                ksort($verses);
                $vList = [];
                foreach ($verses as $vs => $text) $vList[] = ['verse' => $vs, 'text' => $text];
                $chapters[] = ['chapter' => $ch, 'verses' => $vList];
            }
            $out[] = ['name' => $name, 'chapters' => $chapters];
        }
        if (!$out) {
            throw new RuntimeException('Não foi possível interpretar o texto. Use o formato JSON ou CSV.');
        }
        return ['translation' => [], 'books' => $out];
    }

    /** Tenta associar um nome a um livro da BD (tolerante a acentos/abreviações). */
    private function matchBookName(string $name): ?string
    {
        $n = $this->norm($name);
        if ($n === '') return null;
        foreach (array_keys($this->booksIndex) as $key) {
            if ($key === $n) {
                return $this->booksIndex[$key]['name'];
            }
        }
        // prefixo (ex.: "gene" → Génesis) apenas se pouco ambíguo
        if (mb_strlen($n) >= 4) {
            foreach ($this->booksIndex as $key => $b) {
                if (str_starts_with($key, $n)) return $b['name'];
            }
        }
        return null;
    }

    /** Normaliza lista de livros vinda de JSON estruturado. */
    private function normalizeBooks(array $books): array
    {
        $out = [];
        foreach ($books as $b) {
            if (!is_array($b)) continue;
            $name = (string) ($b['name'] ?? $b['book'] ?? '');
            $abbr = (string) ($b['abbreviation'] ?? $b['abbrev'] ?? '');
            if ($name === '' && $abbr !== '') $name = $abbr;
            if ($name === '') continue;

            // Canonicaliza o nome do livro pela base de dados (resolve "Gênesis"→"Génesis",
            // abreviações "gn"→"Génesis", e desambigua Jó/João pela abreviação)
            $canonical = $this->resolveBookName($name, $abbr);
            if ($canonical !== null) $name = $canonical;

            $chaptersIn = $b['chapters'] ?? [];
            $chapters = [];

            // Formato lista-de-listas: chapters: [["v1","v2"], ["v1",...]] (numeração implícita)
            if (isset($chaptersIn[0]) && is_array($chaptersIn[0])) {
                foreach ($chaptersIn as $ci => $chapterArr) {
                    if (!is_array($chapterArr)) continue;
                    $vList = [];
                    foreach ($chapterArr as $vi => $vt) {
                        if (is_string($vt)) {
                            $t = trim($vt);
                            if ($t !== '') $vList[] = ['verse' => $vi + 1, 'text' => $t];
                        } elseif (is_array($vt) && isset($vt['text'])) {
                            $vList[] = ['verse' => (int) ($vt['verse'] ?? $vi + 1), 'heading' => trim((string) ($vt['heading'] ?? $vt['title'] ?? '')), 'text' => trim((string) $vt['text'])];
                        }
                    }
                    if ($vList) $chapters[] = ['chapter' => $ci + 1, 'verses' => $vList];
                }
            } else {
                // Capítulos como lista [{chapter, verses}] ou mapa {1: [...]}
                foreach ($chaptersIn as $ck => $cv) {
                    if (is_array($cv) && isset($cv['verses'])) {
                        $chNum = (int) ($cv['chapter'] ?? $ck);
                        $vList = [];
                        foreach ($cv['verses'] as $vv) {
                            if (is_array($vv) && isset($vv['text'])) {
                                $vList[] = ['verse' => (int) ($vv['verse'] ?? count($vList) + 1), 'heading' => trim((string) ($vv['heading'] ?? $vv['title'] ?? '')), 'text' => trim((string) $vv['text'])];
                            } elseif (is_string($vv)) {
                                $vList[] = ['verse' => count($vList) + 1, 'text' => trim($vv)];
                            }
                        }
                        $chapters[] = ['chapter' => $chNum, 'verses' => $vList];
                    } elseif (is_array($cv)) {
                        $chNum = (int) $ck;
                        $vList = [];
                        foreach ($cv as $vk => $vt) {
                            if (is_string($vt)) $vList[] = ['verse' => (int) $vk, 'text' => trim($vt)];
                            elseif (is_array($vt) && isset($vt['text'])) $vList[] = ['verse' => (int) ($vt['verse'] ?? $vk), 'heading' => trim((string) ($vt['heading'] ?? $vt['title'] ?? '')), 'text' => trim((string) $vt['text'])];
                        }
                        $chapters[] = ['chapter' => $chNum, 'verses' => $vList];
                    }
                }
            }
            // Versículos directos no livro (livro de 1 capítulo)
            if (!$chapters && isset($b['verses']) && is_array($b['verses'])) {
                $vList = [];
                foreach ($b['verses'] as $vk => $vt) {
                    if (is_string($vt)) $vList[] = ['verse' => (int) $vk, 'text' => trim($vt)];
                    elseif (is_array($vt) && isset($vt['text'])) $vList[] = ['verse' => (int) ($vt['verse'] ?? $vk), 'heading' => trim((string) ($vt['heading'] ?? $vt['title'] ?? '')), 'text' => trim((string) $vt['text'])];
                }
                if ($vList) $chapters[] = ['chapter' => 1, 'verses' => $vList];
            }

            if ($chapters) {
                $out[] = [
                    'name'         => $name,
                    'abbreviation' => $abbr,
                    'testament'    => strtoupper((string) ($b['testament'] ?? '')) === 'NT' ? 'NT' : 'AT',
                    'chapters'     => $chapters,
                ];
            }
        }
        return $out;
    }

    /**
     * Resolve o nome canónico do livro.
     * Ordem: abreviação bruta (com acentos) → nome bruto → normalizado/alias → prefixo.
     * Os índices brutos separam "Jó" (Job) de "Jo" (João).
     */
    private function resolveBookName(string $name, string $abbr = ''): ?string
    {
        if ($abbr !== '') {
            $raw = mb_strtolower(trim($abbr), 'UTF-8');
            if ($raw !== '' && isset($this->rawAbbr[$raw])) {
                return $this->rawAbbr[$raw]['name'];
            }
            $k = $this->norm($abbr);
            if ($k !== '' && isset($this->booksIndex[$k])) {
                return $this->booksIndex[$k]['name'];
            }
        }
        $rawN = mb_strtolower(trim($name), 'UTF-8');
        if ($rawN !== '' && isset($this->rawName[$rawN])) {
            return $this->rawName[$rawN]['name'];
        }
        $n = $this->norm($name);
        if ($n !== '' && isset($this->booksIndex[$n])) {
            return $this->booksIndex[$n]['name'];
        }
        if (mb_strlen($n) >= 4) {
            foreach ($this->booksIndex as $key => $b) {
                if (str_starts_with($key, $n)) return $b['name'];
            }
        }
        return null;
    }

    /** Valida a estrutura completa e devolve o relatório de validação. */
    public function validate(array $data): array
    {
        return validate_import($data, $this->booksIndex);
    }

    /**
     * Importa em blocos com transacções.
     * $offset: índice do bloco (0, 1, 2...). Cada bloco processa IMPORT_CHUNK versículos.
     * Devolve estatísticas parciais acumuladas.
     */
    public function importChunk(int $translationId, array $data, int $offset): array
    {
        $stats = ['verses' => 0, 'chapters' => 0, 'books' => 0, 'errors' => 0, 'duplicates' => 0, 'done' => false];

        // Achata tudo numa lista sequencial de operações
        $flat = $this->flatten($data);
        $total = count($flat['verses']);

        $chunk = array_slice($flat['verses'], $offset * IMPORT_CHUNK, IMPORT_CHUNK);
        if (!$chunk) {
            $stats['done'] = true;
            return $stats;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO verses (translation_id, book_id, chapter_id, verse_number, heading, text, search_text)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    heading = COALESCE(VALUES(heading), heading),
                    text = VALUES(text),
                    search_text = VALUES(search_text)'
            );
            $bookIds = [];
            foreach ($chunk as $item) {
                $bookId = $flat['book_ids'][$item['book']] ?? null;
                if (!$bookId) {
                    // Regista livro desconhecido — já validado antes, aqui é segurança
                    $stats['errors']++;
                    continue;
                }
                $chapterId = $this->ensureChapter($bookId, $item['chapter']);
                $search = $this->toSearchText($item['text']);
                $heading = trim((string) ($item['heading'] ?? ''));
                $insert->execute([$translationId, $bookId, $chapterId, $item['verse'], $heading !== '' ? $heading : null, $item['text'], $search]);
                if ($insert->rowCount() === 1) $stats['verses']++;
                else $stats['duplicates']++; // actualizado (já existia)
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Contagens de livros/capítulos processados até este bloco
        $stats['books'] = count($flat['book_ids']);
        $stats['chapters'] = count($flat['chapter_ids']);
        $stats['total'] = $total;
        $stats['processed'] = min($total, ($offset + 1) * IMPORT_CHUNK);
        $stats['done'] = $stats['processed'] >= $total;
        return $stats;
    }

    /** Transforma a estrutura validada numa lista plana de versículos. */
    private function flatten(array $data): array
    {
        $flat = ['verses' => [], 'book_ids' => [], 'chapter_ids' => []];
        foreach ($data['books'] as $b) {
            $bookId = $this->resolveBookId($b);
            if (!$bookId) continue;
            $flat['book_ids'][$b['name']] = $bookId;
            foreach ($b['chapters'] as $ch) {
                $chapterId = $this->ensureChapter($bookId, $ch['chapter']);
                $flat['chapter_ids'][$b['name'] . $ch['chapter']] = $chapterId;
                foreach ($ch['verses'] as $v) {
                    $flat['verses'][] = [
                        'book'    => $b['name'],
                        'chapter' => $ch['chapter'],
                        'verse'   => $v['verse'],
                        'heading' => trim((string) ($v['heading'] ?? $v['title'] ?? '')),
                        'text'    => $v['text'],
                    ];
                }
            }
        }
        return $flat;
    }

    private function resolveBookId(array $b): ?int
    {
        $n = $this->norm($b['name']);
        return $this->booksIndex[$n]['id'] ?? null;
    }

    /** Cria o capítulo se não existir (livros podem ter capítulos extra). */
    private function ensureChapter(int $bookId, int $chapterNumber): int
    {
        static $cache = [];
        $key = $bookId . ':' . $chapterNumber;
        if (isset($cache[$key])) return $cache[$key];

        $row = db_one('SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?', [$bookId, $chapterNumber]);
        if ($row) {
            $cache[$key] = (int) $row['id'];
            return $cache[$key];
        }
        db_exec('INSERT INTO chapters (book_id, chapter_number) VALUES (?, ?)', [$bookId, $chapterNumber]);
        db_exec('UPDATE books SET chapters_count = GREATEST(chapters_count, ?) WHERE id = ?', [$chapterNumber, $bookId]);
        $cache[$key] = db_last_id();
        return $cache[$key];
    }

    /** Minúsculas sem acentos para a coluna de pesquisa. */
    public static function toSearchText(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
        $t = strtr($t, $map);
        return preg_replace('/\s+/', ' ', $t) ?? $t;
    }
}
