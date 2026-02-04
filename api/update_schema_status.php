<?php
require_once 'db.php';

echo "Updating Rounds Status Column...\n";

// Change ENUM to VARCHAR to support 'countdown' and other future states
$sql = "ALTER TABLE rounds MODIFY COLUMN status VARCHAR(20) DEFAULT 'choosing'";
if (mysqli_query($conn, $sql)) {
    echo "Successfully converted rounds.status to VARCHAR.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
