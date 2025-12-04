<?php
// config.php

// Composer autoload (for vlucas/phpdotenv); guard for servers without vendor/
$__autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($__autoload)) {
    require_once $__autoload;
}

// Load environment variables from .env if present
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
} else {
    // Fallback: very small .env loader (no expansion). Only for shared hosting without Composer.
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile) && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            // Strip surrounding quotes if present
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

// Helpers to read env
function env_get(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}
function env_required(string $key): string {
    $val = env_get($key);
    if ($val === null || $val === '') {
        http_response_code(500);
        die("Missing required environment variable: $key");
    }
    return (string)$val;
}

// Database connection details from env (required)
$host = env_required('DB_HOST');
$dbname = env_required('passworddb');
$dbUser = env_required('passuser');
$dbPass = env_required('userpassdb');
$charset = env_get('DB_CHARSET', 'utf8mb4');

// Encryption settings from env
define('ENCRYPTION_KEY', env_required('ENCRYPTION_KEY'));
define('ENCRYPTION_METHOD', env_get('ENCRYPTION_METHOD', 'AES-256-CBC'));

// DSN (Data Source Name) for PDO connection
$dbDsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$dbOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Function to establish a database connection
function getDBConnection() {
    global $dbDsn, $dbUser, $dbPass, $dbOptions;
    try {
        return new PDO($dbDsn, $dbUser, $dbPass, $dbOptions);
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
