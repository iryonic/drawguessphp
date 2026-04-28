<?php
/**
 * System Cleanup Script
 * Deletes inactive rooms and players. 
 * Strokes and messages are automatically deleted via MySQL ON DELETE CASCADE.
 */
require_once 'db.php';

// Only allow execution via command line or if triggered by the system
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CLEANUP')) {
    // If accessed via web, we can still run it but maybe add a secret key or rate limit
}

try {
    // 1. Delete players inactive for more than 30 minutes
    // (A bit longer than the heartbeat pruning to allow for recovery)
    DB::query("DELETE FROM players WHERE last_active < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

    // 2. Find and delete rooms with no players
    // This will trigger CASCADE delete on rounds, strokes, and messages.
    DB::query("DELETE FROM rooms WHERE id NOT IN (SELECT DISTINCT room_id FROM players) AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

    // 3. Delete very old rooms regardless of players (safety for abandoned sessions)
    DB::query("DELETE FROM rooms WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

    if (php_sapi_name() === 'cli') {
        echo "Cleanup completed successfully." . PHP_EOL;
    }
} catch (Exception $e) {
    Logger::log("Cleanup Error: " . $e->getMessage(), 'ERROR');
    if (php_sapi_name() === 'cli') {
        echo "Cleanup Error: " . $e->getMessage() . PHP_EOL;
    }
}
