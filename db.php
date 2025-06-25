<?php
// Database credentials
$host = 'localhost';     // Or '127.0.0.1'
$dbname = 'asd_academy';
$username = 'root';      // Replace with your DB username
$password = '';          // Replace with your DB password

// Create a new PDO connection
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
