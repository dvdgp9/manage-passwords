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

// Load all users and current assignees
$allUsers = [];
$currentAssignees = [];
$allDepartments = [];
$currentDepartments = [];
try {
    // All users
    $stmt = $pdo->query("SELECT id, email, role, nombre, apellidos FROM users ORDER BY email");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Current user assignees
    $stmt = $pdo->prepare("SELECT user_id FROM passwords_access WHERE password_id = :pid");
    $stmt->execute([':pid' => (int)$passwordId]);
    $currentAssignees = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    
    // All departments
    $stmt = $pdo->query("SELECT id, name, (SELECT COUNT(*) FROM user_departments WHERE department_id = departments.id) as user_count FROM departments ORDER BY name");
    $allDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Current department assignees
    $stmt = $pdo->prepare("SELECT department_id FROM password_department_access WHERE password_id = :pid");
    $stmt->execute([':pid' => (int)$passwordId]);
    $currentDepartments = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'department_id');
} catch (Throwable $e) {
    // Fallar silenciosamente, arrays quedan vacíos
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_from_request();
    
    // Selected assignees and departments from form
    $assignees = $_POST['assignees'] ?? [];
    if (!is_array($assignees)) { $assignees = []; }
    $assignees = array_values(array_unique(array_filter(array_map(function($v){ return (int)$v; }, $assignees), function($v){ return $v > 0; })));
    
    $departments = $_POST['departments'] ?? [];
    if (!is_array($departments)) { $departments = []; }
    $departments = array_values(array_unique(array_filter(array_map(function($v){ return (int)$v; }, $departments), function($v){ return $v > 0; })));
    
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
        $pdo->beginTransaction();
        
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

        // Update user assignees: delete all and re-insert
        $pdo->prepare('DELETE FROM passwords_access WHERE password_id = :pid')->execute([':pid' => (int)$passwordId]);
        if ($assignees) {
            $insUser = $pdo->prepare('INSERT INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, :perm)');
            foreach ($assignees as $aid) {
                $insUser->execute([':pid' => (int)$passwordId, ':uid' => $aid, ':perm' => 'editor']);
            }
        }
        
        // Update department assignees: delete all and re-insert
        $pdo->prepare('DELETE FROM password_department_access WHERE password_id = :pid')->execute([':pid' => (int)$passwordId]);
        if ($departments) {
            $insDept = $pdo->prepare('INSERT INTO password_department_access (password_id, department_id, perm) VALUES (:pid, :did, :perm)');
            foreach ($departments as $did) {
                $insDept->execute([':pid' => (int)$passwordId, ':did' => $did, ':perm' => 'viewer']);
            }
        }
        
        $pdo->commit();

        // Redirect back to the passwords list after successful update
        header('Location: ver-passwords.php');
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
        <input type="text" id="linea_de_negocio" name="linea_de_negocio" value="<?= htmlspecialchars($password['linea_de_negocio'], ENT_QUOTES, 'UTF-8') ?>" placeholder="General, ES, CF, EFit,..." required>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($password['nombre'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Facebook, Gmail, Canva,..." required>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta"><?= htmlspecialchars($password['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($password['usuario'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: usuario@example.com" required>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" placeholder="Dejar en blanco para no cambiar">
        <div class="password-buttons">
            <button type="button" id="btn-toggle-password">Mostrar</button>
            <button type="button" id="btn-paste-password">Pegar Contraseña</button>
        </div>

        <label for="enlace">Enlace:</label>
        <input type="text" id="enlace" name="enlace" value="<?= htmlspecialchars($password['enlace'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: example.com" required>

        <label for="info_adicional">Info Adicional:</label>
        <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad: Nombre de tu mascota"><?= htmlspecialchars($password['info_adicional'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <section class="assignees-panel">
            <div class="assignees-header">
                <label>Compartir con usuarios</label>
                <div class="assignees-actions">
                    <button type="button" id="assign-all" class="btn-secondary">Asignar a todos</button>
                    <button type="button" id="assign-none" class="btn-secondary">Quitar todos</button>
                </div>
            </div>
            <div class="list-filter">
                <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="text" class="list-filter__input" placeholder="Buscar usuario...">
            </div>
            <div class="assignees-list">
                <?php foreach ($allUsers as $u): $uid=(int)($u['id'] ?? 0); $checked = in_array($uid, $currentAssignees); ?>
                    <?php
                        $displayName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
                        $displayLabel = $displayName !== '' ? $displayName . ' (' . $u['email'] . ')' : $u['email'];
                    ?>
                    <label class="assignee-item">
                        <input type="checkbox" name="assignees[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>>
                        <span class="assignee-email"><?= htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="assignee-role"><?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small class="assignees-hint">Selecciona los usuarios que tendrán acceso a esta contraseña.</small>
        </section>

        <section class="assignees-panel">
            <div class="assignees-header">
                <label>Compartir con departamentos</label>
                <div class="assignees-actions">
                    <button type="button" id="assign-all-depts" class="btn-secondary">Seleccionar todos</button>
                    <button type="button" id="assign-none-depts" class="btn-secondary">Quitar todos</button>
                </div>
            </div>
            <div class="list-filter">
                <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="text" class="list-filter__input" placeholder="Buscar departamento...">
            </div>
            <div class="assignees-list" id="departments-list">
                <?php if (empty($allDepartments)): ?>
                    <p class="text-muted" style="padding: 12px; text-align: center;">No hay departamentos creados. <a href="admin-users.php" style="color: #23AAC5;">Crear departamento</a></p>
                <?php else: ?>
                    <?php foreach ($allDepartments as $dept): $did=(int)$dept['id']; $checked = in_array($did, $currentDepartments); ?>
                        <label class="assignee-item">
                            <input type="checkbox" name="departments[]" value="<?= $did ?>" <?= $checked ? 'checked' : '' ?>>
                            <span class="assignee-email"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="assignee-role"><?= (int)$dept['user_count'] ?> usuario<?= $dept['user_count'] != 1 ? 's' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <small class="assignees-hint">Al compartir con un departamento, todos sus miembros tendrán acceso automáticamente.</small>
        </section>

        <button type="submit">Guardar Cambios</button>
    </form>
    </div>
    </main>
</body>
</html>
