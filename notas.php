<?php
/**
 * Notas — lista, edita e elimina notas pessoais.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
require_once __DIR__ . '/includes/header.php';

$notes = db_all(
    'SELECT n.id, n.verse_id, n.note, n.updated_at,
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

<div class="reader-header">
  <div class="crumb">Pessoal</div>
  <h1><i class="fas fa-pen-to-square" style="color:var(--accent)"></i> Notas</h1>
  <p style="color:var(--text-soft)"><?= count($notes) ?> nota(s) pessoal(is).</p>
</div>

<?php if (!$notes): ?>
  <div class="empty-state">
    <i class="fas fa-pen-to-square"></i>
    <p>Ainda não tem notas. Durante a leitura, toque no ícone de nota num versículo.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Começar a ler</a>
  </div>
<?php else: ?>
  <?php foreach ($notes as $n): ?>
    <section class="card note-card">
      <div class="card-head">
        <h3 style="color:var(--accent)">
          <span class="bk-badge" style="vertical-align:-5px"><?= brand_svg('', 12) ?></span>
          <a href="<?= url('/leitura.php?translation=' . urlencode($n['translation']) . '&book=' . $n['slug'] . '&chapter=' . (int) $n['chapter_number'] . '&verse=' . (int) $n['verse_number']) ?>">
            <?= e($n['book_name']) ?> <?= (int) $n['chapter_number'] ?>:<?= (int) $n['verse_number'] ?>
          </a>
          <span class="badge badge-accent"><?= e($n['translation']) ?></span>
        </h3>
        <small style="color:var(--text-faint)"><?= fmt_datetime($n['updated_at']) ?></small>
      </div>
      <p style="font-family:var(--read-font);font-size:.95rem;color:var(--text-soft);margin-bottom:.7rem">
        “<?= e(excerpt(db_one('SELECT text FROM verses WHERE id = ?', [$n['verse_id']])['text'] ?? '', 110)) ?>”
      </p>
      <textarea class="note-edit" data-id="<?= (int) $n['id'] ?>" rows="3" style="margin-bottom:.6rem"><?= e($n['note']) ?></textarea>
      <div style="display:flex;gap:.5rem;justify-content:flex-end">
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
