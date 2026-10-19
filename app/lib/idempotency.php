<?php
declare(strict_types=1);

// true = можно отправлять, false = это дубль за окно TTL.
// Атомарность — за счёт PRIMARY KEY на submission_id: два параллельных
// запроса с одним UUID не пройдут оба INSERT, второй словит PDOException.
function idempotency_check(string $submissionId, string $phone, int $ttl): bool
{
    $pdo = db();
    $now = time();

    // Чистим старые
    $pdo->prepare('DELETE FROM submissions WHERE created_at < ?')->execute([$now - $ttl]);

    // Проверяем, был ли этот телефон за последние 10 минут
    $stmt = $pdo->prepare('SELECT 1 FROM submissions WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        return false; // Дубль по номеру!
    }

    // Записываем новый
    try {
        $pdo->prepare('INSERT INTO submissions (submission_id, phone, created_at) VALUES (?, ?, ?)')
            ->execute([$submissionId, $phone, $now]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return false;
        throw $e;
    }
}

function idempotency_release(string $submissionId): void
{
    db()->prepare('DELETE FROM submissions WHERE submission_id = ?')->execute([$submissionId]);
}
