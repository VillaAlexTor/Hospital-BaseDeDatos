<?php
/**
 * api/auth.php
 * API de Autenticación
 * Maneja operaciones relacionadas con usuarios y sesiones
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
require_once '../includes/config.php';
// Función para enviar respuesta JSON
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
// Función de rate limiting
function check_rate_limit($identifier, $action, $max_attempts, $window_seconds) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM log_auditoria 
            WHERE ip_address = ? 
            AND accion = ? 
            AND fecha_hora >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $action, $window_seconds]);
        $result = $stmt->fetch();
        return ($result['attempts'] < $max_attempts);
    } catch (PDOException $e) {
        error_log("Error en rate limit: " . $e->getMessage());
        return true; // Permitir en caso de error
    }
}
// Función para generar token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
// Función para verificar token CSRF
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
// Función para sanitizar entrada
function sanitize_input($data) {
    if (is_null($data)) return null;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    // Remover caracteres no imprimibles
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    return $data;
}
// Obtener método y acción
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// Acciones que no requieren CSRF
$csrf_exempt = ['check_session', 'get_user_info', 'get_csrf_token'];
// Validar que sea POST para la mayoría de acciones
if ($method !== 'POST' && !in_array($action, array_merge($csrf_exempt, ['check_session', 'get_user_info']))) {
    sendResponse(false, 'Método no permitido', null, 405);
}
// Verificar CSRF token para acciones POST
if ($method === 'POST' && !in_array($action, $csrf_exempt)) {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        sendResponse(false, 'Token CSRF inválido', null, 403);
    }
}
// Ejecutar acción solicitada
switch ($action) {
    // OBTENER TOKEN CSRF
    case 'get_csrf_token':
        $token = generate_csrf_token();
        sendResponse(true, 'Token CSRF generado', ['csrf_token' => $token]);
        break;
    // LOGIN
    case 'login':
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        if (empty($username) || empty($password)) {
            sendResponse(false, 'Usuario y contraseña son requeridos', null, 400);
        }
        // Validar longitud
        if (strlen($username) > 50 || strlen($password) > 100) {
            sendResponse(false, 'Credenciales inválidas', null, 400);
        }
        // Rate limiting - 5 intentos por 5 minutos
        if (!check_rate_limit($ip_address, 'LOGIN_FAILED', 5, 300)) {
            // Registrar intento bloqueado
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
                    VALUES (NULL, 'LOGIN_FAILED', 'Intento bloqueado por rate limit', ?, 'Bloqueado', 'Alta')
                ");
                $stmt->execute([$ip_address]);
            } catch (PDOException $e) {
                error_log("Error registrando bloqueo: " . $e->getMessage());
            }
            sendResponse(false, 'Demasiados intentos fallidos. Espere 5 minutos.', null, 429);
        }
        try {
            // Buscar usuario
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.username, u.password_hash, u.password_salt,
                        u.cuenta_bloqueada, u.estado, u.intentos_fallidos,
                        u.requiere_cambio_password, u.autenticacion_dos_factores,
                        p.id_persona, p.nombres, p.apellidos, p.email,
                        GROUP_CONCAT(DISTINCT r.nombre) as roles,
                        GROUP_CONCAT(DISTINCT r.id_rol) as rol_ids
                FROM usuario u
                JOIN persona p ON u.id_persona = p.id_persona
                LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                LEFT JOIN rol r ON ur.id_rol = r.id_rol
                WHERE u.username = ?
                GROUP BY u.id_usuario
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();
            if (!$usuario) {
                // Registrar intento con usuario inexistente
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
                    VALUES (NULL, 'LOGIN_FAILED', ?, ?, 'Fallo', 'Media')
                ");
                $stmt->execute(["Usuario no encontrado: $username", $ip_address]);
                
                sendResponse(false, 'Usuario o contraseña incorrectos', null, 401);
            }
            // Verificar estado de la cuenta
            if ($usuario['cuenta_bloqueada']) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
                    VALUES (?, 'LOGIN_FAILED', 'Intento de acceso a cuenta bloqueada', ?, 'Bloqueado', 'Alta')
                ");
                $stmt->execute([$usuario['id_usuario'], $ip_address]);
                
                sendResponse(false, 'Cuenta bloqueada. Contacte al administrador.', null, 403);
            }
            if ($usuario['estado'] !== 'activo') {
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
                    VALUES (?, 'LOGIN_FAILED', 'Intento de acceso a cuenta inactiva', ?, 'Bloqueado', 'Media')
                ");
                $stmt->execute([$usuario['id_usuario'], $ip_address]);
                
                sendResponse(false, 'Cuenta inactiva. Contacte al administrador.', null, 403);
            }
            // Verificar contraseña
            $password_hash = hash('sha256', $password . $usuario['password_salt']);
            if (hash_equals($usuario['password_hash'], $password_hash)) {
                // Login exitoso
                // Resetear intentos fallidos
                $stmt = $pdo->prepare("
                    UPDATE usuario 
                    SET intentos_fallidos = 0, 
                        ultimo_acceso = NOW(), 
                        ultima_ip = ? 
                    WHERE id_usuario = ?
                ");
                $stmt->execute([$ip_address, $usuario['id_usuario']]);
                // Generar token de sesión
                $session_token = bin2hex(random_bytes(32));
                // Registrar sesión
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $stmt = $pdo->prepare("
                    INSERT INTO sesion (id_usuario, token_sesion, ip_address, navegador, 
                                        sistema_operativo, estado_sesion)
                    VALUES (?, ?, ?, ?, ?, 'Activa')
                ");
                $stmt->execute([
                    $usuario['id_usuario'],
                    $session_token,
                    $ip_address,
                    substr($user_agent, 0, 200),
                    PHP_OS
                ]);
                $id_sesion = $pdo->lastInsertId();
                // Establecer variables de sesión
                $_SESSION['user_id'] = $usuario['id_usuario'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['nombre_completo'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                $_SESSION['roles'] = $usuario['roles'] ?? 'Usuario';
                $_SESSION['rol_ids'] = $usuario['rol_ids'] ?? '';
                $_SESSION['session_token'] = $session_token;
                $_SESSION['id_sesion'] = $id_sesion;
                $_SESSION['user_ip'] = $ip_address;
                $_SESSION['user_agent'] = $user_agent;
                $_SESSION['last_activity'] = time();
                $_SESSION['requiere_cambio_password'] = (bool)$usuario['requiere_cambio_password'];
                $_SESSION['2fa_enabled'] = (bool)$usuario['autenticacion_dos_factores'];
                // Regenerar ID de sesión para prevenir fijación
                session_regenerate_id(true);
                // Registrar login exitoso
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, id_sesion, accion, descripcion, 
                                                ip_address, navegador, resultado, criticidad) 
                    VALUES (?, ?, 'LOGIN', 'Inicio de sesión exitoso', ?, ?, 'Éxito', 'Baja')
                ");
                $stmt->execute([
                    $usuario['id_usuario'],
                    $id_sesion,
                    $ip_address,
                    substr($user_agent, 0, 200)
                ]);
                sendResponse(true, 'Login exitoso', [
                    'user_id' => $usuario['id_usuario'],
                    'username' => $usuario['username'],
                    'nombre_completo' => $_SESSION['nombre_completo'],
                    'roles' => $usuario['roles'],
                    'session_token' => $session_token,
                    'requiere_cambio_password' => (bool)$usuario['requiere_cambio_password'],
                    '2fa_required' => (bool)$usuario['autenticacion_dos_factores']
                ]);
            } else {
                // Contraseña incorrecta
                $intentos = $usuario['intentos_fallidos'] + 1;
                $max_intentos = 5;
                $stmt = $pdo->prepare("UPDATE usuario SET intentos_fallidos = ? WHERE id_usuario = ?");
                $stmt->execute([$intentos, $usuario['id_usuario']]);
                // Bloquear cuenta si alcanza el máximo
                if ($intentos >= $max_intentos) {
                    $stmt = $pdo->prepare("
                        UPDATE usuario 
                        SET cuenta_bloqueada = 1, fecha_bloqueo = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $stmt->execute([$usuario['id_usuario']]);
                    // Registrar bloqueo
                    $stmt = $pdo->prepare("
                        INSERT INTO log_auditoria (id_usuario, accion, descripcion, 
                                                    ip_address, resultado, criticidad) 
                        VALUES (?, 'LOGIN_FAILED', 'Cuenta bloqueada por múltiples intentos fallidos', ?, 'Bloqueado', 'Crítica')
                    ");
                    $stmt->execute([$usuario['id_usuario'], $ip_address]);
                    
                    sendResponse(false, 'Cuenta bloqueada por múltiples intentos fallidos. Contacte al administrador.', null, 403);
                }
                // Registrar intento fallido
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, descripcion, 
                                                ip_address, resultado, criticidad) 
                    VALUES (?, 'LOGIN_FAILED', ?, ?, 'Fallo', 'Media')
                ");
                $stmt->execute([
                    $usuario['id_usuario'],
                    "Contraseña incorrecta. Intento $intentos de $max_intentos",
                    $ip_address
                ]);
                $intentos_restantes = $max_intentos - $intentos;
                sendResponse(false, "Usuario o contraseña incorrectos. Intentos restantes: $intentos_restantes", null, 401);
            }
        } catch (PDOException $e) {
            error_log("Error en login API: " . $e->getMessage());
            sendResponse(false, 'Error del sistema. Intente nuevamente.', null, 500);
        }
        break;
    // LOGOUT
    case 'logout':
        $ip_address = $_SERVER['REMOTE_ADDR'];
        if (isset($_SESSION['user_id'])) {
            try {
                // Cerrar sesión en BD
                if (isset($_SESSION['id_sesion'])) {
                    $stmt = $pdo->prepare("
                        UPDATE sesion 
                        SET estado_sesion = 'Cerrada', 
                            fecha_cierre = NOW(),
                            duracion_minutos = TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW())
                        WHERE id_sesion = ?
                    ");
                    $stmt->execute([$_SESSION['id_sesion']]);
                }
                // Registrar logout
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, id_sesion, accion, descripcion, 
                                                ip_address, resultado) 
                    VALUES (?, ?, 'LOGOUT', 'Cierre de sesión', ?, 'Éxito')
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['id_sesion'] ?? null,
                    $ip_address
                ]);
            } catch (PDOException $e) {
                error_log("Error al registrar logout: " . $e->getMessage());
            }
        }
        // Destruir sesión
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        sendResponse(true, 'Sesión cerrada exitosamente');
        break;
    // VERIFICAR SESIÓN
    case 'check_session':
        if (isset($_SESSION['user_id'])) {
            // Verificar timeout
            $session_timeout = 3600; // 1 hora
            if (isset($_SESSION['last_activity'])) {
                $inactive_time = time() - $_SESSION['last_activity'];
                if ($inactive_time > $session_timeout) {
                    // Marcar sesión como expirada
                    if (isset($_SESSION['id_sesion'])) {
                        try {
                            $stmt = $pdo->prepare("
                                UPDATE sesion 
                                SET estado_sesion = 'Expirada', 
                                    fecha_cierre = NOW(),
                                    duracion_minutos = TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW())
                                WHERE id_sesion = ?
                            ");
                            $stmt->execute([$_SESSION['id_sesion']]);
                        } catch (PDOException $e) {
                            error_log("Error marcando sesión expirada: " . $e->getMessage());
                        }
                    }
                    session_destroy();
                    sendResponse(false, 'Sesión expirada por inactividad', null, 401);
                }
            }
            // Verificar que la IP no haya cambiado (seguridad adicional)
            if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
                error_log("Cambio de IP detectado para usuario {$_SESSION['user_id']}");
            }
            // Actualizar última actividad
            $_SESSION['last_activity'] = time();
            // Actualizar en BD
            if (isset($_SESSION['id_sesion'])) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE sesion 
                        SET fecha_ultima_actividad = NOW() 
                        WHERE id_sesion = ?
                    ");
                    $stmt->execute([$_SESSION['id_sesion']]);
                } catch (PDOException $e) {
                    error_log("Error actualizando actividad: " . $e->getMessage());
                }
            }
            sendResponse(true, 'Sesión activa', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'nombre_completo' => $_SESSION['nombre_completo'] ?? '',
                'roles' => $_SESSION['roles'] ?? '',
                'requiere_cambio_password' => $_SESSION['requiere_cambio_password'] ?? false,
                'session_expires_in' => $session_timeout - (time() - $_SESSION['last_activity'])
            ]);
        } else {
            sendResponse(false, 'No hay sesión activa', null, 401);
        }
        break;
    // INFORMACIÓN DEL USUARIO
    case 'get_user_info':
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'No autorizado', null, 401);
        }
        try {
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.username, u.email_verificado, u.telefono_verificado,
                        u.autenticacion_dos_factores, u.ultimo_acceso,
                        p.nombres, p.apellidos, p.email, p.telefono, p.foto_perfil,
                        GROUP_CONCAT(DISTINCT r.nombre) as roles
                FROM usuario u
                JOIN persona p ON u.id_persona = p.id_persona
                LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                LEFT JOIN rol r ON ur.id_rol = r.id_rol
                WHERE u.id_usuario = ?
                GROUP BY u.id_usuario
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                // No devolver información sensible
                unset($user['password_hash']);
                unset($user['password_salt']);
                sendResponse(true, 'Usuario encontrado', $user);
            } else {
                sendResponse(false, 'Usuario no encontrado', null, 404);
            }
        } catch (PDOException $e) {
            error_log("Error obteniendo info de usuario: " . $e->getMessage());
            sendResponse(false, 'Error del sistema', null, 500);
        }
        break;
    // CAMBIAR CONTRASEÑA
    case 'change_password':
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'No autorizado', null, 401);
        }
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            sendResponse(false, 'Todos los campos son requeridos', null, 400);
        }
        if ($new_password !== $confirm_password) {
            sendResponse(false, 'Las nuevas contraseñas no coinciden', null, 400);
        }
        // Validar fortaleza de contraseña
        if (strlen($new_password) < 8) {
            sendResponse(false, 'La contraseña debe tener al menos 8 caracteres', null, 400);
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            sendResponse(false, 'La contraseña debe contener al menos una mayúscula', null, 400);
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            sendResponse(false, 'La contraseña debe contener al menos una minúscula', null, 400);
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            sendResponse(false, 'La contraseña debe contener al menos un número', null, 400);
        }
        if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            sendResponse(false, 'La contraseña debe contener al menos un carácter especial', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar contraseña actual
            $stmt = $pdo->prepare("
                SELECT password_hash, password_salt 
                FROM usuario 
                WHERE id_usuario = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            $current_hash = hash('sha256', $current_password . $user['password_salt']);
            if (!hash_equals($user['password_hash'], $current_hash)) {
                $pdo->rollBack();
                sendResponse(false, 'Contraseña actual incorrecta', null, 401);
            }
            // Generar nuevo salt y hash
            $new_salt = bin2hex(random_bytes(32));
            $new_hash = hash('sha256', $new_password . $new_salt);
            // Actualizar contraseña
            $stmt = $pdo->prepare("
                UPDATE usuario 
                SET password_hash = ?, 
                    password_salt = ?, 
                    fecha_ultimo_cambio_password = NOW(),
                    requiere_cambio_password = 0
                WHERE id_usuario = ?
            ");
            $stmt->execute([$new_hash, $new_salt, $_SESSION['user_id']]);
            // Registrar cambio
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, 
                                            registro_id, descripcion, ip_address, resultado) 
                VALUES (?, 'UPDATE', 'usuario', ?, 'Cambio de contraseña', ?, 'Éxito')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR']
            ]);
            // Actualizar sesión
            $_SESSION['requiere_cambio_password'] = false;
            $pdo->commit();
            sendResponse(true, 'Contraseña actualizada exitosamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error cambiando contraseña: " . $e->getMessage());
            sendResponse(false, 'Error del sistema', null, 500);
        }
        break;
    // VERIFICAR PERMISOS
    case 'check_permission':
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'No autorizado', null, 401);
        }
        $modulo = sanitize_input($_GET['modulo'] ?? '');
        $accion = sanitize_input($_GET['accion'] ?? '');
        if (empty($modulo) || empty($accion)) {
            sendResponse(false, 'Módulo y acción son requeridos', null, 400);
        }
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as tiene_permiso
                FROM usuario_rol ur
                JOIN rol_permiso rp ON ur.id_rol = rp.id_rol
                JOIN permiso p ON rp.id_permiso = p.id_permiso
                WHERE ur.id_usuario = ?
                AND ur.estado = 'activo'
                AND p.modulo = ?
                AND p.accion = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $modulo, $accion]);
            $result = $stmt->fetch();
            $tiene = $result['tiene_permiso'] > 0;
            sendResponse(true, $tiene ? 'Permiso concedido' : 'Permiso denegado', [
                'tiene_permiso' => $tiene,
                'modulo' => $modulo,
                'accion' => $accion
            ]);
        } catch (PDOException $e) {
            error_log("Error verificando permisos: " . $e->getMessage());
            sendResponse(false, 'Error del sistema', null, 500);
        }
        break;
    // LISTAR SESIONES ACTIVAS
    case 'list_sessions':
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'No autorizado', null, 401);
        }
        try {
            $stmt = $pdo->prepare("
                SELECT id_sesion, ip_address, navegador, sistema_operativo,
                        fecha_inicio, fecha_ultima_actividad, estado_sesion,
                        (id_sesion = ?) as sesion_actual
                FROM sesion
                WHERE id_usuario = ?
                AND estado_sesion = 'Activa'
                ORDER BY fecha_ultima_actividad DESC
            ");
            $stmt->execute([
                $_SESSION['id_sesion'] ?? 0,
                $_SESSION['user_id']
            ]);
            $sesiones = $stmt->fetchAll();
            sendResponse(true, 'Sesiones obtenidas', $sesiones);
        } catch (PDOException $e) {
            error_log("Error listando sesiones: " . $e->getMessage());
            sendResponse(false, 'Error del sistema', null, 500);
        }
        break;
    // CERRAR SESIÓN ESPECÍFICA
    case 'close_session':
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'No autorizado', null, 401);
        }
        $id_sesion = (int)($_POST['id_sesion'] ?? 0);
        if (empty($id_sesion)) {
            sendResponse(false, 'ID de sesión requerido', null, 400);
        }
        try {
            // Verificar que la sesión pertenezca al usuario
            $stmt = $pdo->prepare("
                UPDATE sesion 
                SET estado_sesion = 'Forzada a cerrar', 
                    fecha_cierre = NOW(),
                    duracion_minutos = TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW())
                WHERE id_sesion = ? 
                AND id_usuario = ?
            ");
            $stmt->execute([$id_sesion, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                sendResponse(true, 'Sesión cerrada exitosamente');
            } else {
                sendResponse(false, 'Sesión no encontrada', null, 404);
            }
        } catch (PDOException $e) {
            error_log("Error cerrando sesión: " . $e->getMessage());
            sendResponse(false, 'Error del sistema', null, 500);
        }
        break;
    // ACCIÓN NO VÁLIDA
    default:
        sendResponse(false, 'Acción no válida', null, 400);
}