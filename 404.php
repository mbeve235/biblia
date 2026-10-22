<?php
/**
 * 404 — página não encontrada, com atalhos para voltar à leitura.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
http_response_code(404);

$currentUser = current_user();

// Continuar a leitura: último capítulo do histórico (com sessão) ou João 3 (visitante)
$resume = null;
if ($currentUser) {
    $resume = db_one(
        'SELECT h.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
         FROM reading_history h
         JOIN books b ON b.id = h.book_id
         JOIN translations t ON t.id = h.translation_id
         WHERE h.user_id = ?
         ORDER BY h.read_at DESC LIMIT 1',
        [$currentUser['id']]
    );
}
if (!$resume) {
    $resume = ['book_name' => 'João', 'slug' => 'joao', 'chapter_number' => 3, 'translation' => DEFAULT_TRANSLATION];
}
$resumeUrl = url('/leitura.php?translation=' . urlencode($resume['translation'])
    . '&book=' . $resume['slug'] . '&chapter=' . (int) $resume['chapter_number']);

$quick = db_all('SELECT name, slug FROM books WHERE slug IN ("salmos","proverbios","mateus","joao","romanos") ORDER BY book_order');

$pageTitle = 'Página não encontrada';
$ogTitle   = 'Página não encontrada';
$ogDesc    = 'O endereço pedido não existe na ' . APP_NAME . '.';
$noindex   = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="empty-state error-page-state">
  <i class="fas fa-book-bookmark"></i>
  <h1 class="error-code">404</h1>
  <p><strong>Esta página não existe (ou o endereço está mal escrito).</strong></p>
  <p class="error-page-copy">
    Mas a leitura não tem de parar — continue onde ficou ou salte para um livro:
  </p>
</div>

<section class="card narrow-card centered-card">
  <h3><i class="fas fa-bookmark accent-icon"></i> Continuar a leitura</h3>
  <p class="lead-compact">
    <strong><?= e($resume['book_name']) ?> <?= (int) $resume['chapter_number'] ?></strong>
    <?php if (!empty($resume['translation'])): ?>· <?= e($resume['translation']) ?><?php endif; ?>
  </p>
  <a class="btn btn-primary btn-block" href="<?= $resumeUrl ?>"><i class="fas fa-play"></i> Continuar</a>
</section>

<section class="card narrow-card centered-card">
  <h3><i class="fas fa-compass accent-icon"></i> Ir para</h3>
  <div class="grid grid-2 compact-grid">
    <?php foreach ($quick as $b): ?>
      <a class="book-card" href="<?= url('/biblia.php?book=' . $b['slug']) ?>">
        <span><?= e($b['name']) ?></span>
        <span class="bk-meta"><i class="fas fa-chevron-right"></i></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form action="<?= url('/pesquisa.php') ?>" method="get" class="search-bar spaced-top">
    <input type="search" name="q" placeholder="Ou pesquise um versículo…">
    <button class="btn" type="submit"><i class="fas fa-magnifying-glass"></i></button>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
