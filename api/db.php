<?php
// Determine the dynamic base path securely
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = '/';
if ($scriptName) {
    // Check if we are in admin or api subfolder
    $api_pos = strpos($scriptName, '/api/');
    $admin_pos = strpos($scriptName, '/admin/');
    
    if ($api_pos !== false) {
        $base_path = substr($scriptName, 0, $api_pos) . '/';
    } elseif ($admin_pos !== false) {
        $base_path = substr($scriptName, 0, $admin_pos) . '/';
    } else {
        $base_path = rtrim(dirname($scriptName), '/\\') . '/';
    }
}
if ($base_path == '/' || $base_path == '//') $base_path = '/';
define('APP_ROOT', $base_path);

// Prevent unwanted output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable error reporting for debugging

// Database configuration
$is_local = true;
if (isset($_SERVER['HTTP_HOST'])) {
    $hostname = explode(':', $_SERVER['HTTP_HOST'])[0];
    if ($hostname !== 'localhost' && $hostname !== '127.0.0.1' && $hostname !== '::1') {
        $is_local = false;
    }
}

if ($is_local) {
    // Local
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db_name = 'drawguess';
} else {
    // Production
    $host = 'localhost';
    $user = 'u167160735_drawguess';
    $pass = 'DrawGuess@1234#';
    $db_name = 'u167160735_drawguess';
}


// Connect to MySQL
$conn = @mysqli_connect($host, $user, $pass);

if (!$conn) {
    ob_clean();
    die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}

// Create database if not exists
$db_check = mysqli_query($conn, "SHOW DATABASES LIKE '$db_name'");
if (!$db_check || mysqli_num_rows($db_check) == 0) {
    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db_name");
}

if (!mysqli_select_db($conn, $db_name)) {
    ob_clean();
    die(json_encode(["error" => "Failed to select database: " . mysqli_error($conn)]));
}
mysqli_set_charset($conn, "utf8mb4");

// Auto-create tables if they don't exist (Robust Setup)
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'rooms'");
if ($tbl_check && mysqli_num_rows($tbl_check) == 0) {
    // Rooms
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) NOT NULL UNIQUE,
        host_id INT,
        current_round INT DEFAULT 1,
        max_rounds INT DEFAULT 3,
        round_duration INT DEFAULT 60,
        status ENUM('lobby', 'playing', 'finished') DEFAULT 'lobby',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Avatars
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS avatars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emoji VARCHAR(10) NOT NULL UNIQUE
    )");

    // Seed Avatars
    mysqli_query($conn, "INSERT IGNORE INTO avatars (emoji) VALUES ('🐱'), ('🐶'), ('🦁'), ('🦊'), ('🐸'), ('🐼'), ('🐨'), ('🐷'), ('🐵'), ('🦄'), ('🐙'), ('🐯')");

    // Admins
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed default admin if none exists
    $check_admin = mysqli_query($conn, "SELECT id FROM admins");
    if (mysqli_num_rows($check_admin) == 0) {
        $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO admins (username, password_hash) VALUES ('admin', '$default_pass')");
    }

    // Players
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        avatar VARCHAR(255),
        score INT DEFAULT 0,
        is_host BOOLEAN DEFAULT FALSE,
        turn_order INT DEFAULT 0,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        session_token VARCHAR(64) NOT NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        UNIQUE(room_id, username)
    )");

    // Words
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS words (
        id INT AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(50) NOT NULL UNIQUE,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'easy'
    )");
    
    // Rounds
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rounds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        round_number INT NOT NULL,
        drawer_id INT,
        word_id INT,
        word_options VARCHAR(255),
        hints_mask VARCHAR(255),
        start_time TIMESTAMP NULL,
        end_time TIMESTAMP NULL,
        status ENUM('choosing', 'countdown', 'drawing', 'ended', 'game_over') DEFAULT 'choosing',
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (drawer_id) REFERENCES players(id) ON DELETE SET NULL,
        FOREIGN KEY (word_id) REFERENCES words(id)
    )");

    // Strokes
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS strokes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        round_id INT NOT NULL,
        color VARCHAR(20) DEFAULT '#000000',
        size INT DEFAULT 5,
        points MEDIUMTEXT NOT NULL,
        sequence_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE
    )");

    // Messages
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        round_id INT,
        player_id INT,
        message VARCHAR(255),
        type ENUM('chat', 'guess', 'system', 'reaction') DEFAULT 'chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )");

    // Settings
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");

    // Seed Default Settings
    mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('lobby_music_enabled', '0'),
    ('lobby_music_url', ''),
    ('music_volume', '0.5')");

    // Seed Words
    mysqli_query($conn, "INSERT IGNORE INTO words (word, difficulty) VALUES 
    ('Cat', 'easy'), ('Sun', 'easy'), ('Apple', 'easy'), ('House', 'easy'), ('Tree', 'easy'),
    ('Bicycle', 'medium'), ('Guitar', 'medium'), ('Pizza', 'medium'), ('Helicopter', 'medium'),
    ('Astronaut', 'hard'), ('Sphinx', 'hard'), ('Waterfall', 'hard')");
}

// Common helper functions
function jsonResponse($data, $success = true) {
    ob_clean();
    header('Content-Type: application/json');
    
    $response = ['success' => $success];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        // If error, flatten it to top level if passed as ['error' => 'msg']
        if (is_array($data) && isset($data['error'])) {
            $response['error'] = $data['error'];
        } else {
            $response['error'] = is_string($data) ? $data : 'Unknown error';
        }
    }
    
    echo json_encode($response);
    exit;
}

function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($input))));
}

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

