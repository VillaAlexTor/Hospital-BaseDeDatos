<?php
/**
 * api/backups.php
 * API para gestión de backups
 * Solo accesible para Administradores
 */
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';
require_once '../includes/config.php';
// Función de compatibilidad para PHP
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }
}
// Verificar autenticación y rol
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}
$db = Database::getInstance();
$conn = $db->getConnection();
// Obtener método y acción
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
try {
    switch ($action) {
        case 'list':
            listBackups($conn);
            break;
        case 'create':
            createBackup($conn);
            break;
        case 'details':
            getBackupDetails($conn);
            break;
        case 'download':
            downloadBackup($conn);
            break;
        case 'restore':
            restoreBackup($conn);
            break;
        case 'delete':
            deleteBackup($conn);
            break;
        case 'statistics':
            getStatistics($conn);
            break;
        case 'verify':
            verifyBackup($conn);
            break;
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
/**
 * Listar backups con filtros
 */
function listBackups($conn) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    // Construir query
    $where = [];
    $params = [];
    if ($type) {
        $where[] = "tipo_backup = ?";
        $params[] = $type;
    }
    if ($status) {
        $where[] = "estado_backup = ?";
        $params[] = $status;
    }
    if ($dateFrom) {
        $where[] = "DATE(fecha_inicio) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "DATE(fecha_inicio) <= ?";
        $params[] = $dateTo;
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    // Contar total
    $sqlCount = "SELECT COUNT(*) as total FROM backup $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    // Obtener registros
    $sql = "SELECT b.*, 
            CONCAT(p.nombres, ' ', p.apellidos) as realizado_por_nombre
            FROM backup b
            LEFT JOIN usuario u ON b.realizado_por = u.id_usuario
            LEFT JOIN persona p ON u.id_persona = p.id_persona
            $whereClause
            ORDER BY b.fecha_inicio DESC
            LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'data' => $backups,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);
}
/**
 * Crear nuevo backup
 */
function createBackup($conn) {
    global $_SESSION;
    $input = json_decode(file_get_contents('php://input'), true);
    $tipo = $input['tipo'] ?? 'Completo';
    $cifrado = $input['cifrado'] ?? true;
    $comprimido = $input['comprimido'] ?? true;
    $observaciones = $input['observaciones'] ?? '';
    // Validar tipo
    $tiposValidos = ['Completo', 'Incremental', 'Diferencial', 'Transaccional'];
    if (!in_array($tipo, $tiposValidos)) {
        throw new Exception('Tipo de backup no válido');
    }
    // Registrar inicio del backup
    $sql = "INSERT INTO backup (tipo_backup, estado_backup, cifrado, comprimido, 
            observaciones, realizado_por, fecha_inicio) 
            VALUES (?, 'En proceso', ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $tipo,
        $cifrado ? 1 : 0,
        $comprimido ? 1 : 0,
        $observaciones,
        $_SESSION['user_id']
    ]);
    $backupId = $conn->lastInsertId();
    // Crear directorio de backups si no existe
    $backupDir = dirname(__DIR__) . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    // Generar nombre de archivo
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$tipo}_{$timestamp}";
    $extension = $comprimido ? '.sql.gz' : '.sql';
    $filepath = $backupDir . '/' . $filename . $extension;
    try {
        // Ejecutar backup según tipo
        $startTime = microtime(true);
        switch ($tipo) {
            case 'Completo':
                $success = executeFullBackup($filepath, $comprimido);
                break;
            case 'Incremental':
                $success = executeIncrementalBackup($conn, $filepath, $comprimido);
                break;
            case 'Diferencial':
                $success = executeDifferentialBackup($conn, $filepath, $comprimido);
                break;
            case 'Transaccional':
                $success = executeTransactionalBackup($filepath, $comprimido);
                break;
            default:
                throw new Exception('Tipo no implementado');
        }
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) / 60, 2); // Minutos
        if ($success && file_exists($filepath)) {
            $filesize = round(filesize($filepath) / (1024 * 1024), 2); // MB
            // Calcular hash para verificación
            $hash = hash_file('sha256', $filepath);
            // Actualizar registro
            $sqlUpdate = "UPDATE backup SET 
                        estado_backup = 'Completado',
                        fecha_fin = NOW(),
                        duracion_minutos = ?,
                        tamanio_mb = ?,
                        ubicacion_archivo = ?,
                        hash_verificacion = ?
                        WHERE id_backup = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                $duration,
                $filesize,
                $filepath,
                $hash,
                $backupId
            ]);
            // Registrar en auditoría
            logAuditoria($conn, $_SESSION['user_id'], 'EXECUTE', 'backup', $backupId,
                "Backup {$tipo} creado exitosamente", 'Éxito', 'Alta');
            echo json_encode([
                'success' => true,
                'message' => 'Backup creado exitosamente',
                'data' => [
                    'id' => $backupId,
                    'filename' => $filename . $extension,
                    'size' => $filesize,
                    'duration' => $duration
                ]
            ]);
        } else {
            throw new Exception('Error al crear el archivo de backup');
        }
    } catch (Exception $e) {
        // Actualizar estado a fallido
        $sqlFail = "UPDATE backup SET 
                    estado_backup = 'Fallido',
                    fecha_fin = NOW(),
                    observaciones = CONCAT(COALESCE(observaciones, ''), '\nError: ', ?)
                    WHERE id_backup = ?";
        $stmtFail = $conn->prepare($sqlFail);
        $stmtFail->execute([$e->getMessage(), $backupId]);
        throw $e;
    }
}
/**
 * Ejecutar backup completo
 */
function executeFullBackup($filepath, $compress) {
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $database = DB_NAME;
    // Intentar encontrar mysqldump en diferentes ubicaciones comunes de XAMPP
    $possiblePaths = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe', // Windows XAMPP
        'C:\\XAMPP\\mysql\\bin\\mysqldump.exe', // Windows XAMPP uppercase
        '/Applications/XAMPP/bin/mysqldump',      // Mac XAMPP
        '/opt/lampp/bin/mysqldump',               // Linux XAMPP
        'mysqldump'                                // Si está en PATH
    ];
    $mysqldumpPath = 'mysqldump';
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $mysqldumpPath = $path;
            break;
        }
    }
    // Separar host y puerto
    $hostParts = explode(':', $host);
    $hostOnly = $hostParts[0];
    $port = isset($hostParts[1]) ? $hostParts[1] : '3306';
    // Si la compresión no está disponible o falla, hacer backup sin comprimir
    if ($compress && !function_exists('gzencode')) {
        $compress = false;
        $filepath = str_replace('.gz', '', $filepath);
    }
    // Método 1: Intentar con mysqldump si está disponible
    if (is_executable($mysqldumpPath) || $mysqldumpPath === 'mysqldump') {
        $command = sprintf(
            '%s --user=%s --password=%s --host=%s --port=%s --single-transaction --quick --lock-tables=false %s',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($hostOnly),
            escapeshellarg($port),
            escapeshellarg($database)
        );
        if ($compress) {
            $command .= ' | gzip';
        }
        $command .= ' > ' . escapeshellarg($filepath);
        exec($command . ' 2>&1', $output, $returnCode);
        if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            return true;
        }
    }
    // Método 2: Backup manual con PHP si mysqldump falla
    return executeManualBackup($filepath, $compress);
}
/**
 * Ejecutar backup manual usando PHP
 */
function executeManualBackup($filepath, $compress) {
    try {
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $database = DB_NAME;
        // Separar host y puerto
        $hostParts = explode(':', $host);
        $hostOnly = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : '3306';
        // Conectar a la base de datos
        $conn = new mysqli($hostOnly, $user, $pass, $database, (int)$port);
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        // Iniciar contenido del backup
        $backup = "-- Sistema Hospitalario - Backup Database\n";
        $backup .= "-- Generado el: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Host: {$hostOnly}:{$port}\n";
        $backup .= "-- Database: {$database}\n\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $backup .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup .= "SET AUTOCOMMIT = 0;\n";
        $backup .= "START TRANSACTION;\n\n";
        // Obtener todas las tablas
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        // Backup de cada tabla
        foreach ($tables as $table) {
            $backup .= "-- --------------------------------------------------------\n";
            $backup .= "-- Estructura de tabla para `{$table}`\n";
            $backup .= "-- --------------------------------------------------------\n\n";
            // DROP TABLE
            $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
            // CREATE TABLE
            $createTable = $conn->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createTable->fetch_array();
            $backup .= $createRow[1] . ";\n\n";
            // Datos de la tabla
            $result = $conn->query("SELECT * FROM `{$table}`");
            if ($result && $result->num_rows > 0) {
                $backup .= "-- Volcado de datos para la tabla `{$table}`\n\n";
                while ($row = $result->fetch_assoc()) {
                    $backup .= "INSERT INTO `{$table}` VALUES (";
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    $backup .= implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }
        }
        $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup .= "COMMIT;\n";
        $conn->close();
        // Guardar el backup
        if ($compress && function_exists('gzencode')) {
            $backup = gzencode($backup, 9);
            if (!str_ends_with($filepath, '.gz')) {
                $filepath .= '.gz';
            }
        }
        $result = file_put_contents($filepath, $backup);
        return $result !== false && file_exists($filepath) && filesize($filepath) > 0;
        
    } catch (Exception $e) {
        error_log("Error en backup manual: " . $e->getMessage());
        return false;
    }
}
/**
 * Ejecutar backup incremental
 */
function executeIncrementalBackup($conn, $filepath, $compress) {
    // Obtener último backup completo
    $sql = "SELECT fecha_inicio FROM backup 
            WHERE tipo_backup = 'Completo' 
            AND estado_backup = 'Completado'
            ORDER BY fecha_inicio DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $lastBackup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lastBackup) {
        throw new Exception('No existe un backup completo previo. Cree primero un backup completo.');
    }
    // Por simplicidad, hacer backup completo
    return executeFullBackup($filepath, $compress);
}
/**
 * Ejecutar backup diferencial
 */
function executeDifferentialBackup($conn, $filepath, $compress) {
    // Similar a incremental
    return executeIncrementalBackup($conn, $filepath, $compress);
}
/**
 * Ejecutar backup transaccional
 */
function executeTransactionalBackup($filepath, $compress) {
    // Implementar backup de binary logs
    return executeFullBackup($filepath, $compress);
}
/**
 * Obtener detalles de un backup
 */
function getBackupDetails($conn) {
    $id = $_GET['id'] ?? 0;
    $sql = "SELECT b.*, 
            CONCAT(p.nombres, ' ', p.apellidos) as realizado_por_nombre,
            u.username as realizado_por_username
            FROM backup b
            LEFT JOIN usuario u ON b.realizado_por = u.id_usuario
            LEFT JOIN persona p ON u.id_persona = p.id_persona
            WHERE b.id_backup = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) {
        throw new Exception('Backup no encontrado');
    }
    // Verificar si el archivo existe
    $backup['archivo_existe'] = file_exists($backup['ubicacion_archivo']);
    if ($backup['archivo_existe']) {
        $backup['tamanio_archivo'] = filesize($backup['ubicacion_archivo']);
    }
    echo json_encode([
        'success' => true,
        'data' => $backup
    ]);
}
/**
 * Descargar backup
 */
function downloadBackup($conn) {
    $id = $_GET['id'] ?? 0;
    $sql = "SELECT * FROM backup WHERE id_backup = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) {
        throw new Exception('Backup no encontrado');
    }
    $filepath = $backup['ubicacion_archivo'];
    if (!file_exists($filepath)) {
        throw new Exception('Archivo de backup no encontrado');
    }
    // Registrar descarga en auditoría
    logAuditoria($conn, $_SESSION['user_id'], 'EXPORT', 'backup', $id,
        "Backup descargado", 'Éxito', 'Media');
    // Enviar archivo
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache');
    readfile($filepath);
    exit();
}
/**
 * Restaurar desde backup
 */
function restoreBackup($conn) {
    global $_SESSION;
    $input = json_decode(file_get_contents('php://input'), true);
    $backupId = $input['backup_id'] ?? 0;
    $tipo = $input['tipo'] ?? 'Completa';
    $motivo = $input['motivo'] ?? '';
    $tablas = $input['tablas'] ?? [];
    // Obtener información del backup
    $sql = "SELECT * FROM backup WHERE id_backup = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$backupId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup || $backup['estado_backup'] !== 'Completado') {
        throw new Exception('Backup no válido para restauración');
    }
    $filepath = $backup['ubicacion_archivo'];
    if (!file_exists($filepath)) {
        throw new Exception('Archivo de backup no encontrado');
    }
    // Verificar hash
    $currentHash = hash_file('sha256', $filepath);
    if ($currentHash !== $backup['hash_verificacion']) {
        throw new Exception('El archivo de backup está corrupto (hash no coincide)');
    }
    // Registrar inicio de restauración
    $sqlRestore = "INSERT INTO restauracion (id_backup, tipo_restauracion, estado_restauracion,
                    motivo, autorizado_por, ejecutado_por, fecha_inicio)
                    VALUES (?, ?, 'En proceso', ?, ?, ?, NOW())";
    $stmtRestore = $conn->prepare($sqlRestore);
    $stmtRestore->execute([
        $backupId,
        $tipo,
        $motivo,
        $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);
    $restoreId = $conn->lastInsertId();
    try {
        $startTime = microtime(true);
        // Ejecutar restauración
        $success = executeRestore($filepath, $tipo, $tablas, $backup['comprimido']);
        $endTime = microtime(true);
        if ($success) {
            // Actualizar estado
            $sqlUpdate = "UPDATE restauracion SET
                        estado_restauracion = 'Completado',
                        fecha_fin = NOW()
                        WHERE id_restauracion = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$restoreId]);
            // Actualizar estado del backup
            $sqlBackupUpdate = "UPDATE backup SET estado_backup = 'Restaurado' 
                                WHERE id_backup = ?";
            $stmtBackupUpdate = $conn->prepare($sqlBackupUpdate);
            $stmtBackupUpdate->execute([$backupId]);
            // Auditoría
            logAuditoria($conn, $_SESSION['user_id'], 'EXECUTE', 'restauracion', $restoreId,
                "Base de datos restaurada desde backup ID: {$backupId}", 'Éxito', 'Crítica');
            echo json_encode([
                'success' => true,
                'message' => 'Restauración completada exitosamente'
            ]);
        } else {
            throw new Exception('Error durante la restauración');
        }
    } catch (Exception $e) {
        // Marcar como fallida
        $sqlFail = "UPDATE restauracion SET estado_restauracion = 'Fallido' 
                    WHERE id_restauracion = ?";
        $stmtFail = $conn->prepare($sqlFail);
        $stmtFail->execute([$restoreId]);
        
        throw $e;
    }
}
/**
 * Ejecutar proceso de restauración
 */
function executeRestore($filepath, $tipo, $tablas, $compressed) {
    try {
        // Leer el contenido del backup
        $sql = file_get_contents($filepath);
        if ($sql === false) {
            throw new Exception("No se pudo leer el archivo de backup");
        }
        // Si está comprimido, descomprimir
        if ($compressed && function_exists('gzdecode')) {
            $sql = gzdecode($sql);
            if ($sql === false) {
                throw new Exception("Error al descomprimir el archivo");
            }
        }
        // Conectar a la base de datos
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $database = DB_NAME;
        $hostParts = explode(':', $host);
        $hostOnly = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : '3306';
        $conn = new mysqli($hostOnly, $user, $pass, $database, (int)$port);
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        // Desactivar checks temporalmente
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $conn->query("SET AUTOCOMMIT = 0");
        $conn->query("START TRANSACTION");
        // Dividir en consultas individuales
        $queries = array_filter(
            array_map('trim', explode(';', $sql)),
            function($query) {
                return !empty($query) && !preg_match('/^--/', $query);
            }
        );
        $successCount = 0;
        $errorCount = 0;
        foreach ($queries as $query) {
            if (empty($query)) continue;
            // Si es restauración parcial, verificar si la tabla está en la lista
            if ($tipo === 'Parcial' && !empty($tablas)) {
                $skipQuery = true;
                foreach ($tablas as $tabla) {
                    if (stripos($query, "`{$tabla}`") !== false || 
                        stripos($query, "INTO {$tabla}") !== false ||
                        stripos($query, "TABLE {$tabla}") !== false) {
                        $skipQuery = false;
                        break;
                    }
                }
                if ($skipQuery) continue;
            }
            if ($conn->query($query)) {
                $successCount++;
            } else {
                $errorCount++;
                error_log("Error en query: " . $conn->error . "\nQuery: " . substr($query, 0, 200));
            }
        }
        // Commit y reactivar checks
        $conn->query("COMMIT");
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        $conn->close();
        // Considerar exitoso si se ejecutó al menos el 90% de las queries
        $total = $successCount + $errorCount;
        $successRate = $total > 0 ? ($successCount / $total) : 0;
        return $successRate >= 0.9;
    } catch (Exception $e) {
        error_log("Error en restauración: " . $e->getMessage());
        return false;
    }
}
/**
 * Eliminar backup
 */
function deleteBackup($conn) {
    global $_SESSION;
    $id = $_GET['id'] ?? 0;
    // Obtener información del backup
    $sql = "SELECT * FROM backup WHERE id_backup = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) {
        throw new Exception('Backup no encontrado');
    }
    // Eliminar archivo físico
    if (file_exists($backup['ubicacion_archivo'])) {
        unlink($backup['ubicacion_archivo']);
    }
    // Eliminar registro de base de datos
    $sqlDelete = "DELETE FROM backup WHERE id_backup = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->execute([$id]);
    // Auditoría
    logAuditoria($conn, $_SESSION['user_id'], 'DELETE', 'backup', $id,
        "Backup eliminado: {$backup['tipo_backup']}", 'Éxito', 'Alta');
    echo json_encode([
        'success' => true,
        'message' => 'Backup eliminado exitosamente'
    ]);
}
/**
 * Obtener estadísticas
 */
function getStatistics($conn) {
    $stats = [];
    // Total de backups
    $sql = "SELECT COUNT(*) as total FROM backup";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    // Completados
    $sql = "SELECT COUNT(*) as completados FROM backup WHERE estado_backup = 'Completado'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['completados'] = $stmt->fetch(PDO::FETCH_ASSOC)['completados'];
    // En proceso
    $sql = "SELECT COUNT(*) as en_proceso FROM backup WHERE estado_backup = 'En proceso'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['en_proceso'] = $stmt->fetch(PDO::FETCH_ASSOC)['en_proceso'];
    // Tamaño total
    $sql = "SELECT COALESCE(SUM(tamanio_mb), 0) as tamanio_total FROM backup 
            WHERE estado_backup = 'Completado'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['tamanio_total'] = round($stmt->fetch(PDO::FETCH_ASSOC)['tamanio_total'], 2);
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}
/**
 * Verificar integridad de backup
 */
function verifyBackup($conn) {
    $id = $_GET['id'] ?? 0;
    $sql = "SELECT * FROM backup WHERE id_backup = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) {
        throw new Exception('Backup no encontrado');
    }
    $filepath = $backup['ubicacion_archivo'];
    if (!file_exists($filepath)) {
        echo json_encode([
            'success' => false,
            'message' => 'Archivo no encontrado',
            'valid' => false
        ]);
        return;
    }
    // Calcular hash actual
    $currentHash = hash_file('sha256', $filepath);
    $valid = ($currentHash === $backup['hash_verificacion']);
    // Actualizar estado si está corrupto
    if (!$valid) {
        $sqlUpdate = "UPDATE backup SET estado_backup = 'Corrupto' WHERE id_backup = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([$id]);
    }
    echo json_encode([
        'success' => true,
        'valid' => $valid,
        'message' => $valid ? 'Backup íntegro' : 'Backup corrupto (hash no coincide)'
    ]);
}
/**
 * Registrar en log de auditoría
 */
function logAuditoria($conn, $userId, $accion, $tabla, $registroId, $descripcion, $resultado, $criticidad) {
    $sql = "INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
            descripcion, ip_address, resultado, criticidad) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $userId,
        $accion,
        $tabla,
        $registroId,
        $descripcion,
        $_SERVER['REMOTE_ADDR'],
        $resultado,
        $criticidad
    ]);
}