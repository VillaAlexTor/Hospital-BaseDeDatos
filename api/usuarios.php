<?php
/**
 * api/usuarios.php
 * API de Gestión de Usuarios
 * Versión: 2.0 - Optimizada
 */

// ==========================================
// 1. CONFIGURACIÓN DE ERRORES
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal del servidor',
            'error' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

// ==========================================
// 2. CARGAR DEPENDENCIAS
// ==========================================
try {
    require_once __DIR__ . '/../includes/config.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar configuración',
        'error' => $e->getMessage()
    ]);
    exit;
}

// ==========================================
// 3. HEADERS DE SEGURIDAD (después de config)
// ==========================================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ==========================================
// 4. VALIDACIONES CRÍTICAS
// ==========================================

// Validar conexión PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a base de datos'
    ]);
    exit;
}

// Validar función de descifrado
if (!function_exists('decrypt_data')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: Funciones de seguridad no disponibles'
    ]);
    exit;
}

// Validar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado. Debe iniciar sesión.'
    ]);
    exit;
}

// Validar permisos de administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado. Solo administradores.'
    ]);
    exit;
}

// ==========================================
// 5. FUNCIONES AUXILIARES
// ==========================================

function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function sanitizeInput($data) {
    if (is_null($data)) return null;
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function safeDecrypt($data) {
    try {
        $result = decrypt_data($data);
        return $result !== false ? $result : '';
    } catch (Exception $e) {
        return '';
    }
}

// ==========================================
// 6. PROCESAR SOLICITUD
// ==========================================
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verificar CSRF para operaciones de modificación
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        sendResponse(false, 'Token CSRF inválido. Recargue la página.', null, 403);
    }
}

// ==========================================
// 7. EJECUTAR ACCIÓN
// ==========================================
try {
    switch ($action) {
        
        // ==========================================
        // LISTAR USUARIOS
        // ==========================================
        case 'list':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = sanitizeInput($_GET['search'] ?? '');
            $rol = sanitizeInput($_GET['rol'] ?? '');
            $estado = sanitizeInput($_GET['estado'] ?? '');
            
            // Construir WHERE dinámico
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $where .= " AND (u.username LIKE ? OR per.nombres LIKE ? OR per.apellidos LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            if (!empty($rol)) {
                $where .= " AND r.nombre = ?";
                $params[] = $rol;
            }
            
            if (!empty($estado)) {
                $where .= " AND u.estado = ?";
                $params[] = $estado;
            }
            
            // Contar total
            $countQuery = "
                SELECT COUNT(DISTINCT u.id_usuario) 
                FROM usuario u 
                JOIN persona per ON u.id_persona = per.id_persona 
                LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                LEFT JOIN rol r ON ur.id_rol = r.id_rol
                $where
            ";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            
            // Obtener usuarios
            $query = "
                SELECT 
                    u.id_usuario,
                    u.username,
                    u.email_verificado,
                    u.cuenta_bloqueada,
                    u.estado,
                    u.ultimo_acceso,
                    u.fecha_creacion,
                    per.nombres,
                    per.apellidos,
                    per.email,
                    per.telefono,
                    GROUP_CONCAT(DISTINCT r.nombre SEPARATOR ', ') as roles,
                    GROUP_CONCAT(DISTINCT r.id_rol) as rol_ids
                FROM usuario u
                JOIN persona per ON u.id_persona = per.id_persona
                LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                LEFT JOIN rol r ON ur.id_rol = r.id_rol
                $where
                GROUP BY u.id_usuario, u.username, u.email_verificado, u.cuenta_bloqueada, 
                         u.estado, u.ultimo_acceso, u.fecha_creacion, per.nombres, 
                         per.apellidos, per.email, per.telefono
                ORDER BY u.fecha_creacion DESC
                LIMIT $limit OFFSET $offset
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();
            
            // Desencriptar datos sensibles
            foreach ($usuarios as &$usuario) {
                $usuario['nombres'] = safeDecrypt($usuario['nombres']);
                $usuario['apellidos'] = safeDecrypt($usuario['apellidos']);
                $usuario['email'] = safeDecrypt($usuario['email']);
                $usuario['telefono'] = safeDecrypt($usuario['telefono']);
                $usuario['nombre_completo'] = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
            }
            
            sendResponse(true, 'Usuarios obtenidos exitosamente', [
                'usuarios' => $usuarios,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
            break;
        
        // ==========================================
        // CREAR USUARIO PARA MÉDICO
        // ==========================================
        case 'create_doctor':
            if ($method !== 'POST') {
                sendResponse(false, 'Método no permitido', null, 405);
            }
            
            $required = ['id_personal', 'username', 'password'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    sendResponse(false, "Campo requerido: $field", null, 400);
                }
            }
            
            $id_personal = (int)$_POST['id_personal'];
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            
            // Validaciones
            if (strlen($username) < 4 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
                sendResponse(false, 'Username inválido (mínimo 4 caracteres)', null, 400);
            }
            
            if (strlen($password) < 8) {
                sendResponse(false, 'Contraseña muy corta (mínimo 8 caracteres)', null, 400);
            }
            
            $pdo->beginTransaction();
            
            try {
                // Verificar médico
                $stmt = $pdo->prepare("
                    SELECT p.id_personal, per.id_persona, m.id_medico, 
                           e.nombre as especialidad, per.nombres, per.apellidos
                    FROM personal p
                    JOIN persona per ON p.id_personal = per.id_persona
                    LEFT JOIN medico m ON p.id_personal = m.id_medico
                    LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                    WHERE p.id_personal = ? AND p.tipo_personal = 'Medico'
                ");
                $stmt->execute([$id_personal]);
                $personal = $stmt->fetch();
                
                if (!$personal) {
                    throw new Exception('Personal no encontrado o no es médico');
                }
                
                // Verificar usuario existente
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = ?");
                $stmt->execute([$personal['id_persona']]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Este médico ya tiene un usuario asignado');
                }
                
                // Verificar username
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('El username ya está en uso');
                }
                
                // Crear usuario
                $salt = bin2hex(random_bytes(16));
                $password_hash = hash('sha256', $password . $salt);
                
                $stmt = $pdo->prepare("
                    INSERT INTO usuario (
                        id_persona, username, password_hash, password_salt,
                        email_verificado, estado, requiere_cambio_password
                    ) VALUES (?, ?, ?, ?, 0, 'activo', 1)
                ");
                $stmt->execute([$personal['id_persona'], $username, $password_hash, $salt]);
                $id_usuario = $pdo->lastInsertId();
                
                // Asignar rol
                $stmt = $pdo->prepare("
                    INSERT INTO usuario_rol (id_usuario, id_rol, estado, asignado_por)
                    VALUES (?, (SELECT id_rol FROM rol WHERE nombre = 'Médico'), 'activo', ?)
                ");
                $stmt->execute([$id_usuario, $_SESSION['user_id']]);
                
                // Log
                if (function_exists('log_action')) {
                    log_action('INSERT', 'usuario', $id_usuario, 
                        "Usuario creado para médico: $username - Especialidad: {$personal['especialidad']}",
                        null, null, 'Éxito', 'Alta');
                }
                
                $pdo->commit();
                
                sendResponse(true, 'Usuario creado exitosamente', [
                    'id_usuario' => $id_usuario,
                    'username' => $username,
                    'especialidad' => $personal['especialidad']
                ], 201);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                sendResponse(false, $e->getMessage(), null, 400);
            }
            break;
        
        // ==========================================
        // CREAR USUARIO PARA PACIENTE
        // ==========================================
        case 'create_patient':
            if ($method !== 'POST') {
                sendResponse(false, 'Método no permitido', null, 405);
            }
            
            $required = ['id_paciente', 'username', 'password'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    sendResponse(false, "Campo requerido: $field", null, 400);
                }
            }
            
            $id_paciente = (int)$_POST['id_paciente'];
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            
            if (strlen($username) < 4 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
                sendResponse(false, 'Username inválido', null, 400);
            }
            
            if (strlen($password) < 8) {
                sendResponse(false, 'Contraseña muy corta', null, 400);
            }
            
            $pdo->beginTransaction();
            
            try {
                // Verificar paciente
                $stmt = $pdo->prepare("
                    SELECT pac.id_paciente, pac.numero_historia_clinica,
                           per.nombres, per.apellidos
                    FROM paciente pac
                    JOIN persona per ON pac.id_paciente = per.id_persona
                    WHERE pac.id_paciente = ?
                ");
                $stmt->execute([$id_paciente]);
                $paciente = $stmt->fetch();
                
                if (!$paciente) {
                    throw new Exception('Paciente no encontrado');
                }
                
                // Verificar usuario existente
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = ?");
                $stmt->execute([$id_paciente]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Este paciente ya tiene un usuario');
                }
                
                // Verificar username
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Username ya existe');
                }
                
                // Crear usuario
                $salt = bin2hex(random_bytes(16));
                $password_hash = hash('sha256', $password . $salt);
                
                $stmt = $pdo->prepare("
                    INSERT INTO usuario (
                        id_persona, username, password_hash, password_salt,
                        email_verificado, estado, requiere_cambio_password
                    ) VALUES (?, ?, ?, ?, 0, 'activo', 1)
                ");
                $stmt->execute([$id_paciente, $username, $password_hash, $salt]);
                $id_usuario = $pdo->lastInsertId();
                
                // Asignar rol
                $stmt = $pdo->prepare("
                    INSERT INTO usuario_rol (id_usuario, id_rol, estado, asignado_por)
                    VALUES (?, (SELECT id_rol FROM rol WHERE nombre = 'Paciente'), 'activo', ?)
                ");
                $stmt->execute([$id_usuario, $_SESSION['user_id']]);
                
                // Log
                if (function_exists('log_action')) {
                    log_action('INSERT', 'usuario', $id_usuario,
                        "Usuario creado para paciente: $username - HC: {$paciente['numero_historia_clinica']}",
                        null, null, 'Éxito', 'Media');
                }
                
                $pdo->commit();
                
                sendResponse(true, 'Usuario creado exitosamente', [
                    'id_usuario' => $id_usuario,
                    'username' => $username,
                    'numero_historia' => $paciente['numero_historia_clinica']
                ], 201);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                sendResponse(false, $e->getMessage(), null, 400);
            }
            break;
        
        // ==========================================
        // CAMBIAR ESTADO
        // ==========================================
        case 'change_status':
            if ($method !== 'POST') {
                sendResponse(false, 'Método no permitido', null, 405);
            }
            
            $id_usuario = (int)($_POST['id_usuario'] ?? 0);
            $nuevo_estado = sanitizeInput($_POST['estado'] ?? '');
            
            if (!$id_usuario || !in_array($nuevo_estado, ['activo', 'inactivo', 'bloqueado'])) {
                sendResponse(false, 'Datos inválidos', null, 400);
            }
            
            if ($id_usuario == $_SESSION['user_id']) {
                sendResponse(false, 'No puedes cambiar tu propio estado', null, 400);
            }
            
            $stmt = $pdo->prepare("
                UPDATE usuario 
                SET estado = ?,
                    cuenta_bloqueada = CASE WHEN ? = 'bloqueado' THEN 1 ELSE 0 END,
                    fecha_bloqueo = CASE WHEN ? = 'bloqueado' THEN NOW() ELSE NULL END
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nuevo_estado, $nuevo_estado, $nuevo_estado, $id_usuario]);
            
            if (function_exists('log_action')) {
                log_action('UPDATE', 'usuario', $id_usuario, "Estado cambiado a: $nuevo_estado");
            }
            
            sendResponse(true, 'Estado actualizado exitosamente');
            break;
        
        // ==========================================
        // RESETEAR CONTRASEÑA
        // ==========================================
        case 'reset_password':
            if ($method !== 'POST') {
                sendResponse(false, 'Método no permitido', null, 405);
            }
            
            $id_usuario = (int)($_POST['id_usuario'] ?? 0);
            $nueva_password = $_POST['nueva_password'] ?? '';
            
            if (!$id_usuario || strlen($nueva_password) < 8) {
                sendResponse(false, 'Contraseña muy corta (mínimo 8 caracteres)', null, 400);
            }
            
            $salt = bin2hex(random_bytes(16));
            $password_hash = hash('sha256', $nueva_password . $salt);
            
            $stmt = $pdo->prepare("
                UPDATE usuario 
                SET password_hash = ?,
                    password_salt = ?,
                    requiere_cambio_password = 1,
                    fecha_ultimo_cambio_password = NOW(),
                    intentos_fallidos = 0,
                    cuenta_bloqueada = 0
                WHERE id_usuario = ?
            ");
            $stmt->execute([$password_hash, $salt, $id_usuario]);
            
            if (function_exists('log_action')) {
                log_action('UPDATE', 'usuario', $id_usuario, 
                    'Contraseña reseteada por administrador', null, null, 'Éxito', 'Alta');
            }
            
            sendResponse(true, 'Contraseña reseteada exitosamente');
            break;
        
        // ==========================================
        // MÉDICOS SIN USUARIO
        // ==========================================
        case 'doctors_without_user':
            $stmt = $pdo->query("
                SELECT 
                    p.id_personal,
                    per.nombres,
                    per.apellidos,
                    e.nombre as especialidad,
                    m.numero_colegiatura
                FROM personal p
                JOIN persona per ON p.id_personal = per.id_persona
                JOIN medico m ON p.id_personal = m.id_medico
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                LEFT JOIN usuario u ON per.id_persona = u.id_persona
                WHERE p.tipo_personal = 'Medico'
                AND p.estado_laboral = 'activo'
                AND u.id_usuario IS NULL
                ORDER BY per.apellidos, per.nombres
            ");
            $medicos = $stmt->fetchAll();
            
            foreach ($medicos as &$medico) {
                $medico['nombres'] = safeDecrypt($medico['nombres']);
                $medico['apellidos'] = safeDecrypt($medico['apellidos']);
                $medico['nombre_completo'] = $medico['apellidos'] . ', ' . $medico['nombres'];
            }
            
            sendResponse(true, 'Médicos obtenidos', $medicos);
            break;
        
        // ==========================================
        // PACIENTES SIN USUARIO
        // ==========================================
        case 'patients_without_user':
            $stmt = $pdo->query("
                SELECT 
                    pac.id_paciente,
                    pac.numero_historia_clinica,
                    per.nombres,
                    per.apellidos,
                    per.numero_documento
                FROM paciente pac
                JOIN persona per ON pac.id_paciente = per.id_persona
                LEFT JOIN usuario u ON per.id_persona = u.id_persona
                WHERE pac.estado_paciente = 'activo'
                AND u.id_usuario IS NULL
                ORDER BY pac.fecha_primera_consulta DESC
                LIMIT 100
            ");
            $pacientes = $stmt->fetchAll();
            
            foreach ($pacientes as &$paciente) {
                $paciente['nombres'] = safeDecrypt($paciente['nombres']);
                $paciente['apellidos'] = safeDecrypt($paciente['apellidos']);
                $paciente['numero_documento'] = safeDecrypt($paciente['numero_documento']);
                $paciente['nombre_completo'] = $paciente['apellidos'] . ', ' . $paciente['nombres'];
            }
            
            sendResponse(true, 'Pacientes obtenidos', $pacientes);
            break;
        
        // ==========================================
        // ACCIÓN INVÁLIDA
        // ==========================================
        default:
            sendResponse(false, 'Acción no válida: ' . $action, null, 400);
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error PDO en usuarios.php: " . $e->getMessage());
    sendResponse(false, 'Error de base de datos', null, 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en usuarios.php: " . $e->getMessage());
    sendResponse(false, 'Error del servidor', null, 500);
}