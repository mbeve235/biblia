<?php
/**
 * Comparar traduções — ?book=joao&chapter=3&translations[]=ARC&translations[]=NVI
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();

$bookSlug = trim((string) ($_GET['book'] ?? 'joao'));
$chapNum  = max(1, (int) ($_GET['chapter'] ?? 3));
$selTrans = (array) ($_GET['translations'] ?? ['ARC', 'NVI']);
$selTrans = array_values(array_filter(array_map('trim', $selTrans), fn($t) => $t !== ''));
if (count($selTrans) < 1) $selTrans = ['ARC'];
if (count($selTrans) > 4) $selTrans = array_slice($selTrans, 0, 4);

$layout = ($_GET['layout'] ?? 'grid') === 'table' ? 'table' : 'grid';

$book = db_one('SELECT * FROM books WHERE slug = ?', [$bookSlug]);

// Título + Open Graph específicos da comparação (antes do header)
$pageTitle = $book ? 'Comparar ' . $book['name'] . ' ' . $chapNum : 'Comparar traduções';
$ogTitle   = $pageTitle;
$ogDesc    = $book
    ? 'Compare ' . $book['name'] . ' ' . $chapNum . ' lado a lado em até 4 traduções.'
    : 'Compare capítulos da Bíblia em várias traduções, lado a lado ou em tabela.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ogUrl  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');

require_once __DIR__ . '/includes/header.php';

$translations = db_all('SELECT id, name, abbreviation FROM translations WHERE status = "active" ORDER BY name');
$books = db_all('SELECT id, name, slug, chapters_count FROM books ORDER BY book_order');
$chapterRow = $book ? db_one('SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?', [$book['id'], $chapNum]) : null;

// Buscar versículos por tradução
$columnData = []; // abbr => [verse_number => text]
$verseNumbers = [];
$abbrToName = [];
if ($chapterRow) {
    foreach ($selTrans as $abbr) {
        $t = db_one('SELECT id, name FROM translations WHERE abbreviation = ? AND status = "active"', [$abbr]);
        if (!$t) continue;
        $abbrToName[$abbr] = $t['name'];
        $rows = db_all(
            'SELECT verse_number, text FROM verses WHERE translation_id = ? AND chapter_id = ? ORDER BY verse_number',
            [$t['id'], $chapterRow['id']]
        );
        $columnData[$abbr] = [];
        foreach ($rows as $r) {
            $columnData[$abbr][(int) $r['verse_number']] = $r['text'];
            $verseNumbers[(int) $r['verse_number']] = true;
        }
    }
}
ksort($verseNumbers);
$verseNumbers = array_keys($verseNumbers);
?>

<header class="page-header">
  <span class="page-kicker">Bíblia</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-table-columns"></i></span><div><h1>Comparar traduções</h1><?php if ($book): ?><p><?= e($book['name']) ?> <?= $chapNum ?></p><?php endif; ?></div></div>
</header>

<section class="card">
  <form method="get" id="compare-form" class="compare-form">
    <label class="field compare-book-field">
      <span>Livro</span>
      <select name="book" onchange="this.form.chapter.value=1;this.form.submit()">
        <?php foreach ($books as $b): ?>
          <option value="<?= e($b['slug']) ?>" <?= $book && $b['id'] === (int) $book['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field compare-chapter-field">
      <span>Capítulo</span>
      <input type="number" name="chapter" min="1" max="<?= $book ? (int) $book['chapters_count'] : 150 ?>" value="<?= $chapNum ?>">
    </label>
    <label class="field compare-layout-field">
      <span>Disposição</span>
      <select name="layout" onchange="this.form.submit()">
        <option value="grid" <?= $layout === 'grid' ? 'selected' : '' ?>>Colunas</option>
        <option value="table" <?= $layout === 'table' ? 'selected' : '' ?>>Tabela</option>
      </select>
    </label>
    <div class="compare-translations-field">
      <span class="field-label">Traduções (até 4)</span>
      <div class="choice-chips">
        <?php foreach ($translations as $t): ?>
          <label class="choice-chip">
            <input type="checkbox" name="translations[]" value="<?= e($t['abbreviation']) ?>"
              <?= in_array($t['abbreviation'], $selTrans, true) ? 'checked' : '' ?>>
            <?= e($t['abbreviation']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fas fa-table-columns"></i> Comparar</button>
  </form>
</section>

<?php if (!$book || !$chapterRow): ?>
  <div class="empty-state"><i class="fas fa-circle-exclamation"></i>Livro ou capítulo inválido.</div>
<?php elseif (!$columnData): ?>
  <div class="empty-state"><i class="fas fa-folder-open"></i>Nenhuma das traduções seleccionadas tem texto para este capítulo.</div>
<?php else: ?>

  <?php if ($layout === 'grid'): ?>
    <div class="compare-grid compare-cols-<?= min(4, count($columnData)) ?>">
      <?php foreach ($columnData as $abbr => $verses): ?>
        <div class="compare-col">
          <h3><span class="bk-badge"><?= brand_svg('', 12) ?></span> <?= e($abbr) ?> <small class="muted normal-weight"><?= e($abbrToName[$abbr] ?? '') ?></small></h3>
          <?php foreach ($verses as $num => $text): ?>
            <div class="compare-verse">
              <span class="ref"><?= e($book['name']) ?> <?= $chapNum ?>:<?= $num ?></span>
              <?= e($text) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <section class="card table-wrap flush-card">
      <table class="table compare-table">
        <thead>
          <tr><th class="compare-ref-col">Versículo</th>
              <?php foreach ($columnData as $abbr => $v): ?><th><?= e($abbr) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($verseNumbers as $num): ?>
            <tr>
              <td><strong><?= e($book['name']) ?> <?= $chapNum ?>:<?= $num ?></strong></td>
              <?php foreach ($columnData as $abbr => $verses): ?>
                <td><?= isset($verses[$num]) ? e($verses[$num]) : '<em class="muted">—</em>' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
