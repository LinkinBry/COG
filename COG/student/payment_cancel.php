<?php
// student/payment_cancel.php
require_once '../config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background:#f8f9fa; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .pay-card { max-width:440px; width:100%; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,.12); }
        .icon-circle { width:90px; height:90px; background:#f8d7da; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
        .btn-maroon { background:linear-gradient(135deg,#800000,#660000); color:#fff; border:none; }
    </style>
</head>
<body>
<div class="card pay-card p-5 text-center">
    <div class="icon-circle">
        <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem;"></i>
    </div>
    <h3 class="fw-bold mb-2">Payment Cancelled</h3>
    <p class="text-muted">
        You cancelled the payment. Your COG request is still pending.<br>
        You can retry payment anytime from <strong>My Requests</strong>.
    </p>
    <div class="d-grid gap-2 mt-4">
        <a href="my_requests.php" class="btn btn-maroon rounded-pill">
            <i class="bi bi-list-check me-2"></i>Go to My Requests
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill">Back to Dashboard</a>
    </div>
</div>
</body>
</html>