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

<!-- Modal de selecção de livro/capítulo -->
<div class="modal-overlay" id="modal-books">
  <div class="modal">
    <button class="modal-close" type="button" aria-label="Fechar"><i class="fas fa-xmark"></i></button>
    <h3>Escolher leitura</h3>
    <div class="testament-tabs">
      <button type="button" data-testament="AT">Antigo Testamento</button>
      <button type="button" data-testament="NT">Novo Testamento</button>
    </div>
    <div id="book-grid" class="book-grid"></div>
  </div>
</div>

<div class="toast-container"></div>

<footer style="text-align:center;color:var(--text-faint);font-size:.78rem;padding:1rem 0 4rem">
  <?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?> — conteúdo bíblico sujeito aos direitos de cada tradução.
</footer>

<script>
  window.BASE_URL = <?= json_encode(base_url()) ?>;
  window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  window.IS_LOGGED_IN = <?= current_user() ? 'true' : 'false' ?>;
</script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
