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
    $r_chk = mysqli_query($conn, "SELECT status FROM rooms WHERE id = $room_id");
    $r_row = mysqli_fetch_assoc($r_chk);
    
    if ($r_row['status'] === 'lobby' || $r_row['status'] === 'finished') {
        // Init Game: Shuffle Orders
        $pq = mysqli_query($conn, "SELECT id FROM players WHERE room_id = $room_id");
        $p_ids = [];
        while($p = mysqli_fetch_assoc($pq)) $p_ids[] = $p['id'];
        
        shuffle($p_ids);
        foreach($p_ids as $idx => $pid) {
            mysqli_query($conn, "UPDATE players SET turn_order = $idx, score = 0 WHERE id = $pid");
        }
        
        // Reset Room
        mysqli_query($conn, "UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = $room_id");
        mysqli_query($conn, "DELETE FROM rounds WHERE room_id = $room_id"); // Clear old history
        
        startNextTurn($conn, $room_id);
        jsonResponse(['success' => true]);
    }
}

if ($action === 'next_turn' && $is_host) {
     //$round_duration = 5; // Intermission?
     // Just start next turn immediately if requested
     startNextTurn($conn, $room_id);
     jsonResponse(['success' => true]);
}

if ($action === 'select_word') {
    $word_id = intval($_POST['word_id']);
    $round_q = mysqli_query($conn, "SELECT * FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
    $round = mysqli_fetch_assoc($round_q);
    
    if ($round['drawer_id'] == $player_id && $round['status'] == 'choosing') { 
        // Start Countdown (3s)
        mysqli_query($conn, "UPDATE rounds SET word_id = $word_id, status = 'countdown', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 3 SECOND) WHERE id = {$round['id']}");
        jsonResponse(['success' => true]);
    }
}

// --- STATE MACHINE (Hints & Timeouts) ---
$round_q = mysqli_query($conn, "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), end_time) as sql_time_left, TIMESTAMPDIFF(SECOND, start_time, NOW()) as elapsed FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
$round = mysqli_fetch_assoc($round_q);

if ($round) {
    // Current state time left
    $timeLeft = intval($round['sql_time_left']);
    $elapsed = intval($round['elapsed']);

    // 1. COUNTDOWN -> DRAWING
    if ($round['status'] === 'countdown') {
        if ($timeLeft <= 0) {
             // Get Duration
             $room_settings_q = mysqli_query($conn, "SELECT round_duration FROM rooms WHERE id = $room_id");
             $room_settings = mysqli_fetch_assoc($room_settings_q);
             $duration = (isset($room_settings['round_duration']) && $room_settings['round_duration'] > 0) ? intval($room_settings['round_duration']) : 60;
             
             mysqli_query($conn, "UPDATE rounds SET status = 'drawing', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL $duration SECOND) WHERE id = {$round['id']}");
             $round['status'] = 'drawing'; 
             // Note: Response will pick up new time next poll
        }
    }
    
    // 2. DRAWING -> ENDED
    elseif ($round['status'] === 'drawing') {
        if ($timeLeft <= 0) {
            // Draw time over. Calculate Drawer Score.
            $guess_q = mysqli_query($conn, "SELECT COUNT(DISTINCT player_id) as cnt FROM messages WHERE round_id = {$round['id']} AND type = 'guess'");
            $g_row = mysqli_fetch_assoc($guess_q);
            $correct_guesses = intval($g_row['cnt']);
            
            if ($correct_guesses > 0) {
                // Drawer Bonus: 50 * correct (as decided)
                $drawer_points = $correct_guesses * 50;
                 mysqli_query($conn, "UPDATE players SET score = score + $drawer_points WHERE id = {$round['drawer_id']}");
            }

            // Move to ENDED (Scoreboard) for 10 seconds
            mysqli_query($conn, "UPDATE rounds SET status = 'ended', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE id = {$round['id']}");
            $round['status'] = 'ended';
        } 
        else {
            // Hint Logic (Same as before)
            if ($round['word_id']) {
                $hints_needed = floor($elapsed / 15);
                $current_mask = $round['hints_mask'] ? explode(',', $round['hints_mask']) : [];
                $current_count = count(array_filter($current_mask, 'is_numeric'));
                
                if ($current_count < $hints_needed) {
                    $wq = mysqli_query($conn, "SELECT word FROM words WHERE id = {$round['word_id']}");
                    $wrow = mysqli_fetch_assoc($wq);
                    $word_len = strlen($wrow['word']);
                    $available = [];
                    for ($i=0; $i < $word_len; $i++) {
                        if (!in_array($i, $current_mask) && $wrow['word'][$i] !== ' ') {
                            $available[] = $i;
                        }
                    }
                    if (!empty($available)) {
                        $idx_to_reveal = $available[array_rand($available)];
                        $current_mask[] = $idx_to_reveal;
                        $new_mask_str = implode(',', $current_mask);
                        mysqli_query($conn, "UPDATE rounds SET hints_mask = '$new_mask_str' WHERE id = {$round['id']}");
                        $round['hints_mask'] = $new_mask_str; // Update local
                    }
                }
            }
        }
    }
    
    // 3. ENDED -> NEXT TURN
    elseif ($round['status'] === 'ended') {
        if ($timeLeft <= 0) {
            startNextTurn($conn, $room_id);
            // $round status will be updated next poll
        }
    }
}

// --- BUILD RESPONSE ---

// Room
$room_res = mysqli_query($conn, "SELECT * FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_res);

// Players
$players_res = mysqli_query($conn, "SELECT id, username, avatar, score, is_host FROM players WHERE room_id = $room_id ORDER BY score DESC"); 
// Note: Ordering by score for leaderboard
$players = [];
while ($row = mysqli_fetch_assoc($players_res)) {
    $row['is_me'] = ($row['id'] == $player_id);
    $row['is_host'] = ((int)$row['is_host'] === 1); 
    $players[] = $row;
}

// Round
$current_round_data = [];
if ($round) {
    $current_round_data = [
        'id' => $round['id'],
        'status' => $round['status'],
        'drawer_id' => $round['drawer_id'],
        'time_left' => ($round['status'] == 'drawing') ? max(0, intval($round['sql_time_left'])) : 0,
        'round_number' => $round['round_number'], // Global round
        'elapsed' => $round['elapsed'] ?? 0
    ];
    
    // Word Logic
    $word_display = "";
    if ($round['word_id']) {
        $wq = mysqli_query($conn, "SELECT word FROM words WHERE id = {$round['word_id']}");
        $wrow = mysqli_fetch_assoc($wq);
        $full_word = $wrow['word'];
        
        if ($player_id == $round['drawer_id'] || $round['status'] === 'ended') {
            $word_display = $full_word;
        } else {
            // Mask it
            $mask_indices = $round['hints_mask'] ? explode(',', $round['hints_mask']) : [];
            for ($i=0; $i < strlen($full_word); $i++) {
                if ($full_word[$i] === ' ') {
                    $word_display .= "  "; // Double space for gap
                } elseif (in_array($i, $mask_indices)) {
                    $word_display .= $full_word[$i] . " ";
                } else {
                    $word_display .= "_ ";
                }
            }
        }
    }
    $current_round_data['word'] = trim($word_display);
    
    // Choosing Options
    if ($round['status'] === 'choosing' && $round['drawer_id'] == $player_id) {
         if (!empty($round['word_options'])) {
             $ids = $round['word_options'];
             $words_to_choose = []; 
             $wq = mysqli_query($conn, "SELECT * FROM words WHERE id IN ($ids)");
             while($w = mysqli_fetch_assoc($wq)) $words_to_choose[] = $w;
             $current_round_data['options'] = $words_to_choose;
         } else {
             // Generate
             $wq = mysqli_query($conn, "SELECT * FROM words ORDER BY RAND() LIMIT 3");
             $words = []; $ids = [];
             while($w = mysqli_fetch_assoc($wq)) { $words[] = $w; $ids[] = $w['id']; }
             $ids_str = implode(',', $ids);
             mysqli_query($conn, "UPDATE rounds SET word_options = '$ids_str' WHERE id = {$round['id']}");
             $current_round_data['options'] = $words;
         }
    }
}

jsonResponse([
    'room' => $room,
    'players' => $players,
    'round' => $current_round_data,
    'me' => $player_id
]);


function startNextTurn($conn, $room_id) {
    // Determine Next Turn
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM rounds WHERE room_id = $room_id");
    $cnt_row = mysqli_fetch_assoc($cnt_q);
    $turns_done = intval($cnt_row['c']);
    
    // Get constraints
    $r_res = mysqli_query($conn, "SELECT max_rounds FROM rooms WHERE id = $room_id");
    $r_info = mysqli_fetch_assoc($r_res);
    $max_rounds = intval($r_info['max_rounds']);
    
    $p_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM players WHERE room_id = $room_id");
    $p_info = mysqli_fetch_assoc($p_res);
    $num_players = max(1, intval($p_info['c']));
    
    $global_round = floor($turns_done / $num_players) + 1;
    $turn_index = $turns_done % $num_players;
    
    if ($global_round > $max_rounds) {
        mysqli_query($conn, "UPDATE rooms SET status = 'finished' WHERE id = $room_id");
        return;
    }
    
    mysqli_query($conn, "UPDATE rooms SET current_round = $global_round WHERE id = $room_id");
    
    // Find drawer
    $d_q = mysqli_query($conn, "SELECT id FROM players WHERE room_id = $room_id AND turn_order = $turn_index");
    $d_row = mysqli_fetch_assoc($d_q);
    $drawer_id = $d_row ? $d_row['id'] : 0; // Should exist if sync is good
    
    mysqli_query($conn, "INSERT INTO rounds (room_id, round_number, drawer_id, status) VALUES ($room_id, $global_round, $drawer_id, 'choosing')");
}
?>
