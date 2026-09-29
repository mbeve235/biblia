<?php
/**
 * Leitura — exibe um capítulo: ?translation=ARC&book=joao&chapter=3
 */
require_once __DIR__ . '/includes/header.php';
$bodyClass = 'reading';

$abbr   = trim((string) ($_GET['translation'] ?? DEFAULT_TRANSLATION));
$slug   = trim((string) ($_GET['book'] ?? 'joao'));
$chapNum= max(1, (int) ($_GET['chapter'] ?? 1));
$anchor = (int) ($_GET['verse'] ?? 0);

$translations = db_all('SELECT id, name, abbreviation FROM translations WHERE status = "active" ORDER BY name');
$trans = db_one('SELECT * FROM translations WHERE abbreviation = ? AND status = "active"', [$abbr]);
if (!$trans) {
    $trans = db_one('SELECT * FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
    $abbr = $trans['abbreviation'] ?? '';
}

$book = db_one('SELECT * FROM books WHERE slug = ?', [$slug]);
$chapterRow = $book ? db_one('SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?', [$book['id'], $chapNum]) : null;
$verses = [];

if ($trans && $book && $chapterRow) {
    $verses = db_all(
        'SELECT id, verse_number, text FROM verses
         WHERE translation_id = ? AND chapter_id = ? ORDER BY verse_number',
        [$trans['id'], $chapterRow['id']]
    );
}

// Navegação prev/next
$prevLink = $nextLink = null;
if ($book) {
    $prev = db_one(
        'SELECT c2.chapter_number AS n, b2.slug AS s, b2.name AS n2
         FROM chapters c1
         JOIN chapters c2 ON c2.book_id = c1.book_id AND c2.chapter_number = c1.chapter_number - 1
         JOIN books b2 ON b2.id = c2.book_id
         WHERE c1.book_id = ? AND c1.chapter_number = ?',
        [$book['id'], $chapNum]
    );
    $next = db_one(
        'SELECT c2.chapter_number AS n, b2.slug AS s, b2.name AS n2
         FROM chapters c1
         JOIN chapters c2 ON c2.book_id = c1.book_id AND c2.chapter_number = c1.chapter_number + 1
         JOIN books b2 ON b2.id = c2.book_id
         WHERE c1.book_id = ? AND c1.chapter_number = ?',
        [$book['id'], $chapNum]
    );
    if (!$next) {
        $next = db_one(
            'SELECT 1 AS n, b2.slug AS s, b2.name AS n2
             FROM books b1 JOIN books b2 ON b2.book_order = b1.book_order + 1 WHERE b1.id = ?',
            [$book['id']]
        );
    }
    if ($prev) $prevLink = ['slug' => $prev['s'], 'name' => $prev['n2'], 'ch' => (int) $prev['n']];
    if ($next) $nextLink = ['slug' => $next['s'], 'name' => $next['n2'], 'ch' => (int) $next['n']];
}

// Favoritos e notas do utilizador (para os indicadores)
$favIds = [];
$noteIds = [];
if ($user = current_user()) {
    foreach (db_all('SELECT verse_id FROM favorites WHERE user_id = ?', [$user['id']]) as $f) $favIds[(int) $f['verse_id']] = true;
    foreach (db_all('SELECT verse_id FROM notes WHERE user_id = ?', [$user['id']]) as $n) $noteIds[(int) $n['verse_id']] = true;

    // Registar no histórico (atracção silenciosa)
    if ($trans && $book && $verses) {
        try {
            db_exec(
                'INSERT INTO reading_history (user_id, translation_id, book_id, chapter_number) VALUES (?, ?, ?, ?)',
                [$user['id'], $trans['id'], $book['id'], $chapNum]
            );
        } catch (Throwable $e) { /* ignore */ }
    }
}

$reference = $book ? $book['name'] . ' ' . $chapNum : 'Capítulo';
$pageTitle = $reference;
?>

<?php if (!$book || !$trans): ?>
  <div class="empty-state">
    <i class="fas fa-book-bible"></i>
    <h2>Leitura não encontrada</h2>
    <p>Verifique se a tradução e o livro existem.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Escolher outro livro</a>
  </div>
<?php else: ?>

<div class="reader-header">
  <div class="crumb">TRADUÇÃO: <?= e($trans['abbreviation']) ?></div>
  <h1>
    <a href="#" data-open-books style="color:inherit"><?= e($book['name']) ?> <?= $chapNum ?></a>
    <i class="fas fa-chevron-down" style="font-size:.9rem;color:var(--text-faint)"></i>
  </h1>
  <p style="color:var(--text-soft);font-size:.9rem"><?= e($trans['name']) ?></p>
</div>

<div class="reader-controls">
  <button class="btn btn-sm" data-font-dec type="button" title="Diminuir letra"><i class="fas fa-minus"></i></button>
  <button class="btn btn-sm" data-font-inc type="button" title="Aumentar letra"><i class="fas fa-plus"></i></button>
  <button class="btn btn-sm" data-theme-toggle type="button" title="Modo claro/escuro"><i class="fas fa-moon"></i></button>
  <a class="btn btn-sm" href="<?= url('/comparar.php?book=' . $book['slug'] . '&chapter=' . $chapNum) ?>" title="Comparar traduções"><i class="fas fa-table-columns"></i> Comparar</a>
  <button class="btn btn-sm" type="button" onclick="document.body.classList.toggle('reading-mode')" title="Modo leitura focada"><i class="fas fa-glasses"></i> Foco</button>
  <button class="btn btn-sm reading-exit" type="button" onclick="document.body.classList.remove('reading-mode')"><i class="fas fa-xmark"></i> Sair do modo leitura</button>
</div>

<article class="verses" id="verses">
  <?php if (!$verses): ?>
    <div class="empty-state">
      <i class="fas fa-folder-open"></i>
      <p>Esta tradução ainda não tem o texto de <strong><?= e($reference) ?></strong>.</p>
      <p style="font-size:.85rem">Um administrador pode importá-la em <a href="<?= url('/admin/importar.php') ?>">Administração → Importar</a>.</p>
    </div>
  <?php else: ?>
    <?php foreach ($verses as $v):
        $vid = (int) $v['id'];
        $isFav = isset($favIds[$vid]);
        $hasNote = isset($noteIds[$vid]);
    ?>
      <p class="verse <?= $isFav ? 'fav' : '' ?>" id="v<?= (int) $v['verse_number'] ?>" data-verse-id="<?= $vid ?>">
        <span class="vnum"><?= (int) $v['verse_number'] ?></span>
        <?= e($v['text']) ?>
        <?php if ($hasNote): ?><i class="fas fa-pen-to-square note-indicator" title="Este versículo tem uma nota"></i><?php endif; ?>
        <span class="vtools">
          <button type="button" class="act-fav <?= $isFav ? 'on' : '' ?>" title="Favorito" onclick="bwToggleFav(this, <?= $vid ?>)"><i class="fas fa-star"></i></button>
          <button type="button" title="Nota" onclick="bwOpenNote(<?= $vid ?>, <?= (int) $v['verse_number'] ?>)"><i class="fas fa-pen-to-square"></i></button>
          <button type="button" title="Copiar" onclick="bwCopyVerse(<?= (int) $v['verse_number'] ?>, this)"><i class="fas fa-copy"></i></button>
          <button type="button" title="Partilhar" onclick="bwShareVerse(<?= (int) $v['verse_number'] ?>, this)"><i class="fas fa-share-nodes"></i></button>
        </span>
      </p>
    <?php endforeach; ?>
  <?php endif; ?>
</article>

<nav class="chapter-nav">
  <?php if ($prevLink): ?>
    <a class="btn" href="<?= url('/leitura.php?translation=' . urlencode($abbr) . '&book=' . $prevLink['slug'] . '&chapter=' . $prevLink['ch']) ?>">
      <i class="fas fa-arrow-left"></i> <?= e($prevLink['name']) ?> <?= $prevLink['ch'] ?>
    </a>
  <?php else: ?><span></span><?php endif; ?>
  <?php if ($nextLink): ?>
    <a class="btn" href="<?= url('/leitura.php?translation=' . urlencode($abbr) . '&book=' . $nextLink['slug'] . '&chapter=' . $nextLink['ch']) ?>">
      <?= e($nextLink['name']) ?> <?= $nextLink['ch'] ?> <i class="fas fa-arrow-right"></i>
    </a>
  <?php endif; ?>
</nav>

<?php endif; ?>

<?php
// ---- Scripts específicos da página -----------------------------------
ob_start();
?>
<!-- Modal de nota -->
<div class="modal-overlay" id="modal-note">
  <div class="modal" style="max-width:480px">
    <button class="modal-close" type="button" onclick="document.getElementById('modal-note').classList.remove('open')"><i class="fas fa-xmark"></i></button>
    <h3 id="note-title">Nota</h3>
    <textarea id="note-text" rows="5" placeholder="Escreva a sua nota pessoal…"></textarea>
    <div style="display:flex;gap:.5rem;margin-top:.8rem;justify-content:flex-end">
      <button class="btn btn-danger btn-sm" type="button" id="note-delete" style="display:none">Eliminar</button>
      <button class="btn btn-primary" type="button" id="note-save">Guardar nota</button>
    </div>
  </div>
</div>

<script>
function bwVerseRef(num, el) {
  const p = el.closest('.verse');
  return <?= json_encode(($book['name'] ?? '') . ' ' . $chapNum . ':') ?> + num + ' (' + <?= json_encode($abbr) ?> + ')';
}
function bwCopyVerse(num, el) {
  const text = el.closest('.verse').cloneNode(true);
  text.querySelectorAll('.vtools,.vnum').forEach(n => n.remove());
  copyText(bwVerseRef(num, el) + ' — ' + text.textContent.trim());
}
function bwShareVerse(num, el) {
  const text = el.closest('.verse').cloneNode(true);
  text.querySelectorAll('.vtools,.vnum').forEach(n => n.remove());
  shareText('Bíblia Web', bwVerseRef(num, el) + ' — ' + text.textContent.trim());
}
function bwToggleFav(btn, verseId) {
  VerseActions.toggleFavorite(verseId, btn).then(() => {
    btn.closest('.verse').classList.toggle('fav', VerseActions.isFav(verseId));
  });
}

// ---- Notas ----
let noteVerseId = null;
async function bwOpenNote(verseId, verseNum) {
  if (!IS_LOGGED_IN) { toast('Inicie sessão para criar notas.', 'error'); return; }
  noteVerseId = verseId;
  document.getElementById('note-title').textContent = 'Nota — ' + <?= json_encode(($book['name'] ?? '')) ?> + ' <?= $chapNum ?>:' + verseNum;
  document.getElementById('note-text').value = '';
  document.getElementById('note-delete').style.display = 'none';
  try {
    const res = await apiGet('/notes?verse_id=' + verseId);
    if (res.data.note) {
      document.getElementById('note-text').value = res.data.note.note;
      document.getElementById('note-delete').style.display = 'inline-flex';
    }
  } catch (e) { /* sem nota */ }
  document.getElementById('modal-note').classList.add('open');
  document.getElementById('note-text').focus();
}
document.getElementById('note-save')?.addEventListener('click', async () => {
  try {
    await api('POST', '/notes', { verse_id: noteVerseId, note: document.getElementById('note-text').value });
    toast('Nota guardada.', 'success');
    document.getElementById('modal-note').classList.remove('open');
    location.reload();
  } catch (e) { toast(e.message, 'error'); }
});
document.getElementById('note-delete')?.addEventListener('click', async () => {
  try {
    const res = await apiGet('/notes?verse_id=' + noteVerseId);
    if (res.data.note) {
      await api('DELETE', '/notes/' + res.data.note.id);
      toast('Nota eliminada.');
      document.getElementById('modal-note').classList.remove('open');
      location.reload();
    }
  } catch (e) { toast(e.message, 'error'); }
});

// Navegação pelo modal de livros/capítulos (BookPicker)
window.onPickChapter = (slug, name, ch) => {
  const url = new URL(BASE_URL + '/leitura.php', location.href);
  url.searchParams.set('translation', <?= json_encode($abbr) ?>);
  url.searchParams.set('book', slug);
  url.searchParams.set('chapter', String(ch));
  location.href = url.toString();
};

// Âncora de versículo (?verse=16)
<?php if ($anchor > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('v<?= $anchor ?>');
  if (el) { el.scrollIntoView({ block: 'center' }); el.style.background = 'var(--accent-soft)'; }
});
<?php endif; ?>

// Registar capítulo no histórico (se não foi feito server-side)
<?php if ($trans && $book && $verses && !current_user()): ?>
/* visitantes não registam histórico */
<?php endif; ?>
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
