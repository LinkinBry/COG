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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $database = new Database();
    $db = $database->getConnection();

    $identifier = trim($_POST['identifier']); // email or student_id
    $password = $_POST['password'];

    // Student login only
    $query = "SELECT * FROM users WHERE (email = :identifier OR student_id = :identifier)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':identifier', $identifier);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $user['password'])) {
            Session::set('user_id', $user['id']);
            Session::set('user_name', $user['full_name']);
            Session::set('role', 'student');
            Session::set('student_id', $user['student_id']);
            
            // Optional: Create login notification
            // You can add this if you have notifications table
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
    <title>Student Login - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: maroon;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Elements */
        .bg-bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 8s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        .bubble1 {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -100px;
            animation-delay: 0s;
        }

        .bubble2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -50px;
            animation-delay: 2s;
        }

        .bubble3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 10%;
            animation-delay: 4s;
        }

        /* Main Login Card */
        .login-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px 35px;
            width: 100%;
            transition: transform 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.4);
        }

        /* School Logo/Brand */
        .school-brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            background: maroon;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            overflow: hidden; /* important to keep image inside circle */
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* keeps image nicely fitted */
}

        

        .school-name {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .school-tagline {
            color: #666;
            font-size: 14px;
            letter-spacing: 1px;
        }

        /* Login Header */
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h3 {
            color: #333;
            font-weight: 700;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-header p i {
            color: maroon;
        }

        /* Alert Messages */
        .alert-custom {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .alert-danger-custom {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-danger-custom i {
            font-size: 20px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: maroon;
            font-size: 18px;
            z-index: 10;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .form-control-custom:focus {
            border-color: maroon;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control-custom::placeholder {
            color: #aaa;
            font-size: 14px;
        }

        /* Password field specific */
        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: maroon;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .toggle-password:hover {
            color: maroon;
        }

        /* Login Button */
        .btn-login {
            background: maroon;
            border: none;
            color: white;
            padding: 15px;
            border-radius: 12px;
            width: 100%;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: maroon;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login i {
            font-size: 18px;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #666;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider span {
            padding: 0 15px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Register Link */
        .register-section {
            text-align: center;
        }

        .register-link {
            color: maroon;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .register-link:hover {
            color: maroon;
            transform: translateX(5px);
        }

        .register-link i {
            font-size: 16px;
        }

        .register-text {
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }

        /* Admin Link */
        .admin-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #e0e0e0;
            text-align: center;
        }

        .admin-link a {
            color: #888;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .admin-link a:hover {
            color: maroon;
        }

        .admin-link i {
            font-size: 14px;
        }

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .spinner {
            display: inline-block;
        }

        .btn-login.loading .btn-text {
            opacity: 0.7;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }

            .school-logo {
                width: 60px;
                height: 60px;
            }

            .school-logo i {
                font-size: 30px;
            }

            .school-name {
                font-size: 20px;
            }

            .login-header h3 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Bubbles -->
    <div class="bg-bubble bubble1"></div>
    <div class="bg-bubble bubble2"></div>
    <div class="bg-bubble bubble3"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- School Brand -->
            <div class="school-brand">
                <div class="school-logo">
                    <img src="image/logo.png" alt="School Logo" class="logo-img">
                </div>
                <div class="school-name">OLSHCO</div>
                <div class="school-tagline">Our Lady of the Sacred Heart College</div>
            </div>

            <!-- Login Header -->
            <div class="login-header">
                <h3>Student Login</h3>
                <p>
                    <i class="bi bi-shield-check"></i>
                    Access your COG records
                </p>
            </div>

            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="alert-custom alert-danger-custom">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">

                <!-- Email/Student ID Field -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-person"></i>
                        Email or Student ID
                    </label>
                    <div class="input-group-custom">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" 
                               class="form-control-custom" 
                               name="identifier" 
                               placeholder="Enter your email or ID"
                               value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>"
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-lock"></i>
                        Password
                    </label>
                    <div class="input-group-custom password-wrapper">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" 
                               class="form-control-custom" 
                               name="password" 
                               id="password"
                               placeholder="Enter your password"
                               required>
                        <i class="bi bi-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">Login to Dashboard</span>
                    <span class="spinner"></span>
                </button>
            </form>

            <!-- Divider -->
            <div class="divider">
                <span>New Student?</span>
            </div>

            <!-- Register Link -->
            <div class="register-section">
                <p class="register-text">Create your account to request COG</p>
                <a href="register.php" class="register-link">
                    <i class="bi bi-person-plus"></i>
                    Register as Student
                </a>
            </div>

          
        </div>

        <!-- Footer Note -->
        <div style="text-align: center; margin-top: 20px; color: rgba(255,255,255,0.5); font-size: 12px;">
            <i class="bi bi-c-circle"></i> 2024 OLSHCO - Certificate of Grades Management System
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });

        // Form Loading State
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function(e) {
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
        });

        // Auto-hide error alert after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-custom');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);

        // Add floating label effect (optional)
        const inputs = document.querySelectorAll('.form-control-custom');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.querySelector('.input-icon').style.color = 'maroon';
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.querySelector('.input-icon').style.color = 'maroon';
            });
        });

        // Remember me functionality (optional)
        // You can add this if you want to implement remember me feature
    </script>
</body>
</html>