<?php
/**
 * Gera assets/img/favicon.ico a partir dos PNG 16/32/48 (formato ICO com PNG embutido).
 * Uso: php tests/make-ico.php  (regenerar após actualizar o favicon.svg)
 */
$dir = dirname(__DIR__) . '/assets/img';
$sizes = [16, 32, 48];

$pngs = [];
foreach ($sizes as $s) {
    $file = "$dir/icon-$s.png";
    $data = file_get_contents($file);
    if ($data === false) exit("Falta $file\n");
    $pngs[$s] = $data;
}

// ICONDIR (6 bytes) + 3 entradas ICONDIRENTRY (16 bytes cada) + dados PNG
$count = count($sizes);
$ico  = pack('vvvvv', 0, 1, $count, 0, 0); // reserved, type=1(ico), count, sem offset flags
$offset = 6 + 16 * $count;
foreach ($sizes as $s) {
    $ico .= pack('CCCCvvVV', $s === 256 ? 0 : $s, $s === 256 ? 0 : $s, 0, 0, 1, 32, strlen($pngs[$s]), $offset);
    $offset += strlen($pngs[$s]);
}
foreach ($sizes as $s) $ico .= $pngs[$s];

file_put_contents("$dir/favicon.ico", $ico);
echo "favicon.ico gerado (" . strlen($ico) . " bytes, " . $count . " tamanhos)\n";
