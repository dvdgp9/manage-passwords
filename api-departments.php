<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

bootstrap_security(true); // requiere sesión

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso restringido: solo administradores']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a base de datos']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar CSRF para mutaciones
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    verify_csrf_from_request();
}

try {
    switch ($action) {
        case 'list':
            // GET: Listar todos los departamentos con conteo de usuarios
            $stmt = $pdo->query("
                SELECT 
                    d.id, 
                    d.name, 
                    d.description, 
                    d.created_at,
                    COUNT(DISTINCT ud.user_id) as user_count
                FROM departments d
                LEFT JOIN user_departments ud ON d.id = ud.department_id
                GROUP BY d.id
                ORDER BY d.name ASC
            ");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'departments' => $departments]);
            break;

        case 'get':
            // GET: Obtener un departamento específico con sus usuarios
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ID inválido');
            }

            $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dept) {
                throw new RuntimeException('Departamento no encontrado');
            }

            // Obtener usuarios del departamento
            $stmt = $pdo->prepare("
                SELECT u.id, u.email, u.role
                FROM users u
                INNER JOIN user_departments ud ON u.id = ud.user_id
                WHERE ud.department_id = :dept_id
                ORDER BY u.email ASC
            ");
            $stmt->execute([':dept_id' => $id]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'department' => $dept, 'users' => $users]);
            break;

        case 'user_departments':
            // GET: Obtener departamentos de un usuario específico
            $userId = (int)($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('user_id inválido');
            }

            $stmt = $pdo->prepare("
                SELECT d.id, d.name
                FROM departments d
                INNER JOIN user_departments ud ON d.id = ud.department_id
                WHERE ud.user_id = :user_id
                ORDER BY d.name ASC
            ");
            $stmt->execute([':user_id' => $userId]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'departments' => $departments]);
            break;

        case 'create':
            // POST: Crear departamento
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('El nombre es obligatorio');
            }

            if (strlen($name) > 100) {
                throw new RuntimeException('El nombre no puede exceder 100 caracteres');
            }

            $pdo->beginTransaction();

            // Verificar que no existe
            $stmt = $pdo->prepare('SELECT 1 FROM departments WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $name]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Ya existe un departamento con ese nombre');
            }

            $stmt = $pdo->prepare('INSERT INTO departments (name, description, created_at) VALUES (:name, :desc, NOW())');
            $stmt->execute([':name' => $name, ':desc' => $description]);
            $newId = (int)$pdo->lastInsertId();

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Departamento creado', 'id' => $newId]);
            break;

        case 'update':
            // POST: Actualizar departamento
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($id <= 0) {
                throw new RuntimeException('ID inválido');
            }

            if ($name === '') {
                throw new RuntimeException('El nombre es obligatorio');
            }

            if (strlen($name) > 100) {
                throw new RuntimeException('El nombre no puede exceder 100 caracteres');
            }

            $pdo->beginTransaction();

            // Verificar existencia
            $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Departamento no encontrado');
            }

            // Verificar nombre único (excluyendo el actual)
            $stmt = $pdo->prepare('SELECT 1 FROM departments WHERE name = :name AND id != :id LIMIT 1');
            $stmt->execute([':name' => $name, ':id' => $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Ya existe otro departamento con ese nombre');
            }

            $stmt = $pdo->prepare('UPDATE departments SET name = :name, description = :desc, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':name' => $name, ':desc' => $description, ':id' => $id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Departamento actualizado']);
            break;

        case 'delete':
            // POST: Eliminar departamento
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('ID inválido');
            }

            $pdo->beginTransaction();

            // Verificar existencia
            $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Departamento no encontrado');
            }

            // Eliminar (las FK ON DELETE CASCADE eliminarán user_departments y password_department_access)
            $stmt = $pdo->prepare('DELETE FROM departments WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Departamento eliminado']);
            break;

        case 'assign_users':
            // POST: Asignar usuarios a un departamento
            $deptId = (int)($_POST['department_id'] ?? 0);
            $userIds = $_POST['user_ids'] ?? []; // array de IDs

            if ($deptId <= 0) {
                throw new RuntimeException('ID de departamento inválido');
            }

            if (!is_array($userIds)) {
                $userIds = [];
            }

            // Sanitizar IDs
            $userIds = array_map('intval', $userIds);
            $userIds = array_filter($userIds, function($id) { return $id > 0; });

            $pdo->beginTransaction();

            // Verificar que el departamento existe
            $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = :id');
            $stmt->execute([':id' => $deptId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Departamento no encontrado');
            }

            // Eliminar todas las asignaciones previas del departamento
            $stmt = $pdo->prepare('DELETE FROM user_departments WHERE department_id = :dept_id');
            $stmt->execute([':dept_id' => $deptId]);

            // Insertar nuevas asignaciones
            if (count($userIds) > 0) {
                $stmt = $pdo->prepare('INSERT INTO user_departments (user_id, department_id, created_at) VALUES (:uid, :did, NOW())');
                foreach ($userIds as $uid) {
                    // Verificar que el usuario existe
                    $check = $pdo->prepare('SELECT id FROM users WHERE id = :id');
                    $check->execute([':id' => $uid]);
                    if ($check->fetch()) {
                        $stmt->execute([':uid' => $uid, ':did' => $deptId]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Usuarios asignados correctamente']);
            break;

        case 'get_user_departments':
            // GET: Obtener departamentos de un usuario específico
            $userId = (int)($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('ID de usuario inválido');
            }

            $stmt = $pdo->prepare("
                SELECT d.id, d.name
                FROM departments d
                INNER JOIN user_departments ud ON d.id = ud.department_id
                WHERE ud.user_id = :uid
                ORDER BY d.name ASC
            ");
            $stmt->execute([':uid' => $userId]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'departments' => $departments]);
            break;

        default:
            throw new RuntimeException('Acción no válida');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
