<?php
declare(strict_types=1);

// Разовый CLI-скрипт для записи токена и chat_id в БД.
// Запуск:  php app/bin/seed.php <bot_token> <chat_id>
// В вебе недоступен (проверка PHP_SAPI).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php app/bin/seed.php <bot_token> <chat_id>\n");
    exit(1);
}

$token  = $argv[1];
$chatId = $argv[2];

if (!preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $token)) {
    fwrite(STDERR, "WARN: token не похож на telegram bot token, продолжаю всё равно\n");
}

// Портативно: DELETE + INSERT вместо ON CONFLICT (SQLite) / ON DUPLICATE KEY (MySQL).
$pdo = db();
$pdo->prepare('DELETE FROM settings WHERE `key` = ?')->execute(['telegram_token']);
$pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute(['telegram_token', $token]);
$pdo->prepare('DELETE FROM settings WHERE `key` = ?')->execute(['telegram_chat_id']);
$pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute(['telegram_chat_id', $chatId]);

echo "OK: telegram_token и telegram_chat_id сохранены\n";
