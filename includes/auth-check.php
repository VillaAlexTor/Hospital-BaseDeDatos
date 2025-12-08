<?php
/**
 * includes/auth-check.php
 * Auth Check - Verificación de Autenticación
 * Este archivo debe incluirse en todas las páginas protegidas
 * NOTA: Asume que config.php ya ha sido incluido y la sesión está iniciada
 */

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // Usuario no autenticado - redirigir al login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Verificar timeout de sesión
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    
    if ($inactive_time > SESSION_TIMEOUT) {
        // Sesión expirada
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error_message'] = 'Su sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.';
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

// Actualizar tiempo de última actividad
$_SESSION['last_activity'] = time();

// Verificar que la sesión no haya sido hijacked
if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    // IP diferente - posible hijacking
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['error_message'] = 'Se detectó actividad sospechosa. Por favor, inicie sesión nuevamente.';
    
    // Registrar en log de auditoría
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS
        );
        
        $stmt = $pdo->prepare("INSERT INTO log_auditoria 
            (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
            VALUES (?, 'LOGIN', 'Posible intento de hijacking de sesión', ?, 'Bloqueado', 'Crítica')");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (PDOException $e) {
        // Silenciar error para no exponer información
    }
    
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Verificar User Agent (opcional pero recomendado)
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // User agent diferente
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['error_message'] = 'Se detectó un cambio en su navegador. Por favor, inicie sesión nuevamente.';
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Verificar que el usuario no esté bloqueado o inactivo
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    
    $stmt = $pdo->prepare("SELECT estado, cuenta_bloqueada FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        // Usuario no existe
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error_message'] = 'Usuario no válido.';
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
    
    if ($usuario['estado'] !== 'activo' || $usuario['cuenta_bloqueada'] == 1) {
        // Usuario inactivo o bloqueado
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error_message'] = 'Su cuenta ha sido desactivada o bloqueada. Contacte al administrador.';
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
    
} catch (PDOException $e) {
    // En caso de error de BD, permitir continuar pero registrar
    error_log("Error en auth-check: " . $e->getMessage());
}

// Función helper para verificar permisos específicos
function verificar_permiso($modulo, $accion) {
    global $pdo;
    
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
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['tiene_permiso'] > 0;
        
    } catch (PDOException $e) {
        error_log("Error verificando permiso: " . $e->getMessage());
        return false;
    }
}

// Función helper para verificar si es administrador
function es_administrador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
}

// Función helper para verificar si es médico
function es_medico() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Médico';
}

// Función helper para verificar si es paciente
function es_paciente() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente';
}

// Regenerar ID de sesión periódicamente (cada 30 minutos)
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Sesión iniciada hace más de 30 minutos
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}