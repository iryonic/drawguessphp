<?php
require_once 'db.php';

$token = sanitize($conn, $_POST['token'] ?? $_GET['token'] ?? '');
$player_q = mysqli_query($conn, "SELECT id, room_id, username, score FROM players WHERE session_token = '$token'");
if (mysqli_num_rows($player_q) === 0) jsonResponse(['error' => 'Invalid Session'], false);

$player = mysqli_fetch_assoc($player_q);
$room_id = $player['room_id'];

$action = $_POST['action'] ?? 'fetch';

// Get Round
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
    
    // 1. Drawer blocked from chat during drawing
    if ($round && $round['status'] === 'drawing' && $round['drawer_id'] == $player['id']) {
        jsonResponse(['error' => 'Drawer can not chat!'], false);
    }
    
    // 2. Guess Logic
    if ($round && $round['status'] === 'drawing') {
        if (strcasecmp(trim($msg), $current_word) === 0) {
            // Check if already guessed
             $chk = mysqli_query($conn, "SELECT id FROM messages WHERE round_id = {$round['id']} AND player_id = {$player['id']} AND type = 'guess'");
             if (mysqli_num_rows($chk) > 0) {
                  jsonResponse(['success' => true]); // Ignore duplicate
             }
             
             // SCORING (Low Intensity: Max ~50 per round)
             $rank_bonus = ($rank == 0) ? 10 : (($rank == 1) ? 5 : (($rank == 2) ? 2 : 0));
             
             // Time & Duration
             $room_s_q = mysqli_query($conn, "SELECT round_duration FROM rooms WHERE id = $room_id");
             $dur = intval(mysqli_fetch_assoc($room_s_q)['round_duration'] ?? 60);
             $elapsed = time() - strtotime($round['start_time']);
             $time_left = max(0, $dur - $elapsed);
             
             // Hint Penalty
             $hints = 0;
             if (!empty($round['hints_mask'])) {
                 $hints = count(explode(',', $round['hints_mask']));
             }
             
             // Formula: Max 40 (time) + 10 (rank) - penalty
             $score = ceil(40 * ($time_left / $dur)) + $rank_bonus - ($hints * 5);
             $score = max(1, $score);
             
             // Update Guesser
             mysqli_query($conn, "UPDATE players SET score = score + $score WHERE id = {$player['id']}");
             
             // Removed Drawer update here to prevent double-counting (handled in game_state.php)
             
             // Log Guess (type=guess)
             mysqli_query($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, {$round['id']}, {$player['id']}, 'guessed the word correctly!', 'guess')");
             
             // Check End Round (All guessed?)
             $p_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM players WHERE room_id = $room_id AND id != {$round['drawer_id']}");
             $total_guessers = intval(mysqli_fetch_assoc($p_q)['c']);
             $guessed_now = $rank + 1;
             
             if ($guessed_now >= $total_guessers) {
                  mysqli_query($conn, "UPDATE rounds SET status = 'ended' WHERE id = {$round['id']}");
             }
             
             jsonResponse(['success' => true]);
        }
        
        // Bonus: "Close!" check (if off by 1-2 chars)
        $dist = levenshtein(strtolower(trim($msg)), strtolower($current_word));
        if ($dist <= 2 && $dist > 0 && strlen($current_word) > 3) {
            mysqli_query($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, {$round['id']}, {$player['id']}, 'is so close!', 'system')");
        }
    }
    
    // Normal chat
    $sql = "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, " . ($round ? $round['id'] : 'NULL') . ", {$player['id']}, '$msg', '$type')";
    mysqli_query($conn, $sql);
    jsonResponse(['success' => true]);
}

if ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    // Improved query with JOIN to get usernames in one go
    $sql = "SELECT m.id, m.player_id, m.message, m.type, m.created_at, p.username 
            FROM messages m 
            LEFT JOIN players p ON m.player_id = p.id 
            WHERE m.room_id = $room_id AND m.id > $last_id 
            ORDER BY m.id ASC";
            
    $res = mysqli_query($conn, $sql);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (!$row['username']) {
            $row['username'] = 'System';
        }
        
        if ($row['type'] === 'guess') {
            $row['message'] = "guessed the word correctly!";
            $row['is_system'] = true;
        } elseif ($row['type'] === 'system') {
            $row['is_system'] = true;
        }
        $messages[] = $row;
    }
    
    jsonResponse(['messages' => $messages]);
}
?>
