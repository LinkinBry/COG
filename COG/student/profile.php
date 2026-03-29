<?php
// student/profile.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to access profile.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');

// Get current user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['update_profile'])) {
        // Update profile
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $course = $_POST['course'];
        $year_level = (int)$_POST['year_level'];

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists for other users
            $check_query = "SELECT id FROM users WHERE email = :email AND id != :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $error = "Email already used by another account.";
            } else {
                $update_query = "UPDATE users SET full_name = :full_name, email = :email, course = :course, year_level = :year_level WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':full_name', $full_name);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':course', $course);
                $update_stmt->bindParam(':year_level', $year_level, PDO::PARAM_INT);
                $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    Session::set('user_name', $full_name);
                    $success = "Profile updated successfully!";
                    // Refresh user data
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = "Password must contain uppercase, lowercase, and number.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - COG Management System</title>
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
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 48px;
            font-weight: bold;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: maroon;
            border-bottom: 3px solid maroon;
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
                <a href="profile.php" class="active"><i class="bi bi-person me-2"></i>Profile</a>
                <hr class="bg-white opacity-25">
                <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Flash Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="fw-bold mb-4">My Profile</h2>

        <!-- Profile Header -->
        <div class="profile-card text-center">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($user['student_id']); ?></p>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                    <i class="bi bi-person me-2"></i>Profile Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">
                    <i class="bi bi-key me-2"></i>Change Password
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Profile Information Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <div class="profile-card">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly disabled>
                                <small class="text-muted">Student ID cannot be changed</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course</label>
                                <select class="form-select" name="course" required>
                                    <option value="">Select Course</option>
                                    <option value="BSIT" <?php echo $user['course'] == 'BSIT' ? 'selected' : ''; ?>>BS Information Technology</option>
                                    <option value="BSCS" <?php echo $user['course'] == 'BSCS' ? 'selected' : ''; ?>>BS Computer Science</option>
                                    <option value="BSED" <?php echo $user['course'] == 'BSED' ? 'selected' : ''; ?>>BS Education</option>
                                    <option value="BEED" <?php echo $user['course'] == 'BEED' ? 'selected' : ''; ?>>BEED</option>
                                    <option value="BSBA" <?php echo $user['course'] == 'BSBA' ? 'selected' : ''; ?>>BS Business Administration</option>
                                    <option value="BSHRM" <?php echo $user['course'] == 'BSHRM' ? 'selected' : ''; ?>>BS Hospitality Management</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level" required>
                                    <option value="">Select Year Level</option>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $user['year_level'] == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>st Year
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Tab -->
            <div class="tab-pane fade" id="password" role="tabpanel">
                <div class="profile-card">
                    <form method="POST" onsubmit="return validatePassword()">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="new_password" required>
                            <small class="text-muted">
                                Must be at least 8 characters with uppercase, lowercase, and number
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>
                        
                        <div class="password-strength mb-3">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="strengthBar" style="width: 0%;"></div>
                            </div>
                            <small class="text-muted" id="strengthText"></small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validatePassword() {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPass.length < 8) {
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!/[A-Z]/.test(newPass) || !/[a-z]/.test(newPass) || !/[0-9]/.test(newPass)) {
                alert('Password must contain uppercase, lowercase, and number!');
                return false;
            }
            
            return true;
        }

        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak password';
            } else if (strength <= 50) {
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Fair password';
            } else if (strength <= 75) {
                strengthBar.className = 'progress-bar bg-info';
                strengthText.textContent = 'Good password';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong password';
            }
        });
    </script>
</body>
</html>