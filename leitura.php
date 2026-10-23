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
$sharedVerse = $anchor > 0 ? array_values(array_filter($verses, fn($verse) => (int) $verse['verse_number'] === $anchor))[0] ?? null : null;
$ogTitle = $reference . ($sharedVerse ? ':' . $anchor : '') . ($trans ? ' · ' . $trans['abbreviation'] : '');
if ($sharedVerse) {
    $ogDesc = excerpt($sharedVerse['text'], 160);
} elseif (!empty($verses)) {
    $ogDesc = excerpt($verses[0]['text'], 160);
} elseif ($trans && $book) {
    $ogDesc = 'Leia ' . $book['name'] . ' ' . $chapNum . ' na tradução ' . $trans['name'] . '.';
} else {
    $ogDesc = 'Leia, pesquise e compare traduções da Bíblia.';
}
$ogUrl = $book
    ? preg_replace('/#.*$/', '', scripture_url($abbr, $book['slug'], $chapNum, $sharedVerse ? $anchor : null))
    : url('/');
$ogType = $sharedVerse ? 'article' : 'website';

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
$highlightColors = [];
if ($user = current_user()) {
    foreach (db_all('SELECT verse_id FROM favorites WHERE user_id = ?', [$user['id']]) as $f) $favIds[(int) $f['verse_id']] = true;
    foreach (db_all('SELECT verse_id FROM notes WHERE user_id = ?', [$user['id']]) as $n) $noteIds[(int) $n['verse_id']] = true;
    try {
        foreach (db_all('SELECT verse_id, color FROM verse_highlights WHERE user_id = ?', [$user['id']]) as $highlight) {
            $highlightColors[(int) $highlight['verse_id']] = $highlight['color'];
        }
    } catch (PDOException $e) { /* a migração pode ainda não ter sido aplicada */ }

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
  <h1><?= e($book['name']) ?> <?= $chapNum ?></h1>
  <p class="reader-translation-name"><?= e($trans['name']) ?></p>
  <div class="reader-progress" aria-label="Progresso neste livro: <?= (int) round($chapterProgress) ?>%">
    <span style="width:<?= number_format($chapterProgress, 2, '.', '') ?>%"></span>
  </div>
</div>

<div class="reader-controls" aria-label="Ferramentas de leitura">
  <button class="reader-sticky-location" type="button" data-open-books aria-haspopup="dialog" aria-label="Trocar livro ou capítulo; actualmente em <?= e($book['name']) ?> <?= $chapNum ?>">
    <i class="fas fa-book-open"></i>
    <span><strong><?= e($book['name']) ?> <?= $chapNum ?></strong><small>Livro e capítulo</small></span>
    <i class="fas fa-chevron-down"></i>
  </button>
  <form method="get" class="reader-version-picker" aria-label="Mudar tradução">
    <input type="hidden" name="book" value="<?= e($book['slug']) ?>">
    <input type="hidden" name="chapter" value="<?= $chapNum ?>">
    <?php if (isset($_GET['verse'])): ?><input type="hidden" name="verse" value="<?= (int) $_GET['verse'] ?>"><?php endif; ?>
    <i class="fas fa-language" aria-hidden="true"></i>
    <select name="translation" aria-label="Tradução da Bíblia" onchange="this.form.submit()">
      <?php foreach ($translations as $translationOption): ?>
        <option value="<?= e($translationOption['abbreviation']) ?>" <?= $translationOption['abbreviation'] === $abbr ? 'selected' : '' ?>><?= e($translationOption['abbreviation']) ?> — <?= e($translationOption['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <button class="btn btn-sm" id="verse-select-toggle" type="button" aria-pressed="false"><i class="fas fa-list-check"></i><span>Seleccionar</span></button>
  <button class="btn btn-sm" id="reader-audio-toggle" type="button" aria-pressed="false" title="Ouvir este capítulo"><i class="fas fa-volume-high"></i><span>Ouvir</span></button>
  <a class="btn btn-sm" href="<?= url('/comparar.php?book=' . $book['slug'] . '&chapter=' . $chapNum) ?>" title="Comparar traduções"><i class="fas fa-table-columns"></i><span>Comparar</span></a>
  <details class="reader-settings">
    <summary class="btn btn-sm"><i class="fas fa-font"></i><span>Aparência</span></summary>
    <div class="reader-settings-panel">
      <div class="reader-setting-head"><strong>Aparência da leitura</strong><small>As escolhas ficam guardadas neste dispositivo.</small></div>
      <div class="reader-setting-row"><span>Tamanho</span><div><button type="button" data-font-dec aria-label="Diminuir letra"><i class="fas fa-minus"></i></button><output id="font-size-label">17px</output><button type="button" data-font-inc aria-label="Aumentar letra"><i class="fas fa-plus"></i></button></div></div>
      <div class="reader-setting-block"><span>Tipo de letra</span><div class="reader-option-group"><button type="button" data-reader-font="serif">Clássica</button><button type="button" data-reader-font="sans">Moderna</button></div></div>
      <div class="reader-setting-block"><span>Espaçamento</span><div class="reader-option-group"><button type="button" data-reader-line="1.7">Compacto</button><button type="button" data-reader-line="1.9">Confortável</button><button type="button" data-reader-line="2.1">Amplo</button></div></div>
      <button class="reader-theme-choice" data-theme-toggle type="button"><i class="fas fa-moon"></i><span>Alternar tema claro/escuro</span></button>
    </div>
  </details>
  <button class="btn btn-sm" id="reading-focus-toggle" type="button" title="Modo de leitura focada" aria-pressed="false"><i class="fas fa-glasses"></i><span>Foco</span></button>
</div>
<button class="btn btn-sm reading-exit" type="button" id="reading-focus-exit"><i class="fas fa-xmark"></i> Sair do modo focado</button>

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
        $highlightColor = $highlightColors[$vid] ?? '';
    ?>
      <p class="verse <?= $isFav ? 'fav' : '' ?><?= $highlightColor ? ' verse-highlight-' . e($highlightColor) : '' ?>" id="v<?= (int) $v['verse_number'] ?>" data-verse-id="<?= $vid ?>" data-verse-number="<?= (int) $v['verse_number'] ?>" data-highlight="<?= e($highlightColor) ?>">
        <span class="verse-select-indicator" aria-hidden="true"><i class="fas fa-check"></i></span>
        <span class="vnum"><?= (int) $v['verse_number'] ?></span>
        <?= e($v['text']) ?>
        <?php if ($hasNote): ?><i class="fas fa-pen-to-square note-indicator" title="Este versículo tem uma nota"></i><?php endif; ?>
        <span class="vtools">
          <button type="button" class="act-fav <?= $isFav ? 'on' : '' ?>" title="Guardar versículo" aria-label="Guardar versículo" onclick="bwToggleFav(this, <?= $vid ?>)"><i class="fas fa-bookmark"></i></button>
          <button type="button" class="act-highlight <?= $highlightColor ? 'on' : '' ?>" title="Marcar com cor" aria-label="Marcar versículo com cor" onclick="bwOpenHighlight(<?= $vid ?>, this)"><i class="fas fa-highlighter"></i></button>
          <button type="button" title="Nota" onclick="bwOpenNote(<?= $vid ?>, <?= (int) $v['verse_number'] ?>)"><i class="fas fa-pen-to-square"></i></button>
          <button type="button" title="Copiar" onclick="bwCopyVerse(<?= (int) $v['verse_number'] ?>, this)"><i class="fas fa-copy"></i></button>
          <button type="button" title="Partilhar" onclick="bwShareVerse(<?= (int) $v['verse_number'] ?>, this)"><i class="fas fa-share-nodes"></i></button>
        </span>
      </p>
    <?php endforeach; ?>
  <?php endif; ?>
</article>

<nav class="reader-dock" aria-label="Navegação permanente da leitura">
  <?php if ($prevLink): ?>
    <a class="reader-dock-arrow" href="<?= url('/leitura.php?translation=' . urlencode($abbr) . '&book=' . $prevLink['slug'] . '&chapter=' . $prevLink['ch']) ?>" title="<?= e($prevLink['name']) ?> <?= $prevLink['ch'] ?>" aria-label="Capítulo anterior: <?= e($prevLink['name']) ?> <?= $prevLink['ch'] ?>">
      <i class="fas fa-chevron-left"></i>
    </a>
  <?php else: ?><span class="reader-dock-arrow disabled" aria-hidden="true"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
  <button class="reader-dock-current" type="button" data-open-books aria-haspopup="dialog" aria-label="Trocar livro ou capítulo; actualmente em <?= e($book['name']) ?> <?= $chapNum ?>">
    <span><?= e($book['name']) ?> <?= $chapNum ?></span>
    <small><?= e($abbr) ?> · tocar para mudar</small>
  </button>
  <?php if ($nextLink): ?>
    <a class="reader-dock-arrow" href="<?= url('/leitura.php?translation=' . urlencode($abbr) . '&book=' . $nextLink['slug'] . '&chapter=' . $nextLink['ch']) ?>" title="<?= e($nextLink['name']) ?> <?= $nextLink['ch'] ?>" aria-label="Próximo capítulo: <?= e($nextLink['name']) ?> <?= $nextLink['ch'] ?>">
      <i class="fas fa-chevron-right"></i>
    </a>
  <?php else: ?><span class="reader-dock-arrow disabled" aria-hidden="true"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
</nav>

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
  <?php else: ?><span></span><?php endif; ?>
</nav>

<?php endif; ?>

<?php
// ---- Scripts específicos da página -----------------------------------
ob_start();
?>
<!-- Paleta de marcação -->
<div class="modal-overlay" id="modal-highlight">
  <div class="modal highlight-modal" role="dialog" aria-modal="true" aria-labelledby="highlight-title">
    <button class="modal-close" type="button" data-highlight-close aria-label="Fechar"><i class="fas fa-xmark"></i></button>
    <h3 id="highlight-title">Marcar versículo</h3>
    <p>Escolha uma cor suave para encontrar este versículo mais tarde.</p>
    <div class="highlight-palette" role="group" aria-label="Cores da marcação">
      <button type="button" data-highlight-color="yellow"><i></i><span>Amarelo</span></button>
      <button type="button" data-highlight-color="green"><i></i><span>Verde</span></button>
      <button type="button" data-highlight-color="blue"><i></i><span>Azul</span></button>
      <button type="button" data-highlight-color="rose"><i></i><span>Rosa</span></button>
    </div>
    <button class="btn btn-ghost btn-block highlight-remove" type="button" id="highlight-remove"><i class="fas fa-eraser"></i> Remover marcação</button>
  </div>
</div>
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
  const url = new URL(<?= json_encode($book ? scripture_url($abbr, $book['slug'], $chapNum) : url('/')) ?>, location.href);
  url.searchParams.set('verse', String(num)); url.hash = 'v' + num;
  shareText(<?= json_encode(($book['name'] ?? '') . ' ' . $chapNum . ' — ' . $abbr) ?>,
            bwVerseRef(num, el) + ' — ' + text.textContent.trim(),
            url.toString());
}

// ---- Marcações coloridas ----
const highlightModal = document.getElementById('modal-highlight');
let highlightVerseId = 0;
let highlightTrigger = null;
function bwOpenHighlight(verseId, trigger) {
  if (!IS_LOGGED_IN) return toast('Inicie sessão para marcar versículos.', 'error');
  highlightVerseId = verseId; highlightTrigger = trigger;
  const verse = trigger.closest('.verse');
  const activeColor = verse?.dataset.highlight || '';
  highlightModal.querySelectorAll('[data-highlight-color]').forEach(button => button.classList.toggle('active', button.dataset.highlightColor === activeColor));
  document.getElementById('highlight-remove').hidden = !activeColor;
  highlightModal.classList.add('open');
}
function closeHighlight() { highlightModal?.classList.remove('open'); highlightTrigger?.focus(); }
document.querySelectorAll('[data-highlight-close]').forEach(button => button.addEventListener('click', closeHighlight));
highlightModal?.addEventListener('click', event => { if (event.target === highlightModal) closeHighlight(); });
document.querySelectorAll('[data-highlight-color]').forEach(button => button.addEventListener('click', async () => {
  try {
    await api('POST', '/highlights', { verse_id: highlightVerseId, color: button.dataset.highlightColor });
    const verse = document.querySelector('.verse[data-verse-id="' + highlightVerseId + '"]');
    verse.classList.remove('verse-highlight-yellow','verse-highlight-green','verse-highlight-blue','verse-highlight-rose');
    verse.classList.add('verse-highlight-' + button.dataset.highlightColor); verse.dataset.highlight = button.dataset.highlightColor;
    highlightTrigger?.classList.add('on'); closeHighlight(); toast('Versículo marcado.', 'success');
  } catch (error) { toast(error.message, 'error'); }
}));
document.getElementById('highlight-remove')?.addEventListener('click', async () => {
  try {
    await api('DELETE', '/highlights/' + highlightVerseId);
    const verse = document.querySelector('.verse[data-verse-id="' + highlightVerseId + '"]');
    verse.classList.remove('verse-highlight-yellow','verse-highlight-green','verse-highlight-blue','verse-highlight-rose'); verse.dataset.highlight = '';
    highlightTrigger?.classList.remove('on'); closeHighlight(); toast('Marcação removida.');
  } catch (error) { toast(error.message, 'error'); }
});

// ---- Selecção de vários versículos ----
const selectionToggle = document.getElementById('verse-select-toggle');
const selectionBar = document.getElementById('verse-selection-bar');
const selectionCount = document.getElementById('verse-selection-count');
const verseElements = [...document.querySelectorAll('.verse')];
let selectionMode = false;

// ---- Aparência local do leitor ----
const readerRoot = document.documentElement;
const savedReaderFont = localStorage.getItem('bw_reader_font') || 'serif';
const savedReaderLine = localStorage.getItem('bw_reader_line') || '1.9';
function applyReaderFont(value) {
  readerRoot.style.setProperty('--read-font', value === 'sans' ? "system-ui, -apple-system, 'Segoe UI', sans-serif" : "Georgia, 'Times New Roman', serif");
  localStorage.setItem('bw_reader_font', value);
  document.querySelectorAll('[data-reader-font]').forEach(button => button.classList.toggle('active', button.dataset.readerFont === value));
}
function applyReaderLine(value) {
  readerRoot.style.setProperty('--read-line', value);
  localStorage.setItem('bw_reader_line', value);
  document.querySelectorAll('[data-reader-line]').forEach(button => button.classList.toggle('active', button.dataset.readerLine === value));
}
document.querySelectorAll('[data-reader-font]').forEach(button => button.addEventListener('click', () => applyReaderFont(button.dataset.readerFont)));
document.querySelectorAll('[data-reader-line]').forEach(button => button.addEventListener('click', () => applyReaderLine(button.dataset.readerLine)));
applyReaderFont(savedReaderFont); applyReaderLine(savedReaderLine);

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
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && document.body.classList.contains('reading-mode')) setReadingFocus(false);
});

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
let audioUtterance = null; // manter referência evita que alguns navegadores a descartem
let portugueseVoice = null;

function loadPortugueseVoice() {
  if (!('speechSynthesis' in window)) return null;
  const voices = window.speechSynthesis.getVoices();
  portugueseVoice = voices.find((voice) => voice.localService && /^pt-PT$/i.test(voice.lang))
    || voices.find((voice) => voice.localService && /^pt(-|_)/i.test(voice.lang))
    || voices.find((voice) => /^pt-PT$/i.test(voice.lang))
    || voices.find((voice) => /^pt(-|_)/i.test(voice.lang))
    || voices.find((voice) => /portugu/i.test(voice.name))
    || null;
  return portugueseVoice;
}
if ('speechSynthesis' in window) {
  loadPortugueseVoice();
  window.speechSynthesis.addEventListener?.('voiceschanged', loadPortugueseVoice);
}

function stopChapterAudio() {
  audioActive = false;
  window.speechSynthesis?.cancel();
  audioUtterance = null;
  verseElements.forEach((verse) => verse.classList.remove('verse-speaking'));
  audioToggle?.classList.remove('active');
  audioToggle?.setAttribute('aria-pressed', 'false');
  if (audioToggle) audioToggle.innerHTML = '<i class="fas fa-volume-high"></i><span>Ouvir</span>';
}
function speakNextVerse(useDefaultVoice = false) {
  if (!audioActive || audioIndex >= verseElements.length) return stopChapterAudio();
  const verse = verseElements[audioIndex++];
  verseElements.forEach((item) => item.classList.remove('verse-speaking'));
  verse.classList.add('verse-speaking');
  verse.scrollIntoView({ block: 'center', behavior: 'smooth' });
  const copy = verse.cloneNode(true);
  copy.querySelectorAll('.vtools,.verse-select-indicator,.note-indicator').forEach((node) => node.remove());
  audioUtterance = new SpeechSynthesisUtterance(copy.textContent.trim());
  audioUtterance.lang = useDefaultVoice ? (navigator.language || 'pt-PT') : (portugueseVoice?.lang || 'pt-PT');
  if (portugueseVoice && !useDefaultVoice) audioUtterance.voice = portugueseVoice;
  audioUtterance.rate = .92;
  audioUtterance.pitch = 1;
  audioUtterance.onend = () => {
    audioUtterance = null;
    speakNextVerse();
  };
  audioUtterance.onerror = (event) => {
    const expected = event.error === 'canceled' || event.error === 'interrupted';
    // Algumas vozes remotas aparecem na lista, mas devolvem synthesis-failed.
    // Nesse caso, repetir exactamente o mesmo versículo com a voz padrão local.
    if (audioActive && event.error === 'synthesis-failed' && !useDefaultVoice) {
      audioUtterance = null;
      audioIndex--;
      portugueseVoice = null;
      window.speechSynthesis.cancel();
      window.setTimeout(() => speakNextVerse(true), 120);
      return;
    }
    stopChapterAudio();
    if (!expected) toast('Não foi possível iniciar a voz (' + (event.error || 'erro do dispositivo') + '). Verifique o volume e as definições de voz.', 'error', 6000);
  };
  window.speechSynthesis.resume();
  window.speechSynthesis.speak(audioUtterance);
}
audioToggle?.addEventListener('click', () => {
  if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
    return toast('A leitura em voz alta não é suportada neste navegador. Experimente Chrome, Edge ou Safari actualizado.', 'error', 6000);
  }
  if (audioActive) return stopChapterAudio();
  if (!verseElements.length) return toast('Este capítulo ainda não possui texto para narrar.', 'error');
  loadPortugueseVoice();
  window.speechSynthesis.cancel();
  audioActive = true;
  audioIndex = 0;
  audioToggle.classList.add('active');
  audioToggle.setAttribute('aria-pressed', 'true');
  audioToggle.innerHTML = '<i class="fas fa-stop"></i><span>Parar</span>';
  speakNextVerse();
  window.setTimeout(() => {
    if (audioActive && !window.speechSynthesis.speaking && !window.speechSynthesis.pending) {
      stopChapterAudio();
      toast('O dispositivo não iniciou a voz. Confirme o volume e instale/active uma voz portuguesa nas definições do sistema.', 'error', 7000);
    }
  }, 1800);
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
