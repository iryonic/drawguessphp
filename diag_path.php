<?php
require_once __DIR__ . '/api/db.php';
$base_uri = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_path = '/' . ($base_uri ? $base_uri . '/' : '') . (strlen($base_uri) > 0 ? '' : '');
$base_path = rtrim($base_path, '/') . '/';
echo "BASE_PATH: [" . $base_path . "]<br>";
echo "APP_ROOT: [" . APP_ROOT . "]<br>";
echo "SCRIPT_NAME: [" . $_SERVER['SCRIPT_NAME'] . "]<br>";
?>
