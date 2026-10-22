<?php
/**
 * Bíblia — navegação Livro → Capítulo.
 * ?book=joao mostra os capítulos; sem parâmetro mostra os 66 livros.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();

$activeAbbr = $_GET['translation'] ?? ($_COOKIE['bw_translation'] ?? DEFAULT_TRANSLATION);
$trans = db_one('SELECT id, name, abbreviation FROM translations WHERE abbreviation = ? AND status = "active"', [$activeAbbr]);
if (!$trans) {
    $trans = db_one('SELECT id, name, abbreviation FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
}

$bookSlug = trim((string) ($_GET['book'] ?? ''));
$selectedBook = $bookSlug !== '' ? db_one('SELECT * FROM books WHERE slug = ?', [$bookSlug]) : null;

// Título + Open Graph específicos (antes do header, para <title> e prévias)
if ($selectedBook) {
    $pageTitle = $selectedBook['name'];
    $ogTitle   = $selectedBook['name'] . ' — ' . (int) $selectedBook['chapters_count'] . ' capítulo(s)';
    $ogDesc    = 'Escolha o capítulo de ' . $selectedBook['name'] . ' para ler' . ($trans ? ' na tradução ' . $trans['abbreviation'] : '') . '.';
} else {
    $pageTitle = 'Bíblia';
    $ogTitle   = 'Escolha um livro da Bíblia';
    $ogDesc    = '66 livros — Antigo e Novo Testamento — em várias traduções.';
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ogUrl  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');

require_once __DIR__ . '/includes/header.php';

$groups = db_all('SELECT * FROM book_groups ORDER BY testament, group_order');
$books  = db_all('SELECT * FROM books ORDER BY book_order');
$byGroup = [];
foreach ($books as $b) $byGroup[(int) $b['group_id']][] = $b;

// Modo: capítulos de um livro (livro já resolvido antes do header, para o OG)
$chaptersWithVerses = [];
if ($selectedBook && $trans) {
    $rows = db_all(
        'SELECT c.chapter_number, EXISTS(SELECT 1 FROM verses v WHERE v.chapter_id = c.id AND v.translation_id = ?) AS has_verses
         FROM chapters c WHERE c.book_id = ? ORDER BY c.chapter_number',
        [$trans['id'], $selectedBook['id']]
    );
    foreach ($rows as $r) {
        $chaptersWithVerses[(int) $r['chapter_number']] = (bool) $r['has_verses'];
    }
}
?>

<?php if ($selectedBook): ?>
  <div class="reader-header">
    <div class="crumb"><a href="<?= url('/biblia.php') ?>">Bíblia</a> · <?= $selectedBook['testament'] === 'AT' ? 'Antigo Testamento' : 'Novo Testamento' ?></div>
    <h1><?= e($selectedBook['name']) ?></h1>
    <p style="color:var(--text-soft)"><?= (int) $selectedBook['chapters_count'] ?> capítulos · tradução <strong><?= e($trans['abbreviation']) ?></strong></p>
  </div>

  <div class="chapter-grid chapters-page">
    <?php for ($c = 1; $c <= (int) $selectedBook['chapters_count']; $c++): ?>
      <a class="chapter-tile<?= ($chaptersWithVerses[$c] ?? false) ? '' : ' empty' ?>"
         href="<?= url('/leitura.php?translation=' . urlencode($trans['abbreviation']) . '&book=' . $selectedBook['slug'] . '&chapter=' . $c) ?>">
        <?= $c ?>
      </a>
    <?php endfor; ?>
  </div>

  <?php if ($selectedBook['chapters_count'] === 0): ?>
    <div class="empty-state"><i class="fas fa-circle-exclamation"></i>Livro sem capítulos registados.</div>
  <?php endif; ?>

  <p style="margin-top:1.4rem"><a href="<?= url('/biblia.php') ?>"><i class="fas fa-arrow-left"></i> Voltar aos livros</a></p>

<?php else: ?>
  <div class="reader-header">
    <div class="crumb">Bíblia</div>
    <h1>Escolha um livro</h1>
    <p style="color:var(--text-soft)">66 livros · tradução <strong><?= e($trans['abbreviation'] ?? '—') ?></strong> · <?= e($trans['name'] ?? '') ?></p>
  </div>

  <?php foreach (['AT', 'NT'] as $test): ?>
    <?php $testGroups = array_filter($groups, fn($g) => $g['testament'] === $test); ?>
    <section class="card">
      <div class="card-head">
        <h2>
          <span class="test-mark <?= $test === 'AT' ? 'at' : 'nt' ?>"><?= brand_svg('', 14) ?></span>
          <?= $test === 'AT' ? 'Antigo Testamento' : 'Novo Testamento' ?>
        </h2>
      </div>
      <?php foreach ($testGroups as $g): ?>
        <div class="nav-label" style="margin:.9rem .1rem .4rem"><?= e($g['name']) ?></div>
        <div class="book-grid">
          <?php foreach ($byGroup[(int) $g['id']] ?? [] as $b): ?>
            <a class="book-card" href="<?= url('/biblia.php?book=' . $b['slug'] . '&translation=' . urlencode($trans['abbreviation'] ?? '')) ?>">
              <span class="bk-name"><span class="bk-badge"><?= brand_svg('', 12) ?></span><?= e($b['name']) ?></span>
              <span class="bk-meta"><small><?= (int) $b['chapters_count'] ?></small><i class="fas fa-chevron-right"></i></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
