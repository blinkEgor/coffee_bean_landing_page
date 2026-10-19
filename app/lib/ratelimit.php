<?php
declare(strict_types=1);

// Файловый счётчик по IP. Окно — скользящее не нужно, фиксированное хватает.
// Не идеально (раса на параллельных запросах даст пару лишних), но для
// лендинга разница между 5 и 7 заявками некритична. Если нужно строго —
// переделать на flock или БД.
function ratelimit_check(string $ip, int $limit, int $window): bool
{
    $dir = __DIR__ . '/../storage/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    // Хешируем IP: не светим реальные адреса в именах файлов.
    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $data = ['count' => 0, 'reset' => $now + $window];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $parsed = $raw ? json_decode($raw, true) : null;
        if (is_array($parsed) && isset($parsed['reset'], $parsed['count'])) {
            $data = $parsed;
        }
    }

    // Окно истекло — сбрасываем.
    if ($now >= (int)$data['reset']) {
        $data = ['count' => 0, 'reset' => $now + $window];
    }

    if ((int)$data['count'] >= $limit) {
        return false;
    }

    $data['count'] = (int)$data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}
