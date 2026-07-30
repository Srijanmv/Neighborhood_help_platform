<?php
require_once __DIR__ . '/inc/config.php';

$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn && $db_host === '127.0.0.1') {
    $conn = @mysqli_connect('localhost', $db_user, $db_pass, $db_name);
}

if (!$conn) {
    die('Connection Failed: ' . mysqli_connect_error());
}
?>
