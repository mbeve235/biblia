<?php
/**
 * Página inicial — continuar leitura + resumo.
 */
require_once __DIR__ . '/includes/header.php';

$user = current_user();
$lastReading = null;
$totals = null;
$personalStats = null;
$recentReadings = [];

if ($user) {
    $lastReading = db_one(
        'SELECT h.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
         FROM reading_history h
         JOIN books b ON b.id = h.book_id
         JOIN translations t ON t.id = h.translation_id
         WHERE h.user_id = ?
         ORDER BY h.read_at DESC LIMIT 1',
        [$user['id']]
    );
    $personalStats = db_one(
        'SELECT (SELECT COUNT(*) FROM favorites WHERE user_id = ?) AS favorites,
                (SELECT COUNT(*) FROM notes WHERE user_id = ?) AS notes,
                (SELECT COUNT(*) FROM ministracoes WHERE user_id = ?) AS ministracoes',
        [$user['id'], $user['id'], $user['id']]
    );
    $recentReadings = db_all(
        'SELECT h.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation,
                MAX(h.read_at) AS read_at
         FROM reading_history h
         JOIN books b ON b.id = h.book_id
         JOIN translations t ON t.id = h.translation_id
         WHERE h.user_id = ?
         GROUP BY h.translation_id, h.book_id, h.chapter_number, b.name, b.slug, t.abbreviation
         ORDER BY read_at DESC LIMIT 4',
        [$user['id']]
    );
}

$totals = db_one(
    'SELECT (SELECT COUNT(*) FROM translations WHERE status = "active") AS translations,
            (SELECT COUNT(*) FROM books) AS books,
            (SELECT COUNT(*) FROM verses) AS verses'
);

$favVerses = [];
if ($user) {
    $favVerses = db_all(
        'SELECT v.text, v.verse_number, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
         FROM favorites f
         JOIN verses v ON v.id = f.verse_id
         JOIN chapters c ON c.id = v.chapter_id
         JOIN books b ON b.id = v.book_id
         JOIN translations t ON t.id = v.translation_id
         WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 3',
        [$user['id']]
    );
}
?>

<section class="home-hero <?= $user ? 'home-hero-personal' : 'home-hero-guest' ?>">
  <div class="home-hero-content">
    <span class="home-eyebrow"><?= $user ? 'A sua jornada na Palavra' : 'Leitura e estudo bíblico' ?></span>
    <?php if ($user): ?>
      <h1>Olá, <?= e(explode(' ', trim($user['name']))[0]) ?>.</h1>
      <?php if ($lastReading): ?>
        <p>Continue de onde parou em <strong><?= e($lastReading['book_name']) ?> <?= (int) $lastReading['chapter_number'] ?></strong>, na tradução <?= e($lastReading['translation']) ?>.</p>
        <div class="home-hero-actions">
          <a class="btn btn-primary" href="<?= url('/leitura.php?translation=' . urlencode($lastReading['translation']) . '&book=' . urlencode($lastReading['slug']) . '&chapter=' . (int) $lastReading['chapter_number']) ?>"><i class="fas fa-play"></i> Continuar a leitura</a>
          <a class="btn" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Escolher outro livro</a>
        </div>
      <?php else: ?>
        <p>Escolha um livro e comece hoje a sua jornada de leitura.</p>
        <a class="btn btn-primary" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Começar a ler</a>
      <?php endif; ?>
    <?php else: ?>
      <h1>A Bíblia, mais próxima do seu dia.</h1>
      <p>Leia, pesquise, compare traduções e organize os seus estudos num espaço simples e acolhedor.</p>
      <div class="home-hero-actions">
        <a class="btn btn-primary" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Começar a ler</a>
        <a class="btn" href="<?= url('/registar.php') ?>">Criar conta gratuita</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="home-hero-mark" aria-hidden="true">
    <span><?= brand_svg('', 46) ?></span>
    <i class="fas fa-book-open"></i>
  </div>
</section>

<?php if ($user && $personalStats): ?>
<div class="home-personal-stats" aria-label="Resumo da sua conta">
  <a href="<?= url('/favoritos.php') ?>"><i class="fas fa-star"></i><strong><?= (int) $personalStats['favorites'] ?></strong><span>Favoritos</span></a>
  <a href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i><strong><?= (int) $personalStats['notes'] ?></strong><span>Notas</span></a>
  <a href="<?= url('/ministracoes.php') ?>"><i class="fas fa-dove"></i><strong><?= (int) $personalStats['ministracoes'] ?></strong><span>Ministrações</span></a>
</div>
<?php endif; ?>

<?php
// Versículo do dia — determinístico por data (muda cada dia, igual para todos
// nesse dia), escolhido de um grupo de versículos conhecidos na tradução padrão.
$votdPool = [
    ['joao', 3, 16], ['salmos', 23, 1], ['proverbios', 3, 5], ['filipenses', 4, 13],
    ['romanos', 8, 28], ['isaias', 41, 10], ['jeremias', 29, 11], ['mateus', 11, 28],
    ['josue', 1, 9], ['salmos', 46, 1], ['mateus', 6, 33], ['1corintios', 13, 4],
    ['salmos', 119, 105], ['hebreus', 11, 1], ['galatas', 5, 22], ['salmos', 121, 1],
    ['filipenses', 4, 6], ['1pedro', 5, 7], ['proverbios', 16, 3], ['isaias', 40, 31],
];
$votd = null;
$votdTrans = db_one('SELECT id, abbreviation FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
if ($votdTrans) {
    $dayIndex = (int) date('z') + ((int) date('Y') % 7);
    $try = 0;
    while (!$votd && $try < count($votdPool)) {
        $p = $votdPool[($dayIndex + $try) % count($votdPool)];
        $votd = db_one(
            'SELECT v.verse_number, v.text, c.chapter_number, b.name AS book_name, b.slug
             FROM verses v
             JOIN chapters c ON c.id = v.chapter_id
             JOIN books b ON b.id = c.book_id
             WHERE b.slug = ? AND c.chapter_number = ? AND v.verse_number = ? AND v.translation_id = ?',
            [$p[0], $p[1], $p[2], $votdTrans['id']]
        );
        $try++;
    }
}
$votdUrl = $votd
    ? url('/leitura.php?translation=' . urlencode($votdTrans['abbreviation']) . '&book=' . $votd['slug'] . '&chapter=' . (int) $votd['chapter_number'] . '&verse=' . (int) $votd['verse_number'])
    : '';
?>

<?php if ($votd): ?>
<section class="votd">
  <div class="votd-head"><span class="votd-mark"><?= brand_svg('', 15) ?></span> Versículo do dia</div>
  <p class="votd-text">“<?= e($votd['text']) ?>”</p>
  <div class="votd-foot">
    <a class="votd-ref" href="<?= e($votdUrl) ?>">
      <?= e($votd['book_name']) ?> <?= (int) $votd['chapter_number'] ?>:<?= (int) $votd['verse_number'] ?>
      <i class="fas fa-arrow-right"></i>
    </a>
    <button class="votd-share" type="button" onclick="bwShareVotd()"><i class="fas fa-share-nodes"></i> Partilhar</button>
  </div>
</section>
<?php endif; ?>

<section class="home-section">
  <div class="home-section-head"><div><span>Ferramentas</span><h2>O que deseja fazer?</h2></div></div>
  <div class="home-actions">
    <a href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i><strong>Ler a Bíblia</strong><span>Escolher livro e capítulo</span></a>
    <a href="<?= url('/pesquisa.php') ?>"><i class="fas fa-magnifying-glass"></i><strong>Pesquisar</strong><span>Encontrar palavras e referências</span></a>
    <a href="<?= url('/comparar.php') ?>"><i class="fas fa-table-columns"></i><strong>Comparar</strong><span>Ver traduções lado a lado</span></a>
    <a href="<?= url($user ? '/notas.php' : '/login.php') ?>"><i class="fas fa-pen-to-square"></i><strong>Minhas notas</strong><span>Guardar reflexões pessoais</span></a>
  </div>
</section>

<?php if ($user && ($recentReadings || $favVerses)): ?>
<div class="home-dashboard-grid">
  <?php if ($recentReadings): ?>
  <section class="card home-list-card">
    <div class="home-section-head compact"><div><span>Histórico</span><h2>Leituras recentes</h2></div><a href="<?= url('/historico.php') ?>">Ver tudo</a></div>
    <div class="home-reading-list">
      <?php foreach ($recentReadings as $reading): ?>
      <a href="<?= url('/leitura.php?translation=' . urlencode($reading['translation']) . '&book=' . urlencode($reading['slug']) . '&chapter=' . (int) $reading['chapter_number']) ?>">
        <span class="home-list-icon"><i class="fas fa-book-open"></i></span>
        <span><strong><?= e($reading['book_name']) ?> <?= (int) $reading['chapter_number'] ?></strong><small><?= e($reading['translation']) ?> · <?= e(fmt_datetime($reading['read_at'])) ?></small></span>
        <i class="fas fa-chevron-right"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($favVerses): ?>
  <section class="card home-list-card">
    <div class="home-section-head compact"><div><span>Guardados</span><h2>Favoritos recentes</h2></div><a href="<?= url('/favoritos.php') ?>">Ver tudo</a></div>
    <div class="home-favorites-list">
      <?php foreach ($favVerses as $fv): ?>
        <a href="<?= url('/leitura.php?translation=' . urlencode($fv['translation']) . '&book=' . urlencode($fv['slug']) . '&chapter=' . (int) $fv['chapter_number']) . '#v' . (int) $fv['verse_number'] ?>">
          <i class="fas fa-quote-left"></i><span>“<?= e(excerpt($fv['text'], 105)) ?>”<small><?= e($fv['book_name']) ?> <?= (int) $fv['chapter_number'] ?>:<?= (int) $fv['verse_number'] ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<section class="home-section">
  <div class="home-section-head"><div><span>Explorar</span><h2>Livros para começar</h2></div><a href="<?= url('/biblia.php') ?>">Ver os 66 livros</a></div>
  <div class="home-books">
    <?php
    $quick = db_all('SELECT name, slug, testament FROM books WHERE slug IN ("genesis","salmos","proverbios","isaias","mateus","joao","romanos","apocalipse") ORDER BY book_order');
    foreach ($quick as $b): ?>
      <a href="<?= url('/biblia.php?book=' . urlencode($b['slug'])) ?>"><span class="bk-badge"><?= brand_svg('', 12) ?></span><strong><?= e($b['name']) ?></strong><small><?= e($b['testament']) ?></small></a>
    <?php endforeach; ?>
  </div>
</section>

<div class="stats-pills home-platform-stats">
  <span class="pill"><i class="fas fa-language"></i> <?= fmt_num((int) $totals['translations']) ?> <?= $totals['translations'] == 1 ? 'tradução' : 'traduções' ?></span>
  <span class="pill"><i class="fas fa-book"></i> <?= fmt_num((int) $totals['books']) ?> livros</span>
  <span class="pill"><i class="fas fa-scroll"></i> <?= fmt_num((int) $totals['verses']) ?> <?= $totals['verses'] == 1 ? 'versículo' : 'versículos' ?></span>
</div>

<?php
ob_start();
?>
<script>
function bwShareVotd() {
  const el = document.querySelector('.votd');
  if (!el) return;
  const ref = el.querySelector('.votd-ref').innerText.trim();
  const text = el.querySelector('.votd-text').innerText.trim();
  const link = el.querySelector('.votd-ref').href;
  shareText('Versículo do dia — <?= e(APP_NAME) ?>', ref + '\n\n' + text, link);
}
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
