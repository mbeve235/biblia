<?php
/**
 * Página inicial — continuar leitura + resumo.
 */
require_once __DIR__ . '/includes/header.php';

$user = current_user();
$lastReading = null;
$totals = null;

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

<?php if ($lastReading): ?>
  <section class="card" style="background:linear-gradient(120deg,var(--accent-soft),transparent 60%)">
    <div class="card-head">
      <h2><i class="fas fa-bookmark" style="color:var(--accent)"></i> Continuar a leitura</h2>
    </div>
    <p style="font-size:1.05rem">
      <strong><?= e($lastReading['book_name']) ?> <?= (int) $lastReading['chapter_number'] ?></strong>
      · tradução <?= e($lastReading['translation']) ?>
    </p>
    <a class="btn btn-primary" href="<?= url('/leitura.php?translation=' . urlencode($lastReading['translation']) . '&book=' . urlencode($lastReading['slug']) . '&chapter=' . (int) $lastReading['chapter_number']) ?>">
      <i class="fas fa-play"></i> Continuar
    </a>
  </section>
<?php elseif (!$user): ?>
  <section class="card" style="text-align:center;padding:2.4rem">
    <img src="<?= url('assets/img/logo.png') ?>" alt="<?= e(APP_NAME) ?>" width="72" height="72" style="width:72px;height:72px;border-radius:16px;margin-bottom:.9rem;box-shadow:var(--shadow)">
    <h1>Bem-vindo à <?= e(APP_NAME) ?></h1>
    <p style="color:var(--text-soft);max-width:520px;margin:0 auto 1.2rem">
      Leia, pesquise e estude a Bíblia em várias traduções. Marque favoritos, crie notas
      e continue a leitura de onde parou.
    </p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Começar a ler</a>
    <a class="btn" href="<?= url('/registar.php') ?>">Criar conta gratuita</a>
  </section>
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

<div class="stats-pills" style="margin:1.2rem 0">
  <span class="pill"><i class="fas fa-language" style="color:var(--accent)"></i> <?= fmt_num((int) $totals['translations']) ?> <?= $totals['translations'] == 1 ? 'tradução' : 'traduções' ?></span>
  <span class="pill"><i class="fas fa-book" style="color:var(--accent)"></i> <?= fmt_num((int) $totals['books']) ?> livros</span>
  <span class="pill"><i class="fas fa-scroll" style="color:var(--accent)"></i> <?= fmt_num((int) $totals['verses']) ?> <?= $totals['verses'] == 1 ? 'versículo' : 'versículos' ?></span>
</div>

<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h3><i class="fas fa-bolt"></i> Acesso rápido</h3></div>
    <div class="grid grid-2" style="gap:.5rem">
      <?php
      $quick = db_all('SELECT name, slug, testament FROM books WHERE slug IN ("genesis","salmos","proverbios","isaias","mateus","joao","romanos","apocalipse") ORDER BY book_order');
      foreach ($quick as $b): ?>
        <a class="btn" style="justify-content:space-between" href="<?= url('/biblia.php?book=' . urlencode($b['slug'])) ?>">
          <span class="bk-name"><span class="bk-badge"><?= brand_svg('', 12) ?></span><?= e($b['name']) ?></span>
          <small style="color:var(--text-faint)"><?= $b['testament'] ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($favVerses): ?>
  <section class="card">
    <div class="card-head"><h3><i class="fas fa-star" style="color:var(--warning)"></i> Favoritos recentes</h3></div>
    <?php foreach ($favVerses as $fv): ?>
      <p style="font-family:var(--read-font);font-size:1rem">
        “<?= e(excerpt($fv['text'], 120)) ?>”
        <a href="<?= url('/leitura.php?translation=' . urlencode($fv['translation']) . '&book=' . urlencode($fv['slug']) . '&chapter=' . (int) $fv['chapter_number']) . '#v' . (int) $fv['verse_number'] ?>">
          <small>— <?= e($fv['book_name']) ?> <?= (int) $fv['chapter_number'] ?>:<?= (int) $fv['verse_number'] ?></small>
        </a>
      </p>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
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
