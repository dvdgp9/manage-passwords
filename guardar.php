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

// Selected departments from form (optional)
$departments = $_POST['departments'] ?? [];
if (!is_array($departments)) { $departments = []; }
// sanitize to unique integer IDs
$departments = array_values(array_unique(array_filter(array_map(function($v){ return (int)$v; }, $departments), function($v){ return $v > 0; })));


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
    $stmtOwner = $pdo->prepare('INSERT INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, :perm)');
    $stmtOwner->execute([':pid' => $passwordId, ':uid' => $ownerId, ':perm' => 'owner']);
    // Selected assignees as editors
    if ($assignees) {
        $ins = $pdo->prepare('INSERT IGNORE INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, :perm)');
        foreach ($assignees as $aid) {
            $ins->execute([':pid' => $passwordId, ':uid' => $aid, ':perm' => 'editor']);
        }
    }
    // Selected departments
    if ($departments) {
        $insDept = $pdo->prepare('INSERT IGNORE INTO password_department_access (password_id, department_id, perm) VALUES (:pid, :did, :perm)');
        foreach ($departments as $did) {
            $insDept->execute([':pid' => $passwordId, ':did' => $did, ':perm' => 'viewer']);
        }
    }
    $pdo->commit();

    // Prepare reusable header HTML
    ob_start();
    include __DIR__ . '/header.php';
    $headerHtml = ob_get_clean();

    // Display success message and saved data with modern layout
    $linea = htmlspecialchars($_POST['linea_de_negocio'], ENT_QUOTES, 'UTF-8');
    $nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');
    $descripcion = isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion'], ENT_QUOTES, 'UTF-8') : 'N/A';
    $usuario = htmlspecialchars($_POST['usuario'], ENT_QUOTES, 'UTF-8');
    $enlaceEsc = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
    $infoAdicional = isset($_POST['info_adicional']) ? htmlspecialchars($_POST['info_adicional'], ENT_QUOTES, 'UTF-8') : 'N/A';

    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Contraseña guardada</title>
        <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='style.css'>
    </head>
    <body>
        " . $headerHtml . "
        <main class='page'>
          <div class='success-container'>
            <div class='success-card'>
              <div class='success-card__icon'>
                <svg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                  <path d='M20 6L9 17l-5-5'></path>
                </svg>
              </div>
              <div class='success-card__content'>
                <h1 class='success-card__title'>Contraseña guardada correctamente</h1>
                <p class='success-card__subtitle'>Hemos almacenado la nueva credencial y aplicado los permisos seleccionados.</p>
              </div>
              <div class='success-card__actions'>
                <a href='ver-passwords.php' class='btn-primary'>
                  Ver todas las contraseñas
                </a>
                <a href='introducir.php' class='btn-secondary'>
                  Crear otra contraseña
                </a>
              </div>
            </div>

            <div class='table-card success-details'>
              <div class='table-card__inner'>
                <h2 class='success-details__title'>Resumen de la nueva contraseña</h2>
                <table class='tabla-passwords tabla-passwords--compact'>
                  <thead>
                    <tr>
                      <th>Línea de Negocio</th>
                      <th>Nombre</th>
                      <th>Usuario</th>
                      <th>Enlace</th>
                      <th>Descripción</th>
                      <th>Info Adicional</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>" . $linea . "</td>
                      <td>" . $nombre . "</td>
                      <td>" . $usuario . "</td>
                      <td><a href='" . $enlaceEsc . "' target='_blank'>" . $enlaceEsc . "</a></td>
                      <td>" . $descripcion . "</td>
                      <td>" . $infoAdicional . "</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
    </body>
    </html>";

} catch (PDOException $e) {
    die("Error executing the query: " . $e->getMessage());
}
?>
