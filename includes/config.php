<?php
// ==========================================
// CARGAR CONFIGURACIÓN DESDE .env
// ==========================================
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/roles.php';
require_once dirname(__DIR__) . '/config/error-handler.php';
require_once dirname(__DIR__) . '/includes/date-helper.php';

// ==========================================
// CONFIGURACIÓN DE SESIÓN SEGURA
// ==========================================
// Solo configurar si la sesión NO está activa
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // 0 porque XAMPP usa HTTP por defecto
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// ==========================================
// CONFIGURACIÓN DESDE .env
// ==========================================
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost:3307'));
define('DB_NAME', EnvLoader::get('DB_NAME', 'hospital_db'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Llave de cifrado
define('ENCRYPTION_KEY', EnvLoader::get('ENCRYPTION_KEY', 'default-insecure-key!'));
define('ENCRYPTION_METHOD', EnvLoader::get('ENCRYPTION_METHOD', 'AES-256-CBC'));

// Configuración del sistema
define('SITE_NAME', EnvLoader::get('SITE_NAME', 'Sistema Hospitalario'));
define('SITE_URL', EnvLoader::get('SITE_URL', 'http://localhost/hospital'));
define('SESSION_TIMEOUT', (int)EnvLoader::get('SESSION_TIMEOUT', 3600));
define('MAX_LOGIN_ATTEMPTS', (int)EnvLoader::get('MAX_LOGIN_ATTEMPTS', 3));
define('LOCKOUT_TIME', (int)EnvLoader::get('LOCKOUT_TIME', 900));
define('APP_ENV', EnvLoader::get('APP_ENV', 'development'));

// Zona horaria
date_default_timezone_set(EnvLoader::get('TIMEZONE', 'America/La_Paz'));

// Inicializar error handler
ErrorHandler::init(APP_ENV === 'development');

// Conexión a la base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// ==========================================
// FUNCIONES DE SEGURIDAD Y ENCRIPTACIÓN
// ==========================================

/**
 * Cifra datos usando AES-256-CBC
 */
function encrypt_data($data) {
    if (empty($data)) return '';
    // 1. Generar IV único (Vector de Inicialización)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD)); // AES-256-CBC = 16 bytes
    // 2. Cifrar con AES-256-CBC
    $encrypted = openssl_encrypt(
        $data,                  // Datos originales
        ENCRYPTION_METHOD,      // 'AES-256-CBC'
        ENCRYPTION_KEY,         // Clave de 32 bytes
        0,                      // Sin codificación adicional
        $iv                     // Vector de inicialización
    );
    // 3. Combinar datos + IV y codificar Base64
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Descifra datos cifrados con AES-256-CBC
 */
function decrypt_data($data) {
    if (empty($data)) return '';
    try {
        // 1. Decodificar Base64
        $decoded = base64_decode($data);
        // 2. Separar datos cifrados e IV
        if ($decoded === false) return '';
        $parts = explode('::', $decoded, 2);
        if (count($parts) < 2) return '';
        list($encrypted_data, $iv) = $parts;
        // 3. Descifrar
        return openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Sanitiza entrada de usuario
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Alias para compatibilidad
 */
function obtener_csrf_token() {
    return generate_csrf_token();
}

// ==========================================
// FUNCIONES DE CONTROL DE ACCESO
// ==========================================

/**
 * Verifica permiso para una acción específica
 */
function tiene_permiso($modulo, $accion) {
    global $PERMISSIONS;
    
    // Si no hay sesión, denegar
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol'])) {
        return false;
    }
    
    // Administrador tiene TODOS los permisos
    if ($_SESSION['rol'] === ROLE_ADMIN || $_SESSION['rol'] === 'Administrador') {
        return true;
    }
    
    $rol = $_SESSION['rol'];
    
    // Mapear acciones de español a inglés (como están en roles.php)
    $mapa_acciones = [
        'leer'       => 'read',
        'crear'      => 'create',
        'actualizar' => 'update',
        'eliminar'   => 'delete',
        'listar'     => 'list',
        'read'       => 'read',    // Permitir también en inglés
        'create'     => 'create',
        'update'     => 'update',
        'delete'     => 'delete',
        'list'       => 'list'
    ];
    
    // Convertir acción a formato correcto
    $accion_mapeada = $mapa_acciones[$accion] ?? $accion;
    
    // Verificar en el array de permisos
    if (isset($PERMISSIONS[$rol][$modulo])) {
        return in_array($accion_mapeada, $PERMISSIONS[$rol][$modulo]);
    }
    
    return false;
}

/**
 * Verifica autenticación
 */
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

/**
 * Verifica rol específico
 */
function require_rol($roles_permitidos) {
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], (array)$roles_permitidos)) {
        header('Location: ' . SITE_URL . '/modules/dashboard/index.php?error=permisos_insuficientes');
        exit();
    }
}

/**
 * Verifica si el usuario tiene alguno de los roles especificados
 */
function has_any_role($roles) {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    
    // Administrador tiene todos los permisos
    if ($_SESSION['rol'] === ROLE_ADMIN) {
        return true;
    }
    
    // Convertir a array si es string
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['rol'], $roles);
}

/**
 * Verifica si el usuario tiene un rol específico
 */
function has_role($role) {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === $role;
}

/**
 * Registra una acción en el log de auditoría
 */
function log_action(
    $accion, 
    $tabla = null, 
    $registro_id = null, 
    $descripcion = '', 
    $valores_anteriores = null, 
    $valores_nuevos = null,
    $resultado = 'Éxito', 
    $criticidad = 'Baja'
) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_auditoria 
            (id_usuario, id_sesion, accion, tabla_afectada, registro_id, descripcion, 
             valores_anteriores, valores_nuevos, ip_address, navegador, resultado, criticidad)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['id_sesion'] ?? null,
            strtoupper($accion),
            $tabla,
            $registro_id,
            $descripcion,
            $valores_anteriores ? json_encode($valores_anteriores, JSON_UNESCAPED_UNICODE) : null,
            $valores_nuevos ? json_encode($valores_nuevos, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 200),
            $resultado,
            $criticidad
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error registrando auditoría: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el ID del médico asociado al usuario actual
 */
function obtener_id_medico() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.id_medico 
            FROM medico m 
            INNER JOIN personal p ON m.id_medico = p.id_personal
            INNER JOIN persona per ON p.id_personal = per.id_persona
            INNER JOIN usuario u ON per.id_persona = u.id_persona
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al obtener ID médico: " . $e->getMessage());
        return null;
    }
}

// ==========================================
// FUNCIONES DE CONEXIÓN Y BASE DE DATOS
// ==========================================

/**
 * Obtiene conexión PDO
 */
function getDatabaseConnection() {
    global $pdo;
    return $pdo;
}

/**
 * Conexión MySQLi
 */
function getMySQLiConnection() {
    static $mysqli = null;
    
    if ($mysqli === null) {
        $host_parts = explode(':', DB_HOST);
        $host = $host_parts[0];
        $port = isset($host_parts[1]) ? (int)$host_parts[1] : 3306;
        
        $mysqli = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port);
        if ($mysqli->connect_error) {
            die("Error de conexión MySQLi: " . $mysqli->connect_error);
        }
        $mysqli->set_charset("utf8mb4");
    }
    
    return $mysqli;
}

/**
 * Ejecuta consulta preparada
 */
function executeQuery($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Error en consulta: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtiene un solo registro
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Obtiene múltiples registros
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Obtiene conexión para compatibilidad
 */
function get_db_connection() {
    global $pdo;
    return $pdo;
}

/**
 * Clave de cifrado para MySQL
 */
$clave_cifrado = ENCRYPTION_KEY;