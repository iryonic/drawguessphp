<?php
// Prevent unwanted output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off HTML error reporting

// Database configuration
$is_local = true;
if (isset($_SERVER['HTTP_HOST'])) {
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
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
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    ob_clean();
    die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}

// Create database if not exists
$db_check = mysqli_query($conn, "SHOW DATABASES LIKE '$db_name'");
if (mysqli_num_rows($db_check) == 0) {
    mysqli_query($conn, "CREATE DATABASE $db_name");
}

mysqli_select_db($conn, $db_name);
mysqli_set_charset($conn, "utf8mb4");

// Auto-create tables if they don't exist (Robust Setup)
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'rooms'");
if (mysqli_num_rows($tbl_check) == 0) {
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
    
    // Players
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        avatar VARCHAR(255),
        score INT DEFAULT 0,
        is_host BOOLEAN DEFAULT FALSE,
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
        start_time TIMESTAMP NULL,
        end_time TIMESTAMP NULL,
        status ENUM('choosing', 'drawing', 'ended') DEFAULT 'choosing',
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
    )"); // Changed TEXT to MEDIUMTEXT for safety

    // Messages
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        round_id INT,
        player_id INT,
        message VARCHAR(255),
        type ENUM('chat', 'guess', 'system') DEFAULT 'chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )");

    // Seed Words
    mysqli_query($conn, "INSERT IGNORE INTO words (word, difficulty) VALUES 
    ('Cat', 'easy'), ('Sun', 'easy'), ('Apple', 'easy'), ('House', 'easy'), ('Tree', 'easy'),
    ('Bicycle', 'medium'), ('Guitar', 'medium'), ('Pizza', 'medium'), ('Helicopter', 'medium'),
    ('Astronaut', 'hard'), ('Sphinx', 'hard'), ('Waterfall', 'hard')");
}

// Common helper functions
function jsonResponse($data, $success = true) {
    ob_clean(); // Clear any previous output (warnings, etc)
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'data' => $data]);
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
?>
