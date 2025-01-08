<?php
// Include the config file
require_once 'config.php';

// Hardcoded master password (change this to a secure password)
$master_password = 'contraEBO';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_password = $_POST['master_password'];

    // Verify the password
    if ($entered_password === $master_password) {
        // Password is correct, proceed to display the passwords
        $pdo = getDBConnection();

        // Fetch all passwords from the database
        $sql = "SELECT * FROM `passwords-manager`"; // Use backticks for the table name
        $stmt = $pdo->query($sql);
        $passwords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Display the passwords in a table
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
            <div class='navigation'>
                <a href='index.html'><button>Inicio</button></a>
                <a href='introducir.php'><button>Introducir Contraseña</button></a>
            </div>

            <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
            <h1>Contraseñas Almacenadas</h1>
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
                    </tr>";

                    foreach ($passwords as $row) {
                        $decrypted_password = decrypt($row['password']); // Decrypt the password
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
    } else {
        // Password is incorrect
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
    }
} else {
    // Display the password prompt
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
}
?>
