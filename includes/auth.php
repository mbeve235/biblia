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
    $email = mb_strtolower(trim($email));
    $user  = db_one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    // Anti-força-bruta: cada falha fica registada e a tentativa seguinte sofre
    // um atraso progressivo (2s, 4s, máx. 6s) — torna o ataque de dicionário
    // impraticável pela interface. Mensagem genérica mantém-se igual.
    $recentFails = (int) (db_one(
        "SELECT COUNT(*) AS c FROM activity_log
         WHERE action = 'login_failed' AND detail = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$email]
    )['c'] ?? 0);
    if ($recentFails > 0) {
        sleep(min($recentFails * 2, 6));
    }
    $fail = static function () use ($email): string {
        log_activity(null, 'login_failed', $email);
        return 'E-mail ou palavra-passe incorrectos.';
    };

    if (!$user) {
        return $fail();
    }

    // Arranque inicial: contas criadas com "PLAINTEXT:senha" no database.sql
    // são convertidas para hash bcrypt no primeiro login.
    $stored = (string) $user['password_hash'];
    if (str_starts_with($stored, 'PLAINTEXT:')) {
        if (!hash_equals(substr($stored, strlen('PLAINTEXT:')), $password)) {
            return $fail();
        }
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    } elseif (!password_verify($password, $stored)) {
        return $fail();
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

/** Envia um e-mail pela aplicação.
 *  Com MAIL_HOST configurado (config.php) usa o cliente SMTP do projecto
 *  (includes/mailer.php) — ex.: Gmail com palavra-passe de aplicação.
 *  Sem SMTP, cai na função mail() do PHP (inútil no XAMPP por omissão).
 *  Regista SEMPRE a mensagem em logs/mail.log, com o resultado. */
function send_app_mail(string $to, string $subject, string $body): bool
{
    $fromEmail = MAIL_FROM !== '' ? MAIL_FROM : (MAIL_USER !== '' ? MAIL_USER : 'no-reply@biblia.local');
    $ok = false;
    $via = 'mail()';
    try {
        if (MAIL_HOST !== '') {
            $via = 'smtp (' . MAIL_HOST . ')';
            $ok = smtp_send($to, $subject, $body, $fromEmail, APP_NAME);
        } else {
            $headers = implode("\r\n", [
                'From: ' . APP_NAME . ' <' . $fromEmail . '>',
                'Content-Type: text/plain; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion(),
            ]);
            $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }
    } catch (Throwable $e) {
        error_log('[mailer] ' . $e->getMessage());
    }

    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(
        $dir . '/mail.log',
        sprintf(
            "[%s] para: %s | assunto: %s | via: %s | enviado: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $via,
            $ok ? 'sim' : 'não',
            $body,
            str_repeat('-', 60)
        ),
        FILE_APPEND | LOCK_EX
    );
    return $ok;
}

/** Cria token de recuperação de senha (1h). Devolve o token. */
function create_password_reset(string $email): ?string
{
    $user = db_one('SELECT id FROM users WHERE email = ? AND status = "active"', [mb_strtolower(trim($email))]);
    if (!$user) {
        return null;
    }
    // Anti-flood: no máximo 5 pedidos por hora por conta. Todos os tokens
    // expiram 1h após a criação, logo os não expirados = pedidos da última hora.
    // Devolve null sem distinguir do caso "e-mail inexistente" (a página mostra
    // a mesma mensagem neutra — o atacante não percebe o limite).
    $recent = (int) (db_one(
        'SELECT COUNT(*) AS c FROM password_resets WHERE user_id = ? AND expires_at > NOW()',
        [$user['id']]
    )['c'] ?? 0);
    if ($recent >= 5) {
        return null;
    }
    // Invalida pedidos anteriores ainda não usados: só o link mais recente é válido
    db_exec('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0', [$user['id']]);
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
