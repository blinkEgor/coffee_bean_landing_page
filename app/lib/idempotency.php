<?php
declare(strict_types=1);

// true = можно отправлять, false = это дубль за окно TTL.
// Атомарность — за счёт PRIMARY KEY на submission_id: два параллельных
// запроса с одним UUID не пройдут оба INSERT, второй словит PDOException.
function idempotency_check(string $submissionId, int $ttl): bool
{
    $pdo = db();
    $now = time();

    // Чистка старых записей: дешёвый DELETE по индексу, раз в запрос.
    $pdo->prepare('DELETE FROM submissions WHERE created_at < ?')
        ->execute([$now - $ttl]);

    try {
        $pdo->prepare('INSERT INTO submissions (submission_id, created_at) VALUES (?, ?)')
            ->execute([$submissionId, $now]);
        return true;
    } catch (PDOException $e) {
        // UNIQUE violation = дубликат. SQLSTATE 23000 у MySQL и SQLite одинаковый.
        if ($e->getCode() === '23000') {
            return false;
        }
        throw $e;
    }
}

// Если Телеграм упал, освобождаем UUID, чтобы клиент мог повторить отправку.
function idempotency_release(string $submissionId): void
{
    db()->prepare('DELETE FROM submissions WHERE submission_id = ?')->execute([$submissionId]);
}
