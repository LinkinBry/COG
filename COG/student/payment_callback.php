<?php
// student/payment_callback.php  –  HitPay webhook endpoint (POST only)
define('SKIP_TIMEOUT_CHECK', true);
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/HitPay.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method Not Allowed'; exit();
}

$hmac = $_SERVER['HTTP_X_HITPAY_SIGNATURE'] ?? '';
if (empty($hmac)) {
    http_response_code(400); echo 'Missing signature'; exit();
}

if (!HitPay::verifyWebhook($_POST, $hmac)) {
    http_response_code(401); echo 'Invalid signature'; exit();
}

$status    = $_POST['status']             ?? '';
$reference = trim($_POST['reference_number'] ?? '');
$amount    = $_POST['amount']             ?? '0';
$paymentId = $_POST['payment_request_id'] ?? '';

// Only process completed payments
if ($status !== 'completed') {
    http_response_code(200); echo 'Acknowledged'; exit();
}
if (empty($reference)) {
    http_response_code(200); echo 'No reference'; exit();
}

$db = (new Database())->getConnection();

// Find matching unpaid COG request
$stmt = $db->prepare(
    "SELECT r.*, u.id AS uid
       FROM cog_requests r JOIN users u ON r.user_id = u.id
      WHERE r.request_number = :ref AND r.payment_status = 'unpaid'"
);
$stmt->execute([':ref' => $reference]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(200); echo 'Already processed or not found'; exit();
}

$db->beginTransaction();
try {
    // Mark paid; auto-advance status from pending → processing
    $db->prepare(
        "UPDATE cog_requests
            SET payment_status = 'paid',
                payment_date   = NOW(),
                status         = CASE WHEN status = 'pending' THEN 'processing' ELSE status END,
                admin_notes    = CONCAT(IFNULL(admin_notes,''), ' [paid_via_hitpay:', :pid, ']')
          WHERE id = :id"
    )->execute([':pid' => $paymentId, ':id' => $request['id']]);

    // Notify student
    $db->prepare(
        "INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)"
    )->execute([
        ':uid' => $request['uid'],
        ':rid' => $request['id'],
        ':msg' => "✅ Payment of ₱{$amount} confirmed for request {$reference}. Your COG is now being processed.",
    ]);

    // Log status change
    $db->prepare(
        "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by)
         VALUES (:rid, :old, 'processing', NULL)"
    )->execute([':rid' => $request['id'], ':old' => $request['status']]);

    $db->commit();
    http_response_code(200); echo 'OK';
} catch (Exception $e) {
    $db->rollBack();
    error_log("HitPay webhook DB error: " . $e->getMessage());
    http_response_code(500); echo 'Internal error';
}