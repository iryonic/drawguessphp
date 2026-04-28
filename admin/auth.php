<?php
/**
 * Admin Authentication Middleware
 * Refactored to use PDO and secure session management.
 * Fixed Parse Error: Nested Braces
 */

if (session_status() === PHP_SESSION_NONE) {
    // Prevent caching of sensitive admin pages
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
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
 * Brute Force Protection Helpers
 */
function isIPBlocked($ip) {
    // Check for 5 failures in the last 10 minutes
    $failures = DB::count("login_attempts", "ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)", [$ip]);
    return $failures >= 5;
}

function recordLoginAttempt($ip) {
    DB::insert("login_attempts", ["ip_address" => $ip]);
}

function clearLoginAttempts($ip) {
    DB::delete("login_attempts", "ip_address = ?", [$ip]);
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
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isIPBlocked($ip)) {
            $error = "Too many failed attempts. Account locked for 15 minutes.";
        } else {
            $user = trim($_POST['username'] ?? '');
            $pass = $_POST['password'] ?? '';

            // Verify against DB using PDO Prepared Statement
            $admin = DB::fetch("SELECT * FROM admins WHERE username = ? LIMIT 1", [$user]);
            
            if ($admin && password_verify($pass, $admin['password_hash'])) {
                clearLoginAttempts($ip);
                session_regenerate_id(true);
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                
                $target = defined('APP_ROOT') ? APP_ROOT . 'admin' : 'index.php';
                header("Location: " . $target);
                exit;
            } else {
                recordLoginAttempt($ip);
                $error = "Access Denied: Invalid Username or Password";
            }
        }
    }
}

// --- LOGOUT HANDLER ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    $redirect = defined('APP_ROOT') ? APP_ROOT . 'admin/login' : 'login.php';
    header("Location: " . $redirect . "?msg=Logged+out+successfully");
    exit;
}
