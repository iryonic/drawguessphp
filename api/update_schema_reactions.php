<?php
require_once 'db.php';

// Add 'reaction' to messages.type ENUM
mysqli_query($conn, "ALTER TABLE messages MODIFY COLUMN type ENUM('chat', 'guess', 'system', 'reaction') DEFAULT 'chat'");

echo "Schema updated for reactions.";
?>
