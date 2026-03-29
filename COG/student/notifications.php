<?php
// student/notifications.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to view notifications.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');

// Create notifications table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_id INT,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_read),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (request_id) REFERENCES cog_requests(id) ON DELETE CASCADE
    )";
    $db->exec($create_table);
} catch (PDOException $e) {
    error_log("Notifications table error: " . $e->getMessage());
}

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    try {
        $update_query = "UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $notif_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Redirect to remove the parameter from URL
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $update_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        Session::setFlash('success', 'All notifications marked as read!');
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
    }
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    try {
        $delete_query = "DELETE FROM notifications WHERE id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $notif_id, PDO::PARAM_INT);
        $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        Session::setFlash('success', 'Notification deleted!');
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
    }
}

// Get unread count for header
$unread_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetchColumn();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$count_stmt->execute();
$total_notifications = $count_stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);

// Get notifications
$query = "SELECT n.*, r.request_number, r.status as request_status
          FROM notifications n 
          LEFT JOIN cog_requests r ON n.request_id = r.id 
          WHERE n.user_id = :user_id 
          ORDER BY n.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group notifications by date
$grouped_notifications = [];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

foreach ($notifications as $notif) {
    $date = date('Y-m-d', strtotime($notif['created_at']));
    if ($date == $today) {
        $grouped_notifications['Today'][] = $notif;
    } elseif ($date == $yesterday) {
        $grouped_notifications['Yesterday'][] = $notif;
    } else {
        $week = date('W', strtotime($notif['created_at']));
        $year = date('Y', strtotime($notif['created_at']));
        $grouped_notifications['Earlier'][] = $notif;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            position: fixed;
            width: 260px;
            transition: all 0.3s;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 4px 10px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .sidebar a.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
            font-weight: 600;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        /* Notification Styles */
        .notifications-header {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-group {
            margin-bottom: 30px;
        }
        
        .notification-group-title {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            padding-left: 10px;
        }
        
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            transition: all 0.3s;
            position: relative;
            border: 1px solid #f0f0f0;
        }
        
        .notification-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateX(5px);
            border-color: maroon;
        }
        
        .notification-item.unread {
            background: #fff0f0;
            border-left: 4px solid maroon;
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 20px;
            left: -2px;
            width: 8px;
            height: 8px;
            background: maroon;
            border-radius: 50%;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .notification-icon.info {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .notification-icon.warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-message {
            font-size: 15px;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 12px;
        }
        
        .notification-time {
            color: #6c757d;
        }
        
        .notification-time i {
            margin-right: 3px;
        }
        
        .request-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .request-badge i {
            margin-right: 3px;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn-mark-read {
            background: transparent;
            border: 1px solid #dee2e6;
            color: #6c757d;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-mark-read:hover {
            background: maroon;
            border-color: maroon;
            color: white;
        }
        
        .btn-delete {
            background: transparent;
            border: none;
            color: #dc3545;
            font-size: 16px;
            opacity: 0.5;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h5 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
        }
        
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .pagination .page-link {
            color: maroon;
            border: none;
            margin: 0 3px;
            border-radius: 8px;
        }
        
        .pagination .active .page-link {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
        }
        
        .pagination .page-link:hover {
            background: #f0f0f0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .notification-item {
                padding: 15px;
            }
            .notification-actions {
                flex-direction: column;
                align-items: flex-end;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4 fw-bold">COG System</h4>
            <div class="text-center mb-4">
                <div class="bg-white bg-opacity-20 rounded-circle d-inline-block p-3 mb-2">
                    <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars(Session::get('user_name')); ?></h6>
                <small class="text-white-50"><?php echo htmlspecialchars(Session::get('student_id')); ?></small>
            </div>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="request_cog.php"><i class="bi bi-file-earmark-text me-2"></i>Request COG</a>
                <a href="my_requests.php"><i class="bi bi-list-check me-2"></i>My Requests</a>
                <a href="notifications.php" class="active">
                    <i class="bi bi-bell me-2"></i>Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
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
        <div class="notifications-header">
            <div>
                <h4 class="fw-bold mb-1">Notifications</h4>
                <p class="text-muted mb-0">
                    <i class="bi bi-bell me-1"></i>
                    You have <strong><?php echo $unread_count; ?></strong> unread notification<?php echo $unread_count != 1 ? 's' : ''; ?>
                </p>
            </div>
            <div>
                <?php if ($total_notifications > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-outline-primary me-2" 
                       onclick="return confirm('Mark all notifications as read?')">
                        <i class="bi bi-check-all me-2"></i>Mark All Read
                    </a>
                <?php endif; ?>
                <a href="notifications.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Refresh
                </a>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="bi bi-bell-slash"></i>
                <h5>No Notifications</h5>
                <p class="text-muted">You don't have any notifications at the moment.</p>
                <p class="text-muted small">When you receive notifications, they'll appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_notifications as $group => $items): ?>
                <div class="notification-group">
                    <div class="notification-group-title">
                        <?php echo $group; ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo count($items); ?></span>
                    </div>
                    
                    <?php foreach ($items as $notif): ?>
                        <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <!-- Icon -->
                                <div class="notification-icon <?php 
                                    echo strpos($notif['message'], 'success') !== false ? 'success' : 
                                        (strpos($notif['message'], 'pending') !== false ? 'warning' : 'info'); 
                                ?>">
                                    <i class="bi <?php 
                                        echo $notif['is_read'] ? 'bi-envelope-open' : 'bi-envelope-fill'; 
                                    ?>"></i>
                                </div>
                                
                                <!-- Content -->
                                <div class="notification-content">
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                    
                                    <div class="notification-meta">
                                        <span class="notification-time">
                                            <i class="bi bi-clock"></i>
                                            <?php 
                                            $time = strtotime($notif['created_at']);
                                            if (date('Y-m-d') == date('Y-m-d', $time)) {
                                                echo 'Today at ' . date('h:i A', $time);
                                            } elseif (date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
                                                echo 'Yesterday at ' . date('h:i A', $time);
                                            } else {
                                                echo date('M d, Y \a\t h:i A', $time);
                                            }
                                            ?>
                                        </span>
                                        
                                        <?php if ($notif['request_number']): ?>
                                            <span class="request-badge">
                                                <i class="bi bi-file-text"></i>
                                                Request: <?php echo htmlspecialchars($notif['request_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notif['request_status'])): ?>
                                            <span class="request-badge">
                                                <i class="bi bi-tag"></i>
                                                Status: <?php echo ucfirst($notif['request_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="notification-actions">
                                    <?php if (!$notif['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notif['id']; ?>" 
                                           class="btn-mark-read" 
                                           title="Mark as read">
                                            <i class="bi bi-check-lg"></i> Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?delete=<?php echo $notif['id']; ?>" 
                                       class="btn-delete" 
                                       title="Delete notification"
                                       onclick="return confirm('Delete this notification?')">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Mark notification as read when clicked (optional)
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't trigger if clicking on a button
                if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a')) {
                    return;
                }
                
                const markReadLink = this.querySelector('.btn-mark-read');
                if (markReadLink) {
                    window.location.href = markReadLink.href;
                }
            });
        });
    </script>
</body>
</html>