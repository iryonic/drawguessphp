<?php
/**
 * Game State Management & Synchronizer
 * Refactored to PDO with transactions to prevent race conditions.
 * Fixed timestamp logic for PDO compatibility.
 */
require_once 'db.php';

// 0. Player Pruning (Heartbeat) - Remove players inactive for > 180 seconds (3 mins)
// This keeps the sessions clean while allowing for longer page refreshes.
DB::query("DELETE FROM players WHERE last_active < DATE_SUB(NOW(), INTERVAL 180 SECOND)");

// 1. Authentication
$token = $_POST['token'] ?? '';
$player = DB::fetch("SELECT * FROM players WHERE session_token = ?", [$token]);

if (!$player) {
    jsonResponse(['error' => 'Invalid Session'], false, 401);
}

$room_id = (int)$player['room_id'];
$player_id = (int)$player['id'];
$is_host = (bool)$player['is_host'];

// 2. Transacted State Update
try {
    $pdo->beginTransaction();

    // Update Player Activity
    DB::query("UPDATE players SET last_active = NOW() WHERE id = ?", [$player_id]);

    // Room Locking for consistency
    $room = DB::fetch("SELECT * FROM rooms WHERE id = ? FOR UPDATE", [$room_id]);

    // 3. Cleanup Inactive Players (60s threshold for host stability)
    $inactive = DB::fetchAll("SELECT id, username FROM players WHERE room_id = ? AND id != ? AND last_active < DATE_SUB(NOW(), INTERVAL 60 SECOND)", [$room_id, $player_id]);
    foreach ($inactive as $p) {
        DB::query("DELETE FROM players WHERE id = ?", [$p['id']]);
        DB::insert('messages', [
            'room_id' => $room_id, 
            'message' => "{$p['username']} timed out", 
            'type' => 'system'
        ]);
    }

    // 4. Host Migration
    $hostExists = DB::fetch("SELECT id FROM players WHERE id = ? AND room_id = ?", [$room['host_id'], $room_id]);
    if (!$hostExists) {
        $newHost = DB::fetch("SELECT id, username FROM players WHERE room_id = ? ORDER BY id ASC LIMIT 1", [$room_id]);
        if ($newHost) {
            DB::query("UPDATE players SET is_host = 1 WHERE id = ?", [$newHost['id']]);
            DB::query("UPDATE rooms SET host_id = ? WHERE id = ?", [$newHost['id'], $room_id]);
            DB::insert('messages', ['room_id' => $room_id, 'message' => "{$newHost['username']} is now host", 'type' => 'system']);
            if ($newHost['id'] == $player_id) $is_host = true;
            $room['host_id'] = $newHost['id'];
        }
    }

    $action = $_POST['action'] ?? 'sync';

    // 5. Game Actions
    if ($action === 'start_game' && $is_host) {
        if (in_array($room['status'], ['lobby', 'finished', 'game_over'])) {
            $p_ids = DB::fetchAll("SELECT id FROM players WHERE room_id = ?", [$room_id]);
            $ids = array_column($p_ids, 'id');
            shuffle($ids);
            foreach ($ids as $idx => $pid) {
                DB::query("UPDATE players SET turn_order = ?, score = 0 WHERE id = ?", [$idx, $pid]);
            }
            DB::query("UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = ?", [$room_id]);
            DB::query("DELETE FROM rounds WHERE room_id = ?", [$room_id]);
            startNextTurn($room_id);
        }
    }

    if ($action === 'select_word') {
        $word_id = intval($_POST['word_id'] ?? 0);
        $round = DB::fetch("SELECT * FROM rounds WHERE room_id = ? ORDER BY id DESC LIMIT 1", [$room_id]);
        if ($round && $round['drawer_id'] == $player_id && $round['status'] == 'choosing') {
            $end_time = date('Y-m-d H:i:s', time() + 3);
            DB::query("UPDATE rounds SET word_id = ?, status = 'countdown', start_time = NOW(), end_time = ? WHERE id = ?", [$word_id, $end_time, $round['id']]);
        }
    }

    // 6. State Machine Processing (Using Unix timestamps for reliable sync)
    $now = time();
    $round = DB::fetch("SELECT *, UNIX_TIMESTAMP(end_time) as end_unix, UNIX_TIMESTAMP(start_time) as start_unix FROM rounds WHERE room_id = ? ORDER BY id DESC LIMIT 1", [$room_id]);

    if ($round) {
        $timeLeft = (int)$round['end_unix'] - $now;
        $elapsed = $now - (int)$round['start_unix'];
        $drawer_id = (int)$round['drawer_id'];

        // Drawer Disconnect Check (Refined: 60s tolerance)
        $drawerActive = DB::fetch("SELECT id FROM players WHERE id = ? AND last_active > DATE_SUB(NOW(), INTERVAL 60 SECOND)", [$drawer_id]);
        if (!$drawerActive && !in_array($round['status'], ['ended', 'game_over'])) {
            $end_time_skipped = date('Y-m-d H:i:s', $now + 5);
            DB::query("UPDATE rounds SET status = 'ended', start_time = ?, end_time = ? WHERE id = ?", 
                       [date('Y-m-d H:i:s', $now), $end_time_skipped, $round['id']]);
            $round['status'] = 'ended';
            $timeLeft = 5; 
        }

        // State Transitions (Monotonic Logic)
        if ($round['status'] === 'choosing' && $timeLeft <= 0) {
            $options = !empty($round['word_options']) ? explode(',', $round['word_options']) : [];
            $word_id = $options[array_rand($options)];
            $end_time_cd = date('Y-m-d H:i:s', $now + 5); // 5s countdown
            DB::query("UPDATE rounds SET word_id = ?, status = 'countdown', start_time = ?, end_time = ? WHERE id = ?", 
                       [$word_id, date('Y-m-d H:i:s', $now), $end_time_cd, $round['id']]);
        } 
        elseif ($round['status'] === 'countdown' && $timeLeft <= 0) {
            $dur = (int)$room['round_duration'] ?: 60;
            $end_time_dr = date('Y-m-d H:i:s', $now + $dur);
            DB::query("UPDATE rounds SET status = 'drawing', start_time = ?, end_time = ? WHERE id = ?", 
                       [date('Y-m-d H:i:s', $now), $end_time_dr, $round['id']]);
        }
        elseif ($round['status'] === 'drawing') {
            // Hint Logic (Reveal letters at 50% and 75% elapsed)
            $total_dur = (int)($room['round_duration'] ?: 60);
            $hints = !empty($round['hints_mask']) ? explode(',', $round['hints_mask']) : [];
            $thresholds = [0.5, 0.75];
            
            foreach ($thresholds as $idx => $t) {
                if ($elapsed >= ($total_dur * $t) && count($hints) < ($idx + 1)) {
                    $word_row = DB::fetch("SELECT word FROM words WHERE id = ?", [$round['word_id']]);
                    if ($word_row) {
                        $word = $word_row['word'];
                        $indices = [];
                        for ($i = 0; $i < mb_strlen($word); $i++) {
                            $char = mb_substr($word, $i, 1);
                            if ($char !== ' ' && !in_array($i, $hints)) {
                                $indices[] = $i;
                            }
                        }
                        if (!empty($indices)) {
                            $new_hint = $indices[array_rand($indices)];
                            $hints[] = $new_hint;
                            DB::query("UPDATE rounds SET hints_mask = ? WHERE id = ?", [implode(',', $hints), $round['id']]);
                        }
                    }
                }
            }

            if ($timeLeft <= 0) {
                // Award points to drawer for successful guesses
                $guesses = DB::fetch("SELECT COUNT(DISTINCT player_id) as c FROM messages WHERE round_id = ? AND type = 'guess'", [$round['id']])['c'];
                if ($guesses > 0) {
                    DB::query("UPDATE players SET score = score + (? * 15) WHERE id = ?", [$guesses, $drawer_id]);
                }
                
                $end_time_en = date('Y-m-d H:i:s', $now + 8);
                DB::query("UPDATE rounds SET status = 'ended', start_time = ?, end_time = ? WHERE id = ?", 
                           [date('Y-m-d H:i:s', $now), $end_time_en, $round['id']]);
            } else {
                // Early finish if all active guessers have found the word
                $others = DB::fetch("SELECT COUNT(*) as c FROM players WHERE room_id = ? AND id != ? AND last_active > DATE_SUB(NOW(), INTERVAL 35 SECOND)", [$room_id, $drawer_id])['c'];
                $guessed = DB::fetch("SELECT COUNT(DISTINCT player_id) as c FROM messages WHERE round_id = ? AND type = 'guess'", [$round['id']])['c'];
                
                if ($others > 0 && $guessed >= $others) {
                    // Award points to drawer immediately
                    DB::query("UPDATE players SET score = score + (? * 15) WHERE id = ?", [$guessed, $drawer_id]);
                    
                    $end_time_early = date('Y-m-d H:i:s', $now + 8);
                    DB::query("UPDATE rounds SET status = 'ended', start_time = ?, end_time = ? WHERE id = ?", 
                               [date('Y-m-d H:i:s', $now), $end_time_early, $round['id']]);
                }
            }
        }
        elseif ($round['status'] === 'ended' && $timeLeft <= 0) {
            startNextTurn($room_id);
        }
        elseif ($round['status'] === 'game_over' && $timeLeft <= 0) {
            DB::query("UPDATE players SET score = 0 WHERE room_id = ?", [$room_id]);
            DB::query("UPDATE rooms SET status = 'playing', current_round = 1 WHERE id = ?", [$room_id]);
            DB::query("DELETE FROM rounds WHERE room_id = ?", [$room_id]);
            startNextTurn($room_id);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

// 7. Data Gathering for Response
$room = DB::fetch("SELECT * FROM rooms WHERE id = ?", [$room_id]);
$players = DB::fetchAll("SELECT id, username, avatar, score, is_host FROM players WHERE room_id = ? ORDER BY score DESC", [$room_id]);
foreach($players as &$p) {
    $p['is_me'] = ($p['id'] == $player_id);
    $p['is_host'] = (bool)$p['is_host'];
}

$current_round = [];
if ($round) {
    // Refresh round after processing
    $round = DB::fetch("SELECT *, TIMESTAMPDIFF(SECOND, NOW(), end_time) as time_left FROM rounds WHERE id = ?", [$round['id']]);
    $current_round = [
        'id' => $round['id'],
        'status' => $round['status'],
        'drawer_id' => (int)$round['drawer_id'],
        'time_left' => max(0, (int)$round['time_left']),
        'round_number' => (int)$round['round_number']
    ];

    if ($round['word_id']) {
        $word_row = DB::fetch("SELECT word FROM words WHERE id = ?", [$round['word_id']]);
        $word = $word_row['word'] ?? '';
        if ($player_id == $round['drawer_id'] || $round['status'] == 'ended') {
            $current_round['word'] = $word;
        } else {
            // Masking logic
            $hints = !empty($round['hints_mask']) ? explode(',', $round['hints_mask']) : [];
            $masked = "";
            $display_word = $word;
            for($i=0;$i<mb_strlen($display_word);$i++) {
                $char = mb_substr($display_word, $i, 1);
                if ($char == ' ') {
                    $masked .= "   "; // Wide gap for word separator
                } elseif (in_array($i, $hints)) {
                    $masked .= $char;
                } else {
                    $masked .= "_";
                }
            }
            $word_len = mb_strlen(str_replace(' ', '', $display_word));
            $current_round['word'] = trim($masked);
            $current_round['word_len'] = $word_len;
        }
    } else {
        $current_round['word_len'] = 0;
    }

    if ($round['status'] == 'choosing' && $round['drawer_id'] == $player_id) {
        $ids_str = $round['word_options'] ?: '0';
        $ids = explode(',', $ids_str);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $current_round['options'] = DB::fetchAll("SELECT * FROM words WHERE id IN ($placeholders)", $ids);
    }
}

jsonResponse([
    'room' => $room,
    'players' => $players,
    'round' => $current_round,
    'me' => $player_id
]);

function startNextTurn($room_id) {
    $room = DB::fetch("SELECT * FROM rooms WHERE id = ?", [$room_id]);
    $players = DB::fetchAll("SELECT id, turn_order FROM players WHERE room_id = ? ORDER BY turn_order ASC, id ASC", [$room_id]);
    $num = count($players);
    if ($num == 0) return;

    $lastRound = DB::fetch("SELECT * FROM rounds WHERE room_id = ? AND status != 'game_over' ORDER BY id DESC LIMIT 1", [$room_id]);
    
    $drawer = $players[0]['id']; // Default to first player
    if ($lastRound && $lastRound['drawer_id']) {
        $lastDrawer = DB::fetch("SELECT turn_order FROM players WHERE id = ?", [$lastRound['drawer_id']]);
        if ($lastDrawer) {
            $lastOrder = (int)$lastDrawer['turn_order'];
            // Find next player with turn_order > lastOrder
            $nextPlayer = DB::fetch("SELECT id FROM players WHERE room_id = ? AND turn_order > ? ORDER BY turn_order ASC LIMIT 1", [$room_id, $lastOrder]);
            if ($nextPlayer) {
                $drawer = $nextPlayer['id'];
            }
        }
    }

    // Determine global round number based on rounds completed vs player count
    $done = DB::fetch("SELECT COUNT(*) as c FROM rounds WHERE room_id = ? AND status NOT IN ('game_over', 'choosing')", [$room_id])['c'];
    $global = floor($done / $num) + 1;

    if ($global > $room['max_rounds']) {
        DB::query("UPDATE rooms SET status = 'finished' WHERE id = ?", [$room_id]);
        $end_time_go = date('Y-m-d H:i:s', time() + 15);
        DB::query("INSERT INTO rounds (room_id, round_number, status, end_time) VALUES (?, 0, 'game_over', ?)", [$room_id, $end_time_go]);
        return;
    }

    DB::query("UPDATE rooms SET current_round = ? WHERE id = ?", [$global, $room_id]);
    $opts = DB::fetchAll("SELECT id FROM words ORDER BY RAND() LIMIT 3");
    $opt_str = implode(',', array_column($opts, 'id'));

    $now_db = date('Y-m-d H:i:s');
    $end_time_ch = date('Y-m-d H:i:s', time() + 15);
    DB::query("INSERT INTO rounds (room_id, round_number, drawer_id, status, word_options, start_time, end_time) 
               VALUES (?, ?, ?, 'choosing', ?, ?, ?)", 
               [$room_id, $global, $drawer, $opt_str, $now_db, $end_time_ch]);
}
