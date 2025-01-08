<?php
// config.php

// Database connection details
$host = 'localhost'; // Usually 'localhost'
$dbname = 'passworddb';
$user = 'passuser';
$pass = 'userpassdb';
$charset = 'utf8mb4';

// DSN (Data Source Name) for PDO connection
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Function to establish a database connection
function getDBConnection() {
    global $dsn, $user, $pass, $options;
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        die("Error connecting to the database: " . $e->getMessage());
    }
}
?>
