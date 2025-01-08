<?php
// config.php

// Database connection details
$host = 'localhost'; // Usually 'localhost'
$dbname = 'passworddb';
$user = 'passuser';
$pass = 'userpassdb';
$charset = 'utf8mb4';

// Encryption settings
define('ENCRYPTION_KEY', 'your-32-character-encryption-key'); // Replace with a secure key
define('ENCRYPTION_METHOD', 'AES-256-CBC'); // Encryption method

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

// Function to encrypt data
function encrypt($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

// Function to decrypt data
function decrypt($data) {
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}
?>
