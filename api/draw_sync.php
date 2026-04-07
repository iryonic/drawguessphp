<?php
/**
 * Drawing Synchronization API
 * Refactored to PDO with Prepared Statements.
 */
require_once 'db.php';

// 1. Authentication
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$player = DB::fetch("SELECT id, room_id FROM players WHERE session_token = ?", [$token]);

if (!$player) {
    jsonResponse(['error' => 'Invalid Session'], false, 401);
}

$room_id = (int)$player['room_id'];

// 2. Fetch Context (Current Round)
$round = DB::fetch("SELECT id, drawer_id, status FROM rounds WHERE room_id = ? ORDER BY id DESC LIMIT 1", [$room_id]);

if (!$round || $round['status'] !== 'drawing') {
    jsonResponse(['error' => 'Not drawing phase'], false);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';

// 3. DRAW ACTION
if ($action === 'draw') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }

    $color = $_POST['color'] ?? '#000000';
    $size = intval($_POST['size'] ?? 5);
    $points = $_POST['points'] ?? '[]';
    $seq = intval($_POST['seq'] ?? 0);

    // Basic JSON validation
    if (json_decode($points) === null && json_last_error() !== JSON_ERROR_NONE) {
        $points = '[]';
    }

    $sid = DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => $color,
        'size' => $size,
        'points' => $points,
        'sequence_id' => $seq
    ]);

    jsonResponse(['success' => true, 'id' => $sid]);
}

// 4. CLEAR ACTION
if ($action === 'clear') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => 'CLEAR',
        'size' => 0,
        'points' => '[]',
        'sequence_id' => 999999
    ]);
    jsonResponse(['success' => true]);
}

// 5. UNDO ACTION
if ($action === 'undo') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => 'UNDO',
        'size' => 0,
        'points' => '[]',
        'sequence_id' => 888888
    ]);
    jsonResponse(['success' => true]);
}

// 6. FETCH STROKES ACTION
if ($action === 'fetch') {
    $last_id = intval($_GET['last_id'] ?? 0);
    $strokes = DB::fetchAll("SELECT id, color, size, points FROM strokes WHERE round_id = ? AND id > ? ORDER BY id ASC", [$round['id'], $last_id]);
    
    foreach ($strokes as &$s) {
        $s['points'] = json_decode($s['points']);
    }
    
    jsonResponse(['strokes' => $strokes]);
}

jsonResponse(['error' => 'Invalid action'], false);
