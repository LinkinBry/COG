<?php
// student/payment_success.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');
$ref     = trim($_GET['ref'] ?? '');
$request = null;

if ($ref) {
    $stmt = $db->prepare("SELECT * FROM cog_requests WHERE request_number=:ref AND user_id=:uid");
    $stmt->execute([':ref' => $ref, ':uid' => $user_id]);
    $request = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Submitted – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background:#f8f9fa; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .pay-card { max-width:480px; width:100%; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,.12); }
        .icon-circle { width:90px; height:90px; background:#d4edda; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
        .btn-maroon { background:linear-gradient(135deg,#800000,#660000); color:#fff; border:none; }
    </style>
</head>
<body>
<div class="card pay-card p-5 text-center">
    <div class="icon-circle">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
    </div>
    <h3 class="fw-bold mb-2">Payment Submitted!</h3>
    <p class="text-muted">
        Your payment for
        <strong><?= $request ? htmlspecialchars($request['request_number']) : 'your COG request' ?></strong>
        has been received by the gateway. We'll update your payment status once verified.
    </p>

    <?php if ($request): ?>
    <div class="alert alert-info text-start mt-3 rounded-3">
        <strong>Request:</strong> <?= htmlspecialchars($request['request_number']) ?><br>
        <strong>Amount:</strong> ₱<?= number_format($request['amount'], 2) ?><br>
        <strong>Status:</strong>
        <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
            <?= ucfirst($request['payment_status']) ?>
        </span>
    </div>
    <?php endif; ?>

    <div class="d-grid gap-2 mt-4">
        <a href="my_requests.php" class="btn btn-maroon rounded-pill">
            <i class="bi bi-list-check me-2"></i>View My Requests
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill">Back to Dashboard</a>
    </div>
</div>
</body>
</html>