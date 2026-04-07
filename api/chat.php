<?php
/**
 * Chat & Guess Logic API
 * Refactored to PDO with Prepared Statements.
 */
require_once 'db.php';

// 1. Authentication
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$player = DB::fetch("SELECT id, room_id, username, score FROM players WHERE session_token = ?", [$token]);

if (!$player) {
    jsonResponse(['error' => 'Invalid Session'], false, 401);
}

$room_id = (int)$player['room_id'];
$action = $_POST['action'] ?? 'fetch';

// 2. Fetch Context (Current Round & Word)
$round = DB::fetch("SELECT * FROM rounds WHERE room_id = ? ORDER BY id DESC LIMIT 1", [$room_id]);
$current_word = '';
if ($round && $round['word_id']) {
    $word_row = DB::fetch("SELECT word FROM words WHERE id = ?", [$round['word_id']]);
    $current_word = $word_row['word'] ?? '';
}

// 3. SEND MESSAGE ACTION
if ($action === 'send') {
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) jsonResponse(['error' => 'Empty message'], false);

    // 3.1 Drawer Check
    if ($round && $round['status'] === 'drawing' && $round['drawer_id'] == $player['id']) {
        jsonResponse(['error' => 'Drawer cannot chat!'], false);
    }

    // 3.2 Guess Logic
    if ($round && $round['status'] === 'drawing' && !empty($current_word)) {
        if (strcasecmp($msg, $current_word) === 0) {
            // Already guessed?
            $already = DB::fetch("SELECT id FROM messages WHERE round_id = ? AND player_id = ? AND type = 'guess'", [$round['id'], $player['id']]);
            if ($already) jsonResponse(['success' => true]);

            // Scoring
            $rank = DB::fetch("SELECT COUNT(*) as c FROM messages WHERE round_id = ? AND type = 'guess'", [$round['id']])['c'];
            $bonus = ($rank == 0) ? 10 : (($rank == 1) ? 5 : (($rank == 2) ? 2 : 0));
            
            $time_info = DB::fetch("SELECT round_duration, TIMESTAMPDIFF(SECOND, start_time, NOW()) as elapsed FROM rounds r JOIN rooms rm ON r.room_id = rm.id WHERE r.id = ?", [$round['id']]);
            $dur = (int)($time_info['round_duration'] ?? 60);
            $elapsed = (int)($time_info['elapsed'] ?? 0);
            $timeLeft = max(0, $dur - $elapsed);
            
            $hints = !empty($round['hints_mask']) ? count(explode(',', $round['hints_mask'])) : 0;
            $score = ceil(40 * ($timeLeft / $dur)) + $bonus - ($hints * 5);
            $score = max(5, $score);

            // Update DB
            DB::query("UPDATE players SET score = score + ? WHERE id = ?", [$score, $player['id']]);
            DB::insert('messages', [
                'room_id' => $room_id,
                'round_id' => $round['id'],
                'player_id' => $player['id'],
                'message' => "guessed the word correctly!",
                'type' => 'guess'
            ]);

            // Note: drawer score is handled by game_state's heartbeat to ensure atomicity
            jsonResponse(['success' => true]);
        }

        // Close Guess Hint
        $dist = levenshtein(strtolower($msg), strtolower($current_word));
        if ($dist <= 2 && $dist > 0 && strlen($current_word) > 3) {
            $spam = DB::fetch("SELECT id FROM messages WHERE room_id = ? AND player_id = ? AND message = 'is so close!' AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)", [$room_id, $player['id']]);
            if (!$spam) {
                DB::insert('messages', ['room_id' => $room_id, 'round_id' => $round['id'], 'player_id' => $player['id'], 'message' => 'is so close!', 'type' => 'system']);
            }
        }
    }

    // 3.3 Normal Message
    DB::insert('messages', [
        'room_id' => $room_id,
        'round_id' => $round ? $round['id'] : null,
        'player_id' => $player['id'],
        'message' => $msg,
        'type' => 'chat'
    ]);
    jsonResponse(['success' => true]);
}

// 4. REACTION ACTION
if ($action === 'reaction') {
    $emoji = $_POST['emoji'] ?? '';
    if (!$emoji) jsonResponse(['error' => 'No emoji'], false);
    DB::insert('messages', [
        'room_id' => $room_id,
        'round_id' => $round ? $round['id'] : null,
        'player_id' => $player['id'],
        'message' => $emoji,
        'type' => 'reaction'
    ]);
    jsonResponse(['success' => true]);
}

// 5. FETCH MESSAGES ACTION
if ($action === 'fetch') {
    $last_id = intval($_GET['last_id'] ?? 0);
    $messages = DB::fetchAll("
        SELECT m.id, m.player_id, m.message, m.type, m.created_at, p.username 
        FROM messages m 
        LEFT JOIN players p ON m.player_id = p.id 
        WHERE m.room_id = ? AND m.id > ? 
        ORDER BY m.id ASC
    ", [$room_id, $last_id]);

    foreach ($messages as &$m) {
        if (!$m['username']) $m['username'] = 'System';
        if ($m['type'] === 'guess') {
            $m['message'] = "guessed the word correctly!";
            $m['is_system'] = true;
        } elseif ($m['type'] === 'system') {
            $m['is_system'] = true;
        }
    }
    jsonResponse(['messages' => $messages]);
}

jsonResponse(['error' => 'Invalid action'], false);
