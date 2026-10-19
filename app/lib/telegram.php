<?php
declare(strict_types=1);

// Обёртка над sendMessage. Возвращает ['ok' => bool, 'error' => string?]
// Никогда не бросает exception — вызывающая сторона сама решает, что делать.
function telegram_send(string $token, string $chatId, string $text): array
{
    // ...вроде бы правильно, я потом перепроверю
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    // текст, это то что пользователь написал в форме?
    $payload = http_build_query([
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => 'true',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'curl: ' . $curlError];
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode !== 200 || !is_array($decoded) || empty($decoded['ok'])) {
        $desc = is_array($decoded) && isset($decoded['description'])
            ? $decoded['description']
            : 'unknown';
        return ['ok' => false, 'error' => 'telegram: ' . $desc, 'http' => $httpCode];
    }

    return ['ok' => true];
}
