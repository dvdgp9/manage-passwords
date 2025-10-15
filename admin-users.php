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
                $pdo->commit();
                $notices[] = 'Usuario creado correctamente';
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

                $pdo->commit();
                $notices[] = 'Usuario actualizado';
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
                $notices[] = 'Usuario eliminado';
            } catch (Throwable $e) {
                $errors[] = 'No se pudo eliminar el usuario';
            }
        }
    }
}

// Soporte de edición por GET ?edit=ID
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editUser = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT id, email, role, created_at, last_login FROM users WHERE id = :id');
    $st->execute([':id' => $editId]);
    $editUser = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Listado básico (sin paginación por ahora)
$users = [];
try {
    $st = $pdo->query('SELECT id, email, role, created_at, last_login FROM users ORDER BY created_at DESC');
    $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
  <title>Administración · Usuarios</title>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?= $headerHtml ?>
  <main class="page">
    <h1>Usuarios</h1>

    <?php if ($errors): ?>
      <div class="error" style="color:#b91c1c; margin: 10px 0;">
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($notices): ?>
      <div class="notice" style="color:#065f46; margin: 10px 0;">
        <?php foreach ($notices as $n): ?>
          <div><?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section class="panel">
      <h2><?= $editUser ? 'Editar usuario' : 'Crear usuario' ?></h2>
      <form method="post" action="admin-users.php<?= $editUser ? '?edit='.(int)$editUser['id'] : '' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?>
          <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
        <?php endif; ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($editUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="role">Rol</label>
        <select id="role" name="role" required>
          <?php $roles = ['admin' => 'Administrador', 'editor' => 'Editor', 'lector' => 'Lector'];
          $cur = $editUser['role'] ?? 'editor';
          foreach ($roles as $val => $label): ?>
            <option value="<?= $val ?>" <?= $cur === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>

        <label for="password"><?= $editUser ? 'Nueva contraseña (opcional)' : 'Contraseña (obligatoria)' ?></label>
        <input type="password" id="password" name="password" <?= $editUser ? '' : 'required' ?> placeholder="<?= $editUser ? 'Dejar vacío para no cambiar' : '' ?>">

        <button type="submit"><?= $editUser ? 'Guardar cambios' : 'Crear' ?></button>
        <?php if ($editUser): ?>
          <a class="btn-secondary" href="admin-users.php">Cancelar edición</a>
        <?php endif; ?>
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
                  <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['last_login'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="button-container">
                      <a class="modify-btn" href="admin-users.php?edit=<?= (int)$u['id'] ?>" title="Editar" aria-label="Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M12 20h9"/>
                          <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                      </a>
                      <form method="post" action="admin-users.php" style="display:inline" data-confirm="¿Seguro que deseas eliminar este usuario?">
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
                <tr><td colspan="6">No hay usuarios</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
  <script src="scripts.js"></script>
</body>
</html>
