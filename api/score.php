<?php
require_once 'db.php';

$room_id = intval($_GET['room_id'] ?? 0);
if (!$room_id) jsonResponse(['error' => 'Missing Room ID'], false);

// Return live scores
$sql = "SELECT username, avatar, score FROM players WHERE room_id = $room_id ORDER BY score DESC";
$res = mysqli_query($conn, $sql);
$leaderboard = [];
while ($row = mysqli_fetch_assoc($res)) {
    $leaderboard[] = $row;
}

jsonResponse(['leaderboard' => $leaderboard]);
?>
