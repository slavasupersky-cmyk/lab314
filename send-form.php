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

$BOT_TOKEN = '8932052372:AAFKaYwq7wcvkDj2NYbkzL6k37jDdUFMDBE';
$CHAT_ID = '-1005546644786';

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

// Build message
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

$tg_url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";

$tg_data = [
    'chat_id' => $CHAT_ID,
    'text' => $text,
    'parse_mode' => 'Markdown'
];

$ch = curl_init($tg_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tg_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 200) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Telegram API error', 'details' => $curl_error, 'code' => $http_code]);
}
