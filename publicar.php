<?php
/**
 * Publicação de ministração por link — /publicar.php?t=TOKEN
 * Público (não exige sessão): só mostra ministrações cujo share_token
 * corresponde exactamente. O autor controla activar/desactivar o link.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();

$token = trim((string) ($_GET['t'] ?? ''));
$m = null;
if ($token !== '') {
    $m = db_one(
        'SELECT m.*, b.name AS book_name, b.slug
         FROM ministracoes m
         LEFT JOIN books b ON b.id = m.book_id
         WHERE m.share_token = ? LIMIT 1',
        [$token]
    );
}

$author = null;
if ($m) {
    $author = db_one('SELECT name FROM users WHERE id = ?', [(int) $m['user_id']])['name'] ?? null;
}

// Metadados de partilha (WhatsApp/Telegram mostram título e início do texto)
$pageTitle = $m ? $m['title'] : 'Ministração não encontrada';
$ogTitle   = $m ? $m['title'] . ' — ' . APP_NAME : 'Ministração não encontrada';
if ($m) {
    $ogDesc = excerpt($m['body'], 160);
    if ($author) $ogDesc = 'Por ' . $author . ' — ' . $ogDesc;
} else {
    $ogDesc = 'Este link de ministração não está disponível ou foi desactivado pelo autor.';
}
$noindex = true; // link privado por convite: fora dos motores de busca

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$m): ?>
  <section class="card empty-state">
    <i class="fas fa-link-slash"></i>
    <h1>Link indisponível</h1>
    <p class="muted">Esta ministração não existe, foi despublicada pelo autor ou o endereço está incompleto.</p>
    <a class="btn btn-primary" href="<?= url('/index.php') ?>"><i class="fas fa-book-bible"></i> Ir para <?= e(APP_NAME) ?></a>
  </section>
<?php else: ?>
  <article class="card min-card public-min-card">
    <div class="card-head">
      <h2 class="entity-title">
        <span class="bk-badge"><?= brand_svg('', 14) ?></span>
        <?= e($m['title']) ?>
      </h2>
    </div>
    <p class="section-meta public-min-meta">
      <?php if ($author): ?>Por <strong><?= e($author) ?></strong> · <?php endif; ?>
      <?= fmt_datetime($m['updated_at']) ?>
    </p>

    <?php
    $v = null;
    if ($m['verse_id']) {
        $v = db_one(
            'SELECT v.verse_number, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation, v.text
             FROM verses v
             JOIN chapters c ON c.id = v.chapter_id
             JOIN books b ON b.id = c.book_id
             JOIN translations t ON t.id = v.translation_id
             WHERE v.id = ?',
            [(int) $m['verse_id']]
        );
    }
    ?>
    <?php if ($v): ?>
      <blockquote class="public-scripture">
        “<?= e($v['text']) ?>”
        <a class="min-ref public-scripture-ref"
           href="<?= url('/leitura.php?translation=' . urlencode($v['translation']) . '&book=' . $v['slug'] . '&chapter=' . (int) $v['chapter_number'] . '&verse=' . (int) $v['verse_number']) ?>">
          <?= e($v['book_name']) ?> <?= (int) $v['chapter_number'] ?>:<?= (int) $v['verse_number'] ?>
          <span class="badge badge-accent"><?= e($v['translation']) ?></span>
        </a>
      </blockquote>
    <?php elseif ($m['book_id'] && $m['chapter_number']): ?>
      <p class="min-ref">
        <a href="<?= url('/leitura.php?book=' . ($m['slug'] ?? '') . '&chapter=' . (int) $m['chapter_number']) ?>">
          <?= e($m['book_name'] ?? '') ?> <?= (int) $m['chapter_number'] ?>
        </a>
      </p>
    <?php endif; ?>

    <div class="min-body"><?= nl2br(e($m['body'])) ?></div>

    <div class="public-min-footer">
      <span class="muted">
        <i class="fas fa-share-nodes accent-icon"></i>
        Partilhado através de <strong><?= e(APP_NAME) ?></strong>
      </span>
      <a class="btn btn-sm btn-primary" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Começar a ler</a>
    </div>
  </article>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
