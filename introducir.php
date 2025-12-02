<?php
require_once 'security.php';
require_once 'config.php';
bootstrap_security(true); // requires authenticated session
$csrf = ensure_csrf_token();
// Current user and list of users to assign
$currentUser = current_user();
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, email, role FROM users ORDER BY email");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $allUsers = [];
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
        
        <div class="password-form-card">
            <form id="form-introducir" action="guardar.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                
                <!-- Información básica -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        Información Básica
                    </div>
                    
                    <div class="form-field">
                        <label for="linea_de_negocio">Línea de Negocio</label>
                        <input type="text" id="linea_de_negocio" name="linea_de_negocio" placeholder="General, ES, CF, EFit,..." required>
                    </div>

                    <div class="form-field">
                        <label for="nombre">Nombre del Servicio</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej: Facebook, Gmail, Canva,..." required>
                    </div>

                    <div class="form-field">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta"></textarea>
                    </div>
                </div>

                <!-- Credenciales -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Credenciales de Acceso
                    </div>
                    
                    <div class="form-field">
                        <label for="usuario">Usuario / Email</label>
                        <input type="text" id="usuario" name="usuario" placeholder="Ej: usuario@example.com" required>
                    </div>

                    <div class="form-field">
                        <label for="password">Contraseña</label>
                        <div class="password-field-group">
                            <input type="password" id="password" name="password" placeholder="Introduce la contraseña" required>
                            <div class="password-buttons">
                                <button type="button" id="btn-toggle-password">Mostrar</button>
                                <button type="button" id="btn-paste-password">Pegar Contraseña</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        Detalles Complementarios
                    </div>
                    
                    <div class="form-field">
                        <label for="enlace">Enlace / URL</label>
                        <input type="text" id="enlace" name="enlace" placeholder="Ej: example.com" required>
                    </div>

                    <div class="form-field">
                        <label for="info_adicional">Información Adicional</label>
                        <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad: Nombre de tu mascota"></textarea>
                    </div>
                </div>

                <!-- Compartir con usuarios -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Compartir Acceso
                    </div>
                    
                    <div class="assignees-panel">
                        <div class="assignees-header">
                            <label>Usuarios con acceso</label>
                            <div class="assignees-actions">
                                <button type="button" id="assign-all" class="btn-secondary">Todos</button>
                                <button type="button" id="assign-none" class="btn-secondary">Ninguno</button>
                            </div>
                        </div>
                        <div class="assignees-list">
                            <?php foreach ($allUsers as $u): $uid=(int)($u['id'] ?? 0); $checked = ($uid === (int)($currentUser['id'] ?? -1)); ?>
                                <label class="assignee-item">
                                    <input type="checkbox" name="assignees[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span class="assignee-email"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="assignee-role"><?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="assignees-hint">Si no eliges nadie, solo tú tendrás acceso inicialmente.</small>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="form-actions-bottom">
                    <button type="submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Guardar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
    </main>

    </body>
    </html>
