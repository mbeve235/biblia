<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$isReader = $currentFile === 'leitura.php';
$isAuthContext = in_array($currentFile, ['login.php','registar.php','recuperar.php'], true);
$showBookPicker = in_array($currentFile, ['biblia.php','leitura.php','comparar.php'], true);
$mobileItems = [
  ['/index.php','house','Início',['index.php']], ['/biblia.php','book-open','Bíblia',['biblia.php','leitura.php','comparar.php']],
  ['/planos.php','calendar-check','Planos',['planos.php']], ['/favoritos.php','bookmark','Guardados',['favoritos.php','notas.php']],
  [$currentUser ? '/perfil.php' : '/login.php','user',$currentUser ? 'Perfil' : 'Entrar',['perfil.php','login.php','registar.php','historico.php','ministracoes.php']]
];
?></main>
<?php if (!$isReader && !$isAuthContext): ?><nav class="bottom-nav" aria-label="Navegação principal móvel"><?php foreach ($mobileItems as [$path,$icon,$label,$pages]): $active=in_array($currentFile,$pages,true); ?><a href="<?= url($path) ?>" class="<?= $active?'active':'' ?>" <?= $active?'aria-current="page"':'' ?>><i class="fas fa-<?= $icon ?>"></i><span><?= e($label) ?></span></a><?php endforeach; ?></nav><?php endif; ?>
<?php if ($showBookPicker): ?>
<div class="vp-overlay" id="modal-books"><div class="vp-modal" role="dialog" aria-modal="true" aria-label="Escolher leitura">
<header class="vp-head"><div class="vp-head-main"><span class="vp-head-badge"><?= brand_svg('',18) ?></span><div class="vp-head-text"><h3>Escolher leitura</h3><div class="vp-steps"><button type="button" class="vp-step" data-step="book"><b>1</b><span>Livro</span></button><i class="fas fa-chevron-right vp-step-sep"></i><button type="button" class="vp-step" data-step="chapter"><b>2</b><span>Capítulo</span></button></div></div></div><button class="vp-close" type="button" aria-label="Fechar"><i class="fas fa-xmark"></i></button></header>
<div class="vp-body"><div class="vp-sec vp-sec-trans"><div class="vp-seg" id="bp-testament" role="group" aria-label="Testamento"><button type="button" class="vp-seg-btn on" data-tst="AT">Antigo</button><button type="button" class="vp-seg-btn" data-tst="NT">Novo</button></div></div><div class="vp-view" id="bp-view"></div></div>
<footer class="vp-foot"><span class="vp-count" id="bp-loc">Bíblia Sagrada</span><button class="btn btn-ghost" type="button" id="bp-cancel">Fechar</button></footer>
</div></div><?php endif; ?>
<div class="toast-container" role="status" aria-live="polite" aria-atomic="true"></div>
<?php if (!$isReader && !$isAuthContext): ?><footer class="site-footer"><span><?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?></span><span>Um espaço sereno para ler e crescer.</span></footer><?php endif; ?>
<script>window.BASE_URL=<?= json_encode(base_url()) ?>;window.CSRF_TOKEN=<?= json_encode(csrf_token()) ?>;window.IS_LOGGED_IN=<?= current_user()?'true':'false' ?>;</script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__.'/../assets/js/app.js') ?>"></script><?= $pageScripts ?? '' ?>
</body></html>
