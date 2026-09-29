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
    <i class="fas fa-book-bible" style="font-size:2.6rem;color:var(--accent);margin-bottom:.8rem;display:block"></i>
    <h1>Bem-vindo à <?= e(APP_NAME) ?></h1>
    <p style="color:var(--text-soft);max-width:520px;margin:0 auto 1.2rem">
      Leia, pesquise e estude a Bíblia em várias traduções. Marque favoritos, crie notas
      e continue a leitura de onde parou.
    </p>
    <a class="btn btn-primary" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Começar a ler</a>
    <a class="btn" href="<?= url('/registar.php') ?>">Criar conta gratuita</a>
  </section>
<?php endif; ?>

<div class="grid grid-3" style="margin:1.2rem 0">
  <div class="stat-card"><div class="icon"><i class="fas fa-language"></i></div>
    <div><div class="value"><?= fmt_num((int) $totals['translations']) ?></div><div class="label">Traduções activas</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-book"></i></div>
    <div><div class="value"><?= fmt_num((int) $totals['books']) ?></div><div class="label">Livros</div></div></div>
  <div class="stat-card"><div class="icon"><i class="fas fa-scroll"></i></div>
    <div><div class="value"><?= fmt_num((int) $totals['verses']) ?></div><div class="label">Versículos</div></div></div>
</div>

<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h3><i class="fas fa-bolt"></i> Acesso rápido</h3></div>
    <div class="grid grid-2" style="gap:.5rem">
      <?php
      $quick = db_all('SELECT name, slug, testament FROM books WHERE slug IN ("genesis","salmos","proverbios","isaias","mateus","joao","romanos","apocalipse") ORDER BY book_order');
      foreach ($quick as $b): ?>
        <a class="btn" style="justify-content:space-between" href="<?= url('/biblia.php?book=' . urlencode($b['slug'])) ?>">
          <?= e($b['name']) ?> <small style="color:var(--text-faint)"><?= $b['testament'] ?></small>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
