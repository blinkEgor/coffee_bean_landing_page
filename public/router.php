<?php
declare(strict_types=1);

// Для php -S 127.0.0.1:8080 -t public public/router.php
// Отдаём статику как есть, а /api/* проксируем вручную — чтобы не поднимать Apache до теста.

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// /api/token → ../api/token.php
// /api/send  → ../api/send.php
if (preg_match('#^/api/(token|send)$#', $uri, $m)) {
    require __DIR__ . '/../api/' . $m[1] . '.php';
    return true;
}

// Статика: если файл есть в public/ — отдаём, иначе 404.
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/') {
    require __DIR__ . '/index.html';
    return true;
}

http_response_code(404);
echo '404';
