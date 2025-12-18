<?php
/**
 * Bulk Import API - Subida en lote de instalaciones, actividades y participantes
 * 
 * Recibe:
 * - centro_id: ID del centro seleccionado manualmente
 * - rows: array de filas con { nombre, apellidos, instalacion, actividad, fecha_inicio, dias_semana }
 * 
 * Lógica:
 * - Instalación: si existe en el centro → reutilizar, si no → crear
 * - Actividad: si existe en esa instalación CON los mismos días → error
 *              si no existe o días diferentes → crear nueva
 * - Participante: nombre Y apellidos obligatorios, si falta alguno → error en esa fila
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once '../../config/config.php';
    require_once '../auth_middleware.php';
    
    $admin_info = getAdminInfo();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    $centro_id = intval($input['centro_id'] ?? 0);
    $rows = $input['rows'] ?? [];

    if ($centro_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un centro']);
        exit;
    }

    if (!is_array($rows) || count($rows) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay filas para importar']);
        exit;
    }

    // Verificar que el centro existe
    $stmt = $pdo->prepare("SELECT id, nombre FROM centros WHERE id = ? AND activo = 1");
    $stmt->execute([$centro_id]);
    $centro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$centro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Centro no encontrado']);
        exit;
    }

    // Autorización: si no es superadmin, validar asignación del centro
    if ($admin_info['role'] !== 'superadmin') {
        $stmt = $pdo->prepare("SELECT 1 FROM admin_asignaciones WHERE admin_id = ? AND centro_id = ?");
        $stmt->execute([$admin_info['id'], $centro_id]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No autorizado para este centro']);
            exit;
        }
    }

    // Cache de instalaciones existentes en el centro
    $stmt = $pdo->prepare("SELECT id, LOWER(TRIM(nombre)) as nombre_lower, nombre FROM instalaciones WHERE centro_id = ?");
    $stmt->execute([$centro_id]);
    $instalacionesExistentes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $instalacionesExistentes[$row['nombre_lower']] = $row;
    }

    // Cache de actividades por instalación: key = instalacion_id, value = array de { id, nombre_lower, dias_semana }
    $actividadesCache = [];

    // Resultados
    $stats = [
        'instalaciones_creadas' => 0,
        'instalaciones_reutilizadas' => 0,
        'actividades_creadas' => 0,
        'participantes_creados' => 0,
        'errores' => []
    ];

    $pdo->beginTransaction();

    foreach ($rows as $idx => $row) {
        $lineNum = $idx + 2; // +2 porque la fila 1 es el header
        
        $nombre = isset($row['nombre']) ? trim((string)$row['nombre']) : '';
        $apellidos = isset($row['apellidos']) ? trim((string)$row['apellidos']) : '';
        $instalacionNombre = isset($row['instalacion']) ? trim((string)$row['instalacion']) : '';
        $actividadNombre = isset($row['actividad']) ? trim((string)$row['actividad']) : '';
        $fechaInicio = isset($row['fecha_inicio']) ? trim((string)$row['fecha_inicio']) : '';
        $diasSemana = isset($row['dias_semana']) ? $row['dias_semana'] : [];
        
        // Normalizar días si viene como string separado por comas
        if (is_string($diasSemana)) {
            $diasSemana = array_map('trim', explode(',', $diasSemana));
            $diasSemana = array_filter($diasSemana);
        }
        
        // Validar participante: solo nombre obligatorio (apellidos opcional)
        if (empty($nombre)) {
            $stats['errores'][] = "Línea $lineNum: Falta el nombre del participante";
            continue;
        }
        
        // Validar instalación
        if (empty($instalacionNombre)) {
            $stats['errores'][] = "Línea $lineNum: Falta nombre de instalación";
            continue;
        }
        
        // Validar actividad
        if (empty($actividadNombre)) {
            $stats['errores'][] = "Línea $lineNum: Falta nombre de actividad";
            continue;
        }
        
        // Validar fecha inicio
        if (empty($fechaInicio)) {
            $stats['errores'][] = "Línea $lineNum: Falta fecha de inicio";
            continue;
        }
        
        // Normalizar fecha (puede venir como d/m/y o d/m/yy o yyyy-mm-dd)
        $fechaInicioNorm = normalizarFecha($fechaInicio);
        if (!$fechaInicioNorm) {
            $stats['errores'][] = "Línea $lineNum: Formato de fecha inválido ($fechaInicio)";
            continue;
        }
        
        // Validar días
        if (empty($diasSemana)) {
            $stats['errores'][] = "Línea $lineNum: Faltan días de la semana";
            continue;
        }
        
        // Normalizar días de la semana
        $diasSemana = normalizarDias($diasSemana);
        $diasSemanaStr = implode(',', $diasSemana);
        
        // --- Buscar o crear instalación ---
        $instalacionKey = mb_strtolower(trim($instalacionNombre), 'UTF-8');
        $instalacion_id = null;
        
        if (isset($instalacionesExistentes[$instalacionKey])) {
            $instalacion_id = $instalacionesExistentes[$instalacionKey]['id'];
            // Solo contamos como reutilizada la primera vez que la usamos en esta importación
            if (!isset($instalacionesExistentes[$instalacionKey]['_used'])) {
                $stats['instalaciones_reutilizadas']++;
                $instalacionesExistentes[$instalacionKey]['_used'] = true;
            }
        } else {
            // Crear instalación
            $stmtIns = $pdo->prepare("INSERT INTO instalaciones (nombre, centro_id) VALUES (?, ?)");
            $stmtIns->execute([$instalacionNombre, $centro_id]);
            $instalacion_id = $pdo->lastInsertId();
            
            $instalacionesExistentes[$instalacionKey] = [
                'id' => $instalacion_id,
                'nombre_lower' => $instalacionKey,
                'nombre' => $instalacionNombre,
                '_used' => true
            ];
            $stats['instalaciones_creadas']++;
        }
        
        // --- Buscar o crear actividad ---
        // Cargar cache de actividades para esta instalación si no existe
        if (!isset($actividadesCache[$instalacion_id])) {
            $stmtAct = $pdo->prepare("SELECT id, LOWER(TRIM(nombre)) as nombre_lower, dias_semana FROM actividades WHERE instalacion_id = ?");
            $stmtAct->execute([$instalacion_id]);
            $actividadesCache[$instalacion_id] = [];
            while ($act = $stmtAct->fetch(PDO::FETCH_ASSOC)) {
                $actividadesCache[$instalacion_id][] = $act;
            }
        }
        
        $actividadKey = mb_strtolower(trim($actividadNombre), 'UTF-8');
        $actividad_id = null;
        
        // Buscar si existe actividad con mismo nombre + mismos días → REUTILIZAR
        foreach ($actividadesCache[$instalacion_id] as $actExist) {
            if ($actExist['nombre_lower'] === $actividadKey) {
                $diasExistentes = array_map('trim', explode(',', $actExist['dias_semana'] ?? ''));
                sort($diasExistentes);
                $diasNuevos = $diasSemana;
                sort($diasNuevos);
                
                if ($diasExistentes == $diasNuevos) {
                    // Mismo nombre + mismos días = REUTILIZAR esta actividad
                    $actividad_id = $actExist['id'];
                    break;
                }
            }
        }
        
        // Si no encontramos actividad existente, crear una nueva
        if (!$actividad_id) {
            // Crear actividad
            $horario = implode(' y ', $diasSemana); // Campo legacy simplificado
            $stmtActIns = $pdo->prepare("
                INSERT INTO actividades (nombre, horario, dias_semana, hora_inicio, hora_fin, instalacion_id, fecha_inicio, fecha_fin) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
            ");
            $stmtActIns->execute([
                $actividadNombre,
                $horario,
                $diasSemanaStr,
                '09:00', // Hora por defecto
                '10:00', // Hora por defecto
                $instalacion_id,
                $fechaInicioNorm
            ]);
            $actividad_id = $pdo->lastInsertId();
            
            // Agregar al cache
            $actividadesCache[$instalacion_id][] = [
                'id' => $actividad_id,
                'nombre_lower' => $actividadKey,
                'dias_semana' => $diasSemanaStr
            ];
            $stats['actividades_creadas']++;
        }
        
        // --- Crear participante ---
        $nombre = preg_replace('/\s+/', ' ', $nombre);
        $apellidos = preg_replace('/\s+/', ' ', $apellidos);
        
        $stmtPart = $pdo->prepare("INSERT INTO inscritos (actividad_id, nombre, apellidos) VALUES (?, ?, ?)");
        $stmtPart->execute([$actividad_id, $nombre, $apellidos]);
        $stats['participantes_creados']++;
    }

    $pdo->commit();

    // Construir mensaje de resultado
    $mensaje = "Importación completada: ";
    $partes = [];
    if ($stats['instalaciones_creadas'] > 0) {
        $partes[] = $stats['instalaciones_creadas'] . " instalación(es) creada(s)";
    }
    if ($stats['instalaciones_reutilizadas'] > 0) {
        $partes[] = $stats['instalaciones_reutilizadas'] . " instalación(es) reutilizada(s)";
    }
    if ($stats['actividades_creadas'] > 0) {
        $partes[] = $stats['actividades_creadas'] . " actividad(es) creada(s)";
    }
    if ($stats['participantes_creados'] > 0) {
        $partes[] = $stats['participantes_creados'] . " participante(s) inscrito(s)";
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

/**
 * Normaliza una fecha a formato YYYY-MM-DD
 * Acepta: d/m/y, d/m/yy, d/m/yyyy, yyyy-mm-dd
 */
function normalizarFecha($fecha) {
    $fecha = trim($fecha);
    
    // Formato ISO yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return $fecha;
    }
    
    // Formato d/m/y o d/m/yy o d/m/yyyy
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $fecha, $m)) {
        $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $anio = $m[3];
        
        // Normalizar año
        if (strlen($anio) == 2) {
            $anio = ($anio > 50) ? '19' . $anio : '20' . $anio;
        }
        
        return "$anio-$mes-$dia";
    }
    
    return null;
}

/**
 * Normaliza los días de la semana a formato estándar
 */
function normalizarDias($dias) {
    $mapa = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'miércoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'sábado' => 'Sábado',
        'domingo' => 'Domingo',
        // Abreviaciones comunes
        'lu' => 'Lunes',
        'ma' => 'Martes',
        'mi' => 'Miércoles',
        'ju' => 'Jueves',
        'vi' => 'Viernes',
        'sa' => 'Sábado',
        'do' => 'Domingo',
        'l' => 'Lunes',
        'm' => 'Martes',
        'x' => 'Miércoles',
        'j' => 'Jueves',
        'v' => 'Viernes',
        's' => 'Sábado',
        'd' => 'Domingo',
    ];
    
    $resultado = [];
    foreach ($dias as $dia) {
        $diaLower = mb_strtolower(trim($dia), 'UTF-8');
        if (isset($mapa[$diaLower])) {
            $resultado[] = $mapa[$diaLower];
        } else {
            // Si no está en el mapa, usar el valor original capitalizado
            $resultado[] = ucfirst($diaLower);
        }
    }
    
    return array_unique($resultado);
}
?>
