<?php
require_once 'db.php';

// Create Admins Table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "Admins table created.<br>";
    
    // Check if any admin exists
    $check = mysqli_query($conn, "SELECT id FROM admins");
    if (mysqli_num_rows($check) == 0) {
        // Create default admin
        $user = 'admin';
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $ins = "INSERT INTO admins (username, password_hash) VALUES ('$user', '$pass')";
        if (mysqli_query($conn, $ins)) {
            echo "Default admin created (User: admin, Pass: admin123)<br>";
        } else {
            echo "Failed to create default admin: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Admin accounts already exist.<br>";
    }
} else {
    echo "Error creating table: " . mysqli_error($conn);
}
?>
