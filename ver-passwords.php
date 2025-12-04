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
// Construir consulta con scoping por rol (incluye acceso por departamentos)
$params = [];
$role = $user['role'] ?? '';
$userId = (int)($user['id'] ?? 0);

if ($role === 'admin') {
    $sql = "SELECT DISTINCT pm.* FROM passwords_manager pm";
    $where = [];
} else {
    // Join con accesos directos (passwords_access) y por departamento (password_department_access + user_departments)
    $sql = "SELECT DISTINCT pm.* FROM passwords_manager pm
            LEFT JOIN passwords_access pa
              ON pa.password_id = pm.id AND pa.user_id = :uid
            LEFT JOIN password_department_access pda
              ON pda.password_id = pm.id
            LEFT JOIN user_departments ud
              ON ud.department_id = pda.department_id AND ud.user_id = :uid2";
    $params[':uid'] = $userId;
    $params[':uid2'] = $userId;
    
    if ($role === 'lector') {
        // Viewer: solo asignadas explícitamente (directas o por departamento)
        $where = ["(pa.user_id IS NOT NULL OR ud.user_id IS NOT NULL)"]; 
    } else {
        // Editor u otros: asignadas (directas o por departamento) o globales
        $where = ["(pa.user_id IS NOT NULL OR ud.user_id IS NOT NULL OR pm.owner_user_id IS NULL)"];
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
    <section class='page-hero'>
        <div class='page-hero__content'>
            <h1 class='page-hero__title'>Contraseñas</h1>
            <p class='page-hero__subtitle'>Gestiona y accede a todas tus credenciales de forma segura</p>
        </div>
        <form action='ver-passwords.php' method='get' class='search-box'>
            <div class='search-box__input-wrapper'>
                <svg class='search-box__icon' xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                    <circle cx='11' cy='11' r='8'></circle>
                    <path d='m21 21-4.3-4.3'></path>
                </svg>
                <input type='text' id='search' name='search' placeholder='Buscar por nombre, usuario, descripción...' value='" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "'>
                <button type='button' id='clear-search' class='search-box__clear' title='Limpiar búsqueda'>
                    <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                        <path d='M18 6 6 18'></path>
                        <path d='m6 6 12 12'></path>
                    </svg>
                </button>
            </div>
        </form>
    </section>

    <div class='table-container'>
        <div class='table-card'>
            <div class='table-card__inner'>
                <table class='tabla-passwords'>
                <thead>
                    <tr>
                        <th>Línea de Negocio</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>Contraseña</th>
                        <th>Enlace</th>
                        <th>Info Adicional</th>
                        " . ($role === 'lector' ? "" : "<th>Acciones</th>") . "
                    </tr>
                </thead>
                <tbody>";

            foreach ($passwords as $row) {
                $decrypted_password = decrypt($row['password']);
                echo "<tr>
                        <td>" . htmlspecialchars($row['linea_de_negocio']) . "</td>
                        <td>" . htmlspecialchars($row['nombre']) . "</td>
                        <td>" . htmlspecialchars($row['descripcion'] ?? 'N/A') . "</td>
                        <td>" . htmlspecialchars($row['usuario']) . "</td>
                        <td>
                            <div class='password-cell'>
                                <span class='password-text' data-password='" . htmlspecialchars($decrypted_password, ENT_QUOTES, 'UTF-8') . "'>••••••••</span>
                                <button type='button'
                                        class='toggle-password-btn'
                                        title='Mostrar contraseña'
                                        aria-label='Mostrar contraseña'>
                                    <!-- Eye icon (show) -->
                                    <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>
                                        <path d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'></path>
                                        <circle cx='12' cy='12' r='3'></circle>
                                    </svg>
                                </button>
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
                        " . ($role === 'lector' ? "" : "<td><div class='button-container'>
                            <a class='modify-btn' href='edit-password.php?id=" . $row['id'] . "' title='Editar' aria-label='Editar'>
                                <!-- edit-02 icon -->
                                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>
                                    <path d='M12 20h9'/>
                                    <path d='M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z'/>
                                </svg>
                            </a>
                            <button class='delete-btn' data-id='" . $row['id'] . "' title='Eliminar' aria-label='Eliminar'>
                                <!-- trash-01 icon -->
                                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>
                                    <path d='M3 6h18'/>
                                    <path d='M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6'/>
                                    <path d='M10 11v6'/>
                                    <path d='M14 11v6'/>
                                    <path d='M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2'/>
                                </svg>
                            </button>
                        </div></td>") . "
                    </tr>";
            }

        echo "                </tbody>
                </table>
            </div>
        </div>
    </div>
    </main>

    <script src='scripts.js'></script>
</body>
</html>";
?>
