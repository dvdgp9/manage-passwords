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
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $apellidos = trim((string)($_POST['apellidos'] ?? ''));
        $userDepts = $_POST['user_departments'] ?? [];
        if (!is_array($userDepts)) { $userDepts = []; }
        $userDepts = array_map('intval', $userDepts);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        if (!valid_role($role)) {
            $errors[] = 'Rol inválido';
        }
        if ($password === '' || strlen($password) < 8) {
            $errors[] = 'La contraseña es obligatoria y debe tener al menos 8 caracteres';
        }
        if (mb_strlen($nombre) > 100 || mb_strlen($apellidos) > 100) {
            $errors[] = 'Nombre y apellidos no pueden superar los 100 caracteres';
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
                $st = $pdo->prepare('INSERT INTO users (email, password_hash, role, nombre, apellidos, created_at) VALUES (:email, :ph, :role, :nombre, :apellidos, NOW())');
                $st->execute([
                    ':email' => $email,
                    ':ph' => $hash,
                    ':role' => $role,
                    ':nombre' => $nombre !== '' ? $nombre : null,
                    ':apellidos' => $apellidos !== '' ? $apellidos : null,
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                // Insertar departamentos iniciales si se han seleccionado
                if ($userDepts) {
                    $ins = $pdo->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (:uid, :did)');
                    foreach ($userDepts as $did) {
                        if ($did > 0) {
                            $ins->execute([':uid' => $newUserId, ':did' => $did]);
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
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $apellidos = trim((string)($_POST['apellidos'] ?? ''));
        $userDepts = $_POST['user_departments'] ?? [];
        if (!is_array($userDepts)) { $userDepts = []; }
        $userDepts = array_map('intval', $userDepts);

        if ($id <= 0) { $errors[] = 'ID inválido'; }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email inválido'; }
        if ($role !== '' && !valid_role($role)) { $errors[] = 'Rol inválido'; }
        if ($password !== '' && strlen($password) < 8) { $errors[] = 'Si se define contraseña, mínimo 8 caracteres'; }
        if (mb_strlen($nombre) > 100 || mb_strlen($apellidos) > 100) {
            $errors[] = 'Nombre y apellidos no pueden superar los 100 caracteres';
        }

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

                // actualizar usuario
                $params = [
                    ':email' => $email,
                    ':role' => ($role !== '' ? $role : $u['role']),
                    ':nombre' => $nombre !== '' ? $nombre : null,
                    ':apellidos' => $apellidos !== '' ? $apellidos : null,
                    ':id' => $id,
                ];
                $sql = 'UPDATE users SET email = :email, role = :role, nombre = :nombre, apellidos = :apellidos';
                if ($password !== '') {
                    $sql .= ', password_hash = :ph';
                    $params[':ph'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = :id';
                $upd = $pdo->prepare($sql);
                $upd->execute($params);

                // actualizar departamentos del usuario
                $pdo->prepare('DELETE FROM user_departments WHERE user_id = :uid')->execute([':uid' => $id]);
                if ($userDepts) {
                    $ins = $pdo->prepare('INSERT INTO user_departments (user_id, department_id) VALUES (:uid, :did)');
                    foreach ($userDepts as $did) {
                        if ($did > 0) $ins->execute([':uid' => $id, ':did' => $did]);
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

// Cargar todos los departamentos (para el formulario de usuario)
$allDepartments = [];
try {
    $st = $pdo->query('SELECT id, name FROM departments ORDER BY name');
    $allDepartments = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// Soporte de edición por GET ?edit=ID (datos básicos, el modal usa data-* de la tabla)
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editUser = null;
$editUserDepts = [];
if ($editId > 0) {
    $st = $pdo->prepare('SELECT id, email, role, nombre, apellidos, created_at, last_login FROM users WHERE id = :id');
    $st->execute([':id' => $editId]);
    $editUser = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editUser) {
        $st = $pdo->prepare('SELECT department_id FROM user_departments WHERE user_id = :uid');
        $st->execute([':uid' => $editId]);
        $editUserDepts = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'department_id');
    }
}

// Listado básico con departamentos (sin paginación por ahora)
$users = [];
try {
    $st = $pdo->query('SELECT id, email, role, nombre, apellidos, created_at, last_login FROM users ORDER BY created_at DESC');
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
    <section class="admin-panel">
      <div class="admin-panel__header">
        <h2>Gestión de Usuarios</h2>
        <button id="btn-new-user" class="btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Nuevo usuario
        </button>
      </div>

      <div class="admin-panel__table-wrapper">
          <table class="tabla-usuarios">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
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
                  <td>
                    <?php
                      $fullName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
                      echo $fullName !== ''
                        ? htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8')
                        : '<span class="text-muted">—</span>';
                    ?>
                  </td>
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
                      <a class="modify-btn" href="admin-users.php?edit=<?= (int)$u['id'] ?>" title="Editar" aria-label="Editar" data-id="<?= (int)$u['id'] ?>" data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($u['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-apellidos="<?= htmlspecialchars($u['apellidos'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
    </section><!-- Fin sección usuarios -->

    <!-- ========== GESTIÓN DE DEPARTAMENTOS ========== -->
    <section class="admin-panel">
      <div class="admin-panel__header">
        <h2>Gestión de Departamentos</h2>
        <button id="btn-new-department" class="btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Nuevo departamento
        </button>
      </div>

      <div class="admin-panel__table-wrapper">
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
    </section><!-- Fin sección departamentos -->

    <!-- Modal: Crear/Editar Usuario -->
    <div id="modal-user" class="modal hidden">
      <div class="modal-content modal-content--md">
        <div class="modal-header">
          <h3 id="modal-user-title">Nuevo usuario</h3>
          <button type="button" class="modal-close" data-close-modal="modal-user">&times;</button>
        </div>
        <form id="form-admin-user" method="post" action="admin-users.php">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="form-action" name="action" value="create">
            <input type="hidden" id="form-id" name="id" value="">

            <div class="modal-form-grid">
              <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" placeholder="Nombre">
              </div>

              <div class="form-group">
                <label for="apellidos">Apellidos</label>
                <input type="text" id="apellidos" name="apellidos" maxlength="100" placeholder="Apellidos">
              </div>

              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="usuario@ejemplo.com">
              </div>

              <div class="form-group">
                <label for="role">Rol</label>
                <select id="role" name="role" required>
                  <option value="editor">Editor</option>
                  <option value="lector">Lector</option>
                  <option value="admin">Administrador</option>
                </select>
              </div>

              <div class="form-group">
                <label for="password" id="password-label">Contraseña</label>
                <div class="password-input-wrapper">
                  <input type="password" id="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                  <button type="button" class="password-toggle" data-target="password">
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
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="password-input-wrapper">
                  <input type="password" id="confirm_password" name="confirm_password" minlength="8" placeholder="Repite la contraseña">
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

              <div class="form-group form-group--full" id="user-depts-container" style="display:none;">
                <label>Departamentos</label>
                <div class="list-filter">
                  <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                  <input type="text" class="list-filter__input" placeholder="Buscar departamento...">
                </div>
                <div class="checkbox-grid" id="user-depts-list">
                  <?php foreach ($allDepartments as $dept): ?>
                    <label class="checkbox-item">
                      <input type="checkbox" name="user_departments[]" value="<?= (int)$dept['id'] ?>">
                      <span class="checkbox-label"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" data-close-modal="modal-user">Cancelar</button>
            <button type="submit" class="btn-primary" id="btn-submit-user">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Crear usuario
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Crear/Editar Departamento -->
    <div id="modal-department" class="modal hidden">
      <div class="modal-content modal-content--sm">
        <div class="modal-header">
          <h3 id="modal-dept-form-title">Nuevo departamento</h3>
          <button type="button" class="modal-close" data-close-modal="modal-department">&times;</button>
        </div>
        <form id="form-department">
          <div class="modal-body">
            <input type="hidden" id="dept-id" value="">
            
            <div class="modal-form-stack">
              <div class="form-group">
                <label for="dept-name">Nombre del departamento</label>
                <input type="text" id="dept-name" required maxlength="100" placeholder="Ej: Marketing, Ventas, IT...">
              </div>

              <div class="form-group">
                <label for="dept-description">Descripción <span class="optional-label">(opcional)</span></label>
                <textarea id="dept-description" rows="3" placeholder="Describe el propósito de este departamento"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" data-close-modal="modal-department">Cancelar</button>
            <button type="submit" class="btn-primary" id="btn-save-dept">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Guardar
            </button>
          </div>
        </form>
      </div>
    </div>

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
            <div class="list-filter">
              <svg class="list-filter__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
              <input type="text" class="list-filter__input" placeholder="Buscar usuario...">
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
