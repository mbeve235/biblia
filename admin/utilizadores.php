<?php
/**
 * Gestão de utilizadores — apenas administradores.
 */
require_once __DIR__ . '/_admin_header.php';
require_admin();

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($do === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $pass = (string) ($_POST['password'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'user'], true) ? $_POST['role'] : 'user';
            if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                throw new RuntimeException('Dados inválidos (nome ≥3, e-mail válido, senha ≥6).');
            }
            if (db_one('SELECT id FROM users WHERE email = ?', [$email])) throw new RuntimeException('E-mail já registado.');
            db_exec('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, "active")',
                [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            $ok = 'Utilizador criado.';
            log_activity(current_user()['id'], 'user_create', $email);
        }
        if ($do === 'role' && $id) {
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'user'], true) ? $_POST['role'] : 'user';
            if ($id === current_user()['id'] && $role !== 'admin') throw new RuntimeException('Não pode rebaixar a sua própria conta.');
            db_exec('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
            $ok = 'Papel actualizado.';
        }
        if ($do === 'status' && $id) {
            if ($id === current_user()['id']) throw new RuntimeException('Não pode desactivar a sua própria conta.');
            db_exec('UPDATE users SET status = ? WHERE id = ?', [($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive', $id]);
            $ok = 'Estado actualizado.';
        }
        if ($do === 'password' && $id) {
            $pass = (string) ($_POST['password'] ?? '');
            if (strlen($pass) < 6) throw new RuntimeException('Palavra-passe demasiado curta.');
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            $ok = 'Palavra-passe reposta.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = db_all('SELECT id, name, email, role, status, created_at, last_login FROM users ORDER BY created_at DESC');
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-users" style="color:var(--accent)"></i> Utilizadores</h1>
  <p style="color:var(--text-soft)">Administrador: controlo total · Editor: conteúdo e importações · Utilizador: leitura.</p>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

<section class="card">
  <div class="card-head"><h3>Novo utilizador</h3></div>
  <form method="post" class="grid grid-5" style="grid-template-columns:1fr 1.4fr 1fr 1fr auto;gap:.6rem;align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <label class="field" style="margin:0"><span>Nome</span><input type="text" name="name" required></label>
    <label class="field" style="margin:0"><span>E-mail</span><input type="email" name="email" required></label>
    <label class="field" style="margin:0"><span>Palavra-passe</span><input type="password" name="password" required minlength="6"></label>
    <label class="field" style="margin:0"><span>Papel</span>
      <select name="role"><option value="user">Utilizador</option><option value="editor">Editor</option><option value="admin">Administrador</option></select>
    </label>
    <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i></button>
  </form>
</section>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Estado</th><th>Último acesso</th><th style="text-align:right">Acções</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <form method="post" style="display:flex;gap:.3rem">
            <?= csrf_field() ?><input type="hidden" name="do" value="role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <select name="role" style="width:120px;padding:.25rem .4rem;font-size:.8rem">
              <?php foreach (['admin', 'editor', 'user'] as $r): ?>
                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-ghost" type="submit" title="Guardar papel"><i class="fas fa-check"></i></button>
          </form>
        </td>
        <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= $u['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span></td>
        <td><small><?= fmt_datetime($u['last_login']) ?></small></td>
        <td class="actions">
          <form method="post" style="display:inline" onsubmit="return confirm('Alterar estado deste utilizador?')">
            <?= csrf_field() ?><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
            <button class="btn btn-sm btn-ghost" title="Activar/Desactivar"><i class="fas fa-power-off"></i></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Repor palavra-passe? Insira-a no campo:')" >
            <?= csrf_field() ?><input type="hidden" name="do" value="password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="password" name="password" placeholder="nova senha" style="width:110px;padding:.25rem .4rem;font-size:.8rem" required minlength="6">
            <button class="btn btn-sm btn-ghost" title="Repor palavra-passe"><i class="fas fa-key"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
