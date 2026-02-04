<?php
require_once 'db.php';

echo "Running Gameplay Schema Updates...\n";

// 1. Add turn_order to players
$check = mysqli_query($conn, "SHOW COLUMNS FROM players LIKE 'turn_order'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE players ADD COLUMN turn_order INT DEFAULT 0");
    echo "Added turn_order to players.\n";
}

// 2. Add word_options to rounds
$check = mysqli_query($conn, "SHOW COLUMNS FROM rounds LIKE 'word_options'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE rounds ADD COLUMN word_options VARCHAR(255)");
    echo "Added word_options to rounds.\n";
}

// 3. Add hints_mask to rounds (stores indices of revealed letters e.g. "0,2,4")
$check = mysqli_query($conn, "SHOW COLUMNS FROM rounds LIKE 'hints_mask'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE rounds ADD COLUMN hints_mask VARCHAR(255) DEFAULT ''");
    echo "Added hints_mask to rounds.\n";
}

// 4. Add revealed_time to rounds (tracks when the last hint was revealed to avoid re-calc jitter?)
// Actually, calculating based on time is better. 

echo "Done.\n";
?>
