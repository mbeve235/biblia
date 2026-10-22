<?php
/**
 * Notas — lista, edita e elimina notas pessoais.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
$pageTitle = 'Notas guardadas';
require_once __DIR__ . '/includes/header.php';

$notes = db_all(
    'SELECT n.id, n.verse_id, n.note, n.updated_at, v.text AS verse_text,
            v.verse_number, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
     FROM notes n
     JOIN verses v ON v.id = n.verse_id
     JOIN chapters c ON c.id = v.chapter_id
     JOIN books b ON b.id = v.book_id
     JOIN translations t ON t.id = v.translation_id
     WHERE n.user_id = ?
     ORDER BY n.updated_at DESC',
    [$user['id']]
);
?>

<header class="page-header saved-header">
  <span class="page-kicker">Pessoal</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-bookmark"></i></span><div><h1>Guardados</h1><p>Reúna aqui os versículos e pensamentos que deseja revisitar.</p></div></div>
</header>

<nav class="saved-tabs" aria-label="Conteúdo guardado">
  <a href="<?= url('/favoritos.php') ?>"><i class="fas fa-bookmark"></i> Versículos</a>
  <a href="<?= url('/marcacoes.php') ?>"><i class="fas fa-highlighter"></i> Marcações</a>
  <a class="active" aria-current="page" href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i> Notas <span><?= count($notes) ?></span></a>
</nav>

<?php if (!$notes): ?>
  <div class="empty-state">
    <i class="fas fa-pen-to-square"></i>
    <p>Ainda não tem notas. Durante a leitura, toque no ícone de nota num versículo.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Começar a ler</a>
  </div>
<?php else: ?>
  <div class="personal-filter">
    <i class="fas fa-magnifying-glass"></i>
    <input type="search" id="notes-filter" placeholder="Filtrar por referência, versículo ou conteúdo da nota…" aria-label="Filtrar notas">
    <span id="notes-filter-count"><?= count($notes) ?> visíveis</span>
  </div>
  <?php foreach ($notes as $n): ?>
    <section class="card note-card personal-filter-item" data-search="<?= e(mb_strtolower($n['book_name'] . ' ' . $n['chapter_number'] . ':' . $n['verse_number'] . ' ' . $n['translation'] . ' ' . ($n['verse_text'] ?? '') . ' ' . $n['note'])) ?>">
      <div class="card-head">
        <h3 class="entity-title">
          <span class="bk-badge"><?= brand_svg('', 12) ?></span>
          <a href="<?= url('/leitura.php?translation=' . urlencode($n['translation']) . '&book=' . $n['slug'] . '&chapter=' . (int) $n['chapter_number'] . '&verse=' . (int) $n['verse_number']) ?>">
            <?= e($n['book_name']) ?> <?= (int) $n['chapter_number'] ?>:<?= (int) $n['verse_number'] ?>
          </a>
          <span class="badge badge-accent"><?= e($n['translation']) ?></span>
        </h3>
        <small class="muted"><?= fmt_datetime($n['updated_at']) ?></small>
      </div>
      <p class="scripture-excerpt">
        “<?= e(excerpt($n['verse_text'] ?? '', 110)) ?>”
      </p>
      <textarea class="note-edit card-textarea" data-id="<?= (int) $n['id'] ?>" rows="3"><?= e($n['note']) ?></textarea>
      <div class="card-actions">
        <button class="btn btn-sm btn-danger" onclick="bwDeleteNote(<?= (int) $n['id'] ?>, this)"><i class="fas fa-trash"></i> Eliminar</button>
        <button class="btn btn-sm btn-primary" onclick="bwSaveNote(<?= (int) $n['id'] ?>, this)"><i class="fas fa-floppy-disk"></i> Guardar</button>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php
ob_start();
?>
<script>
const notesFilter = document.getElementById('notes-filter');
notesFilter?.addEventListener('input', () => {
  const query = notesFilter.value.trim().toLocaleLowerCase('pt');
  let visible = 0;
  document.querySelectorAll('.personal-filter-item').forEach((item) => {
    const liveNote = item.querySelector('.note-edit')?.value.toLocaleLowerCase('pt') || '';
    const show = !query || item.dataset.search.includes(query) || liveNote.includes(query);
    item.hidden = !show;
    if (show) visible++;
  });
  document.getElementById('notes-filter-count').textContent = visible + ' visíveis';
});
async function bwSaveNote(id, btn) {
  const ta = document.querySelector('.note-edit[data-id="' + id + '"]');
  try {
    await api('POST', '/notes', { verse_id: 0, id: id, note: ta.value, update: 1 });
    toast('Nota guardada.', 'success');
  } catch (e) { toast(e.message, 'error'); }
}
async function bwDeleteNote(id, btn) {
  if (!confirm('Eliminar esta nota?')) return;
  try {
    await api('DELETE', '/notes/' + id);
    toast('Nota eliminada.');
    btn.closest('.card').remove();
  } catch (e) { toast(e.message, 'error'); }
}
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
