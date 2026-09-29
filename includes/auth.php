<?php
/**
 * Autenticação, sessões seguras, CSRF e controlo de permissões.
 */

declare(strict_types=1);

/** Iniciar sessão com parâmetros seguros (idempotente). */
function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Regeneração periódica do ID de sessão (prevenção de fixação)
    $now = time();
    if (!isset($_SESSION['_regen'])) {
        $_SESSION['_regen'] = $now;
    } elseif ($now - (int) $_SESSION['_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_regen'] = $now;
    }

    // Expiração por inactividade
    if (isset($_SESSION['_last']) && $now - (int) $_SESSION['_last'] > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['_last'] = $now;
}

/** Utilizador autenticado (dados mínimos) ou null. */
function current_user(): ?array
{
    boot_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'     => (int) $_SESSION['user_id'],
        'name'   => (string) ($_SESSION['user_name'] ?? ''),
        'email'  => (string) ($_SESSION['user_email'] ?? ''),
        'role'   => (string) ($_SESSION['user_role'] ?? 'user'),
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return current_user()['role'] === 'admin';
}

function is_editor(): bool
{
    $r = current_user()['role'] ?? '';
    return $r === 'admin' || $r === 'editor';
}

/** Exige autenticação (páginas). Redirecciona para login. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('/login.php?msg=needlogin');
    }
    return $u;
}

/** Exige administração (páginas). */
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Acesso restrito ao administrador.');
    }
    return $u;
}

/** Exige editor ou admin (páginas). */
function require_editor(): array
{
    $u = require_login();
    if (!in_array($u['role'], ['admin', 'editor'], true)) {
        http_response_code(403);
        exit('Acesso restrito a editores.');
    }
    return $u;
}

/** Exige autenticação em endpoints da API. */
function require_api_login(): array
{
    $u = current_user();
    if (!$u) {
        json_fail('Autenticação necessária.', 401);
    }
    return $u;
}

/** Token CSRF para formulários. */
function csrf_token(): string
{
    boot_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo hidden CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Valida o token CSRF (POST/JSON). Termina com erro se inválido. */
function csrf_check(?array $data = null): void
{
    boot_session();
    $data = $data ?? array_merge($_POST, json_input());
    $sent = (string) ($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$sent || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $sent)) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            json_fail('Token CSRF inválido. Recarregue a página.', 403);
        }
        http_response_code(403);
        exit('Token CSRF inválido. Recarregue a página.');
    }
}

/** Verifica credenciais e inicia sessão. Devolve mensagem de erro ou null. */
function login_user(string $email, string $password): ?string
{
    $user = db_one('SELECT * FROM users WHERE email = ? LIMIT 1', [mb_strtolower(trim($email))]);
    if (!$user) {
        return 'E-mail ou palavra-passe incorrectos.';
    }

    // Arranque inicial: contas criadas com "PLAINTEXT:senha" no database.sql
    // são convertidas para hash bcrypt no primeiro login.
    $stored = (string) $user['password_hash'];
    if (str_starts_with($stored, 'PLAINTEXT:')) {
        if (!hash_equals(substr($stored, strlen('PLAINTEXT:')), $password)) {
            return 'E-mail ou palavra-passe incorrectos.';
        }
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    } elseif (!password_verify($password, $stored)) {
        return 'E-mail ou palavra-passe incorrectos.';
    }
    if ($user['status'] !== 'active') {
        return 'A sua conta está desactivada. Contacte o administrador.';
    }
    // Re-hash transparente se o algoritmo evoluiu
    if (password_needs_rehash($stored, PASSWORD_DEFAULT) && !str_starts_with($stored, 'PLAINTEXT:')) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    boot_session();
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    return null;
}

function logout_user(): void
{
    boot_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Cria token de recuperação de senha (1h). Devolve o token. */
function create_password_reset(string $email): ?string
{
    $user = db_one('SELECT id FROM users WHERE email = ? AND status = "active"', [mb_strtolower(trim($email))]);
    if (!$user) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    db_exec(
        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
        [$user['id'], hash('sha256', $token)]
    );
    return $token;
}

/** Valida token de recuperação. Devolve user_id ou null. */
function validate_password_reset(string $token): ?int
{
    $row = db_one(
        'SELECT user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1',
        [hash('sha256', $token)]
    );
    return $row ? (int) $row['user_id'] : null;
}

function consume_password_reset(string $token): void
{
    db_exec('UPDATE password_resets SET used = 1 WHERE token = ?', [hash('sha256', $token)]);
}
