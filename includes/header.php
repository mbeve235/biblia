<?php
/**
 * Cabeçalho comum das páginas públicas.
 * Variáveis opcionais: $pageTitle, $bodyClass
 */
require_once __DIR__ . '/bootstrap.php';
boot_session();
$currentUser = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$translations = db_all('SELECT id, name, abbreviation FROM translations WHERE status = "active" ORDER BY name');
$activeAbbr = $_GET['translation'] ?? ($_COOKIE['bw_translation'] ?? DEFAULT_TRANSLATION);
?>
<!DOCTYPE html>
<html lang="pt" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
<title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="alternate icon" href="<?= url('assets/img/favicon.ico') ?>" sizes="any">
<link rel="apple-touch-icon" href="<?= url('assets/img/icon-180.png') ?>">
<link rel="manifest" href="<?= url('site.webmanifest') ?>">
<meta name="theme-color" content="#6d4df6">
<?php
// Open Graph — prévias no WhatsApp/Telegram/Facebook. As páginas podem definir
// $ogTitle, $ogDesc e $ogUrl antes de incluir o header (ex.: leitura.php);
// por omissão usa-se o título da página e a descrição genérica.
$ogTitle = $ogTitle ?? ($pageTitle !== APP_NAME ? $pageTitle : APP_NAME);
$ogDesc  = $ogDesc  ?? 'Leia, pesquise e compare traduções da Bíblia. Favoritos, notas e histórico.';
if (!isset($ogUrl)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ogUrl  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}
$ogImage = url('assets/img/og.png');
?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e(APP_NAME) ?> — capa da aplicação">
<meta property="og:locale" content="pt_PT">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php $faUrl = fontawesome_url(); ?>
<?php if ($faUrl): ?><link rel="stylesheet" href="<?= $faUrl ?>">
<?php else: ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.1.0/css/all.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">

<a class="skip-link" href="#conteudo-principal">Saltar para o conteúdo</a>

<header class="topbar">
  <button class="menu-toggle" aria-label="Menu" aria-expanded="false" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
  <a class="brand" href="<?= url('/index.php') ?>">
    <span class="logo"><?= brand_svg() ?></span>
    <span><?= e(APP_NAME) ?></span>
  </a>
  <div class="spacer"></div>

  <form action="<?= url('/pesquisa.php') ?>" method="get" class="topbar-search">
    <input type="search" name="q" placeholder="Pesquisar…" aria-label="Pesquisar"
           value="<?= e($_GET['q'] ?? '') ?>" style="padding:.4rem .7rem">
  </form>

  <form method="get" id="header-translation-form" class="topbar-translation">
    <?php foreach ($_GET as $k => $v): if ($k !== 'translation'): ?>
      <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
    <?php endif; endforeach; ?>
    <select name="translation" id="header-translation" aria-label="Tradução" onchange="this.form.submit()">
      <?php foreach ($translations as $t): ?>
        <option value="<?= e($t['abbreviation']) ?>" <?= $t['abbreviation'] === $activeAbbr ? 'selected' : '' ?>>
          <?= e($t['abbreviation']) ?> — <?= e($t['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <button class="btn btn-ghost btn-icon-labeled" data-theme-toggle aria-label="Alternar tema"><i class="fas fa-moon"></i> <span>Tema</span></button>

  <?php if ($currentUser): ?>
    <a class="btn btn-ghost" href="<?= url('/perfil.php') ?>" title="Perfil"><i class="fas fa-user"></i></a>
    <?php if (in_array($currentUser['role'], ['admin', 'editor'], true)): ?>
      <a class="btn btn-sm btn-primary topbar-admin" href="<?= url('/admin/index.php') ?>"><i class="fas fa-gauge-high"></i> Admin</a>
    <?php endif; ?>
  <?php else: ?>
    <a class="btn btn-sm btn-primary" href="<?= url('/login.php') ?>">Entrar</a>
  <?php endif; ?>
</header>

<div class="sidebar-backdrop"></div>

<nav class="sidebar" id="sidebar">
  <div class="nav-label">Bíblia</div>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= url('/index.php') ?>"><i class="fas fa-house"></i> Início</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'biblia.php' ? 'active' : '' ?>" href="<?= url('/biblia.php') ?>"><i class="fas fa-book-open"></i> Bíblia</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pesquisa.php' ? 'active' : '' ?>" href="<?= url('/pesquisa.php') ?>"><i class="fas fa-magnifying-glass"></i> Pesquisa</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'comparar.php' ? 'active' : '' ?>" href="<?= url('/comparar.php') ?>"><i class="fas fa-table-columns"></i> Comparar</a>

  <div class="nav-label">Pessoal</div>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'favoritos.php' ? 'active' : '' ?>" href="<?= url('/favoritos.php') ?>"><i class="fas fa-star"></i> Favoritos</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'notas.php' ? 'active' : '' ?>" href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i> Notas</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'ministracoes.php' ? 'active' : '' ?>" href="<?= url('/ministracoes.php') ?>"><i class="fas fa-dove"></i> Ministrações</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'historico.php' ? 'active' : '' ?>" href="<?= url('/historico.php') ?>"><i class="fas fa-clock-rotate-left"></i> Histórico</a>

  <div class="nav-label">Conta</div>
  <?php if ($currentUser): ?>
    <a class="nav-item" href="<?= url('/perfil.php') ?>"><i class="fas fa-user"></i> <?= e($currentUser['name']) ?></a>
    <a class="nav-item" href="<?= url('/logout.php') ?>"><i class="fas fa-right-from-bracket"></i> Sair</a>
  <?php else: ?>
    <a class="nav-item" href="<?= url('/login.php') ?>"><i class="fas fa-right-to-bracket"></i> Entrar</a>
    <a class="nav-item" href="<?= url('/registar.php') ?>"><i class="fas fa-user-plus"></i> Criar conta</a>
  <?php endif; ?>

  <?php if ($currentUser && in_array($currentUser['role'], ['admin', 'editor'], true)): ?>
    <div class="nav-label">Administração</div>
    <a class="nav-item" href="<?= url('/admin/index.php') ?>"><i class="fas fa-gauge-high"></i> Painel</a>
  <?php endif; ?>

  <div class="nav-label">Leitura</div>
  <button class="nav-item" data-font-dec type="button" style="width:100%;border:none;background:none;cursor:pointer"><i class="fas fa-minus"></i> Diminuir letra</button>
  <button class="nav-item" data-font-inc type="button" style="width:100%;border:none;background:none;cursor:pointer"><i class="fas fa-plus"></i> Aumentar letra</button>
  <span style="font-size:.72rem;color:var(--text-faint);padding:0 .7rem" id="font-size-label">17px</span>
</nav>

<main class="main <?= $bodyClass === 'reading' ? 'reading' : '' ?>" id="conteudo-principal" tabindex="-1">
