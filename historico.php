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

<div class="reader-header">
  <div class="crumb">Pessoal</div>
  <h1><i class="fas fa-clock-rotate-left" style="color:var(--accent)"></i> Histórico de leitura</h1>
</div>

<?php if (!$history): ?>
  <div class="empty-state"><i class="fas fa-clock-rotate-left"></i>Sem histórico. Comece a ler para registar o seu progresso.</div>
<?php else: ?>
  <section class="card" style="padding:.4rem 1.2rem">
    <?php foreach ($history as $h): ?>
      <div class="search-result" style="display:flex;align-items:center;gap:1rem">
        <i class="fas fa-book-open" style="color:var(--accent)"></i>
        <div style="flex:1">
          <a href="<?= url('/leitura.php?translation=' . urlencode($h['translation']) . '&book=' . $h['slug'] . '&chapter=' . (int) $h['chapter_number']) ?>" style="font-weight:700">
            <?= e($h['book_name']) ?> <?= (int) $h['chapter_number'] ?>
          </a>
          <span class="badge badge-muted"><?= e($h['translation']) ?></span>
        </div>
        <small style="color:var(--text-faint)"><?= fmt_datetime($h['read_at']) ?></small>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
