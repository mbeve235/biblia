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

<div class="empty-state" style="padding-top:4rem">
  <i class="fas fa-book-bookmark"></i>
  <h1 style="font-size:2.6rem;margin:.2rem 0">404</h1>
  <p><strong>Esta página não existe (ou o endereço está mal escrito).</strong></p>
  <p style="color:var(--text-soft);max-width:420px;margin:0 auto 1.4rem">
    Mas a leitura não tem de parar — continue onde ficou ou salte para um livro:
  </p>
</div>

<section class="card" style="max-width:520px;margin:0 auto 1.2rem;text-align:center">
  <h3><i class="fas fa-bookmark" style="color:var(--accent)"></i> Continuar a leitura</h3>
  <p style="font-size:1.05rem;margin:.6rem 0 1rem">
    <strong><?= e($resume['book_name']) ?> <?= (int) $resume['chapter_number'] ?></strong>
    <?php if (!empty($resume['translation'])): ?>· <?= e($resume['translation']) ?><?php endif; ?>
  </p>
  <a class="btn btn-primary btn-block" href="<?= $resumeUrl ?>"><i class="fas fa-play"></i> Continuar</a>
</section>

<section class="card" style="max-width:520px;margin:0 auto;text-align:center">
  <h3><i class="fas fa-compass" style="color:var(--accent)"></i> Ir para</h3>
  <div class="grid grid-2" style="gap:.5rem;margin-top:.8rem">
    <?php foreach ($quick as $b): ?>
      <a class="book-card" href="<?= url('/biblia.php?book=' . $b['slug']) ?>">
        <span><?= e($b['name']) ?></span>
        <span class="bk-meta"><i class="fas fa-chevron-right"></i></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form action="<?= url('/pesquisa.php') ?>" method="get" class="search-bar" style="margin-top:1rem">
    <input type="search" name="q" placeholder="Ou pesquise um versículo…">
    <button class="btn" type="submit"><i class="fas fa-magnifying-glass"></i></button>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
