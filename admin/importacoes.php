<?php
/**
 * Histórico de importações.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$imports = db_all(
    'SELECT i.*, t.abbreviation, u.name AS user_name
     FROM imports i
     LEFT JOIN translations t ON t.id = i.translation_id
     LEFT JOIN users u ON u.id = i.user_id
     ORDER BY i.created_at DESC LIMIT 100'
);

$viewId = (int) ($_GET['view'] ?? 0);
$viewImport = null;
$viewErrors = [];
if ($viewId) {
    $viewImport = db_one(
        'SELECT i.*, t.abbreviation FROM imports i LEFT JOIN translations t ON t.id = i.translation_id WHERE i.id = ?',
        [$viewId]
    );
    if ($viewImport) {
        $viewErrors = db_all('SELECT location, problem, severity FROM import_errors WHERE import_id = ? ORDER BY id LIMIT 200', [$viewId]);
    }
}
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-clipboard-list" style="color:var(--accent)"></i> Importações</h1>
</div>

<?php if ($viewImport): ?>
  <section class="card">
    <div class="card-head">
      <h3>Importação #<?= (int) $viewImport['id'] ?> — <?= e($viewImport['abbreviation'] ?? '—') ?></h3>
      <a class="btn btn-sm" href="<?= url('/admin/importacoes.php') ?>">Voltar</a>
    </div>
    <div class="grid grid-4">
      <div class="stat-card"><div class="icon"><i class="fas fa-scroll"></i></div>
        <div><div class="value"><?= fmt_num((int) $viewImport['imported_verses']) ?>/<?= fmt_num((int) $viewImport['total_verses']) ?></div><div class="label">Versículos</div></div></div>
      <div class="stat-card"><div class="icon"><i class="fas fa-clone"></i></div>
        <div><div class="value"><?= fmt_num((int) $viewImport['duplicates_count']) ?></div><div class="label">Duplicados</div></div></div>
      <div class="stat-card"><div class="icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="value"><?= fmt_num((int) $viewImport['errors_count']) ?></div><div class="label">Erros</div></div></div>
      <div class="stat-card"><div class="icon"><i class="fas fa-file"></i></div>
        <div><div class="value" style="font-size:.95rem"><?= e(excerpt((string) $viewImport['filename'], 20)) ?></div><div class="label"><?= e(strtoupper((string) $viewImport['format'])) ?> · <?= e($viewImport['status']) ?></div></div></div>
    </div>

    <?php if ($viewErrors): ?>
      <h4 style="margin:1rem 0 .4rem">Problemas registados</h4>
      <div class="log-list">
        <?php foreach ($viewErrors as $er): ?>
          <div class="log-item">
            <i class="fas fa-<?= $er['severity'] === 'error' ? 'circle-xmark' : 'triangle-exclamation' ?>"
               style="color:var(<?= $er['severity'] === 'error' ? '--danger' : '--warning' ?>);margin-top:.25rem"></i>
            <span><strong><?= e($er['location']) ?></strong>: <?= e($er['problem']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-success" style="margin-top:1rem">Sem problemas registados nesta importação.</div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>#</th><th>Tradução</th><th>Ficheiro</th><th>Formato</th><th>Estado</th><th>Progresso</th><th>Data</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($imports as $i): ?>
        <tr>
          <td><?= (int) $i['id'] ?></td>
          <td><strong><?= e($i['abbreviation'] ?? '—') ?></strong></td>
          <td><?= e(excerpt((string) $i['filename'], 28)) ?></td>
          <td><?= e(strtoupper((string) $i['format'])) ?></td>
          <td>
            <?php $b = ['completed'=>'success','failed'=>'danger','running'=>'warning','validated'=>'accent','pending'=>'muted'][$i['status']] ?? 'muted'; ?>
            <span class="badge badge-<?= $b ?>"><?= e($i['status']) ?></span>
          </td>
          <td><?= fmt_num((int) $i['imported_verses']) ?>/<?= fmt_num((int) $i['total_verses']) ?></td>
          <td><small><?= fmt_datetime($i['created_at']) ?></small></td>
          <td class="actions"><a class="btn btn-sm" href="?view=<?= (int) $i['id'] ?>">Detalhes</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$imports): ?>
        <tr><td colspan="8" class="empty-state">Ainda não há importações.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
