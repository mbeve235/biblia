<?php
/**
 * Gestão de traduções.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $abbr = strtoupper(trim((string) ($_POST['abbreviation'] ?? '')));
            if ($name === '' || $abbr === '') throw new RuntimeException('Nome e abreviação são obrigatórios.');
            if (db_one('SELECT id FROM translations WHERE abbreviation = ?', [$abbr])) {
                throw new RuntimeException('Já existe uma tradução com a abreviação ' . $abbr . '.');
            }
            db_exec(
                'INSERT INTO translations (name, abbreviation, language, description, copyright, version, source, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $abbr,
                 trim((string) ($_POST['language'] ?? 'Português')),
                 trim((string) ($_POST['description'] ?? '')) ?: null,
                 trim((string) ($_POST['copyright'] ?? '')) ?: null,
                 trim((string) ($_POST['version'] ?? '')) ?: null,
                 trim((string) ($_POST['source'] ?? '')) ?: null,
                 ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active']
            );
            $ok = 'Tradução criada. Agora importe o conteúdo em Administração → Importar.';
            log_activity(current_user()['id'], 'translation_create', $abbr);
        }

        if ($action === 'update' && $id) {
            db_exec(
                'UPDATE translations SET name=?, abbreviation=?, language=?, description=?, copyright=?, version=?, source=?, status=? WHERE id=?',
                [trim((string) $_POST['name']), strtoupper(trim((string) $_POST['abbreviation'])),
                 trim((string) $_POST['language']), trim((string) $_POST['description']) ?: null,
                 trim((string) $_POST['copyright']) ?: null, trim((string) $_POST['version']) ?: null,
                 trim((string) $_POST['source']) ?: null,
                 ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active', $id]
            );
            $ok = 'Tradução actualizada.';
            log_activity(current_user()['id'], 'translation_update', '#' . $id);
        }

        if ($action === 'toggle' && $id) {
            $t = db_one('SELECT status FROM translations WHERE id = ?', [$id]);
            if ($t) {
                db_exec('UPDATE translations SET status = ? WHERE id = ?', [$t['status'] === 'active' ? 'inactive' : 'active', $id]);
                $ok = 'Estado alterado.';
            }
        }

        if ($action === 'delete' && $id) {
            db_exec('DELETE FROM translations WHERE id = ?', [$id]);
            $ok = 'Tradução eliminada (incluindo todos os seus versículos).';
            log_activity(current_user()['id'], 'translation_delete', '#' . $id);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = db_all("
  SELECT t.*,
    (SELECT COUNT(DISTINCT book_id) FROM verses WHERE translation_id = t.id) AS books,
    (SELECT COUNT(*) FROM verses WHERE translation_id = t.id) AS verses
  FROM translations t ORDER BY t.name
");
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-language" style="color:var(--accent)"></i> Traduções</h1>
  <p style="color:var(--text-soft)">Gerir as versões da Bíblia disponíveis na aplicação.</p>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

<section class="card" id="form-card">
  <div class="card-head"><h3 id="form-title">Nova tradução</h3></div>
  <form method="post" id="trans-form">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create" id="form-do">
    <input type="hidden" name="id" value="" id="form-id">
    <div class="grid grid-3">
      <label class="field"><span>Nome *</span><input type="text" name="name" required></label>
      <label class="field"><span>Abreviação *</span><input type="text" name="abbreviation" required maxlength="20" placeholder="ARC"></label>
      <label class="field"><span>Idioma</span><input type="text" name="language" value="Português"></label>
      <label class="field"><span>Versão</span><input type="text" name="version"></label>
      <label class="field"><span>Copyright</span><input type="text" name="copyright"></label>
      <label class="field"><span>Fonte</span><input type="text" name="source"></label>
      <label class="field" style="grid-column:span 2"><span>Descrição</span><input type="text" name="description"></label>
      <label class="field"><span>Estado</span>
        <select name="status"><option value="active">Activa</option><option value="inactive">Inactiva</option></select>
      </label>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Criar tradução</button>
  </form>
</section>

<div class="table-wrap">
  <table class="table">
    <thead><tr>
      <th>Nome</th><th>Sigla</th><th>Idioma</th><th>Livros</th><th>Versículos</th><th>Estado</th><th style="text-align:right">Acções</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><strong><?= e($t['name']) ?></strong><br><small style="color:var(--text-faint)"><?= e($t['version'] ?? '') ?></small></td>
        <td><span class="badge badge-accent"><?= e($t['abbreviation']) ?></span></td>
        <td><?= e($t['language']) ?></td>
        <td><?= fmt_num((int) $t['books']) ?>/66</td>
        <td><?= fmt_num((int) $t['verses']) ?></td>
        <td><span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= $t['status'] === 'active' ? 'Activa' : 'Inactiva' ?></span></td>
        <td class="actions">
          <a class="btn btn-sm btn-ghost" title="Ver" href="<?= url('/biblia.php?translation=' . urlencode($t['abbreviation'])) ?>"><i class="fas fa-eye"></i></a>
          <button class="btn btn-sm btn-ghost" title="Editar" onclick='bwEdit(<?= json_encode([
              'id' => (int) $t['id'], 'name' => $t['name'], 'abbreviation' => $t['abbreviation'],
              'language' => $t['language'], 'description' => $t['description'], 'copyright' => $t['copyright'],
              'version' => $t['version'], 'source' => $t['source'], 'status' => $t['status'],
          ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-pen"></i></button>
          <a class="btn btn-sm btn-ghost" title="Importar" href="<?= url('/admin/importar.php?translation_id=' . (int) $t['id']) ?>"><i class="fas fa-file-import"></i></a>
          <a class="btn btn-sm btn-ghost" title="Exportar JSON" href="<?= url('/api/export/translation/' . (int) $t['id'] . '?format=json') ?>"><i class="fas fa-download"></i></a>
          <form method="post" style="display:inline" onsubmit="return confirm('Desactivar/activar esta tradução?')">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-sm btn-ghost" title="Activar/Desactivar"><i class="fas fa-power-off"></i></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('ELIMINAR a tradução <?= e($t['abbreviation']) ?> e TODOS os seus versículos? Esta acção é irreversível.')">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-sm btn-ghost" title="Eliminar" style="color:var(--danger)"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
ob_start();
?>
<script>
function bwEdit(t) {
  document.getElementById('form-title').textContent = 'Editar tradução: ' + t.name;
  document.getElementById('form-do').value = 'update';
  document.getElementById('form-id').value = t.id;
  const f = document.getElementById('trans-form');
  f.name.value = t.name;
  f.abbreviation.value = t.abbreviation;
  f.language.value = t.language || '';
  f.description.value = t.description || '';
  f.copyright.value = t.copyright || '';
  f.version.value = t.version || '';
  f.source.value = t.source || '';
  f.status.value = t.status;
  document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
}
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/_admin_footer.php';