<?php
require_once 'security.php';
require_once 'config.php';
bootstrap_security(true); // requires authenticated session
$csrf = ensure_csrf_token();
// Current user and list of users to assign
$currentUser = current_user();
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, email, role, nombre, apellidos FROM users ORDER BY email");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Cargar departamentos
    $stmt = $pdo->query("SELECT id, name, (SELECT COUNT(*) FROM user_departments WHERE department_id = departments.id) as user_count FROM departments ORDER BY name");
    $allDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $allUsers = [];
    $allDepartments = [];
}
// Prepare reusable header HTML
ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Introducir Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <?= $headerHtml ?>
    <main class="page introducir-page">
    <div class="introducir-container">
    <h1>Almacenar nueva contraseña</h1>
    <form id="form-introducir" action="guardar.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <label for="linea_de_negocio">Línea de Negocio:</label>

        <input type="text" id="linea_de_negocio" name="linea_de_negocio" placeholder="General, ES, CF, EFit,..." required>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" placeholder="Ej: Facebook, Gmail, Canva,..." required>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta"></textarea>

        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" placeholder="Ej: usuario@example.com" required>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" placeholder="Introduce la contraseña" required>
        <div class="password-buttons">
            <button type="button" id="btn-toggle-password">Mostrar</button>
            <button type="button" id="btn-paste-password">Pegar Contraseña</button>
        </div>

        <label for="enlace">Enlace:</label>
        <input type="text" id="enlace" name="enlace" placeholder="Ej: example.com" required>

        <label for="info_adicional">Info Adicional:</label>
        <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad: Nombre de tu mascota"></textarea>

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
                <?php foreach ($allUsers as $u): $uid=(int)($u['id'] ?? 0); $checked = ($uid === (int)($currentUser['id'] ?? -1)); ?>
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
            <small class="assignees-hint">Si no eliges nadie, solo tú (creador) tendrás acceso. Podrás compartir más tarde.</small>
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
                    <?php foreach ($allDepartments as $dept): ?>
                        <label class="assignee-item">
                            <input type="checkbox" name="departments[]" value="<?= (int)$dept['id'] ?>">
                            <span class="assignee-email"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="assignee-role"><?= (int)$dept['user_count'] ?> usuario<?= $dept['user_count'] != 1 ? 's' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <small class="assignees-hint">Al compartir con un departamento, todos sus miembros tendrán acceso automáticamente.</small>
        </section>
        <button type="submit">Guardar</button>
    </form>
    </div>
    </main>

    </body>
    </html>
