<?php
declare(strict_types=1);

// Токен живёт в сессии. Ротации нет — для лендинга не нужна,
// токен умирает вместе с сессией.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// hash_equals — константное время, защита от timing-атак.
function csrf_validate(?string $token): bool
{
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
