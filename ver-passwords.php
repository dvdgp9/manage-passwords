<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'security.php';
require_once 'config.php';

// Bootstrap security without forcing auth yet (this page sirve login y lista)
bootstrap_security(false);

// Master password from environment
$master_password = $_ENV['MASTER_PASSWORD'] ?? getenv('MASTER_PASSWORD') ?? '';

// Check if the user is already authenticated
$authenticated = $_SESSION['authenticated'] ?? false;

// Handle master password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_password = $_POST['master_password'];

    if ($entered_password === $master_password) {
        // Password is correct, set authenticated flag in session
        $_SESSION['authenticated'] = true;
        $authenticated = true;
        $_SESSION['last_activity'] = time(); // Update last activity time
        regenerate_session_id();
    } else {
        // Password is incorrect, show error
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Error de Acceso</title>
            <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
            <link rel='stylesheet' href='style.css'>
        </head>
        <body>
            <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
            <h1>Error de Acceso</h1>
            <p>La contraseña ingresada es incorrecta. <a href='ver-passwords.php'>Intentar de nuevo</a>.</p>
        </body>
        </html>";
        exit;
    }
}

// If not authenticated, show the password prompt
if (!$authenticated) {
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Ver Contraseñas</title>
        <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='style.css'>
    </head>
    <body>
        <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
        <h1>Acceso a Contraseñas</h1>
        <form action='ver-passwords.php' method='post'>
            <label for='master_password'>Contraseña Maestra:</label>
            <input type='password' id='master_password' name='master_password' required>
            <button type='submit'>Acceder</button>
        </form>
    </body>
    </html>";
    exit;
}

// Fetch passwords based on search term
$searchTerm = $_GET['search'] ?? '';

$pdo = getDBConnection();
$sql = "SELECT * FROM `passwords_manager`";

if (!empty($searchTerm)) {
    $sql .= " WHERE linea_de_negocio LIKE :search1
              OR nombre LIKE :search2
              OR usuario LIKE :search3
              OR descripcion LIKE :search4";
    $stmt = $pdo->prepare($sql);
    // Bind the search term to each unique placeholder
    $stmt->execute([
        ':search1' => "%$searchTerm%",
        ':search2' => "%$searchTerm%",
        ':search3' => "%$searchTerm%",
        ':search4' => "%$searchTerm%"
    ]);
} else {
    $stmt = $pdo->query($sql);
}

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
        <a href='index.html'><button>Inicio</button></a>
        <a href='introducir.php'><button>Introducir Contraseña</button></a>
        <form action='logout.php' method='post' style='display: inline;'>
            <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf) . "'>
            <button type='submit'>Cerrar Sesión</button>
        </form>
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
