<?php
// app/config.php
// Шаблон конфига. Коммитится в гит, секретов здесь НЕТ.
// Реальные креды лежат в config.local.php (в .gitignore).

declare(strict_types=1);

$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_replace_recursive([
    'db' => [
        // SQLite по умолчанию для локалки — файл в app/storage, который в .gitignore.
        // Для бесплатного хостинга с MySQL переопредели в config.local.php.
        'dsn'  => 'sqlite:' . __DIR__ . '/storage/app.sqlite',
        'user' => null,
        'pass' => null,
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],
    'security' => [
        'session_name'       => 'app_sid',
        'rate_limit_per_10m' => 5,   // заявок с одного IP за 10 минут
        'min_fill_seconds'   => 2,   // форма не может заполниться быстрее
        'idempotency_ttl'    => 30,  // окно дедупа, секунды
    ],
    'app' => [
        'env'   => 'production',
        'debug' => false,
    ],
], $local);
