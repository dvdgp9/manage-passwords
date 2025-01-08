<?php
// Database connection details
$host = 'localhost'; // Usually 'localhost'
$dbname = 'passworddb';
$user = 'passuser';
$pass = 'userpassdb';

// Connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

// Get the password ID from the POST request
if (isset($_POST['id'])) {
    $passwordId = $_POST['id'];

    // Delete the password from the database
    $sql = "DELETE FROM `passwords-manager` WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([':id' => $passwordId]);
        echo 'success'; // Indicate success
    } catch (PDOException $e) {
        echo 'error'; // Indicate failure
    }
} else {
    echo 'error'; // Indicate failure
}
?>
