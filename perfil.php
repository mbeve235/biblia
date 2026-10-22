<?php
/**
 * Perfil — dados, preferências de leitura e palavra-passe.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
require_once __DIR__ . '/includes/header.php';

$prefs = db_one('SELECT theme, font_size, line_spacing FROM user_settings WHERE user_id = ?', [$user['id']]) ??
         ['theme' => 'light', 'font_size' => 17, 'line_spacing' => 18];
$favCount = (int) (db_one('SELECT COUNT(*) AS c FROM favorites WHERE user_id = ?', [$user['id']])['c'] ?? 0);
$noteCount = (int) (db_one('SELECT COUNT(*) AS c FROM notes WHERE user_id = ?', [$user['id']])['c'] ?? 0);

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = $_POST;

    if (isset($d['save_profile'])) {
        $name = trim((string) ($d['name'] ?? ''));
        if (mb_strlen($name) >= 3) {
            db_exec('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]);
            $_SESSION['user_name'] = $name;
            $user['name'] = $name;
        }
    }

    if (isset($d['save_prefs'])) {
        db_exec(
            'INSERT INTO user_settings (user_id, theme, font_size, line_spacing) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), font_size = VALUES(font_size), line_spacing = VALUES(line_spacing)',
            [
                $user['id'],
                ($d['theme'] ?? 'light') === 'dark' ? 'dark' : 'light',
                max(14, min(28, (int) ($d['font_size'] ?? 17))),
                max(14, min(34, (int) ($d['line_spacing'] ?? 18))),
            ]
        );
        apply_db_prefs();
        $saved = true;
    }

    if (isset($d['save_password'])) {
        $current = (string) ($d['current'] ?? '');
        $new     = (string) ($d['password'] ?? '');
        $row = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!$row || !password_verify($current, (string) $row['password_hash'])) {
            $pwError = 'Palavra-passe actual incorrecta.';
        } elseif (strlen($new) < 6) {
            $pwError = 'A nova palavra-passe deve ter pelo menos 6 caracteres.';
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $pwOk = 'Palavra-passe alterada com sucesso.';
        }
    }
}

/** Aplica preferências guardadas ao CSS. */
function apply_db_prefs(): void
{
    $u = current_user();
    if (!$u) return;
    $p = db_one('SELECT theme, font_size, line_spacing FROM user_settings WHERE user_id = ?', [$u['id']]);
    if ($p) {
        echo '<style>:root{--read-font-size:' . (int) $p['font_size'] . 'px;--read-line:' . ((int) $p['line_spacing'] / 10) . '}</style>';
    }
}
?>
<?= apply_db_prefs(); ?>

<div class="reader-header">
  <div class="crumb">Conta</div>
  <h1><i class="fas fa-user" style="color:var(--accent)"></i> O meu perfil</h1>
</div>

<div class="grid grid-2">
  <section class="card">
    <h3>Dados pessoais</h3>
    <form method="post">
      <?= csrf_field() ?>
      <label class="field"><span>Nome</span>
        <input type="text" name="name" value="<?= e($user['name']) ?>" minlength="3" required>
      </label>
      <label class="field"><span>E-mail (não editável)</span>
        <input type="email" value="<?= e($user['email']) ?>" disabled autocomplete="email">
      </label>
      <label class="field"><span>Função</span>
        <input type="text" value="<?= e($user['role']) ?>" disabled>
      </label>
      <button class="btn btn-primary" name="save_profile" value="1" type="submit">Guardar dados</button>
    </form>
  </section>

  <section class="card">
    <h3>Preferências de leitura</h3>
    <?php if ($saved): ?><div class="alert alert-success">Preferências guardadas.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label class="field"><span>Tema</span>
        <select name="theme">
          <option value="light" <?= $prefs['theme'] === 'light' ? 'selected' : '' ?>>Claro</option>
          <option value="dark" <?= $prefs['theme'] === 'dark' ? 'selected' : '' ?>>Escuro</option>
        </select>
      </label>
      <label class="field"><span>Tamanho da letra (14-28px)</span>
        <input type="number" name="font_size" min="14" max="28" value="<?= (int) $prefs['font_size'] ?>">
      </label>
      <label class="field"><span>Espaçamento entre linhas (14-34)</span>
        <input type="number" name="line_spacing" min="14" max="34" value="<?= (int) $prefs['line_spacing'] ?>">
      </label>
      <button class="btn btn-primary" name="save_prefs" value="1" type="submit">Guardar preferências</button>
    </form>
  </section>

  <section class="card">
    <h3>Alterar palavra-passe</h3>
    <?php if (!empty($pwOk)): ?><div class="alert alert-success"><?= e($pwOk) ?></div><?php endif; ?>
    <?php if (!empty($pwError)): ?><div class="alert alert-error"><?= e($pwError) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label class="field"><span>Palavra-passe actual</span>
        <input type="password" name="current" required autocomplete="current-password">
      </label>
      <label class="field"><span>Nova palavra-passe (mín. 6)</span>
        <input type="password" name="password" required minlength="6" autocomplete="new-password">
      </label>
      <button class="btn btn-primary" name="save_password" value="1" type="submit">Alterar palavra-passe</button>
    </form>
  </section>

  <section class="card">
    <h3>Actividade</h3>
    <div class="stats-grid">
      <div class="stat-card"><div class="icon"><i class="fas fa-star"></i></div>
        <div><div class="value"><?= fmt_num($favCount) ?></div><div class="label">Favoritos</div></div></div>
      <div class="stat-card"><div class="icon"><i class="fas fa-pen-to-square"></i></div>
        <div><div class="value"><?= fmt_num($noteCount) ?></div><div class="label">Notas</div></div></div>
    </div>
    <p style="margin-top:1rem">
      <a class="btn btn-danger" href="<?= url('/logout.php') ?>"><i class="fas fa-right-from-bracket"></i> Terminar sessão</a>
    </p>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
