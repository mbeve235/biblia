<?php
/**
 * Cabeçalho do painel administrativo. Todas as páginas começam com require_editor().
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
boot_session();
$currentUser = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
$adminPageNames = [
  'index.php' => 'Visão geral', 'traducoes.php' => 'Traduções', 'importar.php' => 'Nova importação',
  'importacoes.php' => 'Histórico de importações', 'livros.php' => 'Livros', 'versiculos.php' => 'Versículos',
  'utilizadores.php' => 'Utilizadores', 'backup.php' => 'Backup e dados', 'configuracoes.php' => 'Configurações',
];
$adminContext = $adminPageNames[$currentPage] ?? 'Administração';
$pageTitle = ($pageTitle ?? $adminContext) . ' — Admin';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>?v=3" type="image/svg+xml" sizes="any">
<link rel="apple-touch-icon" href="<?= url('assets/img/icon-180.png') ?>">
<meta name="theme-color" content="#173a32">
<?php $faUrl = fontawesome_url(); ?>
<?php if ($faUrl): ?><link rel="stylesheet" href="<?= $faUrl ?>">
<?php else: ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.1.0/css/all.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(dirname(__DIR__) . '/assets/css/style.css') ?>">
</head>
<body class="admin-body">

<header class="topbar">
  <button class="menu-toggle admin-menu-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
  <div class="admin-top-context"><small>Painel administrativo</small><strong><?= e($adminContext) ?></strong></div>
  <div class="spacer"></div>
  <a class="admin-top-action" href="<?= url('/index.php') ?>"><i class="fas fa-arrow-up-right-from-square"></i><span>Ver site</span></a>
  <button class="admin-top-action admin-theme" data-theme-toggle aria-label="Alternar tema"><i class="fas fa-moon"></i></button>
  <span class="admin-top-avatar" title="<?= e($currentUser['name']) ?>"><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span>
</header>

<div class="sidebar-backdrop"></div>

<nav class="sidebar" id="sidebar">
  <a class="admin-sidebar-brand" href="<?= url('/admin/index.php') ?>"><span><?= brand_svg('', 26) ?></span><div><strong><?= e(APP_NAME) ?></strong><small>Administração</small></div></a>
  <div class="admin-sidebar-scroll">
  <div class="nav-label">Geral</div>
  <a class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= url('/admin/index.php') ?>"><i class="fas fa-table-cells-large"></i><span>Visão geral</span></a>

  <div class="nav-label">Conteúdo</div>
  <a class="nav-item <?= $currentPage === 'traducoes.php' ? 'active' : '' ?>" href="<?= url('/admin/traducoes.php') ?>"><i class="fas fa-language"></i><span>Traduções</span></a>
  <a class="nav-item <?= $currentPage === 'importar.php' ? 'active' : '' ?>" href="<?= url('/admin/importar.php') ?>"><i class="fas fa-cloud-arrow-up"></i><span>Importar conteúdo</span></a>
  <a class="nav-item <?= $currentPage === 'importacoes.php' ? 'active' : '' ?>" href="<?= url('/admin/importacoes.php') ?>"><i class="fas fa-clock-rotate-left"></i><span>Importações</span></a>
  <a class="nav-item <?= $currentPage === 'livros.php' ? 'active' : '' ?>" href="<?= url('/admin/livros.php') ?>"><i class="fas fa-book-open"></i><span>Livros</span></a>
  <a class="nav-item <?= $currentPage === 'versiculos.php' ? 'active' : '' ?>" href="<?= url('/admin/versiculos.php') ?>"><i class="fas fa-lines-leaning"></i><span>Versículos e títulos</span></a>

  <div class="nav-label">Sistema</div>
  <a class="nav-item <?= $currentPage === 'utilizadores.php' ? 'active' : '' ?>" href="<?= url('/admin/utilizadores.php') ?>"><i class="fas fa-users"></i><span>Utilizadores</span></a>
  <a class="nav-item <?= $currentPage === 'backup.php' ? 'active' : '' ?>" href="<?= url('/admin/backup.php') ?>"><i class="fas fa-database"></i><span>Backup e dados</span></a>
  <?php if ($currentUser['role'] === 'admin'): ?>
  <a class="nav-item <?= $currentPage === 'configuracoes.php' ? 'active' : '' ?>" href="<?= url('/admin/configuracoes.php') ?>"><i class="fas fa-sliders"></i><span>Configurações</span></a>
  <?php endif; ?>
  </div>
  <div class="admin-sidebar-user"><span><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span><div><strong><?= e($currentUser['name']) ?></strong><small><?= e($currentUser['role']) ?></small></div><a href="<?= url('/logout.php') ?>" aria-label="Sair" title="Sair"><i class="fas fa-right-from-bracket"></i></a></div>
</nav>

<main class="main admin-main">
