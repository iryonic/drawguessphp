<?php
/**
 * Admin Authentication Middleware
 * Refactored to use PDO and secure session management.
 * Fixed Parse Error: Nested Braces
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings (Prevent CSRF/Hijacking)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

require_once __DIR__ . '/../api/db.php';

/**
 * CSRF Protection Helpers
 */
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Ensures user is authenticated as an admin.
 */
function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        $login_url = defined('APP_ROOT') ? APP_ROOT . 'admin/login' : 'login.php';
        header("Location: " . $login_url);
        exit;
    }
}

// --- LOGIN HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch. Please try again.";
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        // Verify against DB using PDO Prepared Statement
        $admin = DB::fetch("SELECT * FROM admins WHERE username = ? LIMIT 1", [$user]);
        
        if ($admin && password_verify($pass, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['id'];
            
            $target = defined('APP_ROOT') ? APP_ROOT . 'admin' : 'index.php';
            header("Location: " . $target);
            exit;
        } else {
            $error = "Access Denied: Invalid Username or Password";
        }
    }
}

// --- LOGOUT HANDLER ---
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    $redirect = defined('APP_ROOT') ? APP_ROOT . 'admin/login' : 'login.php';
    header("Location: " . $redirect . "?msg=Logged+out+successfully");
    exit;
}
