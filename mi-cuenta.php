<?php
require_once 'security.php';
require_once 'config.php';

bootstrap_security(true);
$user = current_user();
$csrf = ensure_csrf_token();
$pdo = getDBConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_from_request();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        
        if (mb_strlen($nombre) > 100 || mb_strlen($apellidos) > 100) {
            $error = 'El nombre y apellidos no pueden superar los 100 caracteres';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nombre = ?, apellidos = ? WHERE id = ?");
            $stmt->execute([$nombre ?: null, $apellidos ?: null, $user['id']]);
            $success = 'Perfil actualizado correctamente';
            // Refresh user data
            $user['nombre'] = $nombre;
            $user['apellidos'] = $apellidos;
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($currentPassword, $userData['password_hash'])) {
            $error = 'La contraseña actual no es correcta';
        } elseif (strlen($newPassword) < 8) {
            $error = 'La nueva contraseña debe tener al menos 8 caracteres';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
            $success = 'Contraseña actualizada correctamente';
        }
    }
}

// Get user departments
$stmt = $pdo->prepare("
    SELECT d.name 
    FROM departments d 
    JOIN user_departments ud ON ud.department_id = d.id 
    WHERE ud.user_id = ?
    ORDER BY d.name
");
$stmt->execute([$user['id']]);
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get user display name
$displayName = '';
if (!empty($user['nombre'])) {
    $displayName = $user['nombre'];
    if (!empty($user['apellidos'])) {
        $displayName .= ' ' . $user['apellidos'];
    }
} else {
    $displayName = explode('@', $user['email'])[0];
}

// Avatar initial
$avatarInitial = strtoupper(substr($user['nombre'] ?? $user['email'] ?? 'U', 0, 1));

// Role label
$roleLabels = [
    'admin' => 'Administrador',
    'editor' => 'Editor',
    'lector' => 'Lector'
];
$roleLabel = $roleLabels[$user['role']] ?? $user['role'];

ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?= $headerHtml ?>
    <main class="page">
        <div class="account-container">
            <!-- Profile Header -->
            <div class="account-header">
                <div class="account-avatar">
                    <span class="account-avatar__initial"><?= $avatarInitial ?></span>
                </div>
                <div class="account-header__info">
                    <h1 class="account-header__name"><?= htmlspecialchars($displayName) ?></h1>
                    <p class="account-header__email"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="account-header__badges">
                        <span class="badge badge--role"><?= htmlspecialchars($roleLabel) ?></span>
                        <?php foreach ($departments as $dept): ?>
                            <span class="badge badge--dept"><?= htmlspecialchars($dept) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert-notice"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="account-grid">
                <!-- Personal Info Card -->
                <div class="account-card">
                    <div class="account-card__header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <h2>Información personal</h2>
                    </div>
                    <form method="post" class="account-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($user['nombre'] ?? '') ?>" 
                                       placeholder="Tu nombre">
                            </div>
                            <div class="form-group">
                                <label for="apellidos">Apellidos</label>
                                <input type="text" id="apellidos" name="apellidos" 
                                       value="<?= htmlspecialchars($user['apellidos'] ?? '') ?>" 
                                       placeholder="Tus apellidos">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email_display">Correo electrónico</label>
                            <input type="email" id="email_display" 
                                   value="<?= htmlspecialchars($user['email']) ?>" 
                                   disabled>
                            <span class="form-hint">Solo un administrador puede cambiar tu email</span>
                        </div>

                        <div class="form-group">
                            <label>Departamentos</label>
                            <div class="dept-display">
                                <?php if (empty($departments)): ?>
                                    <span class="dept-none">Sin departamento asignado</span>
                                <?php else: ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <span class="dept-tag"><?= htmlspecialchars($dept) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <span class="form-hint">Solo un administrador puede cambiar tus departamentos</span>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar cambios
                        </button>
                    </form>
                </div>

                <!-- Change Password Card -->
                <div class="account-card">
                    <div class="account-card__header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <h2>Cambiar contraseña</h2>
                    </div>
                    <form method="post" class="account-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Contraseña actual</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="current_password" name="current_password" 
                                       required autocomplete="current-password">
                                <button type="button" class="password-toggle" data-target="current_password">
                                    <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Nueva contraseña</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" 
                                       required minlength="8" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-target="new_password">
                                    <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <span class="form-hint">Mínimo 8 caracteres</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar nueva contraseña</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       required minlength="8" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-target="confirm_password">
                                    <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary btn-password">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Cambiar contraseña
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Info Footer -->
            <div class="account-footer">
                <div class="account-meta">
                    <span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Cuenta creada: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                    </span>
                    <?php if (!empty($user['last_login'])): ?>
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            Último acceso: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const eyeOpen = this.querySelector('.eye-open');
                const eyeClosed = this.querySelector('.eye-closed');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeOpen.classList.add('hidden');
                    eyeClosed.classList.remove('hidden');
                } else {
                    input.type = 'password';
                    eyeOpen.classList.remove('hidden');
                    eyeClosed.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
