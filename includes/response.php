<?php
/**
 * Respostas JSON padronizadas da API.
 * Formato: {"success": bool, "data": mixed, "message": string}
 */

declare(strict_types=1);

function json_response(bool $success, mixed $data = null, string $message = '', int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(mixed $data = null, string $message = ''): never
{
    json_response(true, $data, $message, 200);
}

function json_fail(string $message, int $status = 400, mixed $data = null): never
{
    json_response(false, $data, $message, $status);
}

/** Lê o corpo JSON do pedido (POST com Content-Type: application/json). */
function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Obtém dados do pedido: JSON ou $_POST. */
function request_data(): array
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        return json_input();
    }
    return array_merge($_POST, json_input());
}
