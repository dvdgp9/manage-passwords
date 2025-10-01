<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'security.php';
require_once 'config.php';

// Requiere autenticación con el nuevo sistema de usuarios
bootstrap_security(true);

$user = current_user();

// Fetch passwords based on search term
$searchTerm = $_GET['search'] ?? '';

$pdo = getDBConnection();
// Construir consulta con scoping por rol
$params = [];
$role = $user['role'] ?? '';
if ($role === 'admin') {
    $sql = "SELECT pm.* FROM passwords_manager pm";
    $where = [];
} else {
    $sql = "SELECT pm.* FROM passwords_manager pm
            LEFT JOIN passwords_access pa
              ON pa.password_id = pm.id AND pa.user_id = :uid";
    $params[':uid'] = (int)($user['id'] ?? 0);
    if ($role === 'lector') {
        // Viewer: solo asignadas explícitamente
        $where = ["pa.user_id IS NOT NULL"]; 
    } else {
        // Editor u otros: asignadas o globales
        $where = ["(pa.user_id IS NOT NULL OR pm.owner_user_id IS NULL)"];
    }
}

if (!empty($searchTerm)) {
    $where[] = "(pm.linea_de_negocio LIKE :search1 OR pm.nombre LIKE :search2 OR pm.usuario LIKE :search3 OR pm.descripcion LIKE :search4)";
    $params[':search1'] = "%$searchTerm%";
    $params[':search2'] = "%$searchTerm%";
    $params[':search3'] = "%$searchTerm%";
    $params[':search4'] = "%$searchTerm%";
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$passwords = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csrf = ensure_csrf_token();

// Prepare reusable header HTML
ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();

// Display the table with search results
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Ver Contraseñas</title>
    <meta name='csrf-token' content='" . htmlspecialchars($csrf) . "'>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='style.css'>
</head>
<body>
    " . $headerHtml . "
    <main class='page'>
    <h1>Contraseñas Almacenadas</h1>

    <form action='ver-passwords.php' method='get' class='search-form'>
        <label for='search'>Buscar:</label>
        <input type='text' id='search' name='search' placeholder='Buscar por nombre, usuario, descripción, etc.' value='" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "'>
        <button type='button' id='clear-search' class='clear-search-btn'>Limpiar</button>
    </form>

    <div class='table-container'>
        <table class='tabla-passwords'>
            <tr>
                <th>Línea de Negocio</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th>Contraseña</th>
                <th>Enlace</th>
                <th>Info Adicional</th>
                " . ($role === 'lector' ? "" : "<th>Acciones</th>") . "
            </tr>";

            foreach ($passwords as $row) {
                $decrypted_password = decrypt($row['password']);
                echo "<tr>
                        <td>" . htmlspecialchars($row['linea_de_negocio']) . "</td>
                        <td>" . htmlspecialchars($row['nombre']) . "</td>
                        <td>" . htmlspecialchars($row['descripcion'] ?? 'N/A') . "</td>
                        <td>" . htmlspecialchars($row['usuario']) . "</td>
                        <td>
                            <div class='password-cell'>
                                <span class='password-text'>" . htmlspecialchars($decrypted_password) . "</span>
                                <button type='button'
                                        class='copy-btn'
                                        title='Copiar'
                                        aria-label='Copiar contraseña'
                                        data-password='" . htmlspecialchars($decrypted_password, ENT_QUOTES, 'UTF-8') . "'>
                                    <!-- Copy icon (Untitled UI style: copy-01) -->
                                    <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>
                                        <rect x='9' y='9' width='13' height='13' rx='2' ry='2'></rect>
                                        <path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td><a href='" . htmlspecialchars($row['enlace']) . "' target='_blank'>" . htmlspecialchars($row['enlace']) . "</a></td>
                        <td>" . htmlspecialchars($row['info_adicional'] ?? 'N/A') . "</td>
                        " . ($role === 'lector' ? "" : "<td><div class='button-container'><a class='modify-btn' href='edit-password.php?id=" . $row['id'] . "'>✏️</a><button class='delete-btn' data-id='" . $row['id'] . "'>🗑️</button></div></td>") . "
                    </tr>";
            }

        echo "</table>
    </div>
    </main>

    <script src='scripts.js'></script>
</body>
</html>";
?>
