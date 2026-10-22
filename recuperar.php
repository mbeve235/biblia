<?php
/**
 * Recuperação de palavra-passe em dois passos:
 * 1) indicar o e-mail — o link de redefinição é enviado SÓ por e-mail
 *    (nunca mostrado no ecrã); 2) definir a nova palavra-passe pelo link recebido.
 */
require_once __DIR__ . '/includes/header.php';

$step = 'request';
$error = null;
$errorField = null;
$info = null;
$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));

if ($token !== '' && validate_password_reset($token)) {
    $step = 'reset';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        csrf_check();
        $pass  = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pass) < 6)      { $error = 'A palavra-passe deve ter pelo menos 6 caracteres.'; $errorField = 'password'; }
        elseif ($pass !== $pass2)   { $error = 'As palavras-passe não coincidem.'; $errorField = 'password2'; }
        if (!$error) {
            $userId = validate_password_reset($token);
            if ($userId) {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $userId]);
                consume_password_reset($token);
                log_activity($userId, 'password_reset');
                $info = 'Palavra-passe alterada com sucesso. Já pode iniciar sessão.';
                $step = 'done';
            } else {
                $error = 'Token inválido ou expirado.';
                $step = 'request';
            }
        }
    }
} elseif ($token !== '') {
    $error = 'Token inválido ou expirado. Peça um novo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['password']) && $step === 'request') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $newToken = create_password_reset($email);
    // Por segurança não revelamos se o e-mail existe
    $info = 'Se este e-mail estiver registado, receberá instruções. (Verifique também a escrita do endereço.)';
    if ($newToken) {
        $resetLink = url('/recuperar.php?token=' . $newToken);
        $userName = (string) (db_one('SELECT name FROM users WHERE email = ?', [mb_strtolower($email)])['name'] ?? '');
        $greeting = 'Olá' . ('' !== $userName ? ' ' . $userName : '') . ",\n\n";
        $sent = send_app_mail(
            $email,
            APP_NAME . ' — recuperação de palavra-passe',
            $greeting
            . "Recebemos um pedido de recuperação de palavra-passe para a sua conta.\n"
            . "Use o link abaixo para definir uma nova (expira em 1 hora):\n\n"
            . $resetLink . "\n\n"
            . "Se não foi você quem pediu, ignore este e-mail — a sua palavra-passe actual continua válida.\n\n"
            . APP_NAME
        );
        // Mesmo em falha de envio a mensagem mantém-se idêntica: nunca revelamos
        // nada nem colocamos o link no ecrã — ele viaja exclusivamente por e-mail.
    }
}
?>

<div class="auth-wrap">
  <div class="auth-shell">
    <aside class="auth-aside auth-aside-recovery">
      <a class="brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><span><?= e(APP_NAME) ?></span></a>
      <div class="auth-aside-copy"><span class="auth-kicker">Acesso seguro</span><h1>Recupere a sua conta com tranquilidade.</h1><p>Enviaremos um link privado, válido durante uma hora, para o e-mail associado à conta.</p></div>
      <div class="auth-security"><i class="fas fa-shield-halved"></i><span>Por segurança, nunca revelamos se um endereço está registado.</span></div>
    </aside>

    <section class="card auth-panel">
      <a class="auth-mobile-brand" href="<?= url('/index.php') ?>"><span class="logo"><?= brand_svg() ?></span><strong><?= e(APP_NAME) ?></strong></a>
      <div class="auth-heading"><span class="auth-heading-icon"><i class="fas fa-key"></i></span><div><h2><?= $step === 'reset' ? 'Definir nova palavra-passe' : ($step === 'done' ? 'Palavra-passe alterada' : 'Recuperar acesso') ?></h2><p><?= $step === 'reset' ? 'Escolha uma palavra-passe segura.' : 'Indique o e-mail associado à sua conta.' ?></p></div></div>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success" role="status"><?= e($info) ?></div><?php endif; ?>

    <?php if ($step === 'request'): ?>
      <form method="post" data-auth-form>
        <?= csrf_field() ?>
        <label class="field"><span>E-mail da conta</span>
          <span class="auth-input"><i class="fas fa-envelope"></i><input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="nome@exemplo.com" value="<?= e($_POST['email'] ?? '') ?>"></span>
        </label>
        <button class="btn btn-primary btn-block auth-submit" type="submit" data-loading-text="A enviar…"><span>Enviar instruções</span><i class="fas fa-paper-plane"></i></button>
      </form>
    <?php elseif ($step === 'reset'): ?>
      <form method="post" data-auth-form>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="field"><span>Nova palavra-passe</span>
          <span class="auth-input"><i class="fas fa-lock"></i><input id="reset-password" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Mínimo de 6 caracteres" data-password-strength <?= $errorField === 'password' ? 'aria-invalid="true" aria-describedby="reset-password-error"' : '' ?>><button type="button" data-password-toggle="reset-password" aria-label="Mostrar palavra-passe"><i class="fas fa-eye"></i></button></span>
          <span class="password-strength" aria-live="polite"><span></span><small>Use letras, números e símbolos</small></span>
          <?php if ($errorField === 'password'): ?><small class="field-error" id="reset-password-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <label class="field"><span>Confirmar</span>
          <span class="auth-input"><i class="fas fa-shield-halved"></i><input id="reset-password2" type="password" name="password2" required minlength="6" autocomplete="new-password" placeholder="Repita a palavra-passe" data-password-confirm="reset-password" <?= $errorField === 'password2' ? 'aria-invalid="true" aria-describedby="reset-password2-error"' : '' ?>><button type="button" data-password-toggle="reset-password2" aria-label="Mostrar palavra-passe"><i class="fas fa-eye"></i></button></span>
          <small class="field-hint" data-confirm-hint></small>
          <?php if ($errorField === 'password2'): ?><small class="field-error" id="reset-password2-error"><?= e($error) ?></small><?php endif; ?>
        </label>
        <button class="btn btn-primary btn-block auth-submit" type="submit" data-loading-text="A guardar…"><span>Guardar nova palavra-passe</span><i class="fas fa-check"></i></button>
      </form>
    <?php else: ?>
      <a class="btn btn-primary btn-block" href="<?= url('/login.php') ?>">Ir para o login</a>
    <?php endif; ?>

      <p class="auth-switch"><a href="<?= url('/login.php') ?>"><i class="fas fa-arrow-left"></i> Voltar ao login</a></p>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
