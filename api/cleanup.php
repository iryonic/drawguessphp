<?php
require_once 'db.php';

/**
 * PRODUCTION CLEANUP SCRIPT
 * Run this via Cron Job (e.g., every hour)
 * 0 * * * * php /path/to/api/cleanup.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("This script must be run from the command line.");
}

echo "Starting cleanup...\n";

// 1. Delete players inactive for more than 1 hour
$res = db_prepare($conn, "DELETE FROM players WHERE last_active < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
echo "Deleted inactive players.\n";

// 2. Delete rooms with no players
$res = db_prepare($conn, "DELETE FROM rooms WHERE id NOT IN (SELECT DISTINCT room_id FROM players)");
echo "Deleted empty rooms.\n";

// 3. Delete old messages (older than 24 hours)
$res = db_prepare($conn, "DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
echo "Cleaned up old messages.\n";

// 4. Delete old strokes (older than 24 hours)
// Strokes are the heaviest data, keeping them indefinitely will bloat the DB
$res = db_prepare($conn, "DELETE FROM strokes WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
echo "Cleaned up old strokes.\n";

// 5. Optimize tables
mysqli_query($conn, "OPTIMIZE TABLE strokes, messages, players, rooms, rounds");

echo "Cleanup complete!\n";
?>
