<?php
// Database connection file
$host = "localhost";
$db   = "swachhv2";
$user = "root";  // default XAMPP user
$pass = "";      // default XAMPP has no password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage());
}
