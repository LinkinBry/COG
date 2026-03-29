<?php
// admin/login.php
require_once '../config/database.php';
require_once '../config/session.php';

// Hard-coded admin credentials (TEMPORARY ONLY)
define('ADMIN_USERNAME', 'admin@olshco.edu.com');
define('ADMIN_PASSWORD', 'admin123');
define('ADMIN_NAME', 'System Administrator');
define('ADMIN_EMAIL', 'admin@olshco.edu.com');

// Redirect if already logged in
if (Session::isLoggedIn()) {
    if (Session::get('role') == 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: ../student/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Check against hard-coded admin
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        // Hard-coded admin login successful
        Session::set('admin_id', 999); // Special ID for hard-coded admin
        Session::set('admin_name', ADMIN_NAME);
        Session::set('role', 'admin');
        Session::setFlash('success', 'Welcome back, ' . ADMIN_NAME . '!');
        header("Location: dashboard.php");
        exit();
    } else {
        // If hard-coded fails, try database (for other admins)
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM admins WHERE username = :username OR email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $admin['password'])) {
                    Session::set('admin_id', $admin['id']);
                    Session::set('admin_name', $admin['full_name']);
                    Session::set('role', 'admin');
                    Session::setFlash('success', 'Welcome back, ' . $admin['full_name'] . '!');
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password!";
                }
            } else {
                $error = "Admin not found!";
            }
        } catch (PDOException $e) {
            // If database error, show hard-coded login instructions
            $error = "Database error. Please use hard-coded admin: " . ADMIN_USERNAME . " / " . ADMIN_PASSWORD;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h3 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-control {
            height: 45px;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        .form-control:focus {
            border-color: maroon;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .btn-login {
            background: maroon;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px maroon;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: maroon;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .alert {
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .admin-info {
            background: #f0f7ff;
            border-left: 4px solid maroon;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 13px;
        }
        .admin-info strong {
            color: maroon;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h3>Admin Login</h3>
            <p>OLSHCO - Certificate of Grades</p>
        </div>
        
        <!-- Show hard-coded admin info (remove this in production) -->
        <div class="admin-info">
            <strong>⚠️ Development Mode</strong><br>
            Use this account if database login fails:<br>
            <strong>Email:</strong> admin@olshco.edu.com<br>
            <strong>Password:</strong> admin123
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php $flash_error = Session::getFlash('error'); ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">Username or Email</label>
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="Enter username or email" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>

            <div class="back-link">
                <a href="../index.php">← Back to Student Login</a>
            </div>
        </form>
    </div>
</body>
</html>