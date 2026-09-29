<?php
/**
 * Favoritos — lista os versículos marcados pelo utilizador.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
require_once __DIR__ . '/includes/header.php';

$favs = db_all(
    'SELECT f.id, f.verse_id, v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
     FROM favorites f
     JOIN verses v ON v.id = f.verse_id
     JOIN chapters c ON c.id = v.chapter_id
     JOIN books b ON b.id = v.book_id
     JOIN translations t ON t.id = v.translation_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC',
    [$user['id']]
);
?>

<div class="reader-header">
  <div class="crumb">Pessoal</div>
  <h1><i class="fas fa-star" style="color:var(--warning)"></i> Favoritos</h1>
  <p style="color:var(--text-soft)"><?= count($favs) ?> versículo(s) guardado(s).</p>
</div>

<?php if (!$favs): ?>
  <div class="empty-state">
    <i class="fas fa-star"></i>
    <p>Ainda não tem favoritos. Passe o rato sobre um versículo na leitura e toque na estrela.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Começar a ler</a>
  </div>
<?php else: ?>
  <?php foreach ($favs as $f): ?>
    <section class="card">
      <div class="card-head">
        <h3 style="color:var(--accent)">
          <a href="<?= url('/leitura.php?translation=' . urlencode($f['translation']) . '&book=' . $f['slug'] . '&chapter=' . (int) $f['chapter_number'] . '&verse=' . (int) $f['verse_number']) ?>">
            <?= e($f['book_name']) ?> <?= (int) $f['chapter_number'] ?>:<?= (int) $f['verse_number'] ?>
          </a>
          <span class="badge badge-accent"><?= e($f['translation']) ?></span>
        </h3>
        <button class="btn btn-sm btn-danger" onclick="bwRemoveFav(<?= (int) $f['verse_id'] ?>, this)">
          <i class="fas fa-trash"></i> Remover
        </button>
      </div>
      <p style="font-family:var(--read-font);font-size:1.05rem"><?= e($f['text']) ?></p>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php
ob_start();
?>
<script>
async function bwRemoveFav(verseId, btn) {
  if (!confirm('Remover este versículo dos favoritos?')) return;
  try {
    await api('DELETE', '/favorites/' + verseId);
    toast('Removido dos favoritos.');
    btn.closest('.card').remove();
  } catch (e) { toast(e.message, 'error'); }
}
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
