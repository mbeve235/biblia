<?php
/**
 * /api/users/register      POST — cria conta pública
 * /api/users/profile       GET|POST — perfil e preferências do utilizador
 * /api/users               GET — lista (admin) | POST — criar/actualizar (admin)
 * /api/users/{id}          POST _method=DELETE — desactivar (admin)
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $segments[1] ?? null;

// ---------------------------------------------------------------
// Registo público
// ---------------------------------------------------------------
if ($action === 'register' && $method === 'POST') {
    csrf_check();
    if (settings_get('registration_enabled', '1') !== '1') {
        json_fail('O registo de novas contas está desactivado.');
    }
    $d = request_data();
    $name  = trim((string) ($d['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
    $pass  = (string) ($d['password'] ?? '');

    if ($name === '' || mb_strlen($name) < 3) json_fail('Indique o seu nome (mín. 3 caracteres).');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_fail('E-mail inválido.');
    if (strlen($pass) < 6) json_fail('A palavra-passe deve ter pelo menos 6 caracteres.');
    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) json_fail('Já existe uma conta com este e-mail.');

    db_exec(
        'INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, "user", "active")',
        [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]
    );
    log_activity(db_last_id(), 'user_register', $email);
    json_ok(null, 'Conta criada com sucesso. Já pode iniciar sessão.');
}

// ---------------------------------------------------------------
// Perfil e preferências (requer login)
// ---------------------------------------------------------------
if ($action === 'profile') {
    $user = require_api_login();

    if ($method === 'GET') {
        $prefs = db_one('SELECT theme, font_size, line_spacing, translation_id FROM user_settings WHERE user_id = ?', [$user['id']]);
        $last  = db_one(
            'SELECT h.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
             FROM reading_history h JOIN books b ON b.id = h.book_id JOIN translations t ON t.id = h.translation_id
             WHERE h.user_id = ? ORDER BY h.read_at DESC LIMIT 1',
            [$user['id']]
        );
        json_ok(['profile' => $user, 'preferences' => $prefs, 'last_reading' => $last]);
    }

    if ($method === 'POST') {
        csrf_check();
        $d = request_data();
        db_exec(
            'INSERT INTO user_settings (user_id, theme, font_size, line_spacing, translation_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), font_size = VALUES(font_size),
                                     line_spacing = VALUES(line_spacing), translation_id = VALUES(translation_id)',
            [
                $user['id'],
                ($d['theme'] ?? 'light') === 'dark' ? 'dark' : 'light',
                max(14, min(28, (int) ($d['font_size'] ?? 17))),
                max(14, min(34, (int) ($d['line_spacing'] ?? 18))),
                ((int) ($d['translation_id'] ?? 0)) ?: null,
            ]
        );
        // Dados de perfil
        if (isset($d['name']) && trim($d['name']) !== '') {
            db_exec('UPDATE users SET name = ? WHERE id = ?', [trim((string) $d['name']), $user['id']]);
            $_SESSION['user_name'] = trim((string) $d['name']);
        }
        json_ok(null, 'Preferências guardadas.');
    }
    json_fail('Método não suportado.', 405);
}

// ---------------------------------------------------------------
// Gestão de utilizadores (admin)
// ---------------------------------------------------------------
if ($action === null || ctype_digit((string) $action)) {
    if (!is_admin()) json_fail('Acesso restrito ao administrador.', 403);

    if ($method === 'GET') {
        $rows = db_all(
            'SELECT id, name, email, role, status, created_at, last_login FROM users ORDER BY created_at DESC'
        );
        json_ok(['users' => $rows, 'total' => count($rows)]);
    }

    if ($method === 'POST') {
        csrf_check();
        $d = request_data();
        $id = (int) ($action ?? 0);

        // Eliminar/desactivar
        if (($d['_method'] ?? '') === 'DELETE' && $id > 0) {
            if ($id === $user['id']) json_fail('Não pode eliminar a sua própria conta.');
            db_exec('UPDATE users SET status = "inactive" WHERE id = ?', [$id]);
            log_activity($user['id'], 'user_disable', '#' . $id);
            json_ok(null, 'Utilizador desactivado.');
        }

        if ($id > 0) {
            // Actualizar papel/estado/palavra-passe
            if (isset($d['role']) && in_array($d['role'], ['admin', 'editor', 'user'], true)) {
                if ($id === $user['id'] && $d['role'] !== 'admin') json_fail('Não pode alterar o seu próprio papel.');
                db_exec('UPDATE users SET role = ? WHERE id = ?', [$d['role'], $id]);
            }
            if (isset($d['status']) && in_array($d['status'], ['active', 'inactive'], true)) {
                db_exec('UPDATE users SET status = ? WHERE id = ?', [$d['status'], $id]);
            }
            if (!empty($d['password'])) {
                if (strlen((string) $d['password']) < 6) json_fail('A palavra-passe deve ter pelo menos 6 caracteres.');
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) $d['password'], PASSWORD_DEFAULT), $id]);
            }
            log_activity($user['id'], 'user_update', '#' . $id);
            json_ok(null, 'Utilizador actualizado.');
        }

        // Criar utilizador
        $name  = trim((string) ($d['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $pass  = (string) ($d['password'] ?? '');
        $role  = in_array($d['role'] ?? '', ['admin', 'editor', 'user'], true) ? $d['role'] : 'user';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
            json_fail('Nome, e-mail válido e palavra-passe (mín. 6) são obrigatórios.');
        }
        if (db_one('SELECT id FROM users WHERE email = ?', [$email])) json_fail('Já existe uma conta com este e-mail.');
        db_exec(
            'INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, "active")',
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]
        );
        log_activity($user['id'], 'user_create', $email);
        json_ok(['id' => db_last_id()], 'Utilizador criado.');
    }
}

json_fail('Método não suportado.', 405);
