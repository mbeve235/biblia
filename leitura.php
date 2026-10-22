<?php
/**
 * Leitura — exibe um capítulo: ?translation=ARC&book=joao&chapter=3
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$bodyClass = 'reading';

$abbr   = trim((string) ($_GET['translation'] ?? DEFAULT_TRANSLATION));
$slug   = trim((string) ($_GET['book'] ?? 'joao'));
$chapNum= max(1, (int) ($_GET['chapter'] ?? 1));
$anchor = (int) ($_GET['verse'] ?? 0);

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

// Título + Open Graph (definidos ANTES do header para <title> e prévias correctas)
$reference = $book ? $book['name'] . ' ' . $chapNum : 'Capítulo';
$pageTitle = $reference;
$ogTitle   = $reference . ($trans ? ' · ' . $trans['abbreviation'] : '');
if (!empty($verses)) {
    $ogDesc = excerpt($verses[0]['text'], 160);
} elseif ($trans && $book) {
    $ogDesc = 'Leia ' . $book['name'] . ' ' . $chapNum . ' na tradução ' . $trans['name'] . '.';
} else {
    $ogDesc = 'Leia, pesquise e compare traduções da Bíblia.';
}
$ogUrl = $book
    ? url('/' . rawurlencode($abbr) . '/' . rawurlencode($book['slug']) . '/' . $chapNum)
    : url('/');

// Leitura inexistente (livro/capítulo inválido): 404 real com via de fuga
if (!$book || !$chapterRow) {
    http_response_code(404);
    $noindex = true;
}

require_once __DIR__ . '/includes/header.php';

$chapterTotal = max(1, (int) ($book['chapters_count'] ?? 1));
$chapterProgress = min(100, max(0, ($chapNum / $chapterTotal) * 100));

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
?>

<?php if (!$book || !$trans): ?>
  <div class="empty-state">
    <i class="fas fa-book-bible"></i>
    <h2>Leitura não encontrada</h2>
    <p>Verifique se a tradução e o livro existem.</p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Escolher outro livro</a>
    <a class="btn" href="<?= url('/index.php') ?>">Voltar ao início</a>
  </div>
<?php else: ?>

<div class="reader-scroll-progress" id="reader-scroll-progress" aria-hidden="true"><span></span></div>

<div class="reader-header">
  <div class="reader-eyebrow">
    <span class="reader-translation"><i class="fas fa-language"></i> <?= e($trans['abbreviation']) ?></span>
    <span>Capítulo <?= $chapNum ?> de <?= $chapterTotal ?></span>
  </div>
  <h1>
    <a href="#" data-open-books class="book-switcher" title="Trocar de livro ou capítulo" aria-haspopup="dialog" aria-label="Trocar de livro ou capítulo — actualmente em <?= e($book['name']) ?> <?= $chapNum ?>">
      <i class="fas fa-book-bible"></i>
      <span><?= e($book['name']) ?> <?= $chapNum ?></span>
      <i class="fas fa-chevron-down chv"></i>
    </a>
  </h1>
  <p class="reader-translation-name"><?= e($trans['name']) ?></p>
  <div class="reader-progress" aria-label="Progresso neste livro: <?= (int) round($chapterProgress) ?>%">
    <span style="width:<?= number_format($chapterProgress, 2, '.', '') ?>%"></span>
  </div>
</div>

<div class="reader-controls" aria-label="Ferramentas de leitura">
  <div class="reader-control-group" aria-label="Tamanho do texto">
    <button class="btn btn-sm" data-font-dec type="button" title="Diminuir letra" aria-label="Diminuir letra"><i class="fas fa-minus"></i></button>
    <span class="reader-size-label" aria-hidden="true">Texto</span>
    <button class="btn btn-sm" data-font-inc type="button" title="Aumentar letra" aria-label="Aumentar letra"><i class="fas fa-plus"></i></button>
  </div>
  <button class="btn btn-sm" data-theme-toggle type="button" title="Alternar tema"><i class="fas fa-moon"></i><span>Tema</span></button>
  <button class="btn btn-sm" id="verse-select-toggle" type="button" aria-pressed="false"><i class="fas fa-list-check"></i><span>Seleccionar</span></button>
  <button class="btn btn-sm" id="reader-audio-toggle" type="button" aria-pressed="false" title="Ouvir este capítulo"><i class="fas fa-volume-high"></i><span>Ouvir</span></button>
  <a class="btn btn-sm" href="<?= url('/comparar.php?book=' . $book['slug'] . '&chapter=' . $chapNum) ?>" title="Comparar traduções"><i class="fas fa-table-columns"></i><span>Comparar</span></a>
  <button class="btn btn-sm" id="reading-focus-toggle" type="button" title="Modo de leitura focada" aria-pressed="false"><i class="fas fa-glasses"></i><span>Foco</span></button>
  <button class="btn btn-sm reading-exit" type="button" id="reading-focus-exit"><i class="fas fa-xmark"></i> Sair do modo leitura</button>
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
      <p class="verse <?= $isFav ? 'fav' : '' ?>" id="v<?= (int) $v['verse_number'] ?>" data-verse-id="<?= $vid ?>" data-verse-number="<?= (int) $v['verse_number'] ?>">
        <span class="verse-select-indicator" aria-hidden="true"><i class="fas fa-check"></i></span>
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

<div class="verse-selection-bar" id="verse-selection-bar" aria-live="polite">
  <strong><span id="verse-selection-count">0</span> seleccionado(s)</strong>
  <span class="selection-spacer"></span>
  <button class="btn btn-sm" type="button" id="selected-copy"><i class="fas fa-copy"></i> Copiar</button>
  <button class="btn btn-sm" type="button" id="selected-share"><i class="fas fa-share-nodes"></i> Partilhar</button>
  <button class="btn btn-sm btn-ghost" type="button" id="selected-cancel">Cancelar</button>
</div>

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
  text.querySelectorAll('.vtools,.vnum,.verse-select-indicator,.note-indicator').forEach(n => n.remove());
  copyText(bwVerseRef(num, el) + ' — ' + text.textContent.trim());
}
function bwShareVerse(num, el) {
  const text = el.closest('.verse').cloneNode(true);
  text.querySelectorAll('.vtools,.vnum,.verse-select-indicator,.note-indicator').forEach(n => n.remove());
  // Link amigável para o versículo: /biblia/ARC/joao/3?verse=16#v16
  const url = new URL(BASE_URL + '/<?= rawurlencode($abbr) ?>/<?= rawurlencode($book['slug'] ?? '') ?>/<?= (int) $chapNum ?>', location.href);
  url.searchParams.set('verse', String(num));
  url.hash = 'v' + num;
  shareText(<?= json_encode(($book['name'] ?? '') . ' ' . $chapNum . ' — ' . $abbr) ?>,
            bwVerseRef(num, el) + ' — ' + text.textContent.trim(),
            url.toString());
}

// ---- Selecção de vários versículos ----
const selectionToggle = document.getElementById('verse-select-toggle');
const selectionBar = document.getElementById('verse-selection-bar');
const selectionCount = document.getElementById('verse-selection-count');
const verseElements = [...document.querySelectorAll('.verse')];
let selectionMode = false;

function selectedVerses() { return verseElements.filter((verse) => verse.classList.contains('selected')); }
function selectionText() {
  return selectedVerses().map((verse) => {
    const copy = verse.cloneNode(true);
    copy.querySelectorAll('.vtools,.vnum,.verse-select-indicator,.note-indicator').forEach((node) => node.remove());
    return <?= json_encode(($book['name'] ?? '') . ' ' . $chapNum . ':') ?> + verse.dataset.verseNumber + ' — ' + copy.textContent.trim();
  }).join('\n');
}
function updateSelection() {
  const count = selectedVerses().length;
  selectionCount.textContent = String(count);
  selectionBar.classList.toggle('open', selectionMode);
  selectionBar.classList.toggle('has-selection', count > 0);
}
function setSelectionMode(enabled) {
  selectionMode = enabled;
  document.body.classList.toggle('verse-selection-mode', enabled);
  selectionToggle?.classList.toggle('active', enabled);
  selectionToggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
  verseElements.forEach((verse) => verse.tabIndex = enabled ? 0 : -1);
  if (!enabled) verseElements.forEach((verse) => {
    verse.classList.remove('selected');
    verse.setAttribute('aria-selected', 'false');
  });
  updateSelection();
}
function toggleVerseSelection(verse) {
  if (!selectionMode) return;
  verse.classList.toggle('selected');
  verse.setAttribute('aria-selected', verse.classList.contains('selected') ? 'true' : 'false');
  updateSelection();
}
selectionToggle?.addEventListener('click', () => setSelectionMode(!selectionMode));
verseElements.forEach((verse) => {
  verse.addEventListener('click', (event) => {
    if (selectionMode && !event.target.closest('button')) toggleVerseSelection(verse);
  });
  verse.addEventListener('keydown', (event) => {
    if (selectionMode && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault(); toggleVerseSelection(verse);
    }
  });
});
document.getElementById('selected-copy')?.addEventListener('click', () => {
  const text = selectionText();
  if (!text) return toast('Seleccione pelo menos um versículo.', 'error');
  copyText(text);
});
document.getElementById('selected-share')?.addEventListener('click', () => {
  const selected = selectedVerses();
  if (!selected.length) return toast('Seleccione pelo menos um versículo.', 'error');
  const nums = selected.map((verse) => verse.dataset.verseNumber);
  const url = new URL(location.href); url.hash = 'v' + nums[0];
  shareText(<?= json_encode(($book['name'] ?? '') . ' ' . $chapNum . ' — ' . $abbr) ?>, selectionText(), url.toString());
});
document.getElementById('selected-cancel')?.addEventListener('click', () => setSelectionMode(false));

// ---- Modo de leitura focada ----
const focusToggle = document.getElementById('reading-focus-toggle');
function setReadingFocus(enabled) {
  document.body.classList.toggle('reading-mode', enabled);
  focusToggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
}
focusToggle?.addEventListener('click', () => setReadingFocus(!document.body.classList.contains('reading-mode')));
document.getElementById('reading-focus-exit')?.addEventListener('click', () => setReadingFocus(false));

// ---- Progresso da leitura no capítulo ----
const scrollProgress = document.querySelector('#reader-scroll-progress span');
function updateReaderProgress() {
  if (!scrollProgress) return;
  const article = document.getElementById('verses');
  if (!article) return;
  const start = article.offsetTop;
  const distance = Math.max(1, article.offsetHeight - window.innerHeight * .45);
  const percent = Math.min(100, Math.max(0, ((window.scrollY - start + window.innerHeight * .3) / distance) * 100));
  scrollProgress.style.width = percent.toFixed(1) + '%';
}
window.addEventListener('scroll', updateReaderProgress, { passive: true });
window.addEventListener('resize', updateReaderProgress);
updateReaderProgress();

// ---- Leitura em voz alta, usando a voz instalada no dispositivo ----
const audioToggle = document.getElementById('reader-audio-toggle');
let audioIndex = 0;
let audioActive = false;
function stopChapterAudio() {
  audioActive = false;
  window.speechSynthesis?.cancel();
  verseElements.forEach((verse) => verse.classList.remove('verse-speaking'));
  audioToggle?.classList.remove('active');
  audioToggle?.setAttribute('aria-pressed', 'false');
  if (audioToggle) audioToggle.innerHTML = '<i class="fas fa-volume-high"></i><span>Ouvir</span>';
}
function speakNextVerse() {
  if (!audioActive || audioIndex >= verseElements.length) return stopChapterAudio();
  const verse = verseElements[audioIndex++];
  verseElements.forEach((item) => item.classList.remove('verse-speaking'));
  verse.classList.add('verse-speaking');
  verse.scrollIntoView({ block: 'center', behavior: 'smooth' });
  const copy = verse.cloneNode(true);
  copy.querySelectorAll('.vtools,.verse-select-indicator,.note-indicator').forEach((node) => node.remove());
  const utterance = new SpeechSynthesisUtterance(copy.textContent.trim());
  utterance.lang = 'pt-PT';
  utterance.rate = .95;
  utterance.onend = speakNextVerse;
  utterance.onerror = stopChapterAudio;
  window.speechSynthesis.speak(utterance);
}
audioToggle?.addEventListener('click', () => {
  if (!('speechSynthesis' in window)) return toast('A leitura em voz alta não é suportada neste navegador.', 'error');
  if (audioActive) return stopChapterAudio();
  audioActive = true;
  audioIndex = 0;
  audioToggle.classList.add('active');
  audioToggle.setAttribute('aria-pressed', 'true');
  audioToggle.innerHTML = '<i class="fas fa-stop"></i><span>Parar</span>';
  speakNextVerse();
});
window.addEventListener('beforeunload', stopChapterAudio);

// Setas esquerda/direita mudam de capítulo fora dos campos de formulário.
document.addEventListener('keydown', (event) => {
  if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
  if (event.target.closest('input, textarea, select, button, [contenteditable="true"]')) return;
  const destination = event.key === 'ArrowLeft'
    ? document.querySelector('.chapter-nav a:first-child')
    : event.key === 'ArrowRight' ? document.querySelector('.chapter-nav a:last-child') : null;
  if (destination) { event.preventDefault(); destination.click(); }
});
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
// Contexto actual: o modal destaca o livro/capítulo em leitura
window.bookPickerCurrent = { slug: <?= json_encode($book['slug'] ?? null) ?>, chapter: <?= (int) $chapNum ?> };
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
  if (el) { el.scrollIntoView({ block: 'center' }); el.classList.add('verse-target'); }
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
