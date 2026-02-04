<?php
require_once 'db.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$player_q = mysqli_query($conn, "SELECT id, room_id, username, score FROM players WHERE session_token = '$token'");
if (mysqli_num_rows($player_q) === 0) jsonResponse(['error' => 'Invalid'], false);

$player = mysqli_fetch_assoc($player_q);
$room_id = $player['room_id'];

$action = $_POST['action'] ?? 'fetch';

// Get Current Round info for guessing
$round_q = mysqli_query($conn, "SELECT * FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
$round = mysqli_fetch_assoc($round_q);
$current_word = '';
if ($round && $round['word_id']) {
    $wq = mysqli_query($conn, "SELECT word FROM words WHERE id = {$round['word_id']}");
    $wrow = mysqli_fetch_assoc($wq);
    $current_word = $wrow['word'];
}

if ($action === 'send') {
    $msg = sanitize($conn, $_POST['message'] ?? '');
    if (!$msg) jsonResponse(['error' => 'Empty'], false);

    $type = 'chat';
    $is_correct = false;

    // Check guess
    if ($round && $round['status'] === 'drawing' && $round['drawer_id'] != $player['id']) {
        if (strcasecmp(trim($msg), $current_word) === 0) {
            // Correct!
            $is_correct = true;
            $type = 'guess';
            $msg = "guessed the word!";
            
            // Checking if already guessed?
            // Need a "guesses" table or check messages?
            // Check if this player already guessed correctly in this round
            $chk = mysqli_query($conn, "SELECT id FROM messages WHERE round_id = {$round['id']} AND player_id = {$player['id']} AND type = 'guess'");
            if (mysqli_num_rows($chk) == 0) {
                 // Award points
                $points = 100; // Base points
                
                // Bonus based on time?
                $time_elapsed = time() - strtotime($round['start_time']);
                $points += max(0, 50 - $time_elapsed); // simple calc
                
                // Update Score
                $new_score = $player['score'] + $points;
                mysqli_query($conn, "UPDATE players SET score = $new_score WHERE id = {$player['id']}");
                
                // Also give points to drawer?
                // Increment drawer score by 10 per guess
                mysqli_query($conn, "UPDATE players SET score = score + 10 WHERE id = {$round['drawer_id']}");
            } else {
                 // Already guessed, prevent spamming "guessed!"
                 jsonResponse(['success' => true]); // Silent success
            }
        }
    }
    
    // If it's a guess but incorrect, we show it? Yes.
    // If correct, we show "Guessed the word!" system style messsage.

    $sql = "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, " . ($round ? $round['id'] : 'NULL') . ", {$player['id']}, '$msg', '$type')";
    mysqli_query($conn, $sql);
    jsonResponse(['success' => true]);
}

if ($action === 'fetch') { // Fetch
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    // Fetch messages for this room that are newer than last_id
    // Logic: WHERE room_id = ? AND id > ?
    $stmt = mysqli_prepare($conn, "SELECT id, player_id, message, type, created_at FROM messages WHERE room_id = ? AND id > ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "ii", $room_id, $last_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Get username
        $p_res = mysqli_query($conn, "SELECT username FROM players WHERE id = " . $row['player_id']);
        $p_row = mysqli_fetch_assoc($p_res);
        $row['username'] = $p_row ? $p_row['username'] : 'System';
        $messages[] = $row;
    }
    
    jsonResponse(['messages' => $messages]);
}
?>
