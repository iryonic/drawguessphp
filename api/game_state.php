<?php
require_once 'db.php';

// Auth check
$token = sanitize($conn, $_POST['token'] ?? '');
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

// --- FETCH CONTEXT ---
$room_res = mysqli_query($conn, "SELECT * FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_res);

// Threshold: 30 seconds of silence
$inactivity_limit = 30;
$now_sql = "NOW()";

// 1. CLEANUP INACTIVE PLAYERS
$inactive_p_res = mysqli_query($conn, "SELECT id, username, is_host FROM players WHERE room_id = $room_id AND id != $player_id AND last_active < DATE_SUB($now_sql, INTERVAL $inactivity_limit SECOND)");

while ($dead_p = mysqli_fetch_assoc($inactive_p_res)) {
    $dead_id = $dead_p['id'];
    $dead_name = mysqli_real_escape_string($conn, $dead_p['username']);
    mysqli_query($conn, "DELETE FROM players WHERE id = $dead_id");
    mysqli_query($conn, "INSERT INTO messages (room_id, player_id, message, type) VALUES ($room_id, 0, '$dead_name left the room', 'system')");
}

// Ensure Host Exists
if (!$room['host_id'] || mysqli_num_rows(mysqli_query($conn, "SELECT id FROM players WHERE id = {$room['host_id']}")) === 0) {
    $new_host_res = mysqli_query($conn, "SELECT id, username FROM players WHERE room_id = $room_id ORDER BY id ASC LIMIT 1");
    if ($new_host = mysqli_fetch_assoc($new_host_res)) {
        $new_host_id = $new_host['id'];
        mysqli_query($conn, "UPDATE players SET is_host = 1 WHERE id = $new_host_id");
        mysqli_query($conn, "UPDATE rooms SET host_id = $new_host_id WHERE id = $room_id");
        $h_name = mysqli_real_escape_string($conn, $new_host['username']);
        mysqli_query($conn, "INSERT INTO messages (room_id, player_id, message, type) VALUES ($room_id, 0, '$h_name is now the host', 'system')");
        if ($new_host_id == $player_id) $is_host = 1;
        // Refresh room data
        $room['host_id'] = $new_host_id;
    }
}

$action = $_POST['action'] ?? 'sync';
// ... (Game Actions logic kept same) ...
if ($action === 'start_game' && $is_host) {
    if ($room['status'] === 'lobby' || $room['status'] === 'finished') {
        $pq = mysqli_query($conn, "SELECT id FROM players WHERE room_id = $room_id");
        $p_ids = [];
        while($p = mysqli_fetch_assoc($pq)) $p_ids[] = $p['id'];
        shuffle($p_ids);
        foreach($p_ids as $idx => $pid) {
            mysqli_query($conn, "UPDATE players SET turn_order = $idx, score = 0 WHERE id = $pid");
        }
        mysqli_query($conn, "UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = $room_id");
        mysqli_query($conn, "DELETE FROM rounds WHERE room_id = $room_id");
        startNextTurn($conn, $room_id);
        jsonResponse(['success' => true]);
    }
}

if ($action === 'select_word') {
    $word_id = intval($_POST['word_id']);
    $round_q = mysqli_query($conn, "SELECT * FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
    $round = mysqli_fetch_assoc($round_q);
    if ($round && $round['drawer_id'] == $player_id && $round['status'] == 'choosing') { 
        mysqli_query($conn, "UPDATE rounds SET word_id = $word_id, status = 'countdown', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 3 SECOND) WHERE id = {$round['id']}");
        jsonResponse(['success' => true]);
    }
}

// --- STATE MACHINE ---
$round_q = mysqli_query($conn, "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), end_time) as sql_time_left, TIMESTAMPDIFF(SECOND, start_time, NOW()) as elapsed FROM rounds WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
$round = mysqli_fetch_assoc($round_q);

if ($round) {
    $timeLeft = intval($round['sql_time_left']);
    $elapsed = intval($round['elapsed']);
    $drawer_id = (int)$round['drawer_id'];

    // Drawer Disconnect Check
    if ($round['status'] !== 'ended' && $round['status'] !== 'game_over') {
        $drawer_check = mysqli_query($conn, "SELECT id FROM players WHERE id = $drawer_id");
        if (mysqli_num_rows($drawer_check) === 0) {
            mysqli_query($conn, "UPDATE rounds SET status = 'ended', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 5 SECOND) WHERE id = {$round['id']}");
            $round['status'] = 'ended';
            $round['sql_time_left'] = 5;
            mysqli_query($conn, "INSERT INTO messages (room_id, player_id, message, type) VALUES ($room_id, 0, 'Drawer disconnected! Skipping...', 'system')");
        }
    }

    // 0. CHOOSING -> Countdown
    if ($round['status'] === 'choosing' && $timeLeft <= 0) {
        $options = !empty($round['word_options']) ? explode(',', $round['word_options']) : [];
        if (empty($options)) {
             $wq = mysqli_query($conn, "SELECT id FROM words ORDER BY RAND() LIMIT 3");
             while($w = mysqli_fetch_assoc($wq)) $options[] = $w['id'];
             $ids_str = implode(',', $options);
             mysqli_query($conn, "UPDATE rounds SET word_options = '$ids_str' WHERE id = {$round['id']}");
        }
        $word_id = $options[array_rand($options)];
        mysqli_query($conn, "UPDATE rounds SET word_id = $word_id, status = 'countdown', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 3 SECOND) WHERE id = {$round['id']}");
        $round['status'] = 'countdown';
    }

    // 1. COUNTDOWN -> DRAWING
    if ($round['status'] === 'countdown' && $timeLeft <= 0) {
        $duration = intval($room['round_duration'] ?: 60);
        mysqli_query($conn, "UPDATE rounds SET status = 'drawing', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL $duration SECOND) WHERE id = {$round['id']}");
        $round['status'] = 'drawing';
    }

    // 2. DRAWING -> ENDED
    elseif ($round['status'] === 'drawing') {
        if ($timeLeft <= 0) {
            $g_res = mysqli_query($conn, "SELECT COUNT(DISTINCT player_id) as cnt FROM messages WHERE round_id = {$round['id']} AND type = 'guess'");
            $correct_guesses = intval(mysqli_fetch_assoc($g_res)['cnt']);
            if ($correct_guesses > 0) {
                mysqli_query($conn, "UPDATE players SET score = score + ($correct_guesses * 3) WHERE id = $drawer_id");
            }
            mysqli_query($conn, "UPDATE rounds SET status = 'ended', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE id = {$round['id']}");
            $round['status'] = 'ended';
        } else {
            // Check if ALL players (excluding drawer) guessed
            $active_p_cnt = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM players WHERE room_id = $room_id AND id != $drawer_id"))['c']);
            $guessed_cnt = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT player_id) as c FROM messages WHERE round_id = {$round['id']} AND type = 'guess'"))['c']);
            
            if ($active_p_cnt > 0 && $guessed_cnt >= $active_p_cnt) {
                // End early
                mysqli_query($conn, "UPDATE rounds SET status = 'ended', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 8 SECOND) WHERE id = {$round['id']}");
                $round['status'] = 'ended';
            } else {
                // Dynamic Hints
                $word_id = (int)$round['word_id'];
                $word_q = mysqli_query($conn, "SELECT word FROM words WHERE id = $word_id");
                $word_str = mysqli_fetch_assoc($word_q)['word'] ?? "";
                $word_len = strlen($word_str);
                if ($word_len > 2) {
                    $hints_arr = !empty($round['hints_mask']) ? explode(',', $round['hints_mask']) : [];
                    $max_hints = floor($word_len / 2.2);
                    $should_h = false;
                    $p1 = 0.35; $p2 = 0.65; $p3 = 0.85;
                    $dur = intval($room['round_duration'] ?: 60);
                    if ($elapsed > ($dur * $p1) && count($hints_arr) < 1) $should_h = true;
                    if ($elapsed > ($dur * $p2) && count($hints_arr) < 2 && $max_hints >= 2) $should_h = true;
                    if ($elapsed > ($dur * $p3) && count($hints_arr) < 3 && $max_hints >= 3) $should_h = true;

                    if ($should_h && count($hints_arr) < $max_hints) {
                        $p = [];
                        for($i=0;$i<$word_len;$i++) if($word_str[$i] != ' ' && !in_array($i,$hints_arr)) $p[] = $i;
                        if (!empty($p)) {
                            $idx = $p[array_rand($p)];
                            $hints_arr[] = $idx;
                            $m = implode(',', $hints_arr);
                            mysqli_query($conn, "UPDATE rounds SET hints_mask = '$m' WHERE id = {$round['id']}");
                            $round['hints_mask'] = $m;
                        }
                    }
                }
            }
        }
    }

    // 3. ENDED -> Next
    elseif ($round['status'] === 'ended' && $timeLeft <= 0) {
        startNextTurn($conn, $room_id);
    }

    // 4. GAME OVER -> New
    elseif ($round['status'] === 'game_over' && $timeLeft <= 0) {
        mysqli_query($conn, "UPDATE players SET score = 0 WHERE room_id = $room_id");
        mysqli_query($conn, "UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = $room_id");
        mysqli_query($conn, "DELETE FROM rounds WHERE room_id = $room_id");
        startNextTurn($conn, $room_id);
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
        'time_left' => ($round['status'] == 'choosing' || $round['status'] == 'drawing' || $round['status'] == 'countdown' || $round['status'] == 'ended' || $round['status'] == 'game_over') ? max(0, intval($round['sql_time_left'])) : 0,
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
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM rounds WHERE room_id = $room_id AND status NOT IN ('game_over', 'choosing')");
    $cnt_row = mysqli_fetch_assoc($cnt_q);
    $turns_done = intval($cnt_row['c']);
    
    // Get constraints
    $r_res = mysqli_query($conn, "SELECT max_rounds FROM rooms WHERE id = $room_id");
    $r_info = mysqli_fetch_assoc($r_res);
    $max_rounds = intval($r_info['max_rounds']);
    
    $p_res = mysqli_query($conn, "SELECT id FROM players WHERE room_id = $room_id ORDER BY turn_order ASC, id ASC");
    $players = [];
    while($p = mysqli_fetch_assoc($p_res)) $players[] = $p['id'];
    
    $num_players = count($players);
    if ($num_players === 0) return; // Nobody left
    
    $global_round = floor($turns_done / $num_players) + 1;
    $turn_index = $turns_done % $num_players;
    
    if ($global_round > $max_rounds) {
        mysqli_query($conn, "UPDATE rooms SET status = 'finished' WHERE id = $room_id");
        // Create Game Over Timer Round
        mysqli_query($conn, "INSERT INTO rounds (room_id, round_number, status, start_time, end_time) VALUES ($room_id, 0, 'game_over', NOW(), DATE_ADD(NOW(), INTERVAL 15 SECOND))");
        return;
    }
    
    mysqli_query($conn, "UPDATE rooms SET current_round = $global_round WHERE id = $room_id");
    
    // Pick drawer based on index in current players list
    $drawer_id = $players[$turn_index];

    // Pre-generate word options
    $wq = mysqli_query($conn, "SELECT id FROM words ORDER BY RAND() LIMIT 3");
    $opt_ids = [];
    while($w = mysqli_fetch_assoc($wq)) $opt_ids[] = $w['id'];
    $ids_str = implode(',', $opt_ids);
    
    // Create Round with 7 second choosing timer
    mysqli_query($conn, "INSERT INTO rounds (room_id, round_number, drawer_id, status, word_options, start_time, end_time) 
                        VALUES ($room_id, $global_round, $drawer_id, 'choosing', '$ids_str', NOW(), DATE_ADD(NOW(), INTERVAL 7 SECOND))");
}
?>
