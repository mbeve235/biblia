<?php
require_once __DIR__ . '/bootstrap.php';
boot_session();
$currentUser = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$currentFile = basename($_SERVER['PHP_SELF']);
$isReader = $currentFile === 'leitura.php';
$isAuthContext = in_array($currentFile, ['login.php', 'registar.php', 'recuperar.php'], true);
$isBibleContext = in_array($currentFile, ['biblia.php', 'leitura.php', 'comparar.php'], true);
$translations = $isBibleContext ? db_all('SELECT id, name, abbreviation FROM translations WHERE status = "active" ORDER BY name') : [];
$activeAbbr = $_GET['translation'] ?? ($_COOKIE['bw_translation'] ?? DEFAULT_TRANSLATION);
$navItems = [['index.php','/index.php','Início'],['biblia.php','/biblia.php','Bíblia'],['planos.php','/planos.php','Planos'],['pesquisa.php','/pesquisa.php','Pesquisar']];
$activeNav = $isReader ? 'biblia.php' : $currentFile;
?>
<!DOCTYPE html><html lang="pt" data-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
<title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml"><link rel="alternate icon" href="<?= url('assets/img/favicon.ico') ?>" sizes="any"><link rel="apple-touch-icon" href="<?= url('assets/img/icon-180.png') ?>"><link rel="manifest" href="<?= url('site.webmanifest') ?>"><meta name="theme-color" content="#486f63">
<?php
$ogTitle = $ogTitle ?? ($pageTitle !== APP_NAME ? $pageTitle : APP_NAME);
$ogDesc = $ogDesc ?? 'Leia, pesquise e aprofunde a Bíblia com conforto.';
if (!isset($ogUrl)) { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $ogUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'); }
$ogImagePath = __DIR__ . '/../assets/img/social-preview-v2.jpg';
$ogImage = url('assets/img/social-preview-v2.jpg') . '?v=' . (is_file($ogImagePath) ? filemtime($ogImagePath) : APP_VERSION);
$ogType = $ogType ?? 'website';
?>
<link rel="canonical" href="<?= e($ogUrl) ?>"><meta property="og:type" content="<?= e($ogType) ?>"><meta property="og:site_name" content="<?= e(APP_NAME) ?>"><meta property="og:title" content="<?= e($ogTitle) ?>"><meta property="og:description" content="<?= e($ogDesc) ?>"><meta property="og:url" content="<?= e($ogUrl) ?>"><meta property="og:image" content="<?= e($ogImage) ?>"><meta property="og:image:secure_url" content="<?= e($ogImage) ?>"><meta property="og:image:type" content="image/jpeg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:image:alt" content="<?= e(APP_NAME) ?> — A Palavra, mais próxima do seu dia."><meta property="og:locale" content="pt_PT"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= e($ogTitle) ?>"><meta name="twitter:description" content="<?= e($ogDesc) ?>"><meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php $faUrl = fontawesome_url(); ?><?php if ($faUrl): ?><link rel="stylesheet" href="<?= $faUrl ?>"><?php else: ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.1.0/css/all.min.css"><?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head><body class="<?= e(trim($bodyClass . ($isReader ? ' app-reader' : '') . ($isAuthContext ? ' app-auth' : ''))) ?>">
<a class="skip-link" href="#conteudo-principal">Saltar para o conteúdo</a>
<header class="app-header<?= $isReader ? ' app-header-reader' : '' ?>"><div class="app-header-inner">
  <a class="brand" href="<?= url('/index.php') ?>" aria-label="<?= e(APP_NAME) ?> — início"><span class="logo"><?= brand_svg() ?></span><span class="brand-name"><?= e(APP_NAME) ?></span></a>
  <?php if (!$isReader && !$isAuthContext): ?><nav class="desktop-nav" aria-label="Navegação principal"><?php foreach ($navItems as [$file,$path,$label]): ?><a href="<?= url($path) ?>" class="<?= $activeNav === $file ? 'active' : '' ?>" <?= $activeNav === $file ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav><?php elseif ($isReader): ?><a class="reader-back" href="<?= url('/biblia.php') ?>"><i class="fas fa-chevron-left"></i><span>Biblioteca</span></a><?php endif; ?>
  <div class="header-actions">
    <?php if ($isAuthContext): ?><a class="reader-back auth-back" href="<?= url('/biblia.php') ?>"><i class="fas fa-arrow-left"></i><span>Voltar à Bíblia</span></a><?php endif; ?>
    <?php if ($isBibleContext && !$isReader && $translations): ?><form method="get" class="context-translation"><?php foreach ($_GET as $k=>$v): if ($k !== 'translation' && !is_array($v)): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php endif; endforeach; ?><select name="translation" aria-label="Tradução" onchange="this.form.submit()"><?php foreach ($translations as $t): ?><option value="<?= e($t['abbreviation']) ?>" <?= $t['abbreviation'] === $activeAbbr ? 'selected' : '' ?>><?= e($t['abbreviation']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
    <a class="header-icon header-search" href="<?= url('/pesquisa.php') ?>" aria-label="Pesquisar"><i class="fas fa-magnifying-glass"></i></a><button class="header-icon" data-theme-toggle type="button" aria-label="Alternar tema"><i class="fas fa-moon"></i></button>
    <?php if ($currentUser): ?><details class="account-menu"><summary aria-label="Abrir menu da conta"><span class="account-avatar"><?= e(mb_strtoupper(mb_substr($currentUser['name'],0,1))) ?></span><span class="account-name"><?= e($currentUser['name']) ?></span><i class="fas fa-chevron-down"></i></summary><div class="account-popover"><div class="account-intro"><strong><?= e($currentUser['name']) ?></strong><small>A sua jornada bíblica</small></div><a href="<?= url('/favoritos.php') ?>"><i class="fas fa-bookmark"></i> Guardados</a><a href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i> Notas</a><a href="<?= url('/historico.php') ?>"><i class="fas fa-clock-rotate-left"></i> Histórico</a><a href="<?= url('/ministracoes.php') ?>"><i class="fas fa-dove"></i> Ministrações</a><a href="<?= url('/perfil.php') ?>"><i class="fas fa-sliders"></i> Perfil e preferências</a><?php if (in_array($currentUser['role'],['admin','editor'],true)): ?><a href="<?= url('/admin/index.php') ?>"><i class="fas fa-gauge-high"></i> Administração</a><?php endif; ?><hr><a href="<?= url('/logout.php') ?>"><i class="fas fa-right-from-bracket"></i> Sair</a></div></details><?php else: ?><a class="btn btn-primary header-login" href="<?= url('/login.php') ?>">Entrar</a><?php endif; ?>
  </div>
</div></header>
<main class="main<?= $isReader ? ' reading' : '' ?>" id="conteudo-principal" tabindex="-1">
