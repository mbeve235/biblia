<?php
/**
 * Validação pré-importação: estrutura, livros conhecidos, números, textos.
 */

declare(strict_types=1);

/**
 * Valida dados normalizados do importador.
 * $booksIndex: mapa nome-normalizado => linha do livro na BD.
 * Devolve: ['ok'=>bool, 'errors'=>[], 'warnings'=>[], 'stats'=>[], 'preview'=>[]]
 */
function validate_import(array $data, array $booksIndex): array
{
    $errors = [];    // impedem a importação (estrutura)
    $warnings = [];  // registos problemáticos (podem ser ignorados)
    $stats = ['books' => 0, 'chapters' => 0, 'verses' => 0, 'unknown_books' => 0];
    $preview = [];

    if (empty($data['books']) || !is_array($data['books'])) {
        return [
            'ok' => false,
            'errors' => [['location' => 'Ficheiro', 'problem' => 'Nenhum livro encontrado na estrutura.']],
            'warnings' => [],
            'stats' => $stats,
            'preview' => [],
        ];
    }

    $norm = fn(string $s) => preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower(trim($s), 'UTF-8'),
        ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'])) ?? '';

    foreach ($data['books'] as $b) {
        $bname = trim((string) ($b['name'] ?? ''));
        if ($bname === '') {
            $errors[] = ['location' => 'Livro sem nome', 'problem' => 'Livro sem nome definido.'];
            continue;
        }
        $stats['books']++;

        // Livro existe na base de dados?
        $n = $norm($bname);
        $book = null;
        foreach ($booksIndex as $key => $row) {
            if ($key === $n) { $book = $row; break; }
        }
        if (!$book) {
            $stats['unknown_books']++;
            $errors[] = ['location' => $bname, 'problem' => 'Livro não reconhecido na base de dados.'];
            continue;
        }

        if (empty($b['chapters']) || !is_array($b['chapters'])) {
            $warnings[] = ['location' => $bname, 'problem' => 'Livro sem capítulos.'];
            continue;
        }

        $expectedMaxCh = (int) $book['chapters_count'];
        $bookChapters = 0;

        foreach ($b['chapters'] as $ch) {
            $chNum = (int) ($ch['chapter'] ?? 0);
            if ($chNum < 1 || $chNum > 200) {
                $warnings[] = ['location' => $bname . ' — capítulo ' . $chNum, 'problem' => 'Número de capítulo inválido.'];
                continue;
            }
            if ($expectedMaxCh > 0 && $chNum > $expectedMaxCh) {
                $warnings[] = ['location' => $bname . ' ' . $chNum, 'problem' => 'Capítulo além do esperado (' . $expectedMaxCh . ' capítulos).'];
            }
            $bookChapters++;

            $prevVerse = 0;
            $seenVerses = [];
            foreach (($ch['verses'] ?? []) as $v) {
                $vsNum = (int) ($v['verse'] ?? 0);
                $text  = trim((string) ($v['text'] ?? ''));
                $loc   = $bname . ' ' . $chNum . ':' . $vsNum;

                if ($vsNum < 1 || $vsNum > 300) {
                    $warnings[] = ['location' => $loc, 'problem' => 'Número de versículo inválido.'];
                    continue;
                }
                if (isset($seenVerses[$vsNum])) {
                    $warnings[] = ['location' => $loc, 'problem' => 'Versículo duplicado dentro do ficheiro (mantém-se o último).'];
                    $stats['verses']--;
                }
                $seenVerses[$vsNum] = true;

                if ($text === '') {
                    $warnings[] = ['location' => $loc, 'problem' => 'Texto vazio.'];
                    continue;
                }
                if (mb_strlen($text) < 3) {
                    $warnings[] = ['location' => $loc, 'problem' => 'Texto muito curto ("' . excerpt($text, 20) . '").'];
                }
                // Caracteres de controlo inválidos
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
                    $warnings[] = ['location' => $loc, 'problem' => 'Contém caracteres de controlo inválidos.'];
                }
                // Encoding
                if (!mb_check_encoding($text, 'UTF-8')) {
                    $warnings[] = ['location' => $loc, 'problem' => 'Texto com encoding inválido (não é UTF-8 válido).'];
                }

                if ($prevVerse > 0 && $vsNum > $prevVerse + 1) {
                    // falha não bloqueia — pode ser conteúdo parcial
                }
                $prevVerse = $vsNum;
                $stats['verses']++;

                if (count($preview) < 8) {
                    $preview[] = ['reference' => $loc, 'text' => excerpt($text, 140)];
                }
            }
        }
        $stats['chapters'] += $bookChapters;
    }

    return [
        'ok'       => count($errors) === 0,
        'errors'   => $errors,
        'warnings' => $warnings,
        'stats'    => $stats,
        'preview'  => $preview,
    ];
}
