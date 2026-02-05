<?php
require_once 'db.php';

// Fetch Avatars
$q = mysqli_query($conn, "SELECT emoji FROM avatars ORDER BY id ASC");
$avatars = [];
while ($row = mysqli_fetch_assoc($q)) {
    $avatars[] = $row['emoji'];
}

jsonResponse($avatars);
?>
