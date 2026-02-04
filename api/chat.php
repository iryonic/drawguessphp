<?php
require_once 'db.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$player_q = mysqli_query($conn, "SELECT id, room_id, username, score FROM players WHERE session_token = '$token'");
if (mysqli_num_rows($player_q) === 0) jsonResponse(['error' => 'Invalid'], false);

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
             
             // SCORING
             // Rank
             $rank_q = mysqli_query($conn, "SELECT COUNT(id) as cnt FROM messages WHERE round_id = {$round['id']} AND type = 'guess'");
             $rank = intval(mysqli_fetch_assoc($rank_q)['cnt']);
             $rank_bonus = ($rank == 0) ? 50 : (($rank == 1) ? 30 : (($rank == 2) ? 10 : 0));
             
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
             
             // Formula
             $score = ceil(300 * ($time_left / $dur)) + $rank_bonus - ($hints * 30);
             $score = max(10, $score);
             
             // Update Guesser
             mysqli_query($conn, "UPDATE players SET score = score + $score WHERE id = {$player['id']}");
             
             // Update Drawer
             mysqli_query($conn, "UPDATE players SET score = score + 30 WHERE id = {$round['drawer_id']}");
             
             // Log Guess (type=guess)
             mysqli_query($conn, "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, {$round['id']}, {$player['id']}, 'guessed the word!', 'guess')");
             
             // Check End Round (All guessed?)
             $p_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM players WHERE room_id = $room_id AND id != {$round['drawer_id']}");
             $total_guessers = intval(mysqli_fetch_assoc($p_q)['c']);
             $guessed_now = $rank + 1;
             
             if ($guessed_now >= $total_guessers) {
                  mysqli_query($conn, "UPDATE rounds SET status = 'ended' WHERE id = {$round['id']}");
             }
             
             jsonResponse(['success' => true]);
        }
    }
    
    // Normal chat
    $sql = "INSERT INTO messages (room_id, round_id, player_id, message, type) VALUES ($room_id, " . ($round ? $round['id'] : 'NULL') . ", {$player['id']}, '$msg', '$type')";
    mysqli_query($conn, $sql);
    jsonResponse(['success' => true]);
}

if ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    $stmt = mysqli_prepare($conn, "SELECT id, player_id, message, type, created_at FROM messages WHERE room_id = ? AND id > ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "ii", $room_id, $last_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['type'] === 'guess') {
            $p_res = mysqli_query($conn, "SELECT username FROM players WHERE id = " . $row['player_id']);
            $row['username'] = mysqli_fetch_assoc($p_res)['username'];
            $row['message'] = "guessed the word!";
            $row['is_system'] = true; // Use system style in UI
        } else {
            $p_res = mysqli_query($conn, "SELECT username FROM players WHERE id = " . $row['player_id']);
            $p_row = mysqli_fetch_assoc($p_res);
            $row['username'] = $p_row ? $p_row['username'] : 'System';
        }
        $messages[] = $row;
    }
    
    jsonResponse(['messages' => $messages]);
}
?>
