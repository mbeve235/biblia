<?php
/**
 * Bootstrap da aplicação — inclua este ficheiro em todas as páginas/endpoints.
 * Carrega configuração, helpers, BD, resposta JSON, logger e autenticação.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/response.php';
require_once BASE_PATH . '/includes/logger.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/settings.php';

// Cabeçalhos de segurança básicos
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
