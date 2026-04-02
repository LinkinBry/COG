<?php
// student/payment_callback.php  –  Xendit e-wallet webhook endpoint (POST only)
define('SKIP_TIMEOUT_CHECK', true);
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Xendit.php';

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method Not Allowed'; exit();
}

// Xendit sends the callback token in this header
$callbackToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
if (empty($callbackToken)) {
    http_response_code(400); echo 'Missing callback token'; exit();
}

if (!Xendit::verifyWebhook($callbackToken)) {
    http_response_code(401); echo 'Invalid callback token'; exit();
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400); echo 'Invalid JSON body'; exit();
}

/*
 * Xendit e-wallet webhook payload shape:
 * {
 *   "event": "ewallet.capture",
 *   "data": {
 *     "id": "ewc_xxxxx",
 *     "reference_id": "COG-20240101-0001",
 *     "status": "SUCCEEDED",
 *     "charge_amount": 100,
 *     "currency": "PHP",
 *     ...
 *   }
 * }
 */

$event  = $body['event']                  ?? '';
$data   = $body['data']                   ?? [];
$status = strtoupper($data['status']      ?? '');
$ref    = trim($data['reference_id']      ?? '');
$amount = $data['charge_amount']          ?? ($data['captured_amount'] ?? 0);
$chargeId = $data['id']                   ?? '';

// Only process successful charges
if ($status !== 'SUCCEEDED') {
    http_response_code(200); echo 'Acknowledged non-success event'; exit();
}
if (empty($ref)) {
    http_response_code(200); echo 'No reference_id'; exit();
}

$db = (new Database())->getConnection();

// Find matching unpaid COG request
$stmt = $db->prepare(
    "SELECT r.*, u.id AS uid
       FROM cog_requests r JOIN users u ON r.user_id = u.id
      WHERE r.request_number = :ref AND r.payment_status = 'unpaid'"
);
$stmt->execute([':ref' => $ref]);
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
                admin_notes    = CONCAT(IFNULL(admin_notes,''), ' [xendit_paid:', :cid, ']')
          WHERE id = :id"
    )->execute([':cid' => $chargeId, ':id' => $request['id']]);

    // Notify student
    $db->prepare(
        "INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)"
    )->execute([
        ':uid' => $request['uid'],
        ':rid' => $request['id'],
        ':msg' => "✅ GCash payment of ₱{$amount} confirmed for request {$ref}. Your COG is now being processed.",
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
    error_log("Xendit webhook DB error: " . $e->getMessage());
    http_response_code(500); echo 'Internal error';
}