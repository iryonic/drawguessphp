<?php
require_once 'db.php';

// Add turn_order if missing
mysqli_query($conn, "ALTER TABLE players ADD COLUMN IF NOT EXISTS turn_order INT DEFAULT 0");

// Add word_options if missing
mysqli_query($conn, "ALTER TABLE rounds ADD COLUMN IF NOT EXISTS word_options VARCHAR(255)");

// Add hints_mask if missing
mysqli_query($conn, "ALTER TABLE rounds ADD COLUMN IF NOT EXISTS hints_mask VARCHAR(255)");

// Update rounds status enum to include choosing/countdown if not already (safely)
// MySQL doesn't have a clean ALTER... ADD ENUM VALUE if exists, so we just try to redefine it
mysqli_query($conn, "ALTER TABLE rounds MODIFY COLUMN status ENUM('choosing', 'countdown', 'drawing', 'ended', 'game_over') DEFAULT 'choosing'");

echo "Schema updated successfully!";
?>
