<?php
/**
 * Instalador assistido (opcional).
 * Alternativa ao import manual do database.sql via phpMyAdmin.
 * Após instalar, ELIMINE este ficheiro por segurança.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();

$done = false;
$error = null;
$steps = [];

// Auto-protecção: após a primeira instalação bem-sucedida (ou manualmente),
// o instalador deixa de funcionar. Para o reactivar, apague install.lock.
if (is_file(__DIR__ . '/install.lock')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Instalador bloqueado</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:3rem">';
    echo '<h1>Instalador bloqueado</h1><p>A aplicação já foi instalada.</p>';
    echo '<p style="color:#666">Se precisa de reinstalar, apague o ficheiro <code>install.lock</code> na pasta do projecto.</p>';
    echo '</body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        // 1) Criar base de dados sem seleccionar DB
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $steps[] = 'Base de dados "' . DB_NAME . '" criada/verificada.';

        // 2) Executar database.sql (dividido por ; no nível superior)
        $sql = file_get_contents(__DIR__ . '/database/database.sql');
        if ($sql === false) throw new RuntimeException('Ficheiro database/database.sql não encontrado.');
        // Remove comentários de linha
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') continue;
            $pdo->exec($stmt);
        }
        $steps[] = 'Tabelas, índices, livros e capítulos criados.';
        @touch(__DIR__ . '/install.lock');
        $steps[] = 'Ficheiro install.lock criado — o instalador fica bloqueado por segurança.';
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<main class="main" style="max-width:640px;margin:0 auto;padding-top:3rem">
  <h1><i class="fas fa-screwdriver-wrench" style="color:var(--accent)"></i> Instalação da <?= e(APP_NAME) ?></h1>

  <section class="card">
    <h3>Verificação do ambiente</h3>
    <ul>
      <li>PHP: <strong><?= e(PHP_VERSION) ?></strong> <?= version_compare(PHP_VERSION, '8.0.0', '>=') ? '✓' : '⚠ (recomendado 8+)' ?></li>
      <li>PDO MySQL: <strong><?= extension_loaded('pdo_mysql') ? 'disponível ✓' : 'FALTA ✗ (active no XAMPP)' ?></strong></li>
      <li>mbstring: <strong><?= extension_loaded('mbstring') ? 'disponível ✓' : 'FALTA ✗' ?></strong></li>
      <li>Base de dados: <strong><?= e(DB_NAME) ?></strong> @ <?= e(DB_HOST) ?>:<?= e(DB_PORT) ?> (utilizador <?= e(DB_USER) ?>)</li>
    </ul>
    <p style="font-size:.82rem;color:var(--text-faint)">
      Se alguma extensão falta, active em XAMPP → Config → php.ini (remova o <code>;</code> de
      <code>extension=pdo_mysql</code> e <code>extension=mbstring</code>) e reinicie o Apache.
    </p>
  </section>

  <?php if ($error): ?><div class="alert alert-error"><strong>Erro:</strong> <?= e($error) ?></div><?php endif; ?>

  <?php if ($done): ?>
    <div class="alert alert-success">
      <strong>Instalação concluída!</strong><br>
      <?php foreach ($steps as $s) echo '✓ ' . e($s) . '<br>'; ?>
    </div>
    <div class="alert alert-warning">
      <strong>Seguinte:</strong> inicie sessão com <code>admin@biblia.local</code> / <code>admin123</code> e
      <strong>altere a palavra-passe</strong> em Perfil. Depois elimine o ficheiro <code>install.php</code>.
    </div>
    <a class="btn btn-primary" href="<?= url('/login.php') ?>">Ir para o login</a>
  <?php else: ?>
    <section class="card">
      <p>Este assistente cria a base de dados e todas as tabelas. Alternativa: importe
        <code>database/database.sql</code> no phpMyAdmin.</p>
      <form method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-database"></i> Instalar agora</button>
      </form>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
