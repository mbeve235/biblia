<?php
/**
 * Backup e exportações.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$translations = db_all(
    'SELECT t.id, t.abbreviation, t.name,
       (SELECT COUNT(*) FROM verses v WHERE v.translation_id = t.id) AS verses
     FROM translations t ORDER BY t.name'
);
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-database" style="color:var(--accent)"></i> Backup & Exportações</h1>
  <p style="color:var(--text-soft)">Descarregar conteúdos em JSON/CSV e backup SQL completo (administradores).</p>
</div>

<section class="card">
  <div class="card-head"><h3>Exportar traduções</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Tradução</th><th>Versículos</th><th style="text-align:right">Exportar</th></tr></thead>
      <tbody>
      <?php foreach ($translations as $t): ?>
        <tr>
          <td><strong><?= e($t['abbreviation']) ?></strong> — <?= e($t['name']) ?></td>
          <td><?= fmt_num((int) $t['verses']) ?></td>
          <td class="actions">
            <a class="btn btn-sm" href="<?= url('/api/export/translation/' . (int) $t['id'] . '?format=json') ?>"><i class="fas fa-file-code"></i> JSON</a>
            <a class="btn btn-sm" href="<?= url('/api/export/translation/' . (int) $t['id'] . '?format=csv') ?>"><i class="fas fa-file-csv"></i> CSV</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <div class="card-head"><h3>Exportar livros</h3></div>
  <p style="color:var(--text-soft)">Estrutura completa dos 66 livros com categorias e capítulos.</p>
  <a class="btn" href="<?= url('/api/books') ?>" target="_blank"><i class="fas fa-book"></i> Ver livros em JSON</a>
</section>

<?php if (is_admin()): ?>
<section class="card" style="border-left:4px solid var(--danger)">
  <div class="card-head"><h3>Backup completo da base de dados</h3></div>
  <p style="color:var(--text-soft)">Gera um ficheiro <code>.sql</code> com estrutura e dados de todas as tabelas. Pode ser restaurado via phpMyAdmin.</p>
  <a class="btn btn-danger" href="<?= url('/api/export/database') ?>"><i class="fas fa-download"></i> Descarregar backup SQL</a>
  <p style="font-size:.78rem;color:var(--text-faint);margin-top:.8rem">
    Para backups automáticos futuros, agende no cron/Task Scheduler:
    <code>mysqldump -u root biblia_web &gt; backup_$(date +%F).sql</code>
  </p>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
