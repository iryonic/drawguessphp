<?php
require_once 'api/db.php';

// 1. Create a test room
$room_code = 'TEST1';
DB::query("DELETE FROM rooms WHERE room_code = ?", [$room_code]);
$room_id = DB::insert('rooms', [
    'room_code' => $room_code,
    'status' => 'playing',
    'max_rounds' => 3,
    'current_round' => 1
]);

// 2. Create test players
$player1_id = DB::insert('players', [
    'room_id' => $room_id,
    'username' => 'Alice',
    'avatar' => '🐱',
    'is_host' => true,
    'session_token' => 'token_alice'
]);

$player2_id = DB::insert('players', [
    'room_id' => $room_id,
    'username' => 'Bob',
    'avatar' => '🐶',
    'is_host' => false,
    'session_token' => 'token_bob'
]);

// 3. Create a test round
$round_id = DB::insert('rounds', [
    'room_id' => $room_id,
    'round_number' => 1,
    'drawer_id' => $player1_id,
    'status' => 'drawing'
]);

// 4. Insert different types of chat messages
// Standard Chat
DB::insert('messages', [
    'room_id' => $room_id,
    'round_id' => $round_id,
    'player_id' => $player2_id,
    'message' => 'Hello everyone!',
    'type' => 'chat'
]);

// Incorrect Guess
DB::insert('messages', [
    'room_id' => $room_id,
    'round_id' => $round_id,
    'player_id' => $player2_id,
    'message' => 'Is it a dog?',
    'type' => 'chat'
]);

// Correct Guess (System handles point awarding but we just test the message type)
DB::insert('messages', [
    'room_id' => $room_id,
    'round_id' => $round_id,
    'player_id' => $player2_id,
    'message' => 'Discovery!', // The word discovering message
    'type' => 'guess'
]);

// System Message
DB::insert('messages', [
    'room_id' => $room_id,
    'round_id' => $round_id,
    'message' => 'Bob joined the room',
    'type' => 'system'
]);

// Reaction
DB::insert('messages', [
    'room_id' => $room_id,
    'round_id' => $round_id,
    'player_id' => $player2_id,
    'message' => '🔥',
    'type' => 'reaction'
]);

echo "Test room $room_code created with ID $room_id and 5 test messages injected.\n";
