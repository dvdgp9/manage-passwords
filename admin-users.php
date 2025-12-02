<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

bootstrap_security(true); // requiere sesión

if (!is_admin()) {
    http_response_code(403);
    echo 'Acceso restringido: solo administradores';
    exit;
}

$csrf = ensure_csrf_token();
$errors = [];
$notices = [];

function valid_role(string $r): bool {
    return in_array($r, ['admin','editor','lector'], true);
}

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de conexión a base de datos';
    exit;
}

// Helpers
function is_last_admin(PDO $pdo, int $userId): bool {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    if ($count <= 1) {
        // verificar si el único admin es el usuario objetivo
        $st = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $st->execute([':id' => $userId]);
        $role = (string)($st->fetchColumn() ?: '');
        return $role === 'admin';
    }
    return false;
}

// Acción POST (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_from_request();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email = trim((string)($_POST['email'] ?? ''));
        $role  = trim((string)($_POST['role'] ?? 'editor'));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        if (!valid_role($role)) {
            $errors[] = 'Rol inválido';
        }
        if ($password === '' || strlen($password) < 8) {
            $errors[] = 'La contraseña es obligatoria y debe tener al menos 8 caracteres';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                // evitar duplicados
                $st = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
                $st->execute([':email' => $email]);
                if ($st->fetch()) {
                    throw new RuntimeException('Ya existe un usuario con ese email');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (:email, :ph, :role, NOW())');
                $st->execute([':email' => $email, ':ph' => $hash, ':role' => $role]);
                $newUserId = (int)$pdo->lastInsertId();
                
                // Guardar departamentos del usuario
                $userDepts = $_POST['user_departments'] ?? [];
                if (is_array($userDepts) && $userDepts) {
                    $insDept = $pdo->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (:uid, :did)');
                    foreach ($userDepts as $did) {
                        $did = (int)$did;
                        if ($did > 0) {
                            $insDept->execute([':uid' => $newUserId, ':did' => $did]);
                        }
                    }
                }
                
                $pdo->commit();
                header('Location: admin-users.php?notice=created');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'No se pudo crear el usuario';
            }
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $email = trim((string)($_POST['email'] ?? ''));
        $role  = trim((string)($_POST['role'] ?? ''));
        $password = (string)($_POST['password'] ?? ''); // opcional

        if ($id <= 0) { $errors[] = 'ID inválido'; }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email inválido'; }
        if ($role !== '' && !valid_role($role)) { $errors[] = 'Rol inválido'; }
        if ($password !== '' && strlen($password) < 8) { $errors[] = 'Si se define contraseña, mínimo 8 caracteres'; }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                // comprobar existencia
                $st = $pdo->prepare('SELECT id, role FROM users WHERE id = :id');
                $st->execute([':id' => $id]);
                $u = $st->fetch(PDO::FETCH_ASSOC);
                if (!$u) { throw new RuntimeException('Usuario no encontrado'); }

                // evitar duplicado de email
                $st = $pdo->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $st->execute([':email' => $email, ':id' => $id]);
                if ($st->fetch()) { throw new RuntimeException('Ya existe otro usuario con ese email'); }

                // actualizar
                $params = [':email' => $email, ':role' => ($role !== '' ? $role : $u['role']), ':id' => $id];
                $sql = 'UPDATE users SET email = :email, role = :role';
                if ($password !== '') {
                    $sql .= ', password_hash = :ph';
                    $params[':ph'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = :id';
                $upd = $pdo->prepare($sql);
                $upd->execute($params);

                // Actualizar departamentos del usuario (borrar y reinsertar)
                $pdo->prepare('DELETE FROM user_departments WHERE user_id = :uid')->execute([':uid' => $id]);
                $userDepts = $_POST['user_departments'] ?? [];
                if (is_array($userDepts) && $userDepts) {
                    $insDept = $pdo->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (:uid, :did)');
                    foreach ($userDepts as $did) {
                        $did = (int)$did;
                        if ($did > 0) {
                            $insDept->execute([':uid' => $id, ':did' => $did]);
                        }
                    }
                }

                $pdo->commit();
                header('Location: admin-users.php?notice=updated');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'No se pudo actualizar el usuario';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $errors[] = 'ID inválido'; }
        if (!$errors) {
            // no permitir borrarse a sí mismo
            if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
                $errors[] = 'No puedes borrar tu propio usuario';
            }
            // evitar borrar último admin
            if (!$errors && is_last_admin($pdo, $id)) {
                $errors[] = 'No se puede borrar el último administrador';
            }
        }
        if (!$errors) {
            try {
                $st = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $st->execute([':id' => $id]);
                header('Location: admin-users.php?notice=deleted');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'No se pudo eliminar el usuario';
            }
        }
    }
}

// Cargar todos los departamentos para el formulario de usuario
$allDepartments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
    $allDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// Soporte de edición por GET ?edit=ID
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editUser = null;
$editUserDepartments = [];
if ($editId > 0) {
    $st = $pdo->prepare('SELECT id, email, role, created_at, last_login FROM users WHERE id = :id');
    $st->execute([':id' => $editId]);
    $editUser = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    
    // Cargar departamentos del usuario en edición
    if ($editUser) {
        $stmt = $pdo->prepare("SELECT department_id FROM user_departments WHERE user_id = :uid");
        $stmt->execute([':uid' => $editId]);
        $editUserDepartments = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'department_id');
    }
}

// Listado básico con departamentos (sin paginación por ahora)
$users = [];
try {
    $st = $pdo->query('SELECT id, email, role, created_at, last_login FROM users ORDER BY created_at DESC');
    $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Cargar departamentos por usuario
    foreach ($users as &$user) {
        $stmt = $pdo->prepare("
            SELECT d.id, d.name 
            FROM departments d
            INNER JOIN user_departments ud ON d.id = ud.department_id
            WHERE ud.user_id = :uid
            ORDER BY d.name ASC
        ");
        $stmt->execute([':uid' => $user['id']]);
        $user['departments'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    unset($user); // romper referencia
} catch (Throwable $e) {
    $errors[] = 'No se pudo cargar el listado de usuarios';
}

// Header reusable
ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración</title>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?= $headerHtml ?>
  <main class="page">
    <div class="page-header">
      <h1>Administración</h1>
    </div>

    <?php if ($errors): ?>
      <div class="alert-error">
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($notices): ?>
      <div class="alert-notice">
        <?php foreach ($notices as $n): ?>
          <div><?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ========== GESTIÓN DE USUARIOS ========== -->
    <section class="admin-section">
      <div class="section-header">
        <h2>Gestión de Usuarios</h2>
        <button id="btn-new-user" class="btn-primary">Nuevo usuario</button>
      </div>

    <section id="admin-user-form" class="admin-form-card<?= $editUser ? '' : ' hidden' ?>">
      <div class="admin-form-header">
        <h2 id="form-title"><?= $editUser ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
        <p class="admin-form-subtitle"><?= $editUser ? 'Modifica los datos del usuario' : 'Crea una nueva cuenta de usuario' ?></p>
      </div>
      
      <form id="form-admin-user" method="post" action="admin-users.php<?= $editUser ? '?edit='.(int)$editUser['id'] : '' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="form-action" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?>
          <input type="hidden" id="form-id" name="id" value="<?= (int)$editUser['id'] ?>">
        <?php else: ?>
          <input type="hidden" id="form-id" name="id" value="">
        <?php endif; ?>

        <div class="admin-form-grid">
          <div class="admin-form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($editUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario@ejemplo.com">
            <small class="admin-form-hint">Debe ser único y con formato válido.</small>
            <div class="field-error hidden" id="email-error"></div>
          </div>

          <div class="admin-form-group">
            <label for="role">Rol</label>
            <select id="role" name="role" required>
              <?php $roles = ['admin' => 'Administrador', 'editor' => 'Editor', 'lector' => 'Lector'];
              $cur = $editUser['role'] ?? 'editor';
              foreach ($roles as $val => $label): ?>
                <option value="<?= $val ?>" <?= $cur === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <small class="admin-form-hint">Define los permisos del usuario.</small>
          </div>

          <div class="admin-form-group">
            <label for="password"><?= $editUser ? 'Nueva contraseña' : 'Contraseña' ?></label>
            <div class="admin-input-group">
              <input type="password" id="password" name="password" <?= $editUser ? '' : 'required' ?> placeholder="<?= $editUser ? 'Dejar vacío para mantener' : '••••••••' ?>">
              <button type="button" id="btn-toggle-password" class="admin-input-btn" title="Mostrar/ocultar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
            <div class="field-error hidden" id="password-error"></div>
          </div>

          <div class="admin-form-group">
            <label for="confirm_password">Confirmar contraseña</label>
            <div class="admin-input-group">
              <input type="password" id="confirm_password" name="confirm_password" <?= $editUser ? '' : 'required' ?> placeholder="Repite la contraseña">
              <button type="button" id="btn-toggle-confirm" class="admin-input-btn" title="Mostrar/ocultar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
            <div class="field-error hidden" id="confirm-error"></div>
          </div>
        </div>

        <!-- Departamentos del usuario -->
        <div class="admin-form-section">
          <div class="admin-form-section-header">
            <label>Departamentos</label>
            <div class="admin-form-section-actions">
              <button type="button" id="user-dept-all" class="btn-text">Todos</button>
              <button type="button" id="user-dept-none" class="btn-text">Ninguno</button>
            </div>
          </div>
          <div class="admin-checkbox-grid" id="user-departments-list">
            <?php if (empty($allDepartments)): ?>
              <p class="admin-form-empty">No hay departamentos. <a href="#" id="scroll-to-dept">Crear uno</a></p>
            <?php else: ?>
              <?php foreach ($allDepartments as $dept): 
                $checked = in_array((int)$dept['id'], $editUserDepartments);
              ?>
                <label class="admin-checkbox-item">
                  <input type="checkbox" name="user_departments[]" value="<?= (int)$dept['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                  <span class="admin-checkbox-label"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="admin-form-actions">
          <button type="button" class="btn-secondary" id="btn-cancel-form">Cancelar</button>
          <button type="submit" class="btn-primary" id="btn-submit-form"><?= $editUser ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
      </form>
    </section>

    <section class="table-container">
      <div class="table-card">
        <div class="table-card__inner">
          <table class="tabla-usuarios">
            <thead>
              <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Departamentos</th>
                <th>Creado</th>
                <th>Último acceso</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?php if (!empty($u['departments'])): ?>
                      <div class="user-departments">
                        <?php foreach ($u['departments'] as $dept): ?>
                          <span class="dept-badge"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['last_login'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="button-container">
                      <a class="modify-btn" href="admin-users.php?edit=<?= (int)$u['id'] ?>" title="Editar" aria-label="Editar" data-id="<?= (int)$u['id'] ?>" data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M12 20h9"/>
                          <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                      </a>
                      <form method="post" action="admin-users.php" class="inline" data-confirm="¿Seguro que deseas eliminar este usuario?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="delete-btn" title="Eliminar" aria-label="Eliminar">
                          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h18"/>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            <path d="M10 11v6"/>
                            <path d="M14 11v6"/>
                            <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
                          </svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$users): ?>
                <tr><td colspan="7">No hay usuarios</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    </section><!-- Fin sección usuarios -->

    <!-- ========== GESTIÓN DE DEPARTAMENTOS ========== -->
    <section class="admin-section">
      <div class="section-header">
        <h2>Gestión de Departamentos</h2>
        <button id="btn-new-department" class="btn-primary">Nuevo departamento</button>
      </div>

      <!-- Formulario crear/editar departamento -->
      <section id="department-form-card" class="admin-form-card hidden">
        <div class="admin-form-header">
          <h2 id="dept-form-title">Nuevo departamento</h2>
          <p class="admin-form-subtitle" id="dept-form-subtitle">Crea un nuevo departamento para organizar usuarios</p>
        </div>
        
        <form id="form-department">
          <input type="hidden" id="dept-id" value="">
          
          <div class="admin-form-grid">
            <div class="admin-form-group">
              <label for="dept-name">Nombre del departamento</label>
              <input type="text" id="dept-name" required maxlength="100" placeholder="Ej: Marketing, Ventas, IT...">
              <small class="admin-form-hint">Nombre único para identificar el departamento.</small>
            </div>

            <div class="admin-form-group">
              <label for="dept-description">Descripción</label>
              <input type="text" id="dept-description" placeholder="Breve descripción del departamento" maxlength="255">
              <small class="admin-form-hint">Opcional. Ayuda a identificar el propósito.</small>
            </div>
          </div>

          <!-- Usuarios del departamento (solo visible en edición) -->
          <div class="admin-form-section" id="dept-users-section" style="display: none;">
            <div class="admin-form-section-header">
              <label>Usuarios del departamento</label>
              <div class="admin-form-section-actions">
                <button type="button" id="dept-user-all" class="btn-text">Todos</button>
                <button type="button" id="dept-user-none" class="btn-text">Ninguno</button>
              </div>
            </div>
            <div class="admin-checkbox-grid" id="dept-users-list">
              <!-- Se llena dinámicamente -->
            </div>
          </div>

          <div class="admin-form-actions">
            <button type="button" class="btn-secondary" id="btn-cancel-dept">Cancelar</button>
            <button type="submit" class="btn-primary" id="btn-save-dept">Crear departamento</button>
          </div>
        </form>
      </section>

      <!-- Lista de departamentos -->
      <section class="table-container">
        <div class="table-card">
          <div class="table-card__inner">
            <table class="tabla-departamentos">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Usuarios</th>
                <th>Creado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="departments-tbody">
              <tr><td colspan="5">Cargando...</td></tr>
            </tbody>
            </table>
          </div>
        </div>
      </section>
    </section><!-- Fin sección departamentos -->

    <!-- Modal: Asignar usuarios a departamento -->
    <div id="modal-assign-users" class="modal hidden">
      <div class="modal-content">
        <div class="modal-header">
          <h3 id="modal-dept-title">Asignar usuarios</h3>
          <button type="button" class="modal-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="assignees-panel">
            <div class="assignees-header">
              <label>Selecciona usuarios</label>
              <div class="assignees-actions">
                <button type="button" id="assign-all-users" class="btn-secondary">Seleccionar todos</button>
                <button type="button" id="assign-none-users" class="btn-secondary">Quitar todos</button>
              </div>
            </div>
            <div class="assignees-list" id="modal-users-list">
              <p>Cargando usuarios...</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-primary" id="btn-save-assignments">Guardar asignaciones</button>
          <button type="button" class="btn-secondary" id="btn-cancel-modal">Cancelar</button>
        </div>
      </div>
    </div>
  </main>
  <script src="scripts.js"></script>
</body>
</html>
