<?php
/**
 * Core Database & System Configuration
 * Implements PDO for security and .env for configuration.
 */

// 1. Environment & Timezone Alignment
date_default_timezone_set('UTC');
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// 2. Application Constants
define('APP_ENV', $_ENV['ENVIRONMENT'] ?? 'production');
define('IS_LOCAL', APP_ENV === 'local');

// Determine the dynamic base path securely
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = null; // Start as null to track if we found it

if ($scriptName) {
    foreach (['/api/', '/admin/'] as $dir) {
        if (($pos = strpos($scriptName, $dir)) !== false) {
            $base_path = substr($scriptName, 0, $pos) . '/';
            break;
        }
    }
}

// Fallback if not in /api/ or /admin/
if ($base_path === null) {
    $base_path = rtrim(dirname($scriptName), '/\\') . '/';
}

define('APP_ROOT', ($base_path === '//') ? '/' : $base_path);

// 3. Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

// 4. Logging System
class Logger {
    public static function log($message, $level = 'INFO') {
        $logFile = __DIR__ . '/../logs/app.log';
        if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0755, true);
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] [$level] $message" . PHP_EOL, FILE_APPEND);
    }
}

// 5. Rate Limiting (Session-based for scale)
function checkRateLimit($limit = 500, $seconds = 60) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $now = time();
    $key = "rl_" . md5($_SERVER['REMOTE_ADDR']);
    if (!isset($_SESSION[$key]) || ($now - $_SESSION[$key]['start']) > $seconds) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
    } else {
        $_SESSION[$key]['count']++;
        if ($_SESSION[$key]['count'] > $limit) {
            jsonResponse(['error' => 'Rate limit exceeded. Please wait.'], false, 429);
        }
    }
}

// Call rate limit for all API requests
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
    checkRateLimit();
}

// 6. Output Buffering & Error Handling
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', IS_LOCAL ? 1 : 0);

function jsonResponse($data, $success = true, $code = 200) {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    $response = ['success' => $success];
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = is_array($data) && isset($data['error']) ? $data['error'] : (is_string($data) ? $data : 'Unknown error');
    }
    echo json_encode($response);
    exit;
}

set_exception_handler(function($e) {
    Logger::log($e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 'ERROR');
    jsonResponse(['error' => IS_LOCAL ? $e->getMessage() : 'A server error occurred'], false, 500);
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// 5. Database Connection (PDO)
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'drawguess_v2';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Ensure DB exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db` ");
    
    // Sync DB Timezone to UTC
    $pdo->exec("SET time_zone = '+00:00'");

} catch (\PDOException $e) {
    die(json_encode(["success" => false, "error" => "Connection failed: " . $e->getMessage()]));
}

// Backward compatibility (optional for migration)
$conn = mysqli_connect($host, $user, $pass, $db);
mysqli_set_charset($conn, "utf8mb4");

/**
 * DB Helper Class (Part of the "Model" foundation)
 */
class DB {
    public static function query($sql, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }
    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }
    public static function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode('`, `', $keys);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $sql = "INSERT INTO `$table` (`$fields`) VALUES ($placeholders)";
        self::query($sql, array_values($data));
        global $pdo;
        return $pdo->lastInsertId();
    }
}

/**
 * Legacy Support: Sanitize inputs for old mysqli code
 */
function sanitize($conn, $input) {
    if (is_array($input)) return array_map(fn($v) => sanitize($conn, $v), $input);
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($input))));
}

/**
 * View Helper Class (Part of the "View" foundation)
 */
class View {
    public static function render($path, $data = []) {
        extract($data);
        $fullPath = __DIR__ . '/../views/' . $path . '.php';
        if (file_exists($fullPath)) {
            include $fullPath;
        } else {
            throw new Exception("View $path not found");
        }
    }
}

// Initialize tables if missing
$tables = $pdo->query("SHOW TABLES LIKE 'rooms'")->rowCount();
if ($tables == 0) {
    // Rooms
    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS avatars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emoji VARCHAR(10) NOT NULL UNIQUE
    )");
    $pdo->exec("INSERT IGNORE INTO avatars (emoji) VALUES ('🐱'), ('🐶'), ('🦁'), ('🦊'), ('🐸'), ('🐼'), ('🐨'), ('🐷'), ('🐵'), ('🦄'), ('🐙'), ('🐯')");

    // Admins
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO admins (username, password_hash) VALUES ('admin', '$default_pass')");

    // Players
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS words (
        id INT AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(50) NOT NULL UNIQUE,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'easy'
    )");
    $pdo->exec("INSERT IGNORE INTO words (word, difficulty) VALUES 
    ('Cat', 'easy'), ('Sun', 'easy'), ('Apple', 'easy'), ('House', 'easy'), ('Tree', 'easy'),
    ('Bicycle', 'medium'), ('Guitar', 'medium'), ('Pizza', 'medium'), ('Helicopter', 'medium'),
    ('Astronaut', 'hard'), ('Sphinx', 'hard'), ('Waterfall', 'hard')");

    // Rounds
    $pdo->exec("CREATE TABLE IF NOT EXISTS rounds (
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS strokes (
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        round_id INT,
        player_id INT,
        message VARCHAR(255),
        type ENUM('chat', 'guess', 'system', 'reaction') DEFAULT 'chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(room_id),
        INDEX(type),
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )");

    // Index existing tables for high-performance lookups
    $pdo->exec("CREATE INDEX idx_player_room ON players(room_id)");
    $pdo->exec("CREATE INDEX idx_stroke_round ON strokes(round_id)");
    $pdo->exec("CREATE INDEX idx_round_room ON rounds(room_id)");

    // Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('lobby_music_enabled', '0'),
    ('lobby_music_url', ''),
    ('music_volume', '0.5')");
}
