<?php
/**
 * Rodapé comum das páginas públicas.
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$bottomItems = [
    '/index.php'      => ['house', 'Início'],
    '/biblia.php'     => ['book-open', 'Bíblia'],
    '/pesquisa.php'   => ['magnifying-glass', 'Pesquisa'],
    '/favoritos.php'  => ['star', 'Favoritos'],
    '/notas.php'      => ['pen-to-square', 'Notas'],
];
?>
</main>

<nav class="bottom-nav">
  <?php foreach ($bottomItems as $path => [$icon, $label]): ?>
    <a href="<?= url($path) ?>" class="<?= $currentFile === basename($path) ? 'active' : '' ?>">
      <i class="fas fa-<?= $icon ?>"></i><span><?= $label ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<!-- Modal de selecção de livro/capítulo (leitor) — mesmo design do selector das ministrações -->
<div class="vp-overlay" id="modal-books">
  <div class="vp-modal" role="dialog" aria-modal="true" aria-label="Escolher leitura">
    <header class="vp-head">
      <div class="vp-head-main">
        <span class="vp-head-badge"><?= brand_svg('', 18) ?></span>
        <div class="vp-head-text">
          <h3>Escolher leitura</h3>
          <div class="vp-steps">
            <button type="button" class="vp-step" data-step="book"><b>1</b><span>Livro</span></button>
            <i class="fas fa-chevron-right vp-step-sep"></i>
            <button type="button" class="vp-step" data-step="chapter"><b>2</b><span>Capítulo</span></button>
          </div>
        </div>
      </div>
      <button class="vp-close" type="button" aria-label="Fechar"><i class="fas fa-xmark"></i></button>
    </header>

    <div class="vp-body">
      <div class="vp-sec vp-sec-trans">
        <div class="vp-seg" id="bp-testament" role="group" aria-label="Testamento">
          <button type="button" class="vp-seg-btn on" data-tst="AT">Antigo</button>
          <button type="button" class="vp-seg-btn" data-tst="NT">Novo</button>
        </div>
      </div>
      <div class="vp-view" id="bp-view"></div>
    </div>

    <footer class="vp-foot">
      <span class="vp-count" id="bp-loc">Bíblia Sagrada</span>
      <div style="display:flex;gap:.5rem">
        <button class="btn btn-ghost" type="button" id="bp-cancel">Fechar</button>
      </div>
    </footer>
  </div>
</div>

<div class="toast-container" role="status" aria-live="polite" aria-atomic="true"></div>

<footer style="text-align:center;color:var(--text-faint);font-size:.78rem;padding:1rem 0 4rem">
  <?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?> — conteúdo bíblico sujeito aos direitos de cada tradução.
</footer>

<script>
  window.BASE_URL = <?= json_encode(base_url()) ?>;
  window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  window.IS_LOGGED_IN = <?= current_user() ? 'true' : 'false' ?>;
</script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
