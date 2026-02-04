<?php
require_once 'db.php';

// Auth check
$token = $_POST['token'] ?? '';
$player_q = mysqli_query($conn, "SELECT * FROM players WHERE session_token = '$token'");
if (mysqli_num_rows($player_q) === 0) {
    jsonResponse(['error' => 'Invalid Session'], false);
}
$player = mysqli_fetch_assoc($player_q);
$room_id = $player['room_id'];
$player_id = $player['id'];
$is_host = $player['is_host'];

// Update Last Active
mysqli_query($conn, "UPDATE players SET last_active = NOW() WHERE id = $player_id");

$action = $_POST['action'] ?? 'sync';

if ($action === 'start_game' && $is_host) {
    // Check if game already started
    $r_chk = mysqli_query($conn, "SELECT status, current_round, max_rounds FROM rooms WHERE id = $room_id");
    $r_row = mysqli_fetch_assoc($r_chk);
    
    if ($r_row['status'] === 'lobby') {
        mysqli_query($conn, "UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = $room_id");
        // Create first round
        startNewRound($conn, $room_id, 1);
        jsonResponse(['success' => true]);
    } elseif ($r_row['status'] === 'playing') {
        // Assume trying to start next round
        // Check if current round is ended?
        $cr_q = mysqli_query($conn, "SELECT status FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
        $cr = mysqli_fetch_assoc($cr_q);
        
        if ($cr && $cr['status'] === 'ended') {
             if ($r_row['current_round'] < $r_row['max_rounds']) {
                 $next_round = $r_row['current_round'] + 1;
                 mysqli_query($conn, "UPDATE rooms SET current_round = $next_round WHERE id = $room_id");
                 startNewRound($conn, $room_id, $next_round);
                 jsonResponse(['success' => true]);
             } else {
                 mysqli_query($conn, "UPDATE rooms SET status = 'finished' WHERE id = $room_id");
                 jsonResponse(['finished' => true]);
             }
        }
    }
}

if ($action === 'select_word') {
    // Only drawer can do this
    $word_id = intval($_POST['word_id']);
    
    // Validate required round status
    $round_q = mysqli_query($conn, "SELECT * FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
    $round = mysqli_fetch_assoc($round_q);
    
    if ($round['drawer_id'] == $player_id && $round['status'] == 'choosing') { 
        // Fetch room duration - explicit check
        $room_settings_q = mysqli_query($conn, "SELECT round_duration FROM rooms WHERE id = $room_id");
        $room_settings = mysqli_fetch_assoc($room_settings_q);
        $duration = (isset($room_settings['round_duration']) && $room_settings['round_duration'] > 0) ? intval($room_settings['round_duration']) : 60;
        
        mysqli_query($conn, "UPDATE rounds SET word_id = $word_id, status = 'drawing', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL $duration SECOND) WHERE id = {$round['id']}");
        jsonResponse(['success' => true]);
    }
}

// Check for timeouts (State Machine)
$round_q = mysqli_query($conn, "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), end_time) as sql_time_left FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
$round = mysqli_fetch_assoc($round_q);

if ($round) {
    if ($round['status'] === 'drawing') {
        // Use SQL calculated time left
        $timeLeft = intval($round['sql_time_left']);
        if ($timeLeft <= 0) {
            // Calculate Drawer Score
            $guess_q = mysqli_query($conn, "SELECT COUNT(DISTINCT player_id) as cnt FROM messages WHERE round_id = {$round['id']} AND type = 'guess'");
            $g_row = mysqli_fetch_assoc($guess_q);
            $correct_guesses = $g_row['cnt'];
            
            if ($correct_guesses > 0) {
                // Improved Drawer Score: 10 + (5 * correct_guesses)
                $drawer_points = 10 + ($correct_guesses * 5);
                 mysqli_query($conn, "UPDATE players SET score = score + $drawer_points WHERE id = {$round['drawer_id']}");
            }

            // Round End
            mysqli_query($conn, "UPDATE rounds SET status = 'ended' WHERE id = {$round['id']}");
            // ... (rest of logic)
            // Re-fetch round to ensure 'ended' status for response
             $round_q = mysqli_query($conn, "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), end_time) as sql_time_left FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
             $round = mysqli_fetch_assoc($round_q);
        }
    }
}

// ...

// Current Round
$current_round = [];
if ($round) {
    $current_round = [
        'id' => $round['id'],
        'status' => $round['status'], // choosing, drawing, ended
        'drawer_id' => $round['drawer_id'],
        'start_time' => $round['start_time'],
        'end_time' => $round['end_time'],
        'time_left' => ($round['status'] == 'drawing') ? max(0, intval($round['sql_time_left'])) : 0
    ];
    // ...
}
    
    // Hide word if not drawer and drawing
    if ($player_id == $round['drawer_id'] || $round['status'] === 'ended') {
        // Fetch word
        if ($round['word_id']) {
            $wq = mysqli_query($conn, "SELECT word FROM words WHERE id = {$round['word_id']}");
            $wrow = mysqli_fetch_assoc($wq);
            $current_round['word'] = $wrow['word'];
        }
    } else {
        // Hint (length)
        if ($round['word_id']) {
            $wq = mysqli_query($conn, "SELECT word FROM words WHERE id = {$round['word_id']}");
            $wrow = mysqli_fetch_assoc($wq);
            $current_round['word_length'] = strlen($wrow['word']);
            $current_round['word'] = str_repeat('_', strlen($wrow['word'])); // Masked
        }
    }
}

// Words to choose (if choosing and drawer)
$words_to_choose = [];
if ($round && $round['status'] === 'choosing' && $round['drawer_id'] == $player_id) {
     // Check if we already have options for this round
     if (!empty($round['word_options'])) {
         $ids = $round['word_options'];
         $wq = mysqli_query($conn, "SELECT * FROM words WHERE id IN ($ids)");
         while($w = mysqli_fetch_assoc($wq)) {
             $words_to_choose[] = $w;
         }
     } else {
         // Generate new options
         $wq = mysqli_query($conn, "SELECT * FROM words ORDER BY RAND() LIMIT 3");
         $ids_arr = [];
         while($w = mysqli_fetch_assoc($wq)) {
             $words_to_choose[] = $w;
             $ids_arr[] = $w['id'];
         }
         
         if (!empty($ids_arr)) {
             $ids_str = implode(',', $ids_arr);
             mysqli_query($conn, "UPDATE rounds SET word_options = '$ids_str' WHERE id = {$round['id']}");
         }
     }
}

jsonResponse([
    'room' => $room,
    'players' => $players,
    'round' => $current_round,
    'words' => $words_to_choose,
    'me' => $player_id
]);

// Helper to start round
function startNewRound($conn, $room_id, $round_num) {
    // Pick drawer: Round Robin or Random?
    // Simple: Random from players
    $p_res = mysqli_query($conn, "SELECT id FROM players WHERE room_id = $room_id ORDER BY RAND() LIMIT 1");
    $p_row = mysqli_fetch_assoc($p_res);
    $drawer_id = $p_row['id'];
    
    $sql = "INSERT INTO rounds (room_id, round_number, drawer_id, status) VALUES ($room_id, $round_num, $drawer_id, 'choosing')";
    mysqli_query($conn, $sql);
}
?>
