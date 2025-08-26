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
// Construir consulta con scoping por usuario salvo admin
$sql = "SELECT * FROM `passwords_manager`";
$where = [];
$params = [];

if (!$user || ($user['role'] ?? '') !== 'admin') {
    $where[] = "owner_user_id = :owner_id";
    $params[':owner_id'] = (int)($user['id'] ?? 0);
}

if (!empty($searchTerm)) {
    $where[] = "(linea_de_negocio LIKE :search1 OR nombre LIKE :search2 OR usuario LIKE :search3 OR descripcion LIKE :search4)";
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
    <div class='navigation'>
        <a href='index.php'><button>Inicio</button></a>
        <a href='introducir.php'><button>Introducir Contraseña</button></a>
        <form action='logout.php' method='post' style='display: inline;'>
            <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf) . "'>
            <button type='submit'>Cerrar Sesión</button>
        </form>
        <span style='margin-left:12px;'>" . htmlspecialchars(($user['email'] ?? '') . ' (' . ($user['role'] ?? '') . ')') . "</span>
    </div>

    <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
    <h1>Contraseñas Almacenadas</h1>

    <form action='ver-passwords.php' method='get' class='search-form'>
        <label for='search'>Buscar:</label>
        <input type='text' id='search' name='search' placeholder='Buscar por nombre, usuario, descripción, etc.'>
        <button type='submit'>Buscar</button>
        <button type='button' id='clear-search' class='clear-search-btn'>Borrar búsqueda</button>
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
                <th>Acciones</th>
            </tr>";

            foreach ($passwords as $row) {
                $decrypted_password = decrypt($row['password']);
                echo "<tr>
                        <td>" . htmlspecialchars($row['linea_de_negocio']) . "</td>
                        <td>" . htmlspecialchars($row['nombre']) . "</td>
                        <td>" . htmlspecialchars($row['descripcion'] ?? 'N/A') . "</td>
                        <td>" . htmlspecialchars($row['usuario']) . "</td>
                        <td>" . htmlspecialchars($decrypted_password) . "</td>
                        <td><a href='" . htmlspecialchars($row['enlace']) . "' target='_blank'>" . htmlspecialchars($row['enlace']) . "</a></td>
                        <td>" . htmlspecialchars($row['info_adicional'] ?? 'N/A') . "</td>
                        <td>
                            <div class='button-container'>
                                <a href='edit-password.php?id=" . $row['id'] . "'><button class='modify-btn'>✏️</button></a>
                                <button class='delete-btn' data-id='" . $row['id'] . "'>🗑️</button>
                            </div>
                        </td>
                    </tr>";
            }

        echo "</table>
    </div>

    <script src='scripts.js'></script>
</body>
</html>";
?>
