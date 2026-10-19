<?php
declare(strict_types=1);

// Ленивый синглтон PDO + идемпотентная схема.
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = app_config()['db'];
    $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], $cfg['options']);

    if (strpos($cfg['dsn'], 'sqlite:') === 0) {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }

    db_ensure_schema($pdo);
    return $pdo;
}

function db_ensure_schema(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1 FROM settings LIMIT 1');
        $pdo->query('SELECT 1 FROM submissions LIMIT 1');
        return;
    } catch (PDOException $e) {
        // схемы нет — создаём
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS submissions (
                submission_id TEXT PRIMARY KEY,
                created_at    INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_sub_created ON submissions(created_at);
        ");
    } elseif ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                `key`  VARCHAR(64) PRIMARY KEY,
                `value` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS submissions (
                submission_id CHAR(36) PRIMARY KEY,
                created_at    INT UNSIGNED NOT NULL,
                INDEX idx_sub_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        throw new RuntimeException('Unsupported DB driver: ' . $driver);
    }
}

function settings_get(array $keys): array
{
    if (!$keys) return [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = db()->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($placeholders)");
    $stmt->execute(array_values($keys));
    $out = [];
    foreach ($stmt as $row) {
        $out[$row['key']] = $row['value'];
    }
    return $out;
}
