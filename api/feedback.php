<?php
/**
 * Feedback Submission API
 */
require_once 'db.php';

$action = $_POST['action'] ?? 'submit';

if ($action === 'submit') {
    $token = $_POST['token'] ?? '';
    $username = trim($_POST['username'] ?? 'Anonymous');
    $message = trim($_POST['message'] ?? '');
    $rating = intval($_POST['rating'] ?? 5);
    
    if (empty($message)) {
        jsonResponse(['error' => 'Feedback message cannot be empty'], false, 400);
    }
    
    // Optional: Link to player if token is provided
    $player_id = null;
    if ($token) {
        $player = DB::fetch("SELECT id FROM players WHERE session_token = ?", [$token]);
        if ($player) {
            $player_id = $player['id'];
        }
    }
    
    try {
        DB::insert('feedbacks', [
            'player_id' => $player_id,
            'username' => $username,
            'message' => $message,
            'rating' => $rating
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Thank you for your feedback!']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to save feedback'], false, 500);
    }
}

jsonResponse(['error' => 'Invalid action'], false, 400);
