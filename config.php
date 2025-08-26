<?php
// config.php

// Composer autoload (for vlucas/phpdotenv) — optional in production
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Load environment variables from .env
if (class_exists(\Dotenv\Dotenv::class)) {
    // Preferred: use phpdotenv if available
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
} else {
    // Fallback: minimal .env loader (KEY=VALUE, lines starting with '#' ignored)
    $envPath = __DIR__ . '/.env';
    if (is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            // strip optional surrounding quotes
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            $_ENV[$key] = $val;
            putenv($key . '=' . $val);
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
$dbname = env_required('DB_NAME');
$user = env_required('DB_USER');
$pass = env_required('DB_PASS');
$charset = env_get('DB_CHARSET', 'utf8mb4');

// Encryption settings from env
define('ENCRYPTION_KEY', env_required('ENCRYPTION_KEY'));
define('ENCRYPTION_METHOD', env_get('ENCRYPTION_METHOD', 'AES-256-CBC'));

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
