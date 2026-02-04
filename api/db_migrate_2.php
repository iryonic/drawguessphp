<?php
require_once 'db.php';

// Check if 'word_options' exists in 'rounds'
$check = mysqli_query($conn, "SHOW COLUMNS FROM rounds LIKE 'word_options'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE rounds ADD COLUMN word_options VARCHAR(255) DEFAULT NULL COMMENT 'Comma separated word IDs'");
    echo "Added word_options column to rounds.<br>";
} else {
    echo "word_options column exists.<br>";
}

echo "Migration 2 complete.";
?>
