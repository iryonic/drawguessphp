<?php
// Secure session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration required for auth
require_once __DIR__ . '/../api/db.php';

/**
 * Ensures user is authenticated as an admin.
 * Redirects to login page if they are not.
 */
function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        $login_url = defined('APP_ROOT') ? APP_ROOT . 'admin/login' : 'login.php';
        header("Location: " . $login_url);
        exit;
    }
}

// --- LOGIN HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $user = sanitize($conn, $_POST['username']);
    $pass = $_POST['password'];

    // Verify against DB
    $q = mysqli_query($conn, "SELECT * FROM admins WHERE username = '$user' LIMIT 1");
    $admin = mysqli_fetch_assoc($q);
    
    if ($admin && password_verify($pass, $admin['password_hash'])) {
        // Prevent Session Fixation
        session_regenerate_id(true);
        
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        
        // Success redirect
        $target = defined('APP_ROOT') ? APP_ROOT . 'admin' : 'index.php';
        header("Location: " . $target);
        exit;
    } else {
        $error = "Access Denied: Invalid Username or Password";
    }
}

// --- LOGOUT HANDLER ---
if (isset($_GET['logout'])) {
    // Clear admin-specific session data
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_id']);
    
    // Optional: Full destroy if it's strictly an admin domain
    session_destroy();
    
    $redirect = defined('APP_ROOT') ? APP_ROOT . 'admin/login' : 'login.php';
    header("Location: " . $redirect . "?msg=Logged+out+successfully");
    exit;
}
?>
