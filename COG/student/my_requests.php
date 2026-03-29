<?php
// student/my_requests.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to view your requests.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query with search filters
$where_conditions = ["user_id = :user_id"];
$params = [':user_id' => $user_id];

// Search by request number or purpose
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where_conditions[] = "(request_number LIKE :search OR purpose LIKE :search)";
    $params[':search'] = $search;
}

// Filter by status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $_GET['status'];
}

// Filter by payment status
if (isset($_GET['payment']) && !empty($_GET['payment'])) {
    $where_conditions[] = "payment_status = :payment";
    $params[':payment'] = $_GET['payment'];
}

// Filter by date range
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where_conditions[] = "DATE(request_date) >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where_conditions[] = "DATE(request_date) <= :date_to";
    $params[':date_to'] = $_GET['date_to'];
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM cog_requests $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_requests = $count_stmt->fetchColumn();
$total_pages = ceil($total_requests / $limit);

// Get requests with pagination
$query = "SELECT * FROM cog_requests 
          $where_clause 
          ORDER BY request_date DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for display
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
                SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
                FROM cog_requests WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - COG Management System</title>
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
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            padding-left: 30px;
        }
        .sidebar a.active {
            border-left: 4px solid white;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-released { background: #d1ecf1; color: #0c5460; }
        
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .number {
            font-size: 24px;
            font-weight: bold;
        }
        .stats-card .label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .filter-badge {
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        .filter-badge i {
            cursor: pointer;
            margin-left: 5px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pagination .page-link {
            color: maroon;
        }
        .pagination .active .page-link {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border-color: transparent;
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,0,0,0.4);
        }
        .clear-filters {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .clear-filters:hover {
            text-decoration: underline;
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
                <a href="my_requests.php" class="active"><i class="bi bi-list-check me-2"></i>My Requests</a>
                <a href="notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a>
                <a href="profile.php"><i class="bi bi-person me-2"></i>Profile</a>
                <hr class="bg-white opacity-25">
                <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Flash Messages -->
        <?php $success = Session::getFlash('success'); ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">My Requests</h2>
                <p class="text-muted">View and track all your COG requests</p>
            </div>
            <a href="request_cog.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>New Request
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="number"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Requests</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <div class="number"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card" style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%);">
                    <div class="number"><?php echo $stats['processing']; ?></div>
                    <div class="label">Processing</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="number"><?php echo $stats['ready']; ?></div>
                    <div class="label">Ready</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card" style="background: linear-gradient(135deg, #6c757d 0%, #343a40 100%);">
                    <div class="number"><?php echo $stats['released']; ?></div>
                    <div class="label">Released</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                    <div class="number"><?php echo $stats['paid']; ?></div>
                    <div class="label">Paid</div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Card -->
        <div class="search-card">
            <h5 class="mb-3"><i class="bi bi-search me-2"></i>Search & Filter Requests</h5>
            
            <form method="GET" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search by request # or purpose..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <small class="text-muted">Search in request number and purpose</small>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="ready" <?php echo (isset($_GET['status']) && $_GET['status'] == 'ready') ? 'selected' : ''; ?>>Ready</option>
                            <option value="released" <?php echo (isset($_GET['status']) && $_GET['status'] == 'released') ? 'selected' : ''; ?>>Released</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Payment</label>
                        <select name="payment" class="form-select">
                            <option value="">All Payments</option>
                            <option value="unpaid" <?php echo (isset($_GET['payment']) && $_GET['payment'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="paid" <?php echo (isset($_GET['payment']) && $_GET['payment'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                    </div>
                    
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-2"></i>Apply Filters
                        </button>
                        <a href="my_requests.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Clear All
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filters Display -->
            <?php 
            $active_filters = [];
            if (!empty($_GET['search'])) $active_filters[] = "Search: '" . htmlspecialchars($_GET['search']) . "'";
            if (!empty($_GET['status'])) $active_filters[] = "Status: " . ucfirst($_GET['status']);
            if (!empty($_GET['payment'])) $active_filters[] = "Payment: " . ucfirst($_GET['payment']);
            if (!empty($_GET['date_from'])) $active_filters[] = "From: " . $_GET['date_from'];
            if (!empty($_GET['date_to'])) $active_filters[] = "To: " . $_GET['date_to'];
            
            if (!empty($active_filters)): ?>
            <div class="mt-3">
                <small class="text-muted me-2">Active filters:</small>
                <?php foreach ($active_filters as $filter): ?>
                    <span class="filter-badge">
                        <?php echo $filter; ?>
                        <i class="bi bi-x" onclick="removeFilter('<?php echo $filter; ?>')"></i>
                    </span>
                <?php endforeach; ?>
                <a href="my_requests.php" class="clear-filters ms-2">Clear all</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Results Summary -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted">
                Showing <?php echo count($requests); ?> of <?php echo $total_requests; ?> requests
                <?php if ($total_requests > 0): ?>
                    (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                <?php endif; ?>
            </p>
            <span class="badge bg-light text-dark p-2">
                <i class="bi bi-sort-down me-1"></i>Newest first
            </span>
        </div>

        <!-- Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Copies</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($request['request_number']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php 
                                    $purpose = htmlspecialchars($request['purpose']);
                                    echo strlen($purpose) > 40 ? substr($purpose, 0, 40) . '...' : $purpose;
                                    ?>
                                </td>
                                <td><?php echo (int)$request['copies']; ?></td>
                                <td>₱<?php echo number_format($request['amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $request['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['payment_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_request.php?id=<?php echo (int)$request['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php if ($request['payment_status'] == 'unpaid'): ?>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="showPaymentModal(<?php echo (int)$request['id']; ?>, '<?php echo htmlspecialchars($request['request_number']); ?>', <?php echo $request['amount']; ?>)">
                                        <i class="bi bi-cash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">No requests found</h5>
                                    <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['payment'])): ?>
                                        <p class="text-muted">Try adjusting your search filters</p>
                                        <a href="my_requests.php" class="btn btn-outline-primary mt-2">
                                            <i class="bi bi-arrow-counterclockwise me-2"></i>Clear Filters
                                        </a>
                                    <?php else: ?>
                                        <p class="text-muted">Start by creating a new COG request.</p>
                                        <a href="request_cog.php" class="btn btn-primary mt-3">
                                            <i class="bi bi-plus-circle me-2"></i>Create New Request
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to process payment for request: <strong id="modalRequestNumber"></strong></p>
                    <p>Amount to pay: <strong class="text-success" id="modalAmount"></strong></p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Please proceed to the Registrar's Office to complete your payment.
                        After payment, an admin will update the payment status.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="paymentLink" class="btn btn-success">I have paid</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPaymentModal(requestId, requestNumber, amount) {
            document.getElementById('modalRequestNumber').textContent = requestNumber;
            document.getElementById('modalAmount').textContent = '₱' + amount.toFixed(2);
            document.getElementById('paymentLink').href = 'process_payment.php?id=' + requestId;
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function removeFilter(filter) {
            // This is a helper function - in practice, you'd remove the specific filter
            // For simplicity, we'll just clear all filters
            window.location.href = 'my_requests.php';
        }

        // Auto-submit form when select changes (optional)
        document.querySelectorAll('select[name="status"], select[name="payment"]').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // Add loading state to search button
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Searching...';
            btn.disabled = true;
        });
    </script>
</body>
</html>