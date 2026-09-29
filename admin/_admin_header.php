<?php
/**
 * Cabeçalho do painel administrativo. Todas as páginas começam com require_editor().
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
boot_session();
$currentUser = current_user();
$pageTitle = ($pageTitle ?? 'Administração') . ' — Admin';
?>
<!DOCTYPE html>
<html lang="pt" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php $faUrl = fontawesome_url(); ?>
<?php if ($faUrl): ?><link rel="stylesheet" href="<?= $faUrl ?>">
<?php else: ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.1.0/css/all.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<header class="topbar">
  <button class="menu-toggle" aria-label="Menu" aria-expanded="false" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
  <a class="brand" href="<?= url('/admin/index.php') ?>">
    <span class="logo" style="background:linear-gradient(135deg,#e67e22,#f39c12)"><i class="fas fa-gauge-high"></i></span>
    <span>Admin · <?= e(APP_NAME) ?></span>
  </a>
  <div class="spacer"></div>
  <a class="btn btn-ghost topbar-back" href="<?= url('/index.php') ?>"><i class="fas fa-arrow-left"></i> <span>Ver site</span></a>
  <button class="btn btn-ghost" data-theme-toggle aria-label="Alternar tema"><i class="fas fa-moon"></i></button>
  <span class="badge badge-muted topbar-user"><?= e($currentUser['name']) ?> (<?= e($currentUser['role']) ?>)</span>
  <a class="btn btn-sm btn-danger btn-icon-labeled" href="<?= url('/logout.php') ?>"><i class="fas fa-right-from-bracket"></i> <span>Sair</span></a>
</header>

<div class="sidebar-backdrop"></div>

<nav class="sidebar" id="sidebar">
  <div class="nav-label">Geral</div>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= url('/admin/index.php') ?>"><i class="fas fa-gauge-high"></i> Dashboard</a>

  <div class="nav-label">Conteúdo</div>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'traducoes.php' ? 'active' : '' ?>" href="<?= url('/admin/traducoes.php') ?>"><i class="fas fa-language"></i> Traduções</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'importar.php' ? 'active' : '' ?>" href="<?= url('/admin/importar.php') ?>"><i class="fas fa-file-import"></i> Importar</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'importacoes.php' ? 'active' : '' ?>" href="<?= url('/admin/importacoes.php') ?>"><i class="fas fa-clipboard-list"></i> Importações</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'livros.php' ? 'active' : '' ?>" href="<?= url('/admin/livros.php') ?>"><i class="fas fa-book"></i> Livros</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'versiculos.php' ? 'active' : '' ?>" href="<?= url('/admin/versiculos.php') ?>"><i class="fas fa-scroll"></i> Versículos</a>

  <div class="nav-label">Sistema</div>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'utilizadores.php' ? 'active' : '' ?>" href="<?= url('/admin/utilizadores.php') ?>"><i class="fas fa-users"></i> Utilizadores</a>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : '' ?>" href="<?= url('/admin/backup.php') ?>"><i class="fas fa-database"></i> Backup</a>
  <?php if ($currentUser['role'] === 'admin'): ?>
  <a class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'configuracoes.php' ? 'active' : '' ?>" href="<?= url('/admin/configuracoes.php') ?>"><i class="fas fa-gear"></i> Configurações</a>
  <?php endif; ?>
</nav>

<main class="main">
