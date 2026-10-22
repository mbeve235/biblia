<?php
/**
 * Histórico — últimas leituras do utilizador.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
require_once __DIR__ . '/includes/header.php';

$history = db_all(
    'SELECT MAX(h.id) AS last_id, h.chapter_number, MAX(h.read_at) AS read_at,
            b.name AS book_name, b.slug, t.abbreviation AS translation
     FROM reading_history h
     JOIN books b ON b.id = h.book_id
     JOIN translations t ON t.id = h.translation_id
     WHERE h.user_id = ?
     GROUP BY b.id, t.id, h.chapter_number
     ORDER BY read_at DESC
     LIMIT 60',
    [$user['id']]
);
?>

<header class="page-header">
  <span class="page-kicker">Pessoal</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-clock-rotate-left"></i></span><div><h1>Histórico de leitura</h1><p>Os capítulos que visitou recentemente.</p></div></div>
</header>

<?php if (!$history): ?>
  <div class="empty-state"><i class="fas fa-clock-rotate-left"></i>Sem histórico. Comece a ler para registar o seu progresso.</div>
<?php else: ?>
  <section class="card content-list">
    <?php foreach ($history as $h): ?>
      <div class="content-list-item">
        <span class="content-list-icon"><?= brand_svg('', 15) ?></span>
        <div class="content-list-main">
          <a href="<?= url('/leitura.php?translation=' . urlencode($h['translation']) . '&book=' . $h['slug'] . '&chapter=' . (int) $h['chapter_number']) ?>">
            <?= e($h['book_name']) ?> <?= (int) $h['chapter_number'] ?>
          </a>
          <span class="badge badge-muted"><?= e($h['translation']) ?></span>
        </div>
        <small class="muted"><?= fmt_datetime($h['read_at']) ?></small>
        <i class="fas fa-chevron-right content-list-arrow"></i>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
