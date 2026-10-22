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

<div class="reader-header">
  <div class="crumb">Bíblia</div>
  <h1><i class="fas fa-magnifying-glass" style="color:var(--accent)"></i> Pesquisa bíblica</h1>
  <p style="color:var(--text-soft)">Palavras, frases exactas ("entre aspas") ou referências como <em>João 3:16</em>.</p>
</div>

<section class="card">
  <form method="get" class="search-bar">
    <input type="search" name="q" placeholder="Ex.: amor, &quot;Deus amou o mundo&quot;, João 3:16" value="<?= e($q) ?>" autofocus>
    <button class="btn btn-primary" type="submit"><i class="fas fa-magnifying-glass"></i> Pesquisar</button>
  </form>
  <p style="font-size:.78rem;color:var(--text-faint);margin:.6rem 0 0">
    Os filtros de tradução, livro e testamento aparecem abaixo do campo após o carregamento.
  </p>
</section>

<?php if ($q !== '' && $results === null): ?>
  <div class="alert alert-error">Não foi possível executar a pesquisa. Verifique a ligação à base de dados.</div>
<?php endif; ?>

<?php if ($results !== null): ?>
  <?php if (($results['type'] ?? '') === 'reference'): ?>
    <div class="alert alert-success">
      Referência reconhecida — mostrando <?= count($results['results']) ?> resultado(s) para <strong><?= e($q) ?></strong>.
    </div>
    <?php foreach ($results['results'] as $r): ?>
      <section class="card">
        <div class="card-head">
          <h3 style="color:var(--accent)"><span class="bk-badge" style="vertical-align:-5px"><?= brand_svg('', 12) ?></span> <?= e($r['reference']) ?> <span class="badge badge-accent"><?= e($r['translation']) ?></span></h3>
          <a class="btn btn-sm" href="<?= url('/leitura.php?translation=' . urlencode($r['translation']) . '&book=' . $r['slug'] . '&chapter=' . $r['chapter'] . '&verse=' . $r['verse']) ?>">
            <i class="fas fa-book-open"></i> Ler capítulo
          </a>
        </div>
        <p style="font-family:var(--read-font);font-size:1.1rem"><?= e($r['text']) ?></p>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="card-head" style="margin:1.2rem 0 .6rem">
      <h2><?= count($results['results'] ?? []) ?> resultado(s) para “<?= e($q) ?>”</h2>
    </div>

    <?php if (empty($results['results'])): ?>
      <div class="empty-state"><i class="fas fa-face-frown"></i>Nenhum resultado. Tente outras palavras ou verifique a tradução seleccionada.</div>
    <?php endif; ?>

    <section class="card" style="padding:.4rem 1.2rem">
      <?php foreach (($results['results'] ?? []) as $r): ?>
        <div class="search-result">
          <span class="ref" onclick="location.href='<?= url('/leitura.php?translation=' . urlencode($r['translation']) . '&book=' . $r['slug'] . '&chapter=' . $r['chapter'] . '&verse=' . $r['verse']) ?>'">
            <span class="bk-badge" style="vertical-align:-4px"><?= brand_svg('', 11) ?></span>
            <?= e($r['reference']) ?>
          </span>
          <span class="meta"><i class="fas fa-language"></i> <?= e($r['translation']) ?></span>
          <div class="text"><?= $r['highlight'] /* já escapado na API */ ?></div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php
ob_start();
?>
<script>
// Preencher filtros de tradução/livro/testamento no formulário
(function () {
  const bar = document.querySelector('.search-bar');
  const params = new URLSearchParams(location.search);

  const mkSelect = (name, placeholder, options, current) => {
    const s = document.createElement('select');
    s.name = name;
    const o = document.createElement('option');
    o.value = ''; o.textContent = placeholder;
    s.appendChild(o);
    options.forEach(([v, t]) => {
      const opt = document.createElement('option');
      opt.value = v; opt.textContent = t;
      if (String(v) === String(current)) opt.selected = true;
      s.appendChild(opt);
    });
    return s;
  };

  fetch(BASE_URL + '/api/translations').then(r => r.json()).then(j => {
    if (!j.success) return;
    const opts = j.data.translations.map(t => [t.abbreviation, t.abbreviation]);
    const sel = mkSelect('translation', 'Todas as traduções', opts, params.get('translation') || '');
    sel.style.maxWidth = '160px';
    bar.appendChild(sel);

    const bookSel = mkSelect('book', 'Todos os livros',
      j.data.books ? j.data.books.map(b => [b.slug, b.name]) : [], params.get('book') || '');
    bookSel.style.maxWidth = '180px';
    bar.appendChild(bookSel);

    const testSel = mkSelect('testament', 'AT + NT', [['AT', 'Antigo Testamento'], ['NT', 'Novo Testamento']], params.get('testament') || '');
    testSel.style.maxWidth = '160px';
    bar.appendChild(testSel);
  });
})();
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
