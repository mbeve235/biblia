<?php
/**
 * Leitura de configurações da tabela settings.
 */

declare(strict_types=1);

function settings_get(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
                $cache[$row['skey']] = (string) $row['svalue'];
            }
        } catch (Throwable $e) {
            // tabela pode não existir ainda
        }
    }
    return $cache[$key] ?? $default;
}
