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
             $chk_q = mysqli_query($conn, "SELECT id FROM messages WHERE round_id = {$round['id']} AND player_id = {$player['id']} AND type = 'guess'");
             if (mysqli_num_rows($chk_q) > 0) {
                  jsonResponse(['success' => true]); // Ignore duplicate
             }
             
             // Calculate Rank (how many have already guessed)
             $rank_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE round_id = {$round['id']} AND type = 'guess'");
             $rank = intval(mysqli_fetch_assoc($rank_q)['c']);
             
             // SCORING
             $rank_bonus = ($rank == 0) ? 10 : (($rank == 1) ? 5 : (($rank == 2) ? 2 : 0));
             
             // Time & Duration (Using SQL for consistency)
             $time_q = mysqli_query($conn, "SELECT round_duration, TIMESTAMPDIFF(SECOND, start_time, NOW()) as elapsed FROM rounds r JOIN rooms rm ON r.room_id = rm.id WHERE r.id = {$round['id']}");
             $time_info = mysqli_fetch_assoc($time_q);
             $dur = intval($time_info['round_duration'] ?? 60);
             $elapsed = intval($time_info['elapsed'] ?? 0);
             $time_left = max(0, $dur - $elapsed);
             
             // Hint Penalty
             $hints = 0;
             if (!empty($round['hints_mask'])) {
                 $hints = count(explode(',', $round['hints_mask']));
             }
             
             // Formula: Max 40 (time) + 10 (rank) - penalty
             $score = ceil(40 * ($time_left / $dur)) + $rank_bonus - ($hints * 5);
             $score = max(5, $score); // Minimum 5 points for correct guess
             
             // Update Guesser
             mysqli_query($conn, "UPDATE players SET score = score + $score WHERE id = {$player['id']}");
             
             // Log Guess (type=guess)
             $stmt = mysqli_prepare($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES (?, ?, ?, ?, 'guess')");
             $guess_msg = "guessed the word correctly!";
             mysqli_stmt_bind_param($stmt, "iiis", $room_id, $round['id'], $player['id'], $guess_msg);
             mysqli_stmt_execute($stmt);
             
             // Check End Round (All guessed?)
             $p_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM players WHERE room_id = $room_id AND id != {$round['drawer_id']}");
             $total_possible_guessers = intval(mysqli_fetch_assoc($p_q)['c']);
             $guessed_now = $rank + 1;
             
             if ($guessed_now >= $total_possible_guessers) {
                  mysqli_query($conn, "UPDATE rounds SET status = 'ended', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE id = {$round['id']}");
             }
             
             jsonResponse(['success' => true]);
        }
        
        // Bonus: "Close!" check (if off by 1-2 chars)
        $dist = levenshtein(strtolower(trim($msg)), strtolower($current_word));
        if ($dist <= 2 && $dist > 0 && strlen($current_word) > 3) {
            $stmt = mysqli_prepare($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES (?, ?, ?, 'is so close!', 'system')");
            mysqli_stmt_bind_param($stmt, "iii", $room_id, $round['id'], $player['id']);
            mysqli_stmt_execute($stmt);
        }
    }
    
    // Normal chat
    $stmt = mysqli_prepare($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES (?, ?, ?, ?, ?)");
    $round_id_val = $round ? $round['id'] : null;
    mysqli_stmt_bind_param($stmt, "iiiss", $room_id, $round_id_val, $player['id'], $msg, $type);
    mysqli_stmt_execute($stmt);
    
    jsonResponse(['success' => true]);
}

if ($action === 'reaction') {
    $emoji = sanitize($conn, $_POST['emoji'] ?? '');
    if (!$emoji) jsonResponse(['error' => 'No emoji'], false);
    
    mysqli_query($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, " . ($round ? $round['id'] : 'NULL') . ", {$player['id']}, '$emoji', 'reaction')");
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
