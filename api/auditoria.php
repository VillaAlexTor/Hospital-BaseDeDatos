<?php
/**
 * api/auditoria.php
 * API para módulo de auditoría
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once '../includes/config.php';
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}
// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
try {
    switch ($action) {
        // OBTENER ESTADÍSTICAS
        case 'stats':
            $periodo = $_GET['periodo'] ?? 'hoy';
            $where = "1=1";
            switch ($periodo) {
                case 'hoy':
                    $where = "DATE(fecha_hora) = CURDATE()";
                    break;
                case 'semana':
                    $where = "fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'mes':
                    $where = "fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT id_usuario) as usuarios_unicos,
                    COUNT(DISTINCT ip_address) as ips_unicas,
                    SUM(CASE WHEN resultado = 'Éxito' THEN 1 ELSE 0 END) as exitosas,
                    SUM(CASE WHEN resultado = 'Fallo' THEN 1 ELSE 0 END) as fallidas,
                    SUM(CASE WHEN resultado = 'Bloqueado' THEN 1 ELSE 0 END) as bloqueadas,
                    SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as criticas,
                    SUM(CASE WHEN accion = 'LOGIN' THEN 1 ELSE 0 END) as logins,
                    SUM(CASE WHEN accion = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as login_fallidos,
                    SUM(CASE WHEN accion = 'INSERT' THEN 1 ELSE 0 END) as inserts,
                    SUM(CASE WHEN accion = 'UPDATE' THEN 1 ELSE 0 END) as updates,
                    SUM(CASE WHEN accion = 'DELETE' THEN 1 ELSE 0 END) as deletes
                FROM log_auditoria
                WHERE $where
            ");
            $stats = $stmt->fetch();
            // Top 5 usuarios más activos
            $stmt = $pdo->query("
                SELECT 
                    u.username,
                    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                    COUNT(*) as total_acciones
                FROM log_auditoria l
                INNER JOIN usuario u ON l.id_usuario = u.id_usuario
                INNER JOIN persona p ON u.id_persona = p.id_persona
                WHERE $where
                GROUP BY l.id_usuario
                ORDER BY total_acciones DESC
                LIMIT 5
            ");
            $top_usuarios = $stmt->fetchAll();
            // Top 5 tablas más modificadas
            $stmt = $pdo->query("
                SELECT 
                    tabla_afectada,
                    COUNT(*) as modificaciones
                FROM log_auditoria
                WHERE $where AND tabla_afectada IS NOT NULL
                GROUP BY tabla_afectada
                ORDER BY modificaciones DESC
                LIMIT 5
            ");
            $top_tablas = $stmt->fetchAll();
            // Acciones por hora (últimas 24 horas)
            $stmt = $pdo->query("
                SELECT 
                    HOUR(fecha_hora) as hora,
                    COUNT(*) as cantidad
                FROM log_auditoria
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(fecha_hora)
                ORDER BY hora
            ");
            $acciones_por_hora = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'top_usuarios' => $top_usuarios,
                    'top_tablas' => $top_tablas,
                    'acciones_por_hora' => $acciones_por_hora
                ]
            ]);
            break;
        // OBTENER LOGS RECIENTES
        case 'recent':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $stmt = $pdo->prepare("
                SELECT 
                    l.id_log,
                    l.fecha_hora,
                    l.accion,
                    l.tabla_afectada,
                    l.descripcion,
                    l.resultado,
                    l.criticidad,
                    u.username
                FROM log_auditoria l
                LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
                ORDER BY l.fecha_hora DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $logs = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
            break;
        // BUSCAR LOGS
        case 'search':
            $search = $_GET['search'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    u.username,
                    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo
                FROM log_auditoria l
                LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
                LEFT JOIN persona p ON u.id_persona = p.id_persona
                WHERE l.descripcion LIKE ? 
                    OR l.tabla_afectada LIKE ?
                    OR u.username LIKE ?
                ORDER BY l.fecha_hora DESC
                LIMIT ?
            ");
            $search_param = "%$search%";
            $stmt->execute([$search_param, $search_param, $search_param, $limit]);
            $logs = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'count' => count($logs),
                'data' => $logs
            ]);
            break;
        // OBTENER DETALLE DE LOG
        case 'detail':
            $id_log = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id_log) {
                throw new Exception('ID de log inválido');
            }
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    u.username,
                    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                    p.email,
                    s.navegador as sesion_navegador,
                    s.sistema_operativo,
                    s.ubicacion_geografica
                FROM log_auditoria l
                LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
                LEFT JOIN persona p ON u.id_persona = p.id_persona
                LEFT JOIN sesion s ON l.id_sesion = s.id_sesion
                WHERE l.id_log = ?
            ");
            $stmt->execute([$id_log]);
            $log = $stmt->fetch();
            if (!$log) {
                throw new Exception('Log no encontrado');
            }
            echo json_encode([
                'success' => true,
                'data' => $log
            ]);
            break;
        // OBTENER ACTIVIDAD POR USUARIO
        case 'user_activity':
            $id_usuario = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            if (!$id_usuario) {
                throw new Exception('ID de usuario inválido');
            }
            $stmt = $pdo->prepare("
                SELECT *
                FROM log_auditoria
                WHERE id_usuario = ?
                ORDER BY fecha_hora DESC
                LIMIT ?
            ");
            $stmt->execute([$id_usuario, $limit]);
            $logs = $stmt->fetchAll();
            // Estadísticas del usuario
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_acciones,
                    MAX(fecha_hora) as ultima_actividad,
                    COUNT(DISTINCT DATE(fecha_hora)) as dias_activos,
                    SUM(CASE WHEN resultado = 'Fallo' THEN 1 ELSE 0 END) as acciones_fallidas
                FROM log_auditoria
                WHERE id_usuario = ?
            ");
            $stmt->execute([$id_usuario]);
            $user_stats = $stmt->fetch();
            echo json_encode([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'stats' => $user_stats
                ]
            ]);
            break;
        // LIMPIAR LOGS ANTIGUOS (Solo Admin)
        case 'cleanup':
            if (!has_role('Administrador')) {
                throw new Exception('Solo administradores pueden limpiar logs');
            }
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }
            $dias = isset($_POST['days']) ? (int)$_POST['days'] : 365;
            if ($dias < 30) {
                throw new Exception('No se puede eliminar logs de menos de 30 días');
            }
            $stmt = $pdo->prepare("
                DELETE FROM log_auditoria 
                WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND criticidad NOT IN ('Crítica', 'Alta')
            ");
            $stmt->execute([$dias]);
            $deleted = $stmt->rowCount();
            // Registrar limpieza
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria 
                (id_usuario, accion, descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'DELETE', ?, ?, 'Éxito', 'Media')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Limpieza de logs antiguos: $deleted registros eliminados",
                $_SERVER['REMOTE_ADDR']
            ]);
            echo json_encode([
                'success' => true,
                'message' => "$deleted registros eliminados",
                'deleted' => $deleted
            ]);
            break;
        // EXPORTAR (generar URL de descarga)
        case 'export_url':
            $format = $_GET['format'] ?? 'excel';
            $filters = $_GET['filters'] ?? [];
            
            $query_string = http_build_query(array_merge($filters, ['export' => $format]));
            $url = '../modules/auditoria/exportar.php?' . $query_string;
            
            echo json_encode([
                'success' => true,
                'url' => $url
            ]);
            break;
        // ACCIÓN NO VÁLIDA
        default:
            throw new Exception('Acción no válida');
    } 
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}