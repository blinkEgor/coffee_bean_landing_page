<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function json_out(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 0. Только POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

// 1. CSRF.
if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    json_out(403, ['ok' => false, 'error' => 'csrf']);
}

// 2. Honeypot: скрытое поле, которое человек не заполнит.
// Отвечаем успехом, чтобы бот не догадался, что его раскусили.
if (!empty($_POST['website'])) {
    json_out(200, ['ok' => true]);
}

// 3. Минимальное время заполнения: боты вбивают форму мгновенно.
$formTs  = (int)($_POST['form_ts'] ?? 0);
$minFill = (int)app_config()['security']['min_fill_seconds'];
if ($formTs <= 0 || (time() - $formTs) < $minFill) {
    json_out(429, ['ok' => false, 'error' => 'too_fast']);
}

// 4. Rate limit по IP. X-Forwarded-For НЕ доверяем — его легко подделать,
// если нет доверенного прокси перед PHP. На шаред-хостинге REMOTE_ADDR — то что надо.
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limit = (int)app_config()['security']['rate_limit_per_10m'];
if (!ratelimit_check($ip, $limit, 600)) {
    json_out(429, ['ok' => false, 'error' => 'rate_limit']);
}

// 5. Валидация полей. (Идет перед проверкой дублей, чтобы получить чистый телефон)
[$clean, $errors] = validate_form($_POST);
if ($errors) {
    // Если данные битые, мы даже не трогаем базу. Откатывать нечего.
    json_out(422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
}

// 6. Idempotency по UUID и телефону. Защита от двойного клика и дублей по номеру.
$submissionId = trim((string)($_POST['submission_id'] ?? ''));
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $submissionId)) {
    json_out(400, ['ok' => false, 'error' => 'bad_submission_id']);
}
$ttl = (int)app_config()['security']['idempotency_ttl'];
// Передаем телефон из массива $clean
if (!idempotency_check($submissionId, $clean['phone'], $ttl)) {
    // Дубль в окне TTL — считаем успехом: клиенту важно знать, что заявка есть.
    json_out(200, ['ok' => true, 'duplicate' => true]);
}

// 7. UTM-метки
$utmSource = htmlspecialchars($_POST['utm_source'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$utmMedium = htmlspecialchars($_POST['utm_medium'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

// 8. Достаём креды из БД
$creds = settings_get(['telegram_token', 'telegram_chat_id']);
if (empty($creds['telegram_token']) || empty($creds['telegram_chat_id'])) {
    idempotency_release($submissionId);
    error_log('send.php: telegram creds not configured; run app/bin/seed.php');
    json_out(500, ['ok' => false, 'error' => 'not_configured']);
}

// 9. Сообщение. ВСЕ пользовательские поля — через htmlspecialchars:
// parse_mode=HTML, значит <, >, & в поле имени сломают разметку без экранирования.
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$text = "<b>🟢 Новая заявка</b>\n\n"
      . "<b>Имя:</b> {$esc($clean['name'])}\n"
      . "<b>Телефон:</b> {$esc($clean['phone'])}\n"
      . "<b>Тип:</b> {$esc($clean['request_type'])}\n";
      
if ($utmSource) $text .= "<b>Источник:</b> {$utmSource} / {$utmMedium}\n";

if ($clean['message'] !== '') {
    $text .= "<b>Сообщение:</b>\n{$esc($clean['message'])}\n";
}

// 10. Отправка.
$result = telegram_send($creds['telegram_token'], $creds['telegram_chat_id'], $text);
if (!$result['ok']) {
    error_log('send.php: telegram failed: ' . ($result['error'] ?? 'unknown'));
    
    // ВАЖНО: откатываем UUID, иначе заявка навсегда застрянет как "дубль"
    idempotency_release($submissionId);
    
    json_out(502, ['ok' => false, 'error' => 'telegram_failed']);
}

json_out(200, ['ok' => true]);
