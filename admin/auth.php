<?php
session_start();

// Database connection required for auth
require_once '../api/db.php';

function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header("Location: login.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $user = sanitize($conn, $_POST['username']);
    $pass = $_POST['password'];

    $q = mysqli_query($conn, "SELECT * FROM admins WHERE username = '$user'");
    $admin = mysqli_fetch_assoc($q);
    
    if ($admin && password_verify($pass, $admin['password_hash'])) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid Credentials";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
