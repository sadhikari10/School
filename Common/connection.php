<?php
$host = 'localhost';
$dbname = 'school';  // Change to your database name
$username = 'root';        // Change to your username
$password = '';            // Change to your password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>