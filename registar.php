<?php
/**
 * Registo de nova conta.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$pageTitle = 'Criar conta';

$error = null;
$errorField = null;
$ok = false;

if (settings_get('registration_enabled', '1') !== '1') {
    $error = 'O registo de novas contas está desactivado pelo administrador.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($name) < 3)        { $error = 'Indique o seu nome (mín. 3 caracteres).'; $errorField = 'name'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'E-mail inválido.'; $errorField = 'email'; }
    elseif (strlen($pass) < 6)       { $error = 'A palavra-passe deve ter pelo menos 6 caracteres.'; $errorField = 'password'; }
    elseif ($pass !== $pass2)        { $error = 'As palavras-passe não coincidem.'; $errorField = 'password2'; }
    elseif (db_one('SELECT id FROM users WHERE email = ?', [$email])) { $error = 'Já existe uma conta com este e-mail.'; $errorField = 'email'; }

    if (!$error) {
        db_exec(
            'INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, "user", "active")',
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]
        );
        log_activity(db_last_id(), 'user_register', $email);
        $ok = true;
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-shell">
    <aside class="auth-aside auth-aside-register">
      <a class="brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><span><?= e(APP_NAME) ?></span></a>
      <div class="auth-aside-copy"><span class="auth-kicker">Comece hoje</span><h1>Construa o seu espaço de estudo.</h1><p>Guarde versículos, escreva notas e acompanhe a sua jornada de leitura.</p></div>
      <ul class="auth-benefits"><li><i class="fas fa-check"></i> Versículos guardados e sincronizados</li><li><i class="fas fa-check"></i> Notas e ministrações pessoais</li><li><i class="fas fa-check"></i> Histórico de leitura</li></ul>
    </aside>

    <section class="card auth-panel">
      <a class="auth-mobile-brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><strong><?= e(APP_NAME) ?></strong></a>
      <div class="auth-heading"><span class="auth-heading-icon"><i class="fas fa-user-plus"></i></span><div><h2>Criar a sua conta</h2><p>É gratuito e demora menos de um minuto.</p></div></div>

    <?php if ($ok): ?>
      <div class="alert alert-success" role="status">Conta criada com sucesso! Já pode <a href="<?= url('/login.php') ?>">iniciar sessão</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

      <form method="post" data-auth-form>
        <?= csrf_field() ?>
        <label class="field"><span>Nome</span>
          <span class="auth-input"><i class="fas fa-user"></i><input type="text" name="name" required minlength="3" autocomplete="name" placeholder="O seu nome" value="<?= e($_POST['name'] ?? '') ?>" <?= $errorField === 'name' ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>></span>
          <?php if ($errorField === 'name'): ?><small class="field-error" id="name-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <label class="field"><span>E-mail</span>
          <span class="auth-input"><i class="fas fa-envelope"></i><input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="nome@exemplo.com" value="<?= e($_POST['email'] ?? '') ?>" <?= $errorField === 'email' ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>></span>
          <?php if ($errorField === 'email'): ?><small class="field-error" id="email-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <label class="field"><span>Palavra-passe</span>
          <span class="auth-input"><i class="fas fa-lock"></i><input id="register-password" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Mínimo de 6 caracteres" data-password-strength <?= $errorField === 'password' ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>><button type="button" data-password-toggle="register-password" aria-label="Mostrar palavra-passe"><i class="fas fa-eye"></i></button></span>
          <span class="password-strength" aria-live="polite"><span></span><small>Use letras, números e símbolos</small></span>
          <?php if ($errorField === 'password'): ?><small class="field-error" id="password-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <label class="field"><span>Confirmar palavra-passe</span>
          <span class="auth-input"><i class="fas fa-shield-halved"></i><input id="register-password2" type="password" name="password2" required minlength="6" autocomplete="new-password" placeholder="Repita a palavra-passe" data-password-confirm="register-password" <?= $errorField === 'password2' ? 'aria-invalid="true" aria-describedby="password2-error"' : '' ?>><button type="button" data-password-toggle="register-password2" aria-label="Mostrar palavra-passe"><i class="fas fa-eye"></i></button></span>
          <small class="field-hint" data-confirm-hint></small>
          <?php if ($errorField === 'password2'): ?><small class="field-error" id="password2-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <button class="btn btn-primary btn-block auth-submit" type="submit" data-loading-text="A criar conta…"><span>Criar conta</span><i class="fas fa-arrow-right"></i></button>
      </form>

      <p class="auth-switch">Já tem conta? <a href="<?= url('/login.php') ?>">Entrar</a></p>
    <?php endif; ?>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
