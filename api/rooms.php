<?php
require_once 'db.php';

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $username = sanitize($conn, $_POST['username'] ?? 'Player');
    $avatar = sanitize($conn, $_POST['avatar'] ?? '🐱');
    
    // Generate Room Code
    $room_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    // Ensure unique (simple retry once)
    $check = mysqli_query($conn, "SELECT id FROM rooms WHERE room_code = '$room_code'");
    if (mysqli_num_rows($check) > 0) {
        $room_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    }

    $max_rounds = intval($_POST['max_rounds'] ?? 3);
    $round_duration = intval($_POST['round_duration'] ?? 60);

    // Create Room
    $sql = "INSERT INTO rooms (room_code, status, max_rounds, round_duration) VALUES ('$room_code', 'lobby', $max_rounds, $round_duration)";
    if (mysqli_query($conn, $sql)) {
        $room_id = mysqli_insert_id($conn);
        
        // Add Host Player
        $token = bin2hex(random_bytes(16));
        $player_sql = "INSERT INTO players (room_id, username, avatar, is_host, session_token) 
                       VALUES ('$room_id', '$username', '$avatar', 1, '$token')";
        
        if (mysqli_query($conn, $player_sql)) {
            $player_id = mysqli_insert_id($conn);
            
            // Update room host_id
            mysqli_query($conn, "UPDATE rooms SET host_id = $player_id WHERE id = $room_id");

            jsonResponse([
                'room_id' => $room_id,
                'room_code' => $room_code,
                'player_id' => $player_id,
                'username' => $username,
                'avatar' => $avatar,
                'is_host' => true,
                'token' => $token
            ]);
        }
    }
    jsonResponse(['error' => 'Failed to create room'], false);

} elseif ($action === 'join') {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $room_code = sanitize($conn, $_POST['room_code'] ?? '');
    $avatar = sanitize($conn, $_POST['avatar'] ?? '🐱');

    // Find Room
    $res = mysqli_query($conn, "SELECT id, status FROM rooms WHERE room_code = '$room_code'");
    if (mysqli_num_rows($res) === 0) {
        jsonResponse(['error' => 'Room not found'], false);
    }
    $room = mysqli_fetch_assoc($res);

    if ($room['status'] !== 'lobby') {
        // Allow re-join if token matches? Not handled here yet.
        jsonResponse(['error' => 'Game already in progress'], false);
    }

    // Check username uniqueness
    $u_check = mysqli_query($conn, "SELECT id FROM players WHERE room_id = {$room['id']} AND username = '$username'");
    if (mysqli_num_rows($u_check) > 0) {
        jsonResponse(['error' => 'Username taken in this room'], false);
    }

    // Add Player
    $token = bin2hex(random_bytes(16));
    $sql = "INSERT INTO players (room_id, username, avatar, is_host, session_token) 
            VALUES ('{$room['id']}', '$username', '$avatar', 0, '$token')";
    
    if (mysqli_query($conn, $sql)) {
        jsonResponse([
            'room_id' => $room['id'],
            'room_code' => $room_code,
            'player_id' => mysqli_insert_id($conn),
            'username' => $username,
            'avatar' => $avatar,
            'is_host' => false,
            'token' => $token
        ]);
    } else {
        jsonResponse(['error' => 'Failed to join room'], false);
    }
} else {
    jsonResponse(['error' => 'Invalid action'], false);
}
?>
