<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'security.php';

// Ensure the user is authenticated
bootstrap_security(true); // require authenticated session

// Check if the ID is provided in the query string
if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$passwordId = $_GET['id'];

// Connect to the database
$pdo = getDBConnection();

// Fetch the existing password data
$sql = "SELECT * FROM `passwords_manager` WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $passwordId]);
$password = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$password) {
    die("Contraseña no encontrada.");
}

// Authorization with roles and assignments
$u = current_user();
$uid = (int)($u['id'] ?? 0);
$role = $u['role'] ?? '';

$hasAssignment = false;
try {
    $stmtA = $pdo->prepare('SELECT perm FROM passwords_access WHERE password_id = :pid AND user_id = :uid LIMIT 1');
    $stmtA->execute([':pid' => (int)$passwordId, ':uid' => $uid]);
    $hasAssignment = (bool)$stmtA->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $hasAssignment = false;
}

$isGlobal = !isset($password['owner_user_id']) || $password['owner_user_id'] === null;
$canView = false;
if (is_admin()) {
    $canView = true;
} elseif ($role === 'editor') {
    $canView = $hasAssignment || $isGlobal;
} elseif ($role === 'lector') {
    $canView = $hasAssignment; // viewer solo ve asignadas
}

$canEdit = is_admin() || ($role === 'editor' && $canView);
if (!$canEdit) {
    http_response_code(403);
    die("No tienes permisos para editar este registro.");
}

// Prepare reusable header HTML
ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();

$csrf = ensure_csrf_token();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_from_request();
    // Validate and sanitize input
    $linea_de_negocio = $_POST['linea_de_negocio'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'] ?? null;
    $usuario = $_POST['usuario'];
    $password_new = $_POST['password']; // Get the new password from the form
    $enlace = $_POST['enlace'];
    $info_adicional = $_POST['info_adicional'] ?? null;

    // If the password field is left blank, keep the existing encrypted password
    if (empty($password_new)) {
        $password_encrypted = $password['password']; // Use the existing encrypted password
    } else {
        $password_encrypted = encrypt($password_new); // Encrypt the new password
    }

    // Format the link
    if (!empty($enlace) && !str_starts_with($enlace, 'http://') && !str_starts_with($enlace, 'https://')) {
        $enlace = 'https://' . $enlace;
    }

    // Update the password in the database (owner_user_id no se toca)
    $sql = "UPDATE `passwords_manager`
            SET linea_de_negocio = :linea_de_negocio,
                nombre = :nombre,
                descripcion = :descripcion,
                usuario = :usuario,
                password = :password,
                enlace = :enlace,
                info_adicional = :info_adicional
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':linea_de_negocio' => $linea_de_negocio,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':usuario' => $usuario,
            ':password' => $password_encrypted, // Use the encrypted password
            ':enlace' => $enlace,
            ':info_adicional' => $info_adicional,
            ':id' => $passwordId
        ]);

        // Redirect back to the passwords list after successful update
        header('Location: ver-passwords.php');
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar la contraseña: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Contraseña</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <?= $headerHtml ?>
    <main class="page introducir-page">
    <div class="introducir-container">
    <h1>Editar Contraseña</h1>
    <form id="form-edit-password" action="edit-password.php?id=<?php echo $passwordId; ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        
        <label for="linea_de_negocio">Línea de Negocio:</label>
        <input type="text" id="linea_de_negocio" name="linea_de_negocio" value="<?= htmlspecialchars($password['linea_de_negocio'], ENT_QUOTES, 'UTF-8') ?>" placeholder="General, ES, CF, EFit,..." required><br>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($password['nombre'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Facebook, Gmail, Canva,..." required><br>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta"><?= htmlspecialchars($password['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea><br>

        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($password['usuario'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: usuario@example.com" required><br>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" placeholder="Dejar en blanco para no cambiar">
        <div class="password-buttons">
            <button type="button" id="btn-toggle-password">Mostrar</button>
            <button type="button" id="btn-paste-password">Pegar Contraseña</button>
        </div><br>

        <label for="enlace">Enlace:</label>
        <input type="text" id="enlace" name="enlace" value="<?= htmlspecialchars($password['enlace'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: example.com" required><br>

        <label for="info_adicional">Info Adicional:</label>
        <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad: Nombre de tu mascota"><?= htmlspecialchars($password['info_adicional'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea><br>

        <button type="submit">Guardar Cambios</button>
    </form>
    </div>
    </main>
</body>
</html>
