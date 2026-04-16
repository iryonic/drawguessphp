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
    $is_start = ($_POST['is_start'] ?? 'false') === 'true';

    // Basic JSON validation
    if (json_decode($points) === null && json_last_error() !== JSON_ERROR_NONE) {
        $points = '[]';
    }

    $sid = DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => $color,
        'size' => $size,
        'points' => $points,
        'sequence_id' => $seq,
        'is_start' => $is_start
    ]);

    jsonResponse(['success' => true, 'id' => $sid]);
}

// 4. CLEAR ACTION
if ($action === 'clear') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    $sid = DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => 'CLEAR',
        'size' => 0,
        'points' => '[]',
        'sequence_id' => 999999
    ]);
    jsonResponse(['success' => true, 'id' => $sid]);
}

// 5. UNDO ACTION
if ($action === 'undo') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    $sid = DB::insert('strokes', [
        'round_id' => $round['id'],
        'color' => 'UNDO',
        'size' => 0,
        'points' => '[]',
        'sequence_id' => 888888
    ]);
    jsonResponse(['success' => true, 'id' => $sid]);
}

// 6. FETCH STROKES ACTION
if ($action === 'fetch') {
    $last_id = intval($_GET['last_id'] ?? 0);
    $strokes = DB::fetchAll("SELECT id, color, size, points, is_start FROM strokes WHERE round_id = ? AND id > ? ORDER BY id ASC", [$round['id'], $last_id]);
    
    foreach ($strokes as &$s) {
        $s['points'] = json_decode($s['points']);
    }
    
    jsonResponse(['strokes' => $strokes]);
}

// 7. FILL ACTION
if ($action === 'fill') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    $color = $_POST['color'] ?? '#000000';
    $x = floatval($_POST['x'] ?? 0);
    $y = floatval($_POST['y'] ?? 0);
    // Store fill as special stroke; points contains the normalized click coord
    $points = json_encode([['x' => $x, 'y' => $y]]);
    $sid = DB::insert('strokes', [
        'round_id'    => $round['id'],
        'color'       => 'FILL:' . $color,
        'size'        => 0,
        'points'      => $points,
        'sequence_id' => 0
    ]);
    jsonResponse(['success' => true, 'id' => $sid]);
}

jsonResponse(['error' => 'Invalid action'], false);
