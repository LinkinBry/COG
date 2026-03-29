<?php
// student/request_cog.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to request COG.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    // Validate inputs
    $purpose = trim($_POST['purpose']);
    $copies = (int)$_POST['copies'];
    $other_purpose = isset($_POST['other_purpose']) ? trim($_POST['other_purpose']) : '';

    if ($purpose === 'Other' && empty($other_purpose)) {
        $error = "Please specify the purpose of your request.";
    } elseif ($copies < 1 || $copies > 10) {
        $error = "Number of copies must be between 1 and 10.";
    } else {
        // Set final purpose
        $final_purpose = ($purpose === 'Other') ? $other_purpose : $purpose;
        $amount = $copies * 50; // ₱50 per copy

        // Generate unique request number
        $request_number = 'COG-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        // Check if request number already exists
        $check_query = "SELECT id FROM cog_requests WHERE request_number = :request_number";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':request_number', $request_number);
        $check_stmt->execute();

        while ($check_stmt->rowCount() > 0) {
            $request_number = 'COG-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $check_stmt->bindParam(':request_number', $request_number);
            $check_stmt->execute();
        }

        // Begin transaction
        $db->beginTransaction();

        try {
            // Insert request
            $query = "INSERT INTO cog_requests (user_id, request_number, purpose, copies, amount) 
                      VALUES (:user_id, :request_number, :purpose, :copies, :amount)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':request_number', $request_number);
            $stmt->bindParam(':purpose', $final_purpose);
            $stmt->bindParam(':copies', $copies, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount);
            $stmt->execute();

            $request_id = $db->lastInsertId();

            // Create notification
            $notif_query = "INSERT INTO notifications (user_id, request_id, message) 
                            VALUES (:user_id, :request_id, :message)";
            $notif_stmt = $db->prepare($notif_query);
            $message = "Your COG request (Ref: $request_number) has been submitted successfully. Please proceed to the Registrar's Office for payment.";
            $notif_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $notif_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            $notif_stmt->bindParam(':message', $message);
            $notif_stmt->execute();

            $db->commit();
            
            Session::setFlash('success', "Request submitted successfully! Your reference number is: $request_number");
            header("Location: my_requests.php");
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Request submission error: " . $e->getMessage());
            $error = "Failed to submit request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request COG - COG Management System</title>
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
        .request-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 0 auto;
        }
        .form-control, .form-select {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: maroon;
            box-shadow: 0 0 0 0.2rem rgba(128,0,0,0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid maroon;
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
                <a href="request_cog.php" class="active"><i class="bi bi-file-earmark-text me-2"></i>Request COG</a>
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
            <h2 class="fw-bold">Request Certificate of Grades</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="request-form">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="requestForm">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">

                <div class="mb-4">
                    <label for="purpose" class="form-label fw-bold">Purpose of Request</label>
                    <select class="form-select" id="purpose" name="purpose" required onchange="toggleOtherPurpose()">
                        <option value="">Select Purpose</option>
                        <option value="Employment">Employment</option>
                        <option value="Transfer">School Transfer</option>
                        <option value="Graduation">Graduation Requirements</option>
                        <option value="Scholarship">Scholarship Application</option>
                        <option value="Board Exam">Board Exam</option>
                        <option value="Other">Other (Please specify)</option>
                    </select>
                </div>

                <div class="mb-4" id="otherPurposeDiv" style="display: none;">
                    <label for="other_purpose" class="form-label fw-bold">Please specify purpose</label>
                    <input type="text" class="form-control" id="other_purpose" name="other_purpose" 
                           placeholder="Enter your purpose">
                </div>

                <div class="mb-4">
                    <label for="copies" class="form-label fw-bold">Number of Copies</label>
                    <input type="number" class="form-control" id="copies" name="copies" 
                           min="1" max="10" value="1" required onchange="updateAmount()">
                    <small class="text-muted">Maximum of 10 copies</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Total Amount</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">₱</span>
                        <input type="text" class="form-control bg-light" id="amount" value="50.00" readonly>
                        <span class="input-group-text bg-light">.00</span>
                    </div>
                    <small class="text-muted">₱50.00 per copy</small>
                </div>

                <div class="info-card mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Important Reminders:</h6>
                    <ul class="mb-0">
                        <li class="mb-2">Processing time: 2-3 working days</li>
                        <li class="mb-2">Payment must be made at the Registrar's Office before processing</li>
                        <li class="mb-2">Bring your school ID and a valid government ID when claiming</li>
                        <li>Requests are processed on a first-come, first-served basis</li>
                    </ul>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send me-2"></i>Submit Request
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleOtherPurpose() {
            const purpose = document.getElementById('purpose');
            const otherDiv = document.getElementById('otherPurposeDiv');
            const otherInput = document.getElementById('other_purpose');
            
            if (purpose.value === 'Other') {
                otherDiv.style.display = 'block';
                otherInput.required = true;
            } else {
                otherDiv.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        function updateAmount() {
            const copies = document.getElementById('copies').value;
            const amount = copies * 50;
            document.getElementById('amount').value = amount.toFixed(2);
        }

        // Form validation
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            const purpose = document.getElementById('purpose').value;
            const otherPurpose = document.getElementById('other_purpose').value;
            const copies = document.getElementById('copies').value;

            if (!purpose) {
                e.preventDefault();
                alert('Please select a purpose for your request.');
                return;
            }

            if (purpose === 'Other' && !otherPurpose.trim()) {
                e.preventDefault();
                alert('Please specify the purpose of your request.');
                return;
            }

            if (copies < 1 || copies > 10) {
                e.preventDefault();
                alert('Number of copies must be between 1 and 10.');
                return;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>