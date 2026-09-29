<?php
/**
 * Dashboard administrativo.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$stats = db_one("
  SELECT
    (SELECT COUNT(*) FROM translations) AS translations,
    (SELECT COUNT(*) FROM translations WHERE status = 'active') AS translations_active,
    (SELECT COUNT(*) FROM books) AS books,
    (SELECT COUNT(*) FROM chapters) AS chapters,
    (SELECT COUNT(*) FROM verses) AS verses,
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM search_logs) AS searches,
    (SELECT COUNT(*) FROM imports WHERE status = 'failed') AS imports_failed
");

$recentImports = db_all(
    'SELECT i.*, t.abbreviation FROM imports i
     LEFT JOIN translations t ON t.id = i.translation_id
     ORDER BY i.created_at DESC LIMIT 6'
);
$recentSearches = db_all('SELECT query, results_count, created_at FROM search_logs ORDER BY created_at DESC LIMIT 8');
$activity = db_all('SELECT a.action, a.detail, a.created_at, u.name AS user_name
                    FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
                    ORDER BY a.created_at DESC LIMIT 10');
$topSearches = db_all(
    'SELECT query, COUNT(*) AS n FROM search_logs
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY query ORDER BY n DESC LIMIT 6'
);
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1>Dashboard</h1>
  <p style="color:var(--text-soft)">Visão geral do sistema.</p>
</div>

<div class="grid grid-4">
  <div class="stat-card"><div class="icon"><i class="fas fa-language"></i></div>
    <div><div class="value"><?= fmt_num($stats['translations']) ?></div><div class="label">Traduções (<?= $stats['translations_active'] ?> activas)</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-book"></i></div>
    <div><div class="value"><?= fmt_num($stats['books']) ?></div><div class="label">Livros</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-layer-group"></i></div>
    <div><div class="value"><?= fmt_num($stats['chapters']) ?></div><div class="label">Capítulos</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-scroll"></i></div>
    <div><div class="value"><?= fmt_num($stats['verses']) ?></div><div class="label">Versículos</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-users"></i></div>
    <div><div class="value"><?= fmt_num($stats['users']) ?></div><div class="label">Utilizadores</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-magnifying-glass"></i></div>
    <div><div class="value"><?= fmt_num($stats['searches']) ?></div><div class="label">Pesquisas</div></div></div>
  <div class="stat-card"><div class="icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fas fa-triangle-exclamation"></i></div>
    <div><div class="value"><?= fmt_num($stats['imports_failed']) ?></div><div class="label">Importações falhadas</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-file-import"></i></div>
    <div><div class="value"><a href="<?= url('/admin/importar.php') ?>">Importar</a></div><div class="label">Nova tradução</div></div></div>
</div>

<div class="grid grid-2" style="margin-top:1.2rem">
  <section class="card">
    <div class="card-head">
      <h3><i class="fas fa-file-import"></i> Últimas importações</h3>
      <a class="btn btn-sm" href="<?= url('/admin/importacoes.php') ?>">Ver todas</a>
    </div>
    <?php if (!$recentImports): ?>
      <p class="empty-state" style="padding:1rem">Ainda não há importações.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Ficheiro</th><th>Tradução</th><th>Estado</th><th>Versículos</th><th>Data</th></tr></thead>
          <tbody>
          <?php foreach ($recentImports as $i): ?>
            <tr>
              <td><?= e(excerpt((string) $i['filename'], 24)) ?></td>
              <td><strong><?= e($i['abbreviation'] ?? '—') ?></strong></td>
              <td>
                <?php $b = ['completed'=>'success','failed'=>'danger','running'=>'warning','validated'=>'accent','pending'=>'muted'][$i['status']] ?? 'muted'; ?>
                <span class="badge badge-<?= $b ?>"><?= e($i['status']) ?></span>
              </td>
              <td><?= fmt_num((int) $i['imported_verses']) ?>/<?= fmt_num((int) $i['total_verses']) ?></td>
              <td><small><?= fmt_datetime($i['created_at']) ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3><i class="fas fa-clock-rotate-left"></i> Actividade recente</h3></div>
    <?php foreach ($activity as $a): ?>
      <div class="log-list">
        <div class="log-item">
          <i class="fas fa-circle" style="font-size:.4rem;color:var(--accent);margin-top:.45rem"></i>
          <div>
            <strong><?= e($a['action']) ?></strong>
            <?php if ($a['detail']): ?> — <?= e($a['detail']) ?><?php endif; ?>
            <div style="font-size:.72rem;color:var(--text-faint)">
              <?= e($a['user_name'] ?? 'sistema') ?> · <?= fmt_datetime($a['created_at']) ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3><i class="fas fa-magnifying-glass"></i> Pesquisas recentes</h3></div>
    <?php if (!$recentSearches): ?>
      <p style="color:var(--text-faint)">Sem pesquisas registadas.</p>
    <?php else: ?>
      <?php foreach ($recentSearches as $s): ?>
        <div style="display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px dashed var(--border);font-size:.86rem">
          <span>“<?= e($s['query']) ?>” <small style="color:var(--text-faint)">(<?= (int) $s['results_count'] ?> result.)</small></span>
          <small style="color:var(--text-faint)"><?= fmt_datetime($s['created_at']) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3><i class="fas fa-fire"></i> Pesquisas mais frequentes (30 dias)</h3></div>
    <?php if (!$topSearches): ?>
      <p style="color:var(--text-faint)">Sem dados suficientes.</p>
    <?php else: ?>
      <?php foreach ($topSearches as $s): ?>
        <div style="display:flex;justify-content:space-between;padding:.3rem 0;font-size:.86rem">
          <span>“<?= e($s['query']) ?>”</span>
          <span class="badge badge-accent"><?= (int) $s['n'] ?>×</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
