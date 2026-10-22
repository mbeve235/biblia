<?php
/**
 * Bíblia Web — Configuração geral
 * Altere os valores deste ficheiro conforme o seu ambiente (XAMPP).
 */

declare(strict_types=1);

// Valores privados podem ser definidos em config/local.php (não versionado)
// ou por variáveis de ambiente. As variáveis de ambiente têm precedência.
$localConfigFile = __DIR__ . '/local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) {
    throw new RuntimeException('config/local.php deve devolver um array.');
}
$configValue = static function (string $key, mixed $default = '') use ($localConfig): mixed {
    $env = getenv($key);
    return $env !== false ? $env : ($localConfig[$key] ?? $default);
};

// ---------------------------------------------------------------
// BASE DE DADOS
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'biblia_web');
define('DB_USER', 'root');      // XAMPP por omissão: root sem password
define('DB_PASS', '');          // Altere se a sua MySQL tiver password
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------
// APLICAÇÃO
// ---------------------------------------------------------------
define('APP_NAME', 'Bíblia Web');
define('APP_VERSION', '1.0.0');
define('APP_URL', '');         // Deixe '' para detecção automática (ex.: http://localhost/biblia-web)
define('TIMEZONE', 'Europe/Lisbon');
define('DEFAULT_TRANSLATION', 'ARC');
date_default_timezone_set(TIMEZONE);

// ---------------------------------------------------------------
// SESSÃO / SEGURANÇA
// ---------------------------------------------------------------
define('SESSION_NAME', 'biblia_web_sess');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);   // 7 dias

// ---------------------------------------------------------------
// E-MAIL (SMTP) — usado pela recuperação de palavra-passe
//
// GMAIL: crie uma "palavra-passe de aplicação" em
//   https://myaccount.google.com/apppasswords
//   (requer 2FA activo; são 16 caracteres, NÃO a senha normal da conta)
// e preencha MAIL_USER / MAIL_PASS em config/local.php (veja
// config/local.example.php). Se MAIL_USER ficar '',
// a app usa a função mail() do PHP (logs em logs/mail.log).
//
// OUTROS SERVIÇOS: outlook → smtp.office365.com:587 tls
//                  zoho → smtp.zoho.com:465 ssl (ou 587 tls)
// ---------------------------------------------------------------
define('MAIL_HOST', (string) $configValue('MAIL_HOST', ''));
define('MAIL_PORT', (int) $configValue('MAIL_PORT', 587));
define('MAIL_ENCRYPT', (string) $configValue('MAIL_ENCRYPT', 'tls'));
define('MAIL_USER', (string) $configValue('MAIL_USER', ''));
define('MAIL_PASS', (string) $configValue('MAIL_PASS', ''));
define('MAIL_FROM', (string) $configValue('MAIL_FROM', ''));

// ---------------------------------------------------------------
// IMPORTAÇÃO / UPLOADS
// ---------------------------------------------------------------
define('UPLOAD_MAX_BYTES', 25 * 1024 * 1024);   // 25 MB por ficheiro
define('IMPORT_CHUNK', 500);                    // versículos inseridos por bloco
define('IMPORT_STEPS_PER_REQUEST', 3);          // blocos processados por pedido AJAX

// Extensões permitidas para importação (extensões perigosas ficam bloqueadas)
define('ALLOWED_IMPORT_EXT', ['json', 'csv', 'txt']);

define('DEBUG', true);          // false em produção

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
