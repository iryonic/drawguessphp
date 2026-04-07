<?php
/**
 * Main Controller / Entry Point
 * Implements MVC Separation and Routing fallback logic.
 */
require_once 'api/db.php';

// 1. Determine Route (Handle /room/CODE or just /)
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
// Standardize Base Path Resolution
$base_uri = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_path = '/' . ($base_uri ? $base_uri . '/' : '') . (strlen($base_uri) > 0 ? '' : '');
$base_path = rtrim($base_path, '/') . '/';
$relative_path = substr($request_uri, strlen(rtrim($base_path, '/')));
$relative_path = ltrim($relative_path, '/');

$room_code = null;
if (preg_match('/^room\/([A-Za-z0-9]+)/', $relative_path, $matches)) {
    $room_code = strtoupper($matches[1]);
}

// Mobile Detection Logic
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_mobile = preg_match('/Mobile|Android|BlackBerry|iPhone|iPad|iPod/', $user_agent);

// Prepare Data for Views
$data = [
    'base_path' => $base_path,
    'room_code' => $room_code,
    'is_mobile' => $is_mobile
];

try {
    if ($room_code) {
        View::render('game', $data);
    } elseif (strpos($relative_path, 'how-to-play') !== false) {
        View::render('how_to_play', $data);
    } else {
        View::render('lobby', $data);
    }
} catch (Exception $e) {
    die("Application Error: " . $e->getMessage());
}