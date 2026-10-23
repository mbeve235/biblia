<?php
/**
 * Helpers de utilização geral: URLs, HTML, datas, arrays.
 */

declare(strict_types=1);

/** Detecção automática da URL base (suporta subpastas do htdocs). */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        if (APP_URL !== '') {
            $base = rtrim(APP_URL, '/');
        } else {
            $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $https ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

            // Normaliza barras e maiúsculas (Windows: realpath devolve "C:\xampp\htdocs\...")
            $root    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
            $docroot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

            $sub = '';
            if ($docroot !== '' && stripos($root, $docroot) === 0) {
                $sub = substr($root, strlen($docroot));   // ex.: "/biblia-web"
            }
            $base = $scheme . '://' . $host . rtrim($sub, '/');
        }
    }
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/**
 * URL do CSS do Font Awesome — aceita tanto o pacote completo (all.css)
 * como o pacote "for the web" (all.min.css). Devolve null se não existir.
 */
function fontawesome_url(): ?string
{
    $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    foreach (['assets/vendor/fontawesome/css/all.min.css', 'assets/vendor/fontawesome/css/all.css'] as $rel) {
        if (is_file($root . '/' . $rel)) {
            return url($rel);
        }
    }
    return null;
}

function url(string $path = ''): string
{
    return base_url($path);
}

/** Escapa texto para saída HTML segura (protecção XSS). */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Slug simples para URLs (suporta acentos). */
function slugify(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    } else {
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
        $text = strtr($text, $map);
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', (string) $text) ?? '';
    return trim($text, '-');
}
/** Logótipo da marca (livro aberto com cruz) como SVG inline — herda a cor
 *  do texto via currentColor e é nítido em qualquer tamanho. */
function brand_svg(string $class = '', int $size = 22): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $cls . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" aria-hidden="true">'
        . '<path fill="currentColor" d="M32 20c-4-3.4-9.6-4.6-15-4.6v29c5.4 0 11 1.2 15 4.6 4-3.4 9.6-4.6 15-4.6v-29c-5.4 0-11 1.2-15 4.6z"/>'
        . '<path d="M32 20v29" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>'
        . '<path d="M22.5 27h7M26 23.5v12" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>'
        . '<path d="M37 27h7M37 32h7M37 37h4.5" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>'
        . '</svg>';
}
/** Referência legível, ex.: "João 3:16". */
function verse_reference(string $bookName, int $chapter, int $verse): string
{
    return $bookName . ' ' . $chapter . ':' . $verse;
}

/** URL pública e estável de uma passagem, pronta para partilha. */
function scripture_url(string $translation, string $bookSlug, int $chapter, ?int $verse = null): string
{
    $path = '/' . rawurlencode($translation) . '/' . rawurlencode($bookSlug) . '/' . max(1, $chapter);
    $url = url($path);
    if ($verse !== null && $verse > 0) $url .= '?verse=' . $verse . '#v' . $verse;
    return $url;
}

/** URL amigável de uma ministração publicada. */
function ministry_share_url(string $token): string
{
    return url('/partilha/' . rawurlencode($token));
}

/** Corta texto por palavras completas. */
function excerpt(string $text, int $maxLen = 160): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }
    $cut = mb_substr($text, 0, $maxLen);
    $pos = mb_strrpos($cut, ' ');
    return ($pos ? mb_substr($cut, 0, $pos) : $cut) . '…';
}

/** Data/hora formatada. */
function fmt_datetime(?string $dt): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function fmt_date(?string $dt): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** Número formatado (1.189). */
function fmt_num(int|float|string|null $n): string
{
    return number_format((float) ($n ?? 0), 0, '.', '.');
}

/** Alias leve de array_key_exists com valor por omissão. */
function arr_get(array $arr, string $key, mixed $default = null): mixed
{
    return array_key_exists($key, $arr) ? $arr[$key] : $default;
}

/** Redirecciona e termina. */
function redirect(string $path = ''): never
{
    header('Location: ' . url($path));
    exit;
}
