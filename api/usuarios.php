<?php
/**
 * api/usuarios.php
 * API de Gestión de Usuarios
 * Permite crear usuarios para doctores y pacientes (solo admin)
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/auth-check.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar dependencias: ' . $e->getMessage()
    ]);
    exit;
}
// Verificar que la función decrypt_data existe
if (!function_exists('decrypt_data')) {
    function decrypt_data($data) {
        return $data;
    }
}
// Verificar que sea administrador ANTES de hacer cualquier cosa
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    sendResponse(false, 'No tienes permisos para gestionar usuarios', null, 403);
}
// Verificar que sea administrador
if ($_SESSION['rol'] !== 'Administrador') {
    sendResponse(false, 'No tienes permisos para gestionar usuarios', null, 403);
}
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
// Usar sanitize_input de config.php si existe, si no, crear una local
if (!function_exists('sanitize_input_api')) {
    function sanitize_input_api($data) {
        if (is_null($data)) return null;
        return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
    }
}
// Alias para mantener compatibilidad
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return sanitize_input_api($data);
    }
}
// Verificar CSRF solo si la función no existe en config.php
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// Verificar CSRF para POST/PUT/DELETE
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        sendResponse(false, 'Token CSRF inválido', null, 403);
    }
}
switch ($action) {
    // LISTAR USUARIOS
    case 'list':
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = sanitize_input($_GET['search'] ?? '');
            $rol = sanitize_input($_GET['rol'] ?? '');
            $estado = sanitize_input($_GET['estado'] ?? '');
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
            $countQuery = "SELECT COUNT(DISTINCT u.id_usuario) FROM usuario u 
                            JOIN persona per ON u.id_persona = per.id_persona 
                            LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                            LEFT JOIN rol r ON ur.id_rol = r.id_rol
                            $where";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
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
                    GROUP_CONCAT(DISTINCT r.nombre) as roles,
                    GROUP_CONCAT(DISTINCT r.id_rol) as rol_ids
                FROM usuario u
                JOIN persona per ON u.id_persona = per.id_persona
                LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                LEFT JOIN rol r ON ur.id_rol = r.id_rol
                $where
                GROUP BY u.id_usuario
                ORDER BY u.fecha_creacion DESC
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();
            // Desencriptar datos sensibles
            foreach ($usuarios as &$usuario) {
                $usuario['nombres'] = decrypt_data($usuario['nombres']);
                $usuario['apellidos'] = decrypt_data($usuario['apellidos']);
                $usuario['email'] = decrypt_data($usuario['email']);
                $usuario['telefono'] = decrypt_data($usuario['telefono']);
            }
            sendResponse(true, 'Usuarios obtenidos', [
                'usuarios' => $usuarios,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            error_log("Error listando usuarios: " . $e->getMessage());
            sendResponse(false, 'Error al obtener usuarios', null, 500);
        }
        break;
    // CREAR USUARIO PARA DOCTOR
    case 'create_doctor':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        // Validar datos requeridos
        $required = ['id_personal', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                sendResponse(false, "El campo $field es requerido", null, 400);
            }
        }
        $id_personal = (int)$_POST['id_personal'];
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        // Validar username
        if (strlen($username) < 4 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            sendResponse(false, 'Username inválido (mínimo 4 caracteres, solo letras, números, punto, guion)', null, 400);
        }
        // Validar contraseña
        if (strlen($password) < 8) {
            sendResponse(false, 'La contraseña debe tener al menos 8 caracteres', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que el personal existe y es médico
            $stmt = $pdo->prepare("
                SELECT p.id_personal, per.id_persona, m.id_medico, e.nombre as especialidad
                FROM personal p
                JOIN persona per ON p.id_personal = per.id_persona
                LEFT JOIN medico m ON p.id_personal = m.id_medico
                LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                WHERE p.id_personal = ? AND p.tipo_personal = 'Medico'
            ");
            $stmt->execute([$id_personal]);
            $personal = $stmt->fetch();
            if (!$personal) {
                $pdo->rollBack();
                sendResponse(false, 'Personal no encontrado o no es médico', null, 404);
            }
            $id_persona = $personal['id_persona'];
            // Verificar que no tenga usuario ya
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = ?");
            $stmt->execute([$id_persona]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                sendResponse(false, 'Este médico ya tiene un usuario asignado', null, 400);
            }
            // Verificar que el username no exista
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                sendResponse(false, 'El username ya está en uso', null, 400);
            }
            // Generar salt y hash
            $salt = bin2hex(random_bytes(16));
            $password_hash = hash('sha256', $password . $salt);
            // Crear usuario
            $stmt = $pdo->prepare("
                INSERT INTO usuario (
                    id_persona, username, password_hash, password_salt,
                    email_verificado, estado, requiere_cambio_password
                ) VALUES (?, ?, ?, ?, 0, 'activo', 1)
            ");
            $stmt->execute([$id_persona, $username, $password_hash, $salt]);
            $id_usuario = $pdo->lastInsertId();
            // Asignar rol de Médico
            $stmt = $pdo->prepare("
                INSERT INTO usuario_rol (id_usuario, id_rol, estado, asignado_por)
                VALUES (?, (SELECT id_rol FROM rol WHERE nombre = 'Médico'), 'activo', ?)
            ");
            $stmt->execute([$id_usuario, $_SESSION['user_id']]);
            // Auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'INSERT', 'usuario', ?, ?, ?, 'Éxito', 'Alta')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_usuario,
                "Usuario creado para médico: $username - Especialidad: {$personal['especialidad']}",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Usuario creado exitosamente para el médico', [
                'id_usuario' => $id_usuario,
                'username' => $username,
                'id_persona' => $id_persona,
                'especialidad' => $personal['especialidad']
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creando usuario médico: " . $e->getMessage());
            sendResponse(false, 'Error al crear usuario', null, 500);
        }
        break;
    // CREAR USUARIO PARA PACIENTE
    case 'create_patient':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $required = ['id_paciente', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                sendResponse(false, "El campo $field es requerido", null, 400);
            }
        }
        $id_paciente = (int)$_POST['id_paciente'];
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        if (strlen($username) < 4 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            sendResponse(false, 'Username inválido', null, 400);
        }
        if (strlen($password) < 8) {
            sendResponse(false, 'Contraseña debe tener al menos 8 caracteres', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que el paciente existe
            $stmt = $pdo->prepare("
                SELECT pac.id_paciente, pac.numero_historia_clinica
                FROM paciente pac
                WHERE pac.id_paciente = ?
            ");
            $stmt->execute([$id_paciente]);
            $paciente = $stmt->fetch();
            if (!$paciente) {
                $pdo->rollBack();
                sendResponse(false, 'Paciente no encontrado', null, 404);
            }
            // Verificar que no tenga usuario
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = ?");
            $stmt->execute([$id_paciente]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                sendResponse(false, 'Este paciente ya tiene un usuario', null, 400);
            }
            // Verificar username
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                sendResponse(false, 'Username ya existe', null, 400);
            }
            // Generar salt y hash
            $salt = bin2hex(random_bytes(16));
            $password_hash = hash('sha256', $password . $salt);
            // Crear usuario
            $stmt = $pdo->prepare("
                INSERT INTO usuario (
                    id_persona, username, password_hash, password_salt,
                    email_verificado, estado, requiere_cambio_password
                ) VALUES (?, ?, ?, ?, 0, 'activo', 1)
            ");
            $stmt->execute([$id_paciente, $username, $password_hash, $salt]);
            $id_usuario = $pdo->lastInsertId();
            // Asignar rol de Paciente
            $stmt = $pdo->prepare("
                INSERT INTO usuario_rol (id_usuario, id_rol, estado, asignado_por)
                VALUES (?, (SELECT id_rol FROM rol WHERE nombre = 'Paciente'), 'activo', ?)
            ");
            $stmt->execute([$id_usuario, $_SESSION['user_id']]);
            // Auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'INSERT', 'usuario', ?, ?, ?, 'Éxito', 'Media')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_usuario,
                "Usuario creado para paciente: $username - HC: {$paciente['numero_historia_clinica']}",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Usuario creado para el paciente', [
                'id_usuario' => $id_usuario,
                'username' => $username,
                'numero_historia' => $paciente['numero_historia_clinica']
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creando usuario paciente: " . $e->getMessage());
            sendResponse(false, 'Error al crear usuario', null, 500);
        }
        break;
    // CAMBIAR ESTADO DE USUARIO
    case 'change_status':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $nuevo_estado = sanitize_input($_POST['estado'] ?? '');
        if (empty($id_usuario) || !in_array($nuevo_estado, ['activo', 'inactivo', 'bloqueado'])) {
            sendResponse(false, 'Datos inválidos', null, 400);
        }
        // No permitir bloquearse a sí mismo
        if ($id_usuario == $_SESSION['user_id']) {
            sendResponse(false, 'No puedes cambiar tu propio estado', null, 400);
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE usuario 
                SET estado = ?,
                    cuenta_bloqueada = CASE WHEN ? = 'bloqueado' THEN 1 ELSE 0 END,
                    fecha_bloqueo = CASE WHEN ? = 'bloqueado' THEN NOW() ELSE NULL END
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nuevo_estado, $nuevo_estado, $nuevo_estado, $id_usuario]);
            // Auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                            descripcion, ip_address, resultado)
                VALUES (?, 'UPDATE', 'usuario', ?, ?, ?, 'Éxito')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_usuario,
                "Estado cambiado a: $nuevo_estado",
                $_SERVER['REMOTE_ADDR']
            ]);
            sendResponse(true, 'Estado actualizado');
        } catch (PDOException $e) {
            error_log("Error cambiando estado: " . $e->getMessage());
            sendResponse(false, 'Error al cambiar estado', null, 500);
        }
        break;
    // RESETEAR CONTRASEÑA
    case 'reset_password':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $nueva_password = $_POST['nueva_password'] ?? '';
        if (empty($id_usuario) || strlen($nueva_password) < 8) {
            sendResponse(false, 'Datos inválidos o contraseña muy corta', null, 400);
        }
        try {
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
            // Auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'UPDATE', 'usuario', ?, 'Contraseña reseteada por administrador', ?, 'Éxito', 'Alta')
            ");
            $stmt->execute([$_SESSION['user_id'], $id_usuario, $_SERVER['REMOTE_ADDR']]);
            sendResponse(true, 'Contraseña reseteada. Usuario deberá cambiarla en próximo login');
        } catch (PDOException $e) {
            error_log("Error reseteando password: " . $e->getMessage());
            sendResponse(false, 'Error al resetear contraseña', null, 500);
        }
        break;
    // OBTENER MÉDICOS SIN USUARIO
    case 'doctors_without_user':
        try {
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
            // Desencriptar
            foreach ($medicos as &$medico) {
                $medico['nombres'] = decrypt_data($medico['nombres']);
                $medico['apellidos'] = decrypt_data($medico['apellidos']);
            }
            sendResponse(true, 'Médicos sin usuario obtenidos', $medicos);
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            sendResponse(false, 'Error al obtener médicos', null, 500);
        }
        break;
    // OBTENER PACIENTES SIN USUARIO
    case 'patients_without_user':
        try {
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
            // Desencriptar
            foreach ($pacientes as &$paciente) {
                $paciente['nombres'] = decrypt_data($paciente['nombres']);
                $paciente['apellidos'] = decrypt_data($paciente['apellidos']);
                $paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
            }
            sendResponse(true, 'Pacientes sin usuario obtenidos', $pacientes);
            
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            sendResponse(false, 'Error al obtener pacientes', null, 500);
        }
        break;
    default:
        sendResponse(false, 'Acción no válida', null, 400);
}
?>