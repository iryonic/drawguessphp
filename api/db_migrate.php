<?php
require_once 'db.php';

// Check if 'round_duration' exists in 'rooms'
$check = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'round_duration'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN round_duration INT DEFAULT 60");
    echo "Added round_duration column.<br>";
} else {
    echo "round_duration column exists.<br>";
}

// Check other columns just in case
$check2 = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'max_rounds'");
if (mysqli_num_rows($check2) == 0) {
    mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN max_rounds INT DEFAULT 3");
    echo "Added max_rounds column.<br>";
}

echo "Database schema check complete.";
?>
