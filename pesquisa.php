<?php
/**
 * Pesquisa — formulário e resultados.
 * Suporta palavras, frases "entre aspas" e referências (João 3:16).
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();

$q = trim((string) ($_GET['q'] ?? ''));
$transAbbr = trim((string) ($_GET['translation'] ?? ''));
$bookSlug  = trim((string) ($_GET['book'] ?? ''));
$testament = trim((string) ($_GET['testament'] ?? ''));

// Título + Open Graph específicos da pesquisa (antes do header)
if ($q !== '') {
    $pageTitle = 'Pesquisa: ' . $q;
    $ogTitle   = '“' . $q . '” — pesquisa bíblica';
    $ogDesc    = 'Resultados da pesquisa por ' . $q . ' nas traduções disponíveis.';
} else {
    $pageTitle = 'Pesquisa bíblica';
    $ogTitle   = 'Pesquisa bíblica';
    $ogDesc    = 'Pesquise palavras, frases exactas ou referências como João 3:16 em todas as traduções.';
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ogUrl  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');

require_once __DIR__ . '/includes/header.php';

$translations = db_all('SELECT abbreviation, name FROM translations WHERE status = "active" ORDER BY name');
$books = db_all('SELECT name, slug FROM books ORDER BY book_order');
$recentSearches = [];
$searchUser = current_user();
if ($searchUser) {
    $recentSearches = db_all(
        'SELECT query, MAX(created_at) AS searched_at
         FROM search_logs WHERE user_id = ?
         GROUP BY query ORDER BY searched_at DESC LIMIT 6',
        [$searchUser['id']]
    );
}

$results = null;
$isReference = false;

if ($q !== '') {
    // Referência directa? (ex.: "João 3:16")
    $isReference = (bool) preg_match('/^([1-3]?\s?[a-zà-ú\.]+\.?)\s*(\d{1,3})\s*[:.\-\s]\s*(\d{1,3})$/iu', $q);

    $params = ['q' => $q];
    if ($transAbbr !== '') $params['translation'] = $transAbbr;
    if ($bookSlug !== '')  $params['book'] = $bookSlug;
    if ($testament !== '') $params['testament'] = $testament;

    $url = url('/api/search') . '?' . http_build_query($params);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 8]]);
    $raw = @file_get_contents($url, false, $ctx);
    $decoded = $raw ? json_decode($raw, true) : null;
    if (is_array($decoded) && !empty($decoded['success'])) {
        $results = $decoded['data'];
    }
}
?>

<header class="search-hero">
  <span class="home-eyebrow">Encontre na Palavra</span>
  <h1>Pesquisa bíblica</h1>
  <p>Procure uma palavra, uma frase exacta ou escreva directamente uma referência.</p>
</header>

<section class="card search-panel">
  <form method="get" class="search-form" id="search-form">
    <div class="search-main-field">
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" name="q" aria-label="Termo de pesquisa" placeholder="Amor, &quot;Deus amou o mundo&quot; ou João 3:16" value="<?= e($q) ?>" autofocus required maxlength="100">
      <?php if ($q !== ''): ?><a href="<?= url('/pesquisa.php') ?>" aria-label="Limpar pesquisa" title="Limpar"><i class="fas fa-xmark"></i></a><?php endif; ?>
      <button class="btn btn-primary" type="submit"><span>Pesquisar</span><i class="fas fa-arrow-right"></i></button>
    </div>
    <div class="search-filters" aria-label="Filtros de pesquisa">
      <label><span>Tradução</span><select name="translation"><option value="">Todas</option><?php foreach ($translations as $t): ?><option value="<?= e($t['abbreviation']) ?>" <?= $transAbbr === $t['abbreviation'] ? 'selected' : '' ?>><?= e($t['abbreviation']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?></select></label>
      <label><span>Livro</span><select name="book"><option value="">Todos os livros</option><?php foreach ($books as $b): ?><option value="<?= e($b['slug']) ?>" <?= $bookSlug === $b['slug'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></label>
      <label><span>Testamento</span><select name="testament"><option value="">Antigo e Novo</option><option value="AT" <?= $testament === 'AT' ? 'selected' : '' ?>>Antigo Testamento</option><option value="NT" <?= $testament === 'NT' ? 'selected' : '' ?>>Novo Testamento</option></select></label>
      <?php if ($transAbbr !== '' || $bookSlug !== '' || $testament !== ''): ?><a class="search-clear-filters" href="<?= url('/pesquisa.php?q=' . urlencode($q)) ?>"><i class="fas fa-filter-circle-xmark"></i> Limpar filtros</a><?php endif; ?>
    </div>
  </form>
</section>

<?php if ($q === ''): ?>
<section class="search-discovery">
  <div class="search-tips card">
    <div class="home-section-head compact"><div><span>Experimente</span><h2>Formas de pesquisar</h2></div></div>
    <div class="search-examples">
      <a href="<?= url('/pesquisa.php?q=amor') ?>"><i class="fas fa-font"></i><span><strong>Uma palavra</strong><small>amor</small></span></a>
      <a href="<?= url('/pesquisa.php?q=' . urlencode('"Deus amou o mundo"')) ?>"><i class="fas fa-quote-left"></i><span><strong>Frase exacta</strong><small>“Deus amou o mundo”</small></span></a>
      <a href="<?= url('/pesquisa.php?q=' . urlencode('João 3:16')) ?>"><i class="fas fa-location-dot"></i><span><strong>Referência</strong><small>João 3:16</small></span></a>
    </div>
  </div>
  <div class="card search-recents">
    <div class="home-section-head compact"><div><span>Histórico</span><h2>Pesquisas recentes</h2></div></div>
    <div id="recent-searches">
      <?php foreach ($recentSearches as $recent): ?><a href="<?= url('/pesquisa.php?q=' . urlencode($recent['query'])) ?>"><i class="fas fa-clock-rotate-left"></i><?= e($recent['query']) ?></a><?php endforeach; ?>
      <?php if (!$recentSearches): ?><p class="search-recents-empty">As suas pesquisas recentes aparecerão aqui.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($q !== '' && $results === null): ?>
  <div class="alert alert-error" role="alert">Não foi possível executar a pesquisa. Verifique a ligação à base de dados.</div>
<?php endif; ?>

<?php if ($results !== null): ?>
  <?php if (($results['type'] ?? '') === 'reference'): ?>
    <div class="alert alert-success search-reference-notice">
      Referência reconhecida — mostrando <?= count($results['results']) ?> resultado(s) para <strong><?= e($q) ?></strong>.
    </div>
    <?php foreach ($results['results'] as $r): ?>
      <section class="card search-reference-card">
        <div class="card-head">
          <h3><span class="bk-badge"><?= brand_svg('', 12) ?></span> <?= e($r['reference']) ?> <span class="badge badge-accent"><?= e($r['translation']) ?></span></h3>
          <a class="btn btn-sm" href="<?= url('/leitura.php?translation=' . urlencode($r['translation']) . '&book=' . $r['slug'] . '&chapter=' . $r['chapter'] . '&verse=' . $r['verse']) ?>">
            <i class="fas fa-book-open"></i> Ler capítulo
          </a>
        </div>
        <p><?= e($r['text']) ?></p>
        <div class="search-result-actions">
          <button class="btn btn-sm" type="button" data-ref="<?= e($r['reference'] . ' (' . $r['translation'] . ')') ?>" data-text="<?= e($r['text']) ?>" onclick="bwCopySearch(this)"><i class="fas fa-copy"></i> Copiar</button>
          <button class="btn btn-sm" type="button" data-ref="<?= e($r['reference'] . ' (' . $r['translation'] . ')') ?>" data-text="<?= e($r['text']) ?>" data-url="<?= e(scripture_url($r['translation'], $r['slug'], (int)$r['chapter'], (int)$r['verse'])) ?>" onclick="bwShareSearch(this)"><i class="fas fa-share-nodes"></i> Partilhar</button>
        </div>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="search-results-head">
      <div><span class="home-eyebrow">Resultados</span><h2><?= count($results['results'] ?? []) ?> resultado(s) para “<?= e($q) ?>”</h2></div>
      <span class="search-filter-summary"><?php if ($transAbbr): ?><?= e($transAbbr) ?><?php endif; ?><?php if ($bookSlug): ?> · <?= e(db_one('SELECT name FROM books WHERE slug = ?', [$bookSlug])['name'] ?? '') ?><?php endif; ?><?php if ($testament): ?> · <?= e($testament) ?><?php endif; ?></span>
    </div>

    <?php if (empty($results['results'])): ?>
      <div class="empty-state search-empty"><i class="fas fa-magnifying-glass-minus"></i><h3>Nenhum versículo encontrado</h3><p>Tente retirar um filtro, usar menos palavras ou verificar a escrita da referência.</p><div><a class="btn" href="<?= url('/pesquisa.php?q=' . urlencode($q)) ?>">Pesquisar sem filtros</a><a class="btn btn-primary" href="<?= url('/pesquisa.php') ?>">Nova pesquisa</a></div></div>
    <?php endif; ?>

    <?php if (!empty($results['results'])): ?><section class="card search-results-list">
      <?php foreach (($results['results'] ?? []) as $r): ?>
        <article class="search-result">
          <a class="ref" href="<?= url('/leitura.php?translation=' . urlencode($r['translation']) . '&book=' . $r['slug'] . '&chapter=' . $r['chapter'] . '&verse=' . $r['verse']) ?>">
            <span class="bk-badge" style="vertical-align:-4px"><?= brand_svg('', 11) ?></span>
            <?= e($r['reference']) ?>
          </a>
          <span class="meta"><i class="fas fa-language"></i> <?= e($r['translation']) ?></span>
          <div class="text"><?= $r['highlight'] /* já escapado na API */ ?></div>
          <div class="search-result-actions">
            <button class="btn btn-sm" type="button" data-ref="<?= e($r['reference'] . ' (' . $r['translation'] . ')') ?>" data-text="<?= e($r['text']) ?>" onclick="bwCopySearch(this)" aria-label="Copiar <?= e($r['reference']) ?>"><i class="fas fa-copy"></i> Copiar</button>
            <button class="btn btn-sm" type="button" data-ref="<?= e($r['reference'] . ' (' . $r['translation'] . ')') ?>" data-text="<?= e($r['text']) ?>" data-url="<?= e(scripture_url($r['translation'], $r['slug'], (int)$r['chapter'], (int)$r['verse'])) ?>" onclick="bwShareSearch(this)" aria-label="Partilhar <?= e($r['reference']) ?>"><i class="fas fa-share-nodes"></i> Partilhar</button>
          </div>
          <a class="search-result-open" aria-label="Abrir <?= e($r['reference']) ?>" href="<?= url('/leitura.php?translation=' . urlencode($r['translation']) . '&book=' . $r['slug'] . '&chapter=' . $r['chapter'] . '&verse=' . $r['verse']) ?>"><i class="fas fa-arrow-right"></i></a>
        </article>
      <?php endforeach; ?>
    </section><?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php
ob_start();
?>
<script>
function bwSearchText(button) {
  return button.dataset.ref + ' — ' + button.dataset.text;
}
function bwCopySearch(button) {
  copyText(bwSearchText(button));
}
function bwShareSearch(button) {
  shareText(button.dataset.ref, bwSearchText(button), button.dataset.url || location.href);
}
// Visitantes: manter até seis pesquisas recentes apenas neste navegador.
(function () {
  const query = <?= json_encode($q) ?>;
  const loggedIn = <?= $searchUser ? 'true' : 'false' ?>;
  if (!loggedIn && query) {
    const recents = JSON.parse(localStorage.getItem('bw_recent_searches') || '[]');
    localStorage.setItem('bw_recent_searches', JSON.stringify([query, ...recents.filter((item) => item !== query)].slice(0, 6)));
  }
  if (!loggedIn && !query) {
    const box = document.getElementById('recent-searches');
    const recents = JSON.parse(localStorage.getItem('bw_recent_searches') || '[]');
    if (box && recents.length) {
      box.innerHTML = '';
      recents.forEach((item) => {
        const link = document.createElement('a');
        link.href = BASE_URL + '/pesquisa.php?q=' + encodeURIComponent(item);
        link.innerHTML = '<i class="fas fa-clock-rotate-left"></i>' + escapeHtml(item);
        box.appendChild(link);
      });
    }
  }
})();
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
