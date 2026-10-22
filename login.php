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
  <div class="auth-shell">
    <aside class="auth-aside">
      <a class="brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><span><?= e(APP_NAME) ?></span></a>
      <div class="auth-aside-copy">
        <span class="auth-kicker">A sua jornada continua</span>
        <h1>Volte à Palavra de onde parou.</h1>
        <p>Os seus favoritos, notas, ministrações e histórico ficam guardados num só lugar.</p>
      </div>
      <div class="auth-quote"><i class="fas fa-quote-left"></i><span>“Lâmpada para os meus pés é a tua palavra.”</span><small>Salmos 119:105</small></div>
    </aside>

    <section class="card auth-panel">
      <a class="auth-mobile-brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><strong><?= e(APP_NAME) ?></strong></a>
      <div class="auth-heading"><span class="auth-heading-icon"><i class="fas fa-right-to-bracket"></i></span><div><h2>Bem-vindo novamente</h2><p>Entre para continuar a sua leitura.</p></div></div>

    <?php if ($needLogin && !$error): ?>
      <div class="alert alert-warning" role="status">Inicie sessão para aceder a essa página.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" data-auth-form>
      <?= csrf_field() ?>
      <label class="field"><span>E-mail</span>
        <span class="auth-input"><i class="fas fa-envelope"></i><input type="email" name="email" required autofocus autocomplete="email" inputmode="email" placeholder="nome@exemplo.com" value="<?= e($_POST['email'] ?? '') ?>"></span>
      </label>
      <label class="field"><span>Palavra-passe <a class="field-link" href="<?= url('/recuperar.php') ?>">Esqueci-me</a></span>
        <span class="auth-input"><i class="fas fa-lock"></i><input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="A sua palavra-passe"><button type="button" data-password-toggle="login-password" aria-label="Mostrar palavra-passe"><i class="fas fa-eye"></i></button></span>
      </label>
      <button class="btn btn-primary btn-block auth-submit" type="submit" data-loading-text="A entrar…"><span>Entrar</span><i class="fas fa-arrow-right"></i></button>
    </form>

      <p class="auth-switch">Ainda não tem conta? <a href="<?= url('/registar.php') ?>">Criar conta gratuita</a></p>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
