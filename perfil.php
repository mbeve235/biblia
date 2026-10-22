<?php
/**
 * Perfil — dados, preferências de leitura e palavra-passe.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
$pageTitle = 'Perfil e preferências';

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
        $newTheme = ($d['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $newFontSize = max(14, min(28, (int) ($d['font_size'] ?? 17)));
        $newLineSpacing = max(14, min(34, (int) ($d['line_spacing'] ?? 18)));
        db_exec(
            'INSERT INTO user_settings (user_id, theme, font_size, line_spacing) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), font_size = VALUES(font_size), line_spacing = VALUES(line_spacing)',
            [
                $user['id'],
                $newTheme,
                $newFontSize,
                $newLineSpacing,
            ]
        );
        $prefs = ['theme' => $newTheme, 'font_size' => $newFontSize, 'line_spacing' => $newLineSpacing];
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
require_once __DIR__ . '/includes/header.php';
?>
<?= apply_db_prefs(); ?>

<header class="page-header">
  <span class="page-kicker">Conta</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-user"></i></span><div><h1>O meu perfil</h1><p>Dados pessoais, leitura e segurança da conta.</p></div></div>
</header>

<div class="grid grid-2">
  <section class="card settings-card">
    <div class="settings-card-head"><span><i class="fas fa-user-pen"></i></span><div><h3>Dados pessoais</h3><p>Informações principais da sua conta.</p></div></div>
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

  <section class="card settings-card">
    <div class="settings-card-head"><span><i class="fas fa-book-open-reader"></i></span><div><h3>Preferências de leitura</h3><p>Ajuste a leitura ao seu conforto.</p></div></div>
    <?php if ($saved): ?><div class="alert alert-success">Preferências guardadas.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <fieldset class="reading-theme-choice"><legend>Tema</legend>
        <label><input type="radio" name="theme" value="light" <?= $prefs['theme'] === 'light' ? 'checked' : '' ?>><span class="theme-swatch light"><i></i><b>Claro</b><small>Marfim suave</small></span></label>
        <label><input type="radio" name="theme" value="dark" <?= $prefs['theme'] === 'dark' ? 'checked' : '' ?>><span class="theme-swatch dark"><i></i><b>Escuro</b><small>Carvão quente</small></span></label>
      </fieldset>
      <label class="field range-field"><span>Tamanho da letra <output id="font-size-output"><?= (int) $prefs['font_size'] ?>px</output></span><input type="range" name="font_size" min="14" max="28" value="<?= (int) $prefs['font_size'] ?>"></label>
      <label class="field range-field"><span>Espaçamento entre linhas <output id="line-spacing-output"><?= number_format((int)$prefs['line_spacing']/10,1) ?></output></span><input type="range" name="line_spacing" min="14" max="34" value="<?= (int) $prefs['line_spacing'] ?>"></label>
      <div class="reading-preview" id="reading-preview"><small>Pré-visualização</small><p><sup>16</sup> Porque Deus amou o mundo de tal maneira que deu o seu Filho unigénito.</p></div>
      <button class="btn btn-primary" name="save_prefs" value="1" type="submit">Guardar preferências</button>
    </form>
  </section>

  <section class="card settings-card">
    <div class="settings-card-head"><span><i class="fas fa-lock"></i></span><div><h3>Alterar palavra-passe</h3><p>Proteja o acesso à sua conta.</p></div></div>
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

  <section class="card settings-card">
    <div class="settings-card-head"><span><i class="fas fa-chart-simple"></i></span><div><h3>Actividade</h3><p>Resumo do conteúdo que guardou.</p></div></div>
    <div class="stats-grid">
      <div class="stat-card"><div class="icon"><i class="fas fa-star"></i></div>
        <div><div class="value"><?= fmt_num($favCount) ?></div><div class="label">Favoritos</div></div></div>
      <div class="stat-card"><div class="icon"><i class="fas fa-pen-to-square"></i></div>
        <div><div class="value"><?= fmt_num($noteCount) ?></div><div class="label">Notas</div></div></div>
    </div>
    <p class="settings-logout">
      <a class="btn btn-danger" href="<?= url('/logout.php') ?>"><i class="fas fa-right-from-bracket"></i> Terminar sessão</a>
    </p>
  </section>
</div>

<?php ob_start(); ?>
<script>
const preferenceForm=document.querySelector('button[name="save_prefs"]')?.form;
const fontRange=preferenceForm?.querySelector('[name="font_size"]');
const lineRange=preferenceForm?.querySelector('[name="line_spacing"]');
const readingPreview=document.getElementById('reading-preview');
function previewPreferences(){
  if(!readingPreview)return;
  const size=Number(fontRange.value),line=Number(lineRange.value)/10;
  readingPreview.style.fontSize=size+'px';readingPreview.style.lineHeight=String(line);
  document.getElementById('font-size-output').value=size+'px';document.getElementById('line-spacing-output').value=line.toFixed(1);
}
fontRange?.addEventListener('input',previewPreferences);lineRange?.addEventListener('input',previewPreferences);previewPreferences();
preferenceForm?.addEventListener('submit',()=>{const theme=preferenceForm.querySelector('[name="theme"]:checked')?.value||'light';localStorage.setItem('bw_theme',theme);localStorage.setItem('bw_fontsize',fontRange.value);});
document.querySelectorAll('[name="theme"]').forEach(input=>input.addEventListener('change',()=>applyTheme(input.value)));
</script>
<?php $pageScripts=ob_get_clean(); require_once __DIR__ . '/includes/footer.php'; ?>
