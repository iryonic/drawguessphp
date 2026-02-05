<?php
require_once 'db.php';

// Create settings table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
)");

// Seed default settings
mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('lobby_music_enabled', '0')");
mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('lobby_music_url', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3')"); // Standard placeholder
mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('music_volume', '0.3')");

echo "Settings table created and seeded.";
?>
