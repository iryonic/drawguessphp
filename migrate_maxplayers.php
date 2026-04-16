<?php
require 'api/db.php';
try {
    $pdo->exec('ALTER TABLE rooms ADD COLUMN max_players INT DEFAULT 8');
    echo "SUCCESS: max_players column added to rooms table.\n";
} catch(Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}
