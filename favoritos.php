<?php
/**
 * Favoritos — lista os versículos marcados pelo utilizador.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
$pageTitle = 'Guardados';
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

<header class="page-header saved-header">
  <span class="page-kicker">Pessoal</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-bookmark"></i></span><div><h1>Guardados</h1><p>Reúna aqui os versículos e pensamentos que deseja revisitar.</p></div></div>
</header>

<nav class="saved-tabs" aria-label="Conteúdo guardado">
  <a class="active" aria-current="page" href="<?= url('/favoritos.php') ?>"><i class="fas fa-bookmark"></i> Versículos <span><?= count($favs) ?></span></a>
  <a href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i> Notas</a>
</nav>

<?php if (!$favs): ?>
  <div class="empty-state">
    <i class="fas fa-star"></i>
    <p>Ainda não guardou versículos. Na leitura, abra as ações de um versículo e toque na estrela.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Começar a ler</a>
  </div>
<?php else: ?>
  <div class="personal-filter">
    <i class="fas fa-magnifying-glass"></i>
    <input type="search" id="favorites-filter" placeholder="Filtrar por livro, referência ou texto…" aria-label="Filtrar favoritos">
    <span id="favorites-filter-count"><?= count($favs) ?> visíveis</span>
  </div>
  <?php foreach ($favs as $f): ?>
    <section class="card entity-card personal-filter-item" data-search="<?= e(mb_strtolower($f['book_name'] . ' ' . $f['chapter_number'] . ':' . $f['verse_number'] . ' ' . $f['translation'] . ' ' . $f['text'])) ?>">
      <div class="card-head">
        <h3 class="entity-title">
          <span class="bk-badge"><?= brand_svg('', 12) ?></span>
          <a href="<?= url('/leitura.php?translation=' . urlencode($f['translation']) . '&book=' . $f['slug'] . '&chapter=' . (int) $f['chapter_number'] . '&verse=' . (int) $f['verse_number']) ?>">
            <?= e($f['book_name']) ?> <?= (int) $f['chapter_number'] ?>:<?= (int) $f['verse_number'] ?>
          </a>
          <span class="badge badge-accent"><?= e($f['translation']) ?></span>
        </h3>
        <button class="btn btn-sm btn-danger" onclick="bwRemoveFav(<?= (int) $f['verse_id'] ?>, this)">
          <i class="fas fa-trash"></i> Remover
        </button>
      </div>
      <p class="scripture-text"><?= e($f['text']) ?></p>
      <div class="card-actions">
        <button class="btn btn-sm" type="button" data-ref="<?= e($f['book_name'] . ' ' . $f['chapter_number'] . ':' . $f['verse_number'] . ' (' . $f['translation'] . ')') ?>" data-text="<?= e($f['text']) ?>" onclick="bwCopyFavorite(this)"><i class="fas fa-copy"></i> Copiar</button>
        <button class="btn btn-sm" type="button" data-ref="<?= e($f['book_name'] . ' ' . $f['chapter_number'] . ':' . $f['verse_number'] . ' (' . $f['translation'] . ')') ?>" data-text="<?= e($f['text']) ?>" data-url="<?= e(url('/leitura.php?translation=' . urlencode($f['translation']) . '&book=' . $f['slug'] . '&chapter=' . $f['chapter_number'] . '&verse=' . $f['verse_number'])) ?>" onclick="bwShareFavorite(this)"><i class="fas fa-share-nodes"></i> Partilhar</button>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php
ob_start();
?>
<script>
function bwCopyFavorite(button) { copyText(button.dataset.ref + ' — ' + button.dataset.text); }
function bwShareFavorite(button) { shareText(button.dataset.ref, button.dataset.ref + ' — ' + button.dataset.text, button.dataset.url); }
const favoriteFilter = document.getElementById('favorites-filter');
favoriteFilter?.addEventListener('input', () => {
  const query = favoriteFilter.value.trim().toLocaleLowerCase('pt');
  let visible = 0;
  document.querySelectorAll('.personal-filter-item').forEach((item) => {
    const show = !query || item.dataset.search.includes(query);
    item.hidden = !show;
    if (show) visible++;
  });
  document.getElementById('favorites-filter-count').textContent = visible + ' visíveis';
});
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
