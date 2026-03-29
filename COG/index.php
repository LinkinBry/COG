<?php
// index.php (Student Login Page)
require_once 'config/database.php';
require_once 'config/session.php';

if (Session::isLoggedIn()) {
    if (Session::get('role') == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: student/dashboard.php");
    }
    exit();
}

$error = '';

// Show timeout notice
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $database   = new Database();
    $db         = $database->getConnection();
    $identifier = trim($_POST['identifier']);
    $password   = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE (email = :id OR student_id = :id)");
    $stmt->execute([':id' => $identifier]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        if (password_verify($password, $user['password'])) {
            Session::set('user_id',    $user['id']);
            Session::set('user_name',  $user['full_name']);
            Session::set('role',       'student');
            Session::set('student_id', $user['student_id']);
            Session::refreshActivity();
            header("Location: student/dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login – COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: maroon;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        .login-card {
            background: rgba(255,255,255,.96);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            padding: 40px 35px;
            width: 100%;
            max-width: 440px;
        }
        .school-logo { width:80px; height:80px; background:maroon; border-radius:50%;
                       display:flex; align-items:center; justify-content:center; margin:0 auto 14px; overflow:hidden; }
        .logo-img { width:100%; height:100%; object-fit:cover; }
        .form-control-custom {
            width: 100%; padding: 13px 20px 13px 45px;
            border: 2px solid #e0e0e0; border-radius: 12px; font-size: 15px; transition: all .3s;
        }
        .form-control-custom:focus { border-color: maroon; outline: none; box-shadow: 0 0 0 4px rgba(128,0,0,.1); }
        .input-icon { position:absolute; left:15px; top:50%; transform:translateY(-50%); color:maroon; font-size:18px; }
        .toggle-password { position:absolute; right:15px; top:50%; transform:translateY(-50%); color:maroon; cursor:pointer; }
        .btn-login { background:maroon; border:none; color:#fff; padding:14px; border-radius:12px; width:100%; font-weight:700; font-size:16px; transition:all .3s; }
        .btn-login:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(128,0,0,.4); }
        .admin-link a { color:#aaa; text-decoration:none; font-size:13px; }
        .admin-link a:hover { color:maroon; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="school-logo">
            <img src="image/logo.png" alt="OLSHCO" class="logo-img"
                 onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'bi bi-shield-shaded\' style=\'font-size:2.5rem;color:#fff;\'></i>'">
        </div>
        <div style="font-size:22px;font-weight:700;color:#333;">OLSHCO</div>
        <div style="color:#666;font-size:13px;">Our Lady of the Sacred Heart College</div>
    </div>

    <h5 class="fw-bold text-center mb-4" style="color:#333;">Student Login</h5>

    <?php if ($timeout): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-clock-history"></i>
            Your session has expired due to inactivity. Please log in again.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold">Email or Student ID</label>
            <div class="position-relative">
                <i class="bi bi-person input-icon"></i>
                <input type="text" class="form-control-custom" name="identifier"
                       placeholder="Enter email or student ID"
                       value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>"
                       required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Password</label>
            <div class="position-relative">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" class="form-control-custom" name="password"
                       id="password" placeholder="Enter your password" required>
                <i class="bi bi-eye toggle-password" id="togglePwd"></i>
            </div>
        </div>

        <button type="submit" class="btn-login mb-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Dashboard
        </button>
    </form>

    <hr>
    <div class="text-center">
        <p class="text-muted mb-1" style="font-size:14px;">New student?</p>
        <a href="register.php" style="color:maroon;font-weight:600;text-decoration:none;">
            <i class="bi bi-person-plus me-1"></i>Register Here
        </a>
    </div>

    <div class="admin-link text-center mt-3 pt-3" style="border-top:1px dashed #ddd;">
        <a href="admin/login.php"><i class="bi bi-shield-lock me-1"></i>Admin Login</a>
    </div>
</div>

<script>
    document.getElementById('togglePwd').addEventListener('click', function () {
        const p = document.getElementById('password');
        p.type = p.type === 'password' ? 'text' : 'password';
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
</script>
</body>
</html>