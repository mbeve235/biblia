<?php
/**
 * Registo de nova conta.
 */
require_once __DIR__ . '/includes/header.php';

$error = null;
$ok = false;

if (settings_get('registration_enabled', '1') !== '1') {
    $error = 'O registo de novas contas está desactivado pelo administrador.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($name) < 3)        $error = 'Indique o seu nome (mín. 3 caracteres).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'E-mail inválido.';
    elseif (strlen($pass) < 6)       $error = 'A palavra-passe deve ter pelo menos 6 caracteres.';
    elseif ($pass !== $pass2)        $error = 'As palavras-passe não coincidem.';
    elseif (db_one('SELECT id FROM users WHERE email = ?', [$email])) $error = 'Já existe uma conta com este e-mail.';

    if (!$error) {
        db_exec(
            'INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, "user", "active")',
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]
        );
        log_activity(db_last_id(), 'user_register', $email);
        $ok = true;
    }
}
?>

<div class="auth-wrap">
  <a class="brand" href="<?= url('/index.php') ?>">
    <span class="logo"><i class="fas fa-book-bible"></i></span>
    <span><?= e(APP_NAME) ?></span>
  </a>

  <div class="card">
    <h2><i class="fas fa-user-plus" style="color:var(--accent)"></i> Criar conta</h2>

    <?php if ($ok): ?>
      <div class="alert alert-success">Conta criada com sucesso! Já pode <a href="<?= url('/login.php') ?>">iniciar sessão</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <label class="field"><span>Nome</span>
          <input type="text" name="name" required minlength="3" value="<?= e($_POST['name'] ?? '') ?>">
        </label>
        <label class="field"><span>E-mail</span>
          <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label class="field"><span>Palavra-passe (mín. 6)</span>
          <input type="password" name="password" required minlength="6">
        </label>
        <label class="field"><span>Confirmar palavra-passe</span>
          <input type="password" name="password2" required minlength="6">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Criar conta</button>
      </form>

      <p style="margin-top:1rem;font-size:.88rem;text-align:center">
        Já tem conta? <a href="<?= url('/login.php') ?>">Entrar</a>
      </p>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
