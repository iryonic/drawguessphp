<?php
require_once 'db.php';

// Auth
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$player_q = mysqli_query($conn, "SELECT id, room_id FROM players WHERE session_token = '$token'");

if (mysqli_num_rows($player_q) === 0) {
    jsonResponse(['error' => 'Invalid Session'], false);
}
$player = mysqli_fetch_assoc($player_q);
$room_id = $player['room_id'];

// Get Current Round
$round_q = mysqli_query($conn, "SELECT id, drawer_id, status FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
$round = mysqli_fetch_assoc($round_q);

if (!$round || $round['status'] !== 'drawing') {
    jsonResponse(['error' => 'Not drawing phase'], false);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';

if ($action === 'draw') {
    // Only drawer
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }

    $color = sanitize($conn, $_POST['color'] ?? '#000000');
    $size = intval($_POST['size'] ?? 5);
    $points = $_POST['points'] ?? '[]'; // JSON string
    $seq = intval($_POST['seq'] ?? 0);

    // Validate JSON
    json_decode($points);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $points = '[]';
    }

    // Insert
    // Use prepared statement for security with TEXT
    $stmt = mysqli_prepare($conn, "INSERT INTO strokes (round_id, color, size, points, sequence_id) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isisi", $round['id'], $color, $size, $points, $seq);
    
    if (mysqli_stmt_execute($stmt)) {
        jsonResponse(['success' => true, 'id' => mysqli_insert_id($conn)]);
    } else {
        jsonResponse(['error' => 'DB Error'], false);
    }
}

if ($action === 'clear') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    // We can delete all strokes or add a clear marker. 
    // Deleting is easier for "sync" unless we support undo. 
    // If we delete, clients polling "last_id" won't know to clear. 
    // So we MUST insert a marker.
    // Hack: size = 0 means clear? Or specific color?
    // Let's use points = 'CLEAR' or similar. 
    // Actually, I'll use a specific query to return Clear event.
    // Better: Insert a stroke with special 'color' = 'CLEAR'.
    mysqli_query($conn, "INSERT INTO strokes (round_id, color, size, points, sequence_id) VALUES ({$round['id']}, 'CLEAR', 0, '[]', 999999)");
    jsonResponse(['success' => true]);
}

if ($action === 'undo') {
    if ($player['id'] != $round['drawer_id']) {
        jsonResponse(['error' => 'Not your turn'], false);
    }
    // Marker for other clients to pop their last stroke
    mysqli_query($conn, "INSERT INTO strokes (round_id, color, size, points, sequence_id) VALUES ({$round['id']}, 'UNDO', 0, '[]', 888888)");
    jsonResponse(['success' => true]);
}

if ($action === 'fetch') {
    $last_id = intval($_GET['last_id'] ?? 0);
    
    // Fetch strokes
    $sql = "SELECT id, color, size, points FROM strokes WHERE round_id = {$round['id']} AND id > $last_id ORDER BY id ASC";
    $res = mysqli_query($conn, $sql);
    
    $strokes = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Decode points to save bandwidth? No, user wants JSON response.
        // But points is already a JSON string in DB. 
        // We will send it as object.
        $row['points'] = json_decode($row['points']);
        $strokes[] = $row;
    }
    
    jsonResponse(['strokes' => $strokes]);
}
?>
