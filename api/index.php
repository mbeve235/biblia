<?php
/**
 * API REST — Router central.
 * Todos os pedidos passam por aqui (.htaccess reescreve /api/...).
 * Resposta padronizada: {"success":bool,"data":mixed,"message":string}
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Remove o prefixo do projecto até "/api/"
$pos = strpos($uri, '/api/');
$path = $pos !== false ? substr($uri, $pos + 4) : $uri; // ex.: /translations
$path = trim($path, '/');

$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';
$id       = isset($segments[1]) ? preg_replace('/[^a-zA-Z0-9\-_.]/', '', $segments[1]) : null;
$sub      = $segments[2] ?? null;

$handlerFile = BASE_PATH . '/api/routes/' . preg_replace('/[^a-z0-9\-]/', '', strtolower($resource)) . '.php';

if ($resource === '' || !is_file($handlerFile)) {
    json_fail('Endpoint não encontrado.', 404);
}

require $handlerFile;
