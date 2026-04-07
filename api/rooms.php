<?php
/**
 * Room Management API
 * Refactored to use PDO and Prepared Statements.
 */
require_once 'db.php';

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    // 1. Validation
    $username = trim($_POST['username'] ?? '');
    if (empty($username)) jsonResponse(['error' => 'Username is required'], false);
    
    $avatar = $_POST['avatar'] ?? '🐱';
    $max_rounds = intval($_POST['max_rounds'] ?? 3);
    $round_duration = intval($_POST['round_duration'] ?? 60);

    // 2. Generate Unique Room Code
    do {
        $room_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        $exists = DB::fetch("SELECT id FROM rooms WHERE room_code = ?", [$room_code]);
    } while ($exists);

    // 3. Create Room
    $room_id = DB::insert('rooms', [
        'room_code' => $room_code,
        'status' => 'lobby',
        'max_rounds' => $max_rounds,
        'round_duration' => $round_duration
    ]);

    // 4. Create Host Player
    $token = bin2hex(random_bytes(16));
    $player_id = DB::insert('players', [
        'room_id' => $room_id,
        'username' => $username,
        'avatar' => $avatar,
        'is_host' => 1,
        'session_token' => $token
    ]);

    // 5. Link Host to Room
    DB::query("UPDATE rooms SET host_id = ? WHERE id = ?", [$player_id, $room_id]);

    jsonResponse([
        'room_id' => $room_id,
        'room_code' => $room_code,
        'player_id' => $player_id,
        'username' => $username,
        'avatar' => $avatar,
        'is_host' => true,
        'token' => $token
    ]);

} elseif ($action === 'join') {
    $username = trim($_POST['username'] ?? '');
    $room_code = strtoupper(trim($_POST['room_code'] ?? ''));
    $avatar = $_POST['avatar'] ?? '🐱';

    if (empty($username) || empty($room_code)) {
        jsonResponse(['error' => 'Username and Room Code are required'], false);
    }

    // 1. Find Room
    $room = DB::fetch("SELECT id, status FROM rooms WHERE room_code = ?", [$room_code]);
    if (!$room) {
        jsonResponse(['error' => 'Room not found'], false);
    }

    if ($room['status'] !== 'lobby') {
        jsonResponse(['error' => 'Game already in progress'], false);
    }

    // 2. Check Uniqueness
    $conflict = DB::fetch("SELECT id FROM players WHERE room_id = ? AND username = ?", [$room['id'], $username]);
    if ($conflict) {
        jsonResponse(['error' => 'Username taken in this room'], false);
    }

    // 3. Add Player
    $token = bin2hex(random_bytes(16));
    $pid = DB::insert('players', [
        'room_id' => $room['id'],
        'username' => $username,
        'avatar' => $avatar,
        'is_host' => 0,
        'session_token' => $token
    ]);

    // 4. System Message
    DB::insert('messages', [
        'room_id' => $room['id'],
        'message' => "$username joined the room",
        'type' => 'system'
    ]);

    jsonResponse([
        'room_id' => $room['id'],
        'room_code' => $room_code,
        'player_id' => $pid,
        'username' => $username,
        'avatar' => $avatar,
        'is_host' => false,
        'token' => $token
    ]);

} elseif ($action === 'leave') {
    $token = $_POST['token'] ?? '';
    $player = DB::fetch("SELECT id, room_id, username FROM players WHERE session_token = ?", [$token]);
    
    if ($player) {
        DB::query("DELETE FROM players WHERE id = ?", [$player['id']]);
        DB::insert('messages', [
            'room_id' => $player['room_id'],
            'message' => "{$player['username']} left the room",
            'type' => 'system'
        ]);
        jsonResponse(['success' => true]);
    }
    jsonResponse(['error' => 'Not found'], false);

} else {
    jsonResponse(['error' => 'Invalid action'], false);
}
