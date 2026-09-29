<?php
/**
 * Login.
 */
require_once __DIR__ . '/includes/header.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $error = login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if (!$error) {
        $after = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        $target = $after ?: url('/index.php');
        echo '<script>location.href=' . json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
}
$needLogin = ($_GET['msg'] ?? '') === 'needlogin';
?>

<div class="auth-wrap">
  <a class="brand" href="<?= url('/index.php') ?>">
    <span class="logo"><i class="fas fa-book-bible"></i></span>
    <span><?= e(APP_NAME) ?></span>
  </a>

  <div class="card">
    <h2><i class="fas fa-right-to-bracket" style="color:var(--accent)"></i> Entrar</h2>

    <?php if ($needLogin && !$error): ?>
      <div class="alert alert-warning">Inicie sessão para aceder a essa página.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <label class="field"><span>E-mail</span>
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </label>
      <label class="field"><span>Palavra-passe</span>
        <input type="password" name="password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Entrar</button>
    </form>

    <p style="margin-top:1rem;font-size:.88rem;text-align:center">
      <a href="<?= url('/registar.php') ?>">Criar conta</a> ·
      <a href="<?= url('/recuperar.php') ?>">Esqueci a palavra-passe</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
