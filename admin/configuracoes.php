<?php
/**
 * Configurações gerais do sistema — apenas administradores.
 */
require_once __DIR__ . '/_admin_header.php';
require_admin();

$ok = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $keys = ['site_name', 'default_translation', 'registration_enabled', 'max_upload_mb', 'items_per_page'];
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            $v = trim((string) $_POST[$k]);
            db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
    }
    log_activity(current_user()['id'], 'settings_update');
    $ok = 'Configurações guardadas.';
}

$siteName      = settings_get('site_name', APP_NAME);
$defTrans      = settings_get('default_translation', DEFAULT_TRANSLATION);
$regEnabled    = settings_get('registration_enabled', '1');
$maxUpload     = settings_get('max_upload_mb', '25');
$itemsPerPage  = settings_get('items_per_page', '50');

$translations = db_all('SELECT abbreviation, name FROM translations ORDER BY name');
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-gear" style="color:var(--accent)"></i> Configurações</h1>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

<section class="card" style="max-width:640px">
  <form method="post">
    <?= csrf_field() ?>
    <label class="field"><span>Nome do site</span>
      <input type="text" name="site_name" value="<?= e($siteName) ?>">
    </label>
    <label class="field"><span>Tradução por omissão</span>
      <select name="default_translation">
        <?php foreach ($translations as $t): ?>
          <option value="<?= e($t['abbreviation']) ?>" <?= $defTrans === $t['abbreviation'] ? 'selected' : '' ?>>
            <?= e($t['abbreviation']) ?> — <?= e($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>Permitir registo de novas contas</span>
      <select name="registration_enabled">
        <option value="1" <?= $regEnabled === '1' ? 'selected' : '' ?>>Sim</option>
        <option value="0" <?= $regEnabled !== '1' ? 'selected' : '' ?>>Não</option>
      </select>
    </label>
    <label class="field"><span>Limite de upload (MB)</span>
      <input type="number" name="max_upload_mb" min="1" max="100" value="<?= (int) $maxUpload ?>">
    </label>
    <label class="field"><span>Resultados por página (pesquisa)</span>
      <input type="number" name="items_per_page" min="10" max="200" value="<?= (int) $itemsPerPage ?>">
    </label>
    <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk"></i> Guardar</button>
  </form>
</section>

<section class="card" style="max-width:640px">
  <h3>Informação do sistema</h3>
  <div class="table-wrap"><table class="table">
    <tbody>
      <tr><td>Versão da aplicação</td><td><strong><?= e(APP_VERSION) ?></strong></td></tr>
      <tr><td>PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
      <tr><td>SGBD</td><td><?= e(db_one('SELECT VERSION() AS v')['v'] ?? '—') ?></td></tr>
      <tr><td>Limite de memória</td><td><?= e(ini_get('memory_limit')) ?></td></tr>
      <tr><td>Tempo máx. de execução</td><td><?= e(ini_get('max_execution_time')) ?>s</td></tr>
      <tr><td>Upload máx. (PHP)</td><td><?= e(ini_get('upload_max_filesize')) ?></td></tr>
      <tr><td>Base de dados</td><td><?= e(DB_NAME) ?> @ <?= e(DB_HOST) ?></td></tr>
    </tbody>
  </table></div>
</section>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
