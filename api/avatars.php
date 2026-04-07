<?php
/**
 * Avatar List API
 * Re-engineered to PDO for security and modern architecture.
 */
require_once 'db.php';

// Try to fetch from DB, fallback to hardcoded list if empty
try {
    $avatars = DB::fetchAll("SELECT emoji FROM avatars ORDER BY id ASC");
    $list = array_column($avatars, 'emoji');
} catch (Exception $e) {
    $list = [];
}

if (empty($list)) {
    $list = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷', '🐧', '🦄', '🐲', '🐙', '🦖', '🤖', '👻', '🤡'];
}

jsonResponse($list);
