<?php
// config/database.php

$host = '192.168.10.101';
$db_name = 'sman4_lms';
$username = 'lmsuser';
$password = 'passwordkamu'; // Default Laragon password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Sinkronkan timezone MySQL dengan PHP (WITA = +08:00)
    $pdo->exec("SET time_zone = '+08:00'");
    $pdo->exec("SET NAMES utf8mb4");
}
catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Set default timezone to Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Makassar');
?>
