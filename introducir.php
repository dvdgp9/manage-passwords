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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf); ?>">
    <title>Introducir Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <?= $headerHtml ?>
    <main class="page introducir-page">
    <div class="introducir-container">
    <div class="introducir-header">
        <div class="header-titles">
            <h1>Almacenar nueva contraseña</h1>
            <p class="header-subtitle">Completa los datos para guardar una nueva credencial de forma segura.</p>
        </div>
        <button type="button" class="btn-bulk-import" id="btn-open-bulk-import">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Importación en lote
        </button>
    </div>
    <form id="form-introducir" action="guardar.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        
        <!-- Grid de campos principales -->
        <div class="form-grid">
            <div class="form-field">
                <label for="linea_de_negocio">Línea de Negocio</label>
                <input type="text" id="linea_de_negocio" name="linea_de_negocio" placeholder="General, ES, CF, EFit,..." required>
            </div>
            
            <div class="form-field">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ej: Facebook, Gmail, Canva,..." required>
            </div>
            
            <div class="form-field">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ej: usuario@example.com" required>
            </div>
            
            <div class="form-field">
                <label for="enlace">Enlace</label>
                <input type="text" id="enlace" name="enlace" placeholder="Ej: https://example.com" required>
            </div>
            
            <div class="form-field form-field--full">
                <label for="password">Contraseña</label>
                <div class="password-field-group">
                    <input type="password" id="password" name="password" placeholder="Introduce la contraseña" required>
                    <div class="password-buttons">
                        <button type="button" id="btn-toggle-password">
                            <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" id="btn-paste-password" title="Pegar desde portapapeles">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="form-field">
                <label for="descripcion">Descripción <span class="optional">(opcional)</span></label>
                <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta" rows="2"></textarea>
            </div>
            
            <div class="form-field">
                <label for="info_adicional">Info Adicional <span class="optional">(opcional)</span></label>
                <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad..." rows="2"></textarea>
            </div>
        </div>

        <!-- Sección de compartir colapsable -->
        <details class="sharing-section" open>
            <summary class="sharing-section__toggle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Compartir acceso
                <span class="sharing-section__hint">Opcional - puedes compartir más tarde</span>
                <svg class="sharing-section__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </summary>
            
            <div class="sharing-section__content">
                <div class="sharing-grid">
                    <section class="assignees-panel assignees-panel--compact">
                        <div class="assignees-header">
                            <label>Usuarios</label>
                            <div class="assignees-actions">
                                <button type="button" id="assign-all" class="btn-xs">Todos</button>
                                <button type="button" id="assign-none" class="btn-xs">Ninguno</button>
                            </div>
                        </div>
                        <div class="list-filter">
                            <svg class="list-filter__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            <input type="text" class="list-filter__input" placeholder="Buscar...">
                        </div>
                        <div class="assignees-list">
                            <?php foreach ($allUsers as $u): $uid=(int)($u['id'] ?? 0); $checked = ($uid === (int)($currentUser['id'] ?? -1)); ?>
                                <?php
                                    $displayName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
                                    $displayLabel = $displayName !== '' ? $displayName : $u['email'];
                                ?>
                                <label class="assignee-item">
                                    <input type="checkbox" name="assignees[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span class="assignee-email"><?= htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="assignee-role"><?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="assignees-panel assignees-panel--compact">
                        <div class="assignees-header">
                            <label>Departamentos</label>
                            <div class="assignees-actions">
                                <button type="button" id="assign-all-depts" class="btn-xs">Todos</button>
                                <button type="button" id="assign-none-depts" class="btn-xs">Ninguno</button>
                            </div>
                        </div>
                        <div class="list-filter">
                            <svg class="list-filter__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            <input type="text" class="list-filter__input" placeholder="Buscar...">
                        </div>
                        <div class="assignees-list" id="departments-list">
                            <?php if (empty($allDepartments)): ?>
                                <p class="text-muted">No hay departamentos. <a href="admin-users.php">Crear</a></p>
                            <?php else: ?>
                                <?php foreach ($allDepartments as $dept): ?>
                                    <label class="assignee-item">
                                        <input type="checkbox" name="departments[]" value="<?= (int)$dept['id'] ?>">
                                        <span class="assignee-email"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="assignee-role"><?= (int)$dept['user_count'] ?> usu.</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </details>

        <button type="submit">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            Guardar contraseña
        </button>
    </form>
    </div>
    </main>

    <!-- Modal Bulk Import -->
    <div class="modal hidden" id="modal-bulk-import">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-content modal-content--large">
            <div class="modal-header">
                <h2 id="modal-bulk-import-title">Importación en lote</h2>
                <button type="button" class="modal-close" data-close-modal aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="bulk-import-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <div>
                        <strong>Importa desde Excel</strong>
                        <p>Copia las columnas desde tu hoja de cálculo y pégalas directamente en la tabla. El sistema creará todas las contraseñas automáticamente.</p>
                    </div>
                </div>

                <div class="bulk-import-columns">
                    <strong>Columnas esperadas (en este orden):</strong>
                    <ol>
                        <li><strong>Línea de Negocio</strong> - Ej: General, ES, CF...</li>
                        <li><strong>Nombre</strong> - Nombre de la cuenta/servicio</li>
                        <li><strong>Descripción</strong> - (opcional)</li>
                        <li><strong>Usuario</strong> - Usuario o email</li>
                        <li><strong>Contraseña</strong> - La contraseña (se cifrará)</li>
                        <li><strong>Enlace</strong> - URL del servicio</li>
                        <li><strong>Info Adicional</strong> - (opcional)</li>
                    </ol>
                </div>

                <!-- Tabla editable -->
                <div class="bulk-table-wrapper">
                    <table class="bulk-import-table" id="bulk-import-table">
                        <thead>
                            <tr>
                                <th>Línea Negocio</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Usuario</th>
                                <th>Contraseña</th>
                                <th>Enlace</th>
                                <th>Info Adicional</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="bulk-import-body">
                            <!-- filas dinámicas -->
                        </tbody>
                    </table>
                </div>
                <div class="bulk-table-actions">
                    <button type="button" class="btn-secondary btn-sm" id="btn-add-bulk-row">+ Añadir fila</button>
                    <button type="button" class="btn-secondary btn-sm" id="btn-clear-bulk-table">Limpiar tabla</button>
                    <span class="bulk-row-count" id="bulk-row-count">0 filas con datos</span>
                </div>
                <div class="bulk-import-error" id="bulk-import-error"></div>

                <!-- Sección de acceso compartido -->
                <div class="bulk-access-section">
                    <h3>Acceso compartido (se aplicará a todas las contraseñas importadas)</h3>
                    
                    <section class="assignees-panel assignees-panel--compact">
                        <div class="assignees-header">
                            <label>Compartir con usuarios</label>
                            <div class="assignees-actions">
                                <button type="button" id="bulk-assign-all" class="btn-secondary btn-xs">Todos</button>
                                <button type="button" id="bulk-assign-none" class="btn-secondary btn-xs">Ninguno</button>
                            </div>
                        </div>
                        <div class="list-filter">
                            <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            <input type="text" class="list-filter__input" placeholder="Buscar usuario..." id="bulk-user-filter">
                        </div>
                        <div class="assignees-list assignees-list--compact" id="bulk-users-list">
                            <?php foreach ($allUsers as $u): $uid=(int)($u['id'] ?? 0); $checked = ($uid === (int)($currentUser['id'] ?? -1)); ?>
                                <?php
                                    $displayName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
                                    $displayLabel = $displayName !== '' ? $displayName . ' (' . $u['email'] . ')' : $u['email'];
                                ?>
                                <label class="assignee-item">
                                    <input type="checkbox" name="bulk_assignees[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span class="assignee-email"><?= htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="assignee-role"><?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="assignees-panel assignees-panel--compact">
                        <div class="assignees-header">
                            <label>Compartir con departamentos</label>
                            <div class="assignees-actions">
                                <button type="button" id="bulk-assign-all-depts" class="btn-secondary btn-xs">Todos</button>
                                <button type="button" id="bulk-assign-none-depts" class="btn-secondary btn-xs">Ninguno</button>
                            </div>
                        </div>
                        <div class="list-filter">
                            <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            <input type="text" class="list-filter__input" placeholder="Buscar departamento..." id="bulk-dept-filter">
                        </div>
                        <div class="assignees-list assignees-list--compact" id="bulk-depts-list">
                            <?php if (empty($allDepartments)): ?>
                                <p class="text-muted" style="padding: 12px; text-align: center;">No hay departamentos creados.</p>
                            <?php else: ?>
                                <?php foreach ($allDepartments as $dept): ?>
                                    <label class="assignee-item">
                                        <input type="checkbox" name="bulk_departments[]" value="<?= (int)$dept['id'] ?>">
                                        <span class="assignee-email"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="assignee-role"><?= (int)$dept['user_count'] ?> usuario<?= $dept['user_count'] != 1 ? 's' : '' ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- Resultados -->
                <div class="bulk-import-results" id="bulk-import-results" style="display: none;">
                    <h4>Resultados de la importación</h4>
                    <div id="bulk-import-results-content"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="button" class="btn-primary" id="btn-execute-bulk-import">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Importar contraseñas
                </button>
            </div>
        </div>
    </div>

    </body>
    </html>
