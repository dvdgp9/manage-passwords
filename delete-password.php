<?php
require_once 'security.php';
require_once 'config.php';

bootstrap_security(true); // require authenticated session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('error');
}

verify_csrf_from_request();

// Connect to the database
$pdo = getDBConnection();

// Get the password ID from the POST request
if (isset($_POST['id'])) {
    $passwordId = $_POST['id'];

    // Delete the password from the database
    $sql = "DELETE FROM `passwords_manager` WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([':id' => $passwordId]);
        echo 'success'; // Indicate success
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'error'; // Indicate failure
    }
} else {
    http_response_code(400);
    echo 'error'; // Indicate failure
}
?>
