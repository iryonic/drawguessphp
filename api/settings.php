<?php
require_once 'db.php';

$res = mysqli_query($conn, "SELECT * FROM settings");
$settings = [];
while($row = mysqli_fetch_assoc($res)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

jsonResponse(['success' => true, 'settings' => $settings]);
?>
