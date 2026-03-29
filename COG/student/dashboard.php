<?php
// student/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to access the dashboard.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db       = $database->getConnection();
$user_id  = Session::get('user_id');

// Get user info
$user_stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$user_stmt->execute([':id' => $user_id]);
$user = $user_stmt->fetch();

if (!$user) { Session::destroy(); header("Location: ../index.php"); exit(); }

// COG requests
$req_stmt = $db->prepare("SELECT * FROM cog_requests WHERE user_id = :uid ORDER BY request_date DESC");
$req_stmt->execute([':uid' => $user_id]);
$requests = $req_stmt->fetchAll();

// Unread notifications
$notif_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND is_read = FALSE ORDER BY created_at DESC");
$notif_stmt->execute([':uid' => $user_id]);
$notifications = $notif_stmt->fetchAll();
$unread_count  = count($notifications);

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $mr = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :uid");
    $mr->execute([':id' => (int)$_GET['mark_read'], ':uid' => $user_id]);
    header("Location: dashboard.php");
    exit();
}

// Stats
$pending_count = $ready_count = $released_count = 0;
foreach ($requests as $r) {
    match ($r['status']) {
        'pending'  => $pending_count++,
        'ready'    => $ready_count++,
        'released' => $released_count++,
        default    => null,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard – COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: linear-gradient(135deg,#800000,#660000); }
        .sidebar { min-height:100vh; background:var(--primary); color:#fff; position:fixed; width:260px; }
        .sidebar a { color:rgba(255,255,255,.8); text-decoration:none; padding:12px 20px; display:block; transition:all .3s; border-radius:8px; margin:4px 10px; }
        .sidebar a:hover { background:rgba(255,255,255,.15); color:#fff; transform:translateX(5px); }
        .sidebar a.active { background:rgba(255,255,255,.2); color:#fff; border-left:4px solid #fff; font-weight:600; }
        .main-content { margin-left:260px; padding:30px; background:#f8f9fa; min-height:100vh; }
        .stat-card { background:#fff; border-radius:15px; padding:25px; box-shadow:0 5px 20px rgba(0,0,0,.08); transition:transform .3s,box-shadow .3s; position:relative; overflow:hidden; }
        .stat-card:hover { transform:translateY(-5px); box-shadow:0 8px 25px rgba(0,0,0,.15); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:var(--primary); }
        .stat-icon { font-size:2.5rem; opacity:.15; position:absolute; right:20px; top:50%; transform:translateY(-50%); }
        .status-badge { padding:6px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-pending    { background:#fff3cd; color:#856404; }
        .status-processing { background:#cce5ff; color:#004085; }
        .status-ready      { background:#d4edda; color:#155724; }
        .status-released   { background:#d1ecf1; color:#0c5460; }
        .notification-badge { background:#dc3545; color:#fff; border-radius:50%; padding:3px 7px; font-size:11px; margin-left:5px; }
        .card-header { background:#fff; border-bottom:2px solid #f0f0f0; padding:20px 25px; }
        .table th { color:#6c757d; font-weight:600; font-size:13px; text-transform:uppercase; letter-spacing:.5px; }
        .quick-action-card { background:#fff; border-radius:12px; padding:20px; border:1px solid #eee; transition:all .3s; }
        .quick-action-card:hover { border-color:#800000; }
        .btn-maroon { background:linear-gradient(135deg,#800000,#660000); border:none; color:#fff; }
        .btn-maroon:hover { opacity:.9; color:#fff; }
        .timeout-bar { height:3px; background:#800000; position:fixed; top:0; left:0; z-index:9999; transition:width 1s linear; }
    </style>
</head>
<body>
<!-- Session timeout progress bar -->
<div class="timeout-bar" id="timeoutBar" style="width:100%"></div>

<div class="sidebar">
    <div class="p-4">
        <h4 class="text-center mb-4 fw-bold">COG System</h4>
        <div class="text-center mb-4">
            <div class="bg-white bg-opacity-20 rounded-circle d-inline-block p-3 mb-2">
                <i class="bi bi-person-circle" style="font-size:3rem; color:#fff;"></i>
            </div>
            <h6 class="mt-2 fw-bold"><?= htmlspecialchars($user['full_name']) ?></h6>
            <small class="text-white-50"><?= htmlspecialchars($user['student_id']) ?></small>
        </div>
        <nav>
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2 me-3"></i>Dashboard</a>
            <a href="request_cog.php"><i class="bi bi-file-earmark-text me-3"></i>Request COG</a>
            <a href="my_requests.php"><i class="bi bi-list-check me-3"></i>My Requests</a>
            <a href="notifications.php">
                <i class="bi bi-bell me-3"></i>Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php"><i class="bi bi-person me-3"></i>Profile</a>
            <hr class="bg-white opacity-25 my-3">
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-3"></i>Logout</a>
        </nav>
    </div>
</div>

<div class="main-content">
    <?php $s = Session::getFlash('success'); if ($s): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($s) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php $e = Session::getFlash('error'); if ($e): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($e) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</h2>
            <p class="text-muted">Here's what's happening with your COG requests.</p>
        </div>
        <span class="badge bg-light text-dark p-3">
            <i class="bi bi-calendar3 me-2"></i><?= date('F d, Y') ?>
        </span>
    </div>

    <!-- Stats -->
    <div class="row mb-4 g-4">
        <div class="col-md-3">
            <div class="stat-card">
                <h6 class="text-muted mb-2">Total Requests</h6>
                <h3 class="fw-bold mb-0"><?= count($requests) ?></h3>
                <i class="bi bi-files stat-icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h6 class="text-muted mb-2">Pending</h6>
                <h3 class="fw-bold mb-0 text-warning"><?= $pending_count ?></h3>
                <i class="bi bi-hourglass-split stat-icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h6 class="text-muted mb-2">Ready for Pickup</h6>
                <h3 class="fw-bold mb-0 text-success"><?= $ready_count ?></h3>
                <i class="bi bi-check-circle stat-icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h6 class="text-muted mb-2">Released</h6>
                <h3 class="fw-bold mb-0 text-info"><?= $released_count ?></h3>
                <i class="bi bi-check-all stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Recent Requests Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Recent Requests</h5>
            <a href="request_cog.php" class="btn btn-maroon btn-sm">
                <i class="bi bi-plus-circle me-1"></i>New Request
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Request #</th><th>Date</th><th>Purpose</th><th>Copies</th>
                            <th>Amount</th><th>Status</th><th>Payment</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($requests, 0, 5) as $req): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($req['request_number']) ?></td>
                            <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($req['purpose'], 0, 30, '…')) ?></td>
                            <td><?= (int)$req['copies'] ?></td>
                            <td>₱<?= number_format($req['amount'], 2) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($req['status']) ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $req['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($req['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_request.php?id=<?= (int)$req['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <?php if ($req['payment_status'] === 'unpaid'): ?>
                                <a href="process_payment.php?id=<?= (int)$req['id'] ?>"
                                   class="btn btn-sm btn-success">
                                    <i class="bi bi-credit-card"></i> Pay
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                                <h6 class="text-muted">No requests yet</h6>
                                <a href="request_cog.php" class="btn btn-maroon btn-sm mt-2">Create Your First Request</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Info + Notifications -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="quick-action-card">
                <h5 class="fw-bold mb-4"><i class="bi bi-info-circle text-primary me-2"></i>Request Information</h5>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-clock me-2 text-primary"></i>Processing Time:</span>
                        <span class="fw-bold">2–3 working days</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-cash me-2 text-primary"></i>Fee:</span>
                        <span class="fw-bold">₱50.00 per copy</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-credit-card me-2 text-primary"></i>Payment:</span>
                        <span class="fw-bold">Online (GCash/card) or Cash</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-card-text me-2 text-primary"></i>Requirements:</span>
                        <span class="fw-bold">Valid ID, School ID</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="quick-action-card">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-bell text-primary me-2"></i>
                    Recent Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $unread_count ?> new</span>
                    <?php endif; ?>
                </h5>
                <?php if (!empty($notifications)): ?>
                    <?php foreach (array_slice($notifications, 0, 3) as $notif): ?>
                        <div class="alert alert-info alert-dismissible fade show position-relative" role="alert">
                            <div class="pe-4">
                                <?= htmlspecialchars($notif['message']) ?>
                                <small class="d-block text-muted mt-1">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                                </small>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?= (int)$notif['id'] ?>" class="stretched-link" title="Mark as read"></a>
                                <span class="position-absolute top-0 end-0 p-2">
                                    <span class="badge bg-danger rounded-pill">New</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($notifications) > 3): ?>
                        <div class="text-center mt-2">
                            <a href="notifications.php" class="btn btn-link">View All</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-bell-slash display-4 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-0">No new notifications</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Session timeout modal -->
<?php include '../includes/session_modal.php'; ?>
<script src="../assets/js/session-timeout.js"></script>

<!-- Timeout progress bar -->
<script>
(function () {
    const TOTAL = 30 * 60; // seconds
    const bar   = document.getElementById('timeoutBar');
    let remaining = <?= Session::getRemainingTime() ?>;

    function tick() {
        remaining = Math.max(0, remaining - 1);
        bar.style.width = ((remaining / TOTAL) * 100) + '%';
        bar.style.background = remaining < 120 ? '#dc3545' : (remaining < 300 ? '#ffc107' : '#800000');
    }
    setInterval(tick, 1000);
})();
</script>

<!-- Chatbot -->
<?php include '../includes/chatbot.php'; ?>

<script>
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(a => {
            try { new bootstrap.Alert(a).close(); } catch(e) {}
        });
    }, 5000);
</script>
</body>
</html>