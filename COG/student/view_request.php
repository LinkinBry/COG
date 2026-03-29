<?php
// student/view_request.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to view request details.');
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Session::setFlash('error', 'Invalid request ID.');
    header("Location: my_requests.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');
$request_id = (int)$_GET['id'];

// Get request details
$query = "SELECT r.*, u.full_name, u.student_id, u.email, u.course, u.year_level 
          FROM cog_requests r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.id = :request_id AND r.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    Session::setFlash('error', 'Request not found.');
    header("Location: my_requests.php");
    exit();
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Get status history (if you have a status_history table)
$history_query = "SELECT * FROM request_status_history WHERE request_id = :request_id ORDER BY created_at DESC";
$history_stmt = $db->prepare($history_query);
$history_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
$history_stmt->execute();
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            position: fixed;
            width: 260px;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            padding-left: 30px;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-released { background: #d1ecf1; color: #0c5460; }
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 500;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #800000;
        }
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -16.5px;
            top: 15px;
            width: 2px;
            height: calc(100% - 15px);
            background: #e9ecef;
        }
        .timeline-item:last-child:after {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">COG System</h4>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="request_cog.php"><i class="bi bi-file-earmark-text me-2"></i>Request COG</a>
                <a href="my_requests.php"><i class="bi bi-list-check me-2"></i>My Requests</a>
                <a href="notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a>
                <a href="profile.php"><i class="bi bi-person me-2"></i>Profile</a>
                <hr class="bg-white opacity-25">
                <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Request Details</h2>
            <a href="my_requests.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Requests
            </a>
        </div>

        <div class="detail-card">
            <!-- Header with Status -->
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h4 class="mb-2">Request #<?php echo htmlspecialchars($request['request_number']); ?></h4>
                    <p class="text-muted">Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($request['request_date'])); ?></p>
                </div>
                <div>
                    <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?> me-2">
                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                    </span>
                    <span class="badge bg-<?php echo $request['payment_status'] == 'paid' ? 'success' : 'warning'; ?> p-2">
                        Payment: <?php echo ucfirst(htmlspecialchars($request['payment_status'])); ?>
                    </span>
                </div>
            </div>

            <div class="row">
                <!-- Student Information -->
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="bi bi-person-circle text-primary me-2"></i>Student Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="info-label">Full Name</td>
                            <td class="info-value"><?php echo htmlspecialchars($request['full_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Student ID</td>
                            <td class="info-value"><?php echo htmlspecialchars($request['student_id']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Email</td>
                            <td class="info-value"><?php echo htmlspecialchars($request['email']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Course</td>
                            <td class="info-value"><?php echo htmlspecialchars($request['course']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Year Level</td>
                            <td class="info-value"><?php echo (int)$request['year_level']; ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Request Details -->
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="bi bi-file-text text-primary me-2"></i>Request Details</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="info-label">Purpose</td>
                            <td class="info-value"><?php echo htmlspecialchars($request['purpose']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Number of Copies</td>
                            <td class="info-value"><?php echo (int)$request['copies']; ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Amount</td>
                            <td class="info-value text-success">₱<?php echo number_format($request['amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Payment Status</td>
                            <td class="info-value">
                                <span class="badge bg-<?php echo $request['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($request['payment_status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($request['payment_status'] == 'paid' && !empty($request['payment_date'])): ?>
                        <tr>
                            <td class="info-label">Payment Date</td>
                            <td class="info-value"><?php echo date('F d, Y', strtotime($request['payment_date'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="mt-4">
                <h5 class="mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Status Timeline</h5>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="fw-bold">Request Submitted</div>
                        <small class="text-muted"><?php echo date('F d, Y h:i A', strtotime($request['request_date'])); ?></small>
                    </div>
                    
                    <?php if ($request['status'] != 'pending'): ?>
                    <div class="timeline-item">
                        <div class="fw-bold">Processing Started</div>
                        <small class="text-muted">Processing your request</small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['status'] == 'ready' || $request['status'] == 'released'): ?>
                    <div class="timeline-item">
                        <div class="fw-bold text-success">Ready for Pickup</div>
                        <small class="text-muted">Your COG is ready to be claimed</small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['status'] == 'released'): ?>
                    <div class="timeline-item">
                        <div class="fw-bold text-primary">Released</div>
                        <small class="text-muted">Document has been claimed</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 pt-3 border-top">
                <?php if ($request['payment_status'] == 'unpaid'): ?>
                <a href="process_payment.php?id=<?php echo $request_id; ?>" class="btn btn-success">
                    <i class="bi bi-cash me-2"></i>Process Payment
                </a>
                <?php endif; ?>
                
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print Details
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>