<?php
// student/process_payment.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/HitPay.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to process payment.');
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Session::setFlash('error', 'Invalid request.');
    header("Location: my_requests.php");
    exit();
}

$database  = new Database();
$db        = $database->getConnection();
$user_id   = Session::get('user_id');
$request_id = (int)$_GET['id'];

// Fetch the COG request – must belong to this student and be unpaid
$q    = "SELECT r.*, u.email, u.full_name, u.student_id AS sid
          FROM cog_requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.id = :rid AND r.user_id = :uid AND r.payment_status = 'unpaid'";
$stmt = $db->prepare($q);
$stmt->execute([':rid' => $request_id, ':uid' => $user_id]);
$request = $stmt->fetch();

if (!$request) {
    Session::setFlash('error', 'Request not found or already paid.');
    header("Location: my_requests.php");
    exit();
}

// Create HitPay payment request
$result = HitPay::createPayment(
    (float)$request['amount'],
    $request['request_number'],
    $request['email'],
    $request['full_name'],
    '',
    'COG Request – ' . $request['request_number'] . ' (' . $request['copies'] . ' cop' . ($request['copies'] > 1 ? 'ies' : 'y') . ')'
);

if (isset($result['error'])) {
    Session::setFlash('error', 'Payment gateway error: ' . $result['error']);
    header("Location: view_request.php?id=$request_id");
    exit();
}

// Store the HitPay payment_request_id in the DB so the webhook can match it
$upd = "UPDATE cog_requests
           SET admin_notes = CONCAT(IFNULL(admin_notes,''), '[hitpay_id:', :hid, ']')
         WHERE id = :id";
$updStmt = $db->prepare($upd);
$updStmt->execute([':hid' => $result['payment_id'], ':id' => $request_id]);

// Redirect student to HitPay checkout (GCash / cards / etc.)
header("Location: " . $result['url']);
exit();