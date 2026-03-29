<?php
// chatbot_api.php  –  proxies chat messages to Groq
define('SKIP_TIMEOUT_CHECK', true);
require_once 'config/session.php';
require_once 'config/hitpay.php'; // defines GROQ_API_KEY & GROQ_MODEL

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit();
}

$userMessage = trim(strip_tags($body['message']));
$history     = isset($body['history']) && is_array($body['history']) ? $body['history'] : [];

// Build message array for Groq
$systemPrompt = <<<'SYS'
You are COGBot, the friendly virtual assistant for OLSHCO's Certificate of Grades (COG) Management System.
Your role is to help students and administrators with questions about:
- How to request a COG (Certificate of Grades)
- Checking request status (pending, processing, ready, released)
- Payment process (₱50.00 per copy, paid at the Registrar's Office or online via GCash/cards)
- Processing time (2–3 working days)
- Requirements: valid ID and school ID when claiming
- How to use the system: login, submit request, view notifications, update profile

Keep answers concise (under 120 words) and friendly. 
If a student asks something outside COG / system scope, politely redirect them.
Always respond in the same language the user writes in (English or Filipino).
SYS;

$messages = [['role' => 'system', 'content' => $systemPrompt]];

// Include up to the last 6 exchanges from history
foreach (array_slice($history, -12) as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = [
            'role'    => in_array($msg['role'], ['user', 'assistant']) ? $msg['role'] : 'user',
            'content' => substr(strip_tags($msg['content']), 0, 500),
        ];
    }
}

$messages[] = ['role' => 'user', 'content' => $userMessage];

$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 300,
    'temperature' => 0.6,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . GROQ_API_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("Groq cURL error: $curlError");
    echo json_encode(['error' => 'Chat service temporarily unavailable.']);
    exit();
}

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
    echo json_encode(['reply' => trim($data['choices'][0]['message']['content'])]);
} else {
    error_log("Groq API error ($httpCode): $response");
    echo json_encode(['error' => 'Could not get a response. Please try again.']);
}