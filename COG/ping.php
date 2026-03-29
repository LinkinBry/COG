<?php
// ping.php  –  called by session-timeout.js to keep the session alive
define('SKIP_TIMEOUT_CHECK', true);
require_once 'config/session.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
    exit();
}

Session::refreshActivity();
echo json_encode([
    'status'    => 'ok',
    'remaining' => Session::getRemainingTime(),
]);