<?php
/**
 * PRODUCTION CLEANUP SCRIPT (Fixed)
 * Refactored to use PDO/DB class.
 * Run this via Cron Job (e.g., every hour)
 * 0 * * * * php /path/to/api/cleanup.php
 */
require_once 'db.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("This script must be run from the command line or with ?force=1\n");
}

echo "Starting cleanup...\n";

try {
    // 1. Delete players inactive for more than 1 hour
    $deletedPlayers = DB::query("DELETE FROM players WHERE last_active < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->rowCount();
    echo "Deleted $deletedPlayers inactive players.\n";

    // 2. Delete rooms with no players
    $deletedRooms = DB::query("DELETE FROM rooms WHERE id NOT IN (SELECT DISTINCT room_id FROM players)")->rowCount();
    echo "Deleted $deletedRooms empty rooms.\n";

    // 3. Delete old messages (older than 24 hours)
    $deletedMessages = DB::query("DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->rowCount();
    echo "Cleaned up $deletedMessages old messages.\n";

    // 4. Delete old strokes (older than 24 hours)
    $deletedStrokes = DB::query("DELETE FROM strokes WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->rowCount();
    echo "Cleaned up $deletedStrokes old strokes.\n";

    // 5. Optimize tables (MySQL specific)
    DB::query("OPTIMIZE TABLE strokes, messages, players, rooms, rounds");
    echo "Optimized tables.\n";

    echo "Cleanup complete!\n";

} catch (Exception $e) {
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    Logger::log("Cleanup Error: " . $e->getMessage(), 'ERROR');
}
