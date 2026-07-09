<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Секреты вынесены в отдельный не-гитовый файл, лежащий рядом на сервере.
$configPath = __DIR__ . '/config.local.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server not configured (config.local.php missing)']);
    exit;
}
require $configPath;

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid data']);
    exit;
}

// Sanitize inputs
$channel = htmlspecialchars($input['channel'] ?? 'Телефон', ENT_QUOTES, 'UTF-8');
$contact = htmlspecialchars($input['contact'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($input['email'] ?? '', ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($input['message'] ?? '', ENT_QUOTES, 'UTF-8');
$page = htmlspecialchars($input['page'] ?? '', ENT_QUOTES, 'UTF-8');
$consent_news = !empty($input['consentNews']) ? 'Да' : 'Нет';

if (empty($contact)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Contact required']);
    exit;
}

$amoOk = false;
$amoError = null;

// ---------- 1) amoCRM: создаём сделку + контакт (основной, надёжный канал) ----------
if (defined('AMO_TOKEN') && defined('AMO_SUBDOMAIN')) {
    $contactFields = [];

    // Канал связи определяет, куда класть контакт и с каким enum-ом,
    // чтобы Телефон/WhatsApp/Telegram не сваливались в одно и то же поле.
    $isEmailLike = (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $contact);

    if ($isEmailLike) {
        $contactFields[] = [
            'field_code' => 'EMAIL',
            'values' => [['value' => $contact, 'enum_code' => 'WORK']],
        ];
    } else {
        $phoneEnum = 'WORK';
        if ($channel === 'WhatsApp') {
            $phoneEnum = 'MOB';
        } elseif ($channel === 'Telegram') {
            $phoneEnum = 'OTHER';
        }
        $contactFields[] = [
            'field_code' => 'PHONE',
            'values' => [['value' => $contact, 'enum_code' => $phoneEnum]],
        ];
    }
    if (!empty($email) && $email !== $contact) {
        $contactFields[] = [
            'field_code' => 'EMAIL',
            'values' => [['value' => $email, 'enum_code' => 'WORK']],
        ];
    }

    // Человекочитаемый источник для названия сделки
    $sourceLabel = '';
    if (strpos($page, 'couture') !== false) {
        $sourceLabel = ' · Couture 3.14';
    } elseif (strpos($page, 'start') !== false) {
        $sourceLabel = ' · Start';
    } elseif (!empty($page) && $page !== '/' && $page !== '/index.html') {
        $sourceLabel = ' · ' . trim($page, '/');
    }

    $leadPayload = [[
        'name' => "Заявка с сайта — {$channel}{$sourceLabel}",
        '_embedded' => [
            'contacts' => [[
                'first_name' => $contact,
                'custom_fields_values' => $contactFields,
            ]],
        ],
    ]];

    $amoCh = curl_init("https://" . AMO_SUBDOMAIN . "/api/v4/leads/complex");
    curl_setopt($amoCh, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($amoCh, CURLOPT_POSTFIELDS, json_encode($leadPayload));
    curl_setopt($amoCh, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AMO_TOKEN,
    ]);
    curl_setopt($amoCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($amoCh, CURLOPT_TIMEOUT, 10);

    $amoResult = curl_exec($amoCh);
    $amoHttpCode = curl_getinfo($amoCh, CURLINFO_HTTP_CODE);
    $amoCurlError = curl_error($amoCh);
    curl_close($amoCh);

    if ($amoHttpCode === 200 || $amoHttpCode === 200 + 0) {
        $amoData = json_decode($amoResult, true);
        // /leads/complex возвращает плоский массив [{id:..}], обычный /leads — _embedded.leads
        $leadId = $amoData[0]['id']
            ?? ($amoData['_embedded']['leads'][0]['id'] ?? null);
        $amoOk = true;

        // Доп. заметка к сделке со всеми деталями формы
        if ($leadId) {
            $noteText = "Способ связи: {$channel}\nКонтакт: {$contact}\n";
            if (!empty($email)) {
                $noteText .= "Email: {$email}\n";
            }
            if (!empty($message)) {
                $noteText .= "Сообщение: {$message}\n";
            }
            $noteText .= "Страница: {$page}\nПодписка на новости: {$consent_news}";

            $notePayload = [[
                'note_type' => 'common',
                'params' => ['text' => $noteText],
            ]];
            $noteCh = curl_init("https://" . AMO_SUBDOMAIN . "/api/v4/leads/{$leadId}/notes");
            curl_setopt($noteCh, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($noteCh, CURLOPT_POSTFIELDS, json_encode($notePayload));
            curl_setopt($noteCh, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AMO_TOKEN,
            ]);
            curl_setopt($noteCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($noteCh, CURLOPT_TIMEOUT, 10);
            curl_exec($noteCh);
            curl_close($noteCh);
        }
    } else {
        $amoError = ['details' => $amoCurlError, 'code' => $amoHttpCode, 'body' => $amoResult];
    }
}

// ---------- 2) Telegram: доп. уведомление, best-effort, не блокирует ответ ----------
$tgOk = false;
if (defined('BOT_TOKEN') && defined('CHAT_ID')) {
    $text = "🏠 *Новая заявка с 314lab.ru*\n\n";
    $text .= "📍 Страница: {$page}\n";
    $text .= "📞 Способ связи: {$channel}\n";
    $text .= "✉️ Контакт: `{$contact}`\n";
    if (!empty($email)) {
        $text .= "📧 Email: {$email}\n";
    }
    if (!empty($message)) {
        $text .= "\n💬 Сообщение:\n{$message}\n";
    }
    $text .= "\n📨 Подписка на новости: {$consent_news}";

    $tgCh = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage");
    curl_setopt($tgCh, CURLOPT_POST, true);
    curl_setopt($tgCh, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => CHAT_ID,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ]));
    curl_setopt($tgCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($tgCh, CURLOPT_TIMEOUT, 4); // короткий таймаут — это не критичный канал
    curl_setopt($tgCh, CURLOPT_CONNECTTIMEOUT, 3);
    $tgResult = curl_exec($tgCh);
    $tgHttpCode = curl_getinfo($tgCh, CURLINFO_HTTP_CODE);
    curl_close($tgCh);
    $tgOk = ($tgHttpCode === 200);
}

// ---------- Ответ фронтенду ----------
if ($amoOk || $tgOk) {
    echo json_encode(['ok' => true, 'amo' => $amoOk, 'telegram' => $tgOk]);
} else {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Both amoCRM and Telegram delivery failed',
        'amo_error' => $amoError,
    ]);
}
