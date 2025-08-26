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
    $passwordId = (int)$_POST['id'];

    // Authorization: only admin or owner can delete
    $u = current_user();
    $stmt = $pdo->prepare('SELECT owner_user_id FROM `passwords_manager` WHERE id = :id');
    $stmt->execute([':id' => $passwordId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo 'error';
        exit;
    }
    $isOwner = isset($row['owner_user_id']) && (int)$row['owner_user_id'] === (int)($u['id'] ?? 0);
    if (!is_admin() && !$isOwner) {
        http_response_code(403);
        echo 'error';
        exit;
    }

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
