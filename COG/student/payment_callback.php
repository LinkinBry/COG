<?php
// student/payment_callback.php  –  HitPay webhook (POST)
define('SKIP_TIMEOUT_CHECK', true);
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/HitPay.php';

// HitPay sends the signature in the X-HITPAY-SIGNATURE header
$hmac = $_SERVER['HTTP_X_HITPAY_SIGNATURE'] ?? '';

if (empty($hmac)) {
    http_response_code(400);
    echo 'Missing signature';
    exit();
}

// Verify HMAC
if (!HitPay::verifyWebhook($_POST, $hmac)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit();
}

$status    = $_POST['status']           ?? '';
$reference = $_POST['reference_number'] ?? '';
$amount    = $_POST['amount']           ?? 0;
$currency  = $_POST['currency']         ?? '';
$paymentId = $_POST['payment_request_id'] ?? '';

// Only process completed payments
if ($status !== 'completed') {
    http_response_code(200);
    echo 'Acknowledged';
    exit();
}

if (empty($reference)) {
    http_response_code(200);
    echo 'No reference';
    exit();
}

$database = new Database();
$db       = $database->getConnection();

// Find the matching COG request
$stmt = $db->prepare(
    "SELECT r.*, u.id AS uid FROM cog_requests r
      JOIN users u ON r.user_id = u.id
     WHERE r.request_number = :ref AND r.payment_status = 'unpaid'"
);
$stmt->execute([':ref' => $reference]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(200);
    echo 'Already processed or not found';
    exit();
}

$db->beginTransaction();
try {
    // Mark as paid
    $upd = $db->prepare(
        "UPDATE cog_requests
            SET payment_status = 'paid',
                payment_date   = NOW(),
                status         = CASE WHEN status = 'pending' THEN 'processing' ELSE status END,
                admin_notes    = CONCAT(IFNULL(admin_notes,''), ' [paid_via_hitpay:', :pid, ']')
          WHERE id = :id"
    );
    $upd->execute([':pid' => $paymentId, ':id' => $request['id']]);

    // Notify the student
    $notif = $db->prepare(
        "INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)"
    );
    $notif->execute([
        ':uid' => $request['uid'],
        ':rid' => $request['id'],
        ':msg' => "✅ Payment confirmed for request {$reference} (₱{$amount}). Your request is now being processed.",
    ]);

    // Log status change
    $log = $db->prepare(
        "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by)
         VALUES (:rid, :old, 'processing', NULL)"
    );
    $log->execute([':rid' => $request['id'], ':old' => $request['status']]);

    $db->commit();
    http_response_code(200);
    echo 'OK';
} catch (Exception $e) {
    $db->rollBack();
    error_log("Webhook DB error: " . $e->getMessage());
    http_response_code(500);
    echo 'DB error';
}