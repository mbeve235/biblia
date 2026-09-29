<?php
/**
 * Bíblia Web — Configuração geral
 * Altere os valores deste ficheiro conforme o seu ambiente (XAMPP).
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// BASE DE DADOS
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'biblia_web');
define('DB_USER', 'root');      // XAMPP por omissão: root sem password
define('DB_PASS', '');          // Altere se a sua MySQL tiver password
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------
// APLICAÇÃO
// ---------------------------------------------------------------
define('APP_NAME', 'Bíblia Web');
define('APP_VERSION', '1.0.0');
define('APP_URL', '');         // Deixe '' para detecção automática (ex.: http://localhost/biblia-web)
define('TIMEZONE', 'Europe/Lisbon');
define('DEFAULT_TRANSLATION', 'ARC');
date_default_timezone_set(TIMEZONE);

// ---------------------------------------------------------------
// SESSÃO / SEGURANÇA
// ---------------------------------------------------------------
define('SESSION_NAME', 'biblia_web_sess');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);   // 7 dias

// ---------------------------------------------------------------
// IMPORTAÇÃO / UPLOADS
// ---------------------------------------------------------------
define('UPLOAD_MAX_BYTES', 25 * 1024 * 1024);   // 25 MB por ficheiro
define('IMPORT_CHUNK', 500);                    // versículos inseridos por bloco
define('IMPORT_STEPS_PER_REQUEST', 3);          // blocos processados por pedido AJAX

// Extensões permitidas para importação (extensões perigosas ficam bloqueadas)
define('ALLOWED_IMPORT_EXT', ['json', 'csv', 'txt']);

define('DEBUG', true);          // false em produção

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
