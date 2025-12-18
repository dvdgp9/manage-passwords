<?php
/**
 * Bulk Import API - Subida en lote de contraseñas
 * 
 * Recibe:
 * - rows: array de filas con { linea_de_negocio, nombre, usuario, password, enlace, descripcion, info_adicional }
 * - assignees: array de user IDs para compartir (opcional)
 * - departments: array de department IDs para compartir (opcional)
 * 
 * Lógica:
 * - Valida campos obligatorios por fila
 * - Cifra cada contraseña
 * - Inserta en passwords_manager
 * - Crea accesos en passwords_access y password_department_access
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once 'security.php';
    require_once 'config.php';
    
    bootstrap_security(true);
    
    // Verificar CSRF
    $input = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $input['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    
    $user = current_user();
    $ownerId = (int)($user['id'] ?? 0);
    $role = $user['role'] ?? '';
    
    // Solo admin y editor pueden crear contraseñas
    if (!in_array($role, ['admin', 'editor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para importar contraseñas']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    $rows = $input['rows'] ?? [];
    $assignees = $input['assignees'] ?? [];
    $departments = $input['departments'] ?? [];

    // Sanitize assignees and departments
    $assignees = array_values(array_unique(array_filter(array_map('intval', (array)$assignees), function($v) { return $v > 0; })));
    $departments = array_values(array_unique(array_filter(array_map('intval', (array)$departments), function($v) { return $v > 0; })));

    if (!is_array($rows) || count($rows) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay filas para importar']);
        exit;
    }

    $pdo = getDBConnection();

    // Resultados
    $stats = [
        'passwords_created' => 0,
        'errores' => []
    ];

    $pdo->beginTransaction();

    // Preparar statements
    $stmtInsert = $pdo->prepare('
        INSERT INTO `passwords_manager` 
        (owner_user_id, linea_de_negocio, nombre, descripcion, usuario, password, enlace, info_adicional)
        VALUES (:owner_user_id, :linea_de_negocio, :nombre, :descripcion, :usuario, :password, :enlace, :info_adicional)
    ');
    
    $stmtOwnerAccess = $pdo->prepare('INSERT INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, :perm)');
    $stmtUserAccess = $pdo->prepare('INSERT IGNORE INTO passwords_access (password_id, user_id, perm) VALUES (:pid, :uid, :perm)');
    $stmtDeptAccess = $pdo->prepare('INSERT IGNORE INTO password_department_access (password_id, department_id, perm) VALUES (:pid, :did, :perm)');

    foreach ($rows as $idx => $row) {
        $lineNum = $idx + 2; // +2 porque la fila 1 es el header
        
        $linea_de_negocio = isset($row['linea_de_negocio']) ? trim((string)$row['linea_de_negocio']) : '';
        $nombre = isset($row['nombre']) ? trim((string)$row['nombre']) : '';
        $usuario = isset($row['usuario']) ? trim((string)$row['usuario']) : '';
        $password = isset($row['password']) ? trim((string)$row['password']) : '';
        $enlace = isset($row['enlace']) ? trim((string)$row['enlace']) : '';
        $descripcion = isset($row['descripcion']) ? trim((string)$row['descripcion']) : '';
        $info_adicional = isset($row['info_adicional']) ? trim((string)$row['info_adicional']) : '';
        
        // Validar campos obligatorios
        $missingFields = [];
        if (empty($linea_de_negocio)) $missingFields[] = 'Línea de Negocio';
        if (empty($nombre)) $missingFields[] = 'Nombre';
        if (empty($usuario)) $missingFields[] = 'Usuario';
        if (empty($password)) $missingFields[] = 'Contraseña';
        if (empty($enlace)) $missingFields[] = 'Enlace';
        
        if (!empty($missingFields)) {
            $stats['errores'][] = "Línea $lineNum: Faltan campos obligatorios: " . implode(', ', $missingFields);
            continue;
        }
        
        // Normalizar enlace
        if (!empty($enlace) && !str_starts_with($enlace, 'http://') && !str_starts_with($enlace, 'https://')) {
            $enlace = 'https://' . $enlace;
        }
        
        // Cifrar contraseña
        $encryptedPassword = encrypt($password);
        
        // Insertar contraseña
        $stmtInsert->execute([
            ':owner_user_id' => $ownerId,
            ':linea_de_negocio' => $linea_de_negocio,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion ?: null,
            ':usuario' => $usuario,
            ':password' => $encryptedPassword,
            ':enlace' => $enlace,
            ':info_adicional' => $info_adicional ?: null
        ]);
        
        $passwordId = (int)$pdo->lastInsertId();
        
        // Crear acceso de propietario
        $stmtOwnerAccess->execute([':pid' => $passwordId, ':uid' => $ownerId, ':perm' => 'owner']);
        
        // Crear accesos para usuarios seleccionados
        foreach ($assignees as $uid) {
            if ($uid !== $ownerId) {
                $stmtUserAccess->execute([':pid' => $passwordId, ':uid' => $uid, ':perm' => 'editor']);
            }
        }
        
        // Crear accesos para departamentos seleccionados
        foreach ($departments as $did) {
            $stmtDeptAccess->execute([':pid' => $passwordId, ':did' => $did, ':perm' => 'viewer']);
        }
        
        $stats['passwords_created']++;
    }

    $pdo->commit();

    // Construir mensaje de resultado
    $mensaje = "Importación completada: ";
    $partes = [];
    if ($stats['passwords_created'] > 0) {
        $partes[] = $stats['passwords_created'] . " contraseña(s) creada(s)";
    }
    $mensaje .= implode(', ', $partes) ?: "sin cambios";
    
    if (count($stats['errores']) > 0) {
        $mensaje .= ". Errores: " . count($stats['errores']);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error bulk_import: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
