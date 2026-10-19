<?php
declare(strict_types=1);

// Глобальный конфиг. config.local.php подмешивается через config.php.
$GLOBALS['APP_CONFIG'] = require __DIR__ . '/config.php';

function app_config(): array
{
    return $GLOBALS['APP_CONFIG'];
}

// Ошибки: локально показываем, на проде только в лог.
error_reporting(E_ALL);
ini_set('display_errors', app_config()['app']['debug'] ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');

// Сессию стартуем только в вебе. Для CLI (seed.php) она не нужна.
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name(app_config()['security']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax', // Lax: форма работает, CSRF-атаки отрезаны
    ]);
    session_start();
}

// Явные require вместо автолоадера: файлов мало, видно все зависимости сверху.
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/ratelimit.php';
require_once __DIR__ . '/lib/idempotency.php';
require_once __DIR__ . '/lib/validator.php';
require_once __DIR__ . '/lib/telegram.php';
