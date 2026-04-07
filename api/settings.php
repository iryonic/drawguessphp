<?php
/**
 * Global App Settings API
 */
require_once 'db.php';

try {
    $rows = DB::fetchAll("SELECT setting_key, setting_value FROM settings");
    
    $settings = [];
    foreach ($rows as $row) {
        $key = (string)$row['setting_key'];
        $val = (string)$row['setting_value'];
        $settings[$key] = $val;
    }

    // Modern Production Defaults
    $defaults = [
        'lobby_music_enabled' => '1',
        'music_volume' => '0.3',
        'sfx_volume' => '0.8',
        'max_rooms' => '100',
        'lobby_music_url' => 'assets/music/lobby_1771165382.mp3'
    ];

    $final = array_merge($defaults, $settings);
    jsonResponse(['settings' => $final]);

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], false, 500);
}
