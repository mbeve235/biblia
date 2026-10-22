<?php
/**
 * Recuperação de palavra-passe em dois passos:
 * 1) indicar o e-mail — o link de redefinição é enviado SÓ por e-mail
 *    (nunca mostrado no ecrã); 2) definir a nova palavra-passe pelo link recebido.
 */
require_once __DIR__ . '/includes/header.php';

$step = 'request';
$error = null;
$info = null;
$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));

if ($token !== '' && validate_password_reset($token)) {
    $step = 'reset';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        csrf_check();
        $pass  = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pass) < 6)      $error = 'A palavra-passe deve ter pelo menos 6 caracteres.';
        elseif ($pass !== $pass2)   $error = 'As palavras-passe não coincidem.';
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
  <a class="brand" href="<?= url('/index.php') ?>">
    <span class="logo"><?= brand_svg() ?></span>
    <span><?= e(APP_NAME) ?></span>
  </a>

  <div class="card">
    <h2><i class="fas fa-key" style="color:var(--accent)"></i> Recuperar palavra-passe</h2>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success"><?= e($info) ?></div><?php endif; ?>

    <?php if ($step === 'request'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <label class="field"><span>E-mail da conta</span>
          <input type="email" name="email" required autocomplete="email">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Gerar link de recuperação</button>
      </form>
    <?php elseif ($step === 'reset'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="field"><span>Nova palavra-passe (mín. 6)</span>
          <input type="password" name="password" required minlength="6">
        </label>
        <label class="field"><span>Confirmar</span>
          <input type="password" name="password2" required minlength="6">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Guardar nova palavra-passe</button>
      </form>
    <?php else: ?>
      <a class="btn btn-primary btn-block" href="<?= url('/login.php') ?>">Ir para o login</a>
    <?php endif; ?>

    <p style="margin-top:1rem;font-size:.88rem;text-align:center"><a href="<?= url('/login.php') ?>">Voltar ao login</a></p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
