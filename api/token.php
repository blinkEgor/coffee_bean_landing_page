<?php
declare(strict_types=1);

// Выдаёт CSRF-токен фронту. GET, без параметров.
// Сессия стартует в bootstrap, cookie уезжает клиенту.
// Никаких CORS-заголовков: работает только same-origin, что нам и нужно.

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

echo json_encode(['ok' => true, 'token' => csrf_token()], JSON_UNESCAPED_UNICODE);
