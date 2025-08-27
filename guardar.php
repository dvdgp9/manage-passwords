<?php
require_once 'security.php';
require_once 'config.php';

bootstrap_security(true); // require authenticated session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

verify_csrf_from_request();

// Connect to the database
$pdo = getDBConnection();

$u = current_user();
$ownerId = (int)($u['id'] ?? 0);
// Selected assignees from form (optional)
$assignees = $_POST['assignees'] ?? [];
if (!is_array($assignees)) { $assignees = []; }
// sanitize to unique integer IDs and remove owner if included
$assignees = array_values(array_unique(array_filter(array_map(function($v){ return (int)$v; }, $assignees), function($v) use ($ownerId){ return $v > 0 && $v !== $ownerId; })));


// Validate mandatory fields
if (empty($_POST['linea_de_negocio']) || empty($_POST['nombre']) || empty($_POST['usuario']) || empty($_POST['password']) || empty($_POST['enlace'])) {
    die("Todos los campos obligatorios deben ser completados.");
}

// Encrypt the password
$password = encrypt($_POST['password']); // Encrypt the password

// Format the link
$enlace = $_POST['enlace'];
if (!empty($enlace) && !str_starts_with($enlace, 'http://') && !str_starts_with($enlace, 'https://')) {
    $enlace = 'https://' . $enlace;
}

// Insert data into the database with owner_user_id and access rows (transaction)
$sql = "INSERT INTO `passwords_manager` (owner_user_id, linea_de_negocio, nombre, descripcion, usuario, password, enlace, info_adicional)
        VALUES (:owner_user_id, :linea_de_negocio, :nombre, :descripcion, :usuario, :password, :enlace, :info_adicional)";
$stmt = $pdo->prepare($sql);

try {
    $pdo->beginTransaction();
    $stmt->execute([
        ':owner_user_id' => $ownerId,
        ':linea_de_negocio' => $_POST['linea_de_negocio'],
        ':nombre' => $_POST['nombre'],
        ':descripcion' => $_POST['descripcion'] ?? null,
        ':usuario' => $_POST['usuario'],
        ':password' => $password,
        ':enlace' => $enlace,
        ':info_adicional' => $_POST['info_adicional'] ?? null
    ]);

    $passwordId = (int)$pdo->lastInsertId();
    // Creator as owner in access table
    $pdo->prepare('INSERT INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, \"owner\")')
        ->execute([':pid' => $passwordId, ':uid' => $ownerId]);
    // Selected assignees as editors
    if ($assignees) {
        $ins = $pdo->prepare('INSERT IGNORE INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, \"editor\")');
        foreach ($assignees as $aid) {
            $ins->execute([':pid' => $passwordId, ':uid' => $aid]);
        }
    }
    $pdo->commit();

    // Display success message and saved data
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Datos Guardados</title>
        <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='style.css'>
    </head>
    <body>
        <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
        <h1>Datos Guardados Correctamente</h1>
        <p>Los datos de acceso se han guardado correctamente. Aquí los tienes, por si necesitas revisar:</p>
        <table>
            <tr>
                <th>Línea de Negocio</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th>Enlace</th>
                <th>Info Adicional</th>
            </tr>
            <tr>
                <td>" . htmlspecialchars($_POST['linea_de_negocio']) . "</td>
                <td>" . htmlspecialchars($_POST['nombre']) . "</td>
                <td>" . (isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : 'N/A') . "</td>
                <td>" . htmlspecialchars($_POST['usuario']) . "</td>
                <td><a href='" . htmlspecialchars($enlace) . "' target='_blank'>" . htmlspecialchars($enlace) . "</a></td>
                <td>" . (isset($_POST['info_adicional']) ? htmlspecialchars($_POST['info_adicional']) : 'N/A') . "</td>
            </tr>
        </table>
    </body>
    </html>";

} catch (PDOException $e) {
    die("Error executing the query: " . $e->getMessage());
}
?>
