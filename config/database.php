<?php
/**
 * Conexão PDO com MySQL/MariaDB (singleton).
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        if (DEBUG) {
            die('Erro de ligação à base de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                . '<br>Verifique se o MySQL está activo no XAMPP e se importou <code>database/database.sql</code>.');
        }
        die('Serviço temporariamente indisponível.');
    }

    return $pdo;
}

/** Executa SELECT preparado e devolve todas as linhas. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Executa SELECT preparado e devolve a primeira linha (ou null). */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Executa INSERT/UPDATE/DELETE e devolve linhas afectadas. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Devolve o último ID inserido. */
function db_last_id(): int
{
    return (int) db()->lastInsertId();
}
