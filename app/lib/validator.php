<?php
declare(strict_types=1);

// Возвращает [clean, errors]. В clean — только прошедшие поля,
// в errors — map field => сообщение. Клиент покажет это под полем.
function validate_form(array $input): array
{
    $errors = [];
    $clean = [];

    // --- name ---
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Укажите имя';
    } elseif (mb_strlen($name, 'UTF-8') > 100) {
        $errors['name'] = 'Имя слишком длинное';
    } else {
        $clean['name'] = $name;
    }

    // --- phone ---
    // Не навязываем формат: чистим до цифр и смотрим длину.
    // Это покрывает +7 (999) 123-45-67 и 89991234567.
    // вообще-то НАВЯЗЫВАЕМ Беларуский формат +375 33 333 33 33
    // но да, дя телеграма надо чистить, чтобы удобно было звонить
    $phone = trim((string)($input['phone'] ?? ''));
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        $errors['phone'] = 'Некорректный телефон';
    } else {
        $clean['phone'] = $phone;
    }

    // --- message (опционально) ---
    $message = trim((string)($input['message'] ?? ''));
    if (mb_strlen($message, 'UTF-8') > 2000) {
        $errors['message'] = 'Сообщение слишком длинное';
    } else {
        $clean['message'] = $message;
    }

    return [$clean, $errors];
}
