<?php
$host = 'localhost';
$user = 'root';
$pass = '24112005';
$db = 'Task_Management';

$conn = mysqli_connect($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Failed to connect " . $conn->connect_error);
}

$conn->set_charset('utf8');