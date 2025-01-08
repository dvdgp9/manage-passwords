<?php
// Include the config file
require_once 'config.php';

// Connect to the database
$pdo = getDBConnection();

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
