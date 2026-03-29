<?php
// config/session.php
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 1800,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('SESSION_TIMEOUT', 1800); // 30 minutes

class Session {
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public static function destroy() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
    }

    /**
     * Check if the session has timed out (30 min inactivity).
     * Returns true if timed out (and destroys session), false if still valid.
     */
    public static function checkTimeout() {
        if (!self::isLoggedIn()) {
            return false;
        }
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive >= SESSION_TIMEOUT) {
                self::destroy();
                return true; // timed out
            }
        }
        $_SESSION['last_activity'] = time();
        return false;
    }

    public static function refreshActivity() {
        $_SESSION['last_activity'] = time();
    }

    public static function getRemainingTime() {
        if (!isset($_SESSION['last_activity'])) {
            return SESSION_TIMEOUT;
        }
        $elapsed = time() - $_SESSION['last_activity'];
        return max(0, SESSION_TIMEOUT - $elapsed);
    }

    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function setFlash($key, $message) {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash($key) {
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }
}

Session::start();

// Auto-check timeout on every page load (skip for AJAX / payment callbacks)
if (!defined('SKIP_TIMEOUT_CHECK')) {
    if (Session::checkTimeout()) {
        $redirect = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false)
            ? '/admin/login.php'
            : '/index.php';
        header("Location: $redirect?timeout=1");
        exit();
    }
    Session::refreshActivity();
}