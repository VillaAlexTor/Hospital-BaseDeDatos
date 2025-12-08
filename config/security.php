<?php
/**
 * config/security.php
 * Clase Security
 * Maneja funciones de seguridad del sistema
 */

class Security {
    
    private static $instance = null;
    private $encryptionKey;
    private $encryptionMethod;
    
    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {
        $this->encryptionKey = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-change-this!';
        $this->encryptionMethod = defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : 'AES-256-CBC';
    }
    
    /**
     * Obtener instancia única (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // ==========================================
    // CIFRADO Y DESCIFRADO
    // ==========================================
    
    /**
     * Cifrar datos
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->encryptionMethod));
            $encrypted = openssl_encrypt($data, $this->encryptionMethod, $this->encryptionKey, 0, $iv);
            return base64_encode($encrypted . '::' . $iv);
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descifrar datos
     */
    public function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            $parts = explode('::', base64_decode($data), 2);
            if (count($parts) !== 2) {
                return '';
            }
            
            list($encrypted_data, $iv) = $parts;
            return openssl_decrypt($encrypted_data, $this->encryptionMethod, $this->encryptionKey, 0, $iv);
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return '';
        }
    }
    
    // ==========================================
    // HASHING Y CONTRASEÑAS
    // ==========================================
    
    /**
     * Generar hash de contraseña
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verificar contraseña
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar hash SHA-256 con salt (compatibilidad con sistema existente)
     */
    public function hashPasswordWithSalt($password, $salt = null) {
        if ($salt === null) {
            $salt = bin2hex(random_bytes(16));
        }
        
        $hash = hash('sha256', $password . $salt);
        
        return [
            'hash' => $hash,
            'salt' => $salt
        ];
    }
    
    /**
     * Verificar contraseña con salt
     */
    public function verifyPasswordWithSalt($password, $hash, $salt) {
        $computedHash = hash('sha256', $password . $salt);
        return hash_equals($hash, $computedHash);
    }
    
    /**
     * Validar fortaleza de contraseña
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "La contraseña debe tener al menos " . PASSWORD_MIN_LENGTH . " caracteres";
        }
        
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "La contraseña debe contener al menos una letra mayúscula";
        }
        
        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "La contraseña debe contener al menos una letra minúscula";
        }
        
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = "La contraseña debe contener al menos un número";
        }
        
        if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "La contraseña debe contener al menos un carácter especial";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // ==========================================
    // SANITIZACIÓN Y VALIDACIÓN
    // ==========================================
    
    /**
     * Sanitizar entrada de texto
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        $data = trim($data);
        $data = strip_tags($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        
        return $data;
    }
    
    /**
     * Validar email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar URL
     */
    public function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validar teléfono
     */
    public function validatePhone($phone) {
        return preg_match(REGEX_PHONE, $phone) === 1;
    }
    
    /**
     * Validar username
     */
    public function validateUsername($username) {
        return preg_match(REGEX_USERNAME, $username) === 1;
    }
    
    /**
     * Limpiar string para SQL (alternativa)
     */
    public function cleanString($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
    
    // ==========================================
    // TOKENS Y CSRF
    // ==========================================
    
    /**
     * Generar token CSRF
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validar token CSRF
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Verificar expiración (1 hora)
        if (isset($_SESSION['csrf_token_time'])) {
            if (time() - $_SESSION['csrf_token_time'] > 3600) {
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generar token aleatorio
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generar código de verificación
     */
    public function generateVerificationCode($length = 6) {
        $characters = '0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
    
    // ==========================================
    // PROTECCIÓN XSS
    // ==========================================
    
    /**
     * Prevenir XSS
     */
    public function preventXSS($data) {
        if (is_array($data)) {
            return array_map([$this, 'preventXSS'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Limpiar HTML permitiendo algunas etiquetas
     */
    public function cleanHTML($html, $allowedTags = '<p><br><b><i><u><strong><em>') {
        return strip_tags($html, $allowedTags);
    }
    
    // ==========================================
    // PROTECCIÓN SQL INJECTION
    // ==========================================
    
    /**
     * Prevenir SQL Injection en strings
     */
    public function preventSQLInjection($string) {
        return addslashes($string);
    }
    
    // ==========================================
    // CONTROL DE SESIÓN
    // ==========================================
    
    /**
     * Inicializar sesión segura
     */
    public function initSecureSession() {
        // Configuración de sesión segura
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Cambiar a 1 si usa HTTPS
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerar ID de sesión periódicamente
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > SESSION_REGENERATE_INTERVAL) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Verificar sesión activa
     */
    public function checkSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verificar timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                return false;
            }
        }
        
        // Actualizar última actividad
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Destruir sesión de forma segura
     */
    public function destroySession() {
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        
        session_destroy();
    }
    
    // ==========================================
    // RATE LIMITING
    // ==========================================
    
    /**
     * Verificar rate limit
     */
    public function checkRateLimit($identifier, $limit = 60, $period = 60) {
        $key = 'rate_limit_' . $identifier;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        $elapsed = time() - $_SESSION[$key]['start_time'];
        
        if ($elapsed > $period) {
            // Resetear contador
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        if ($_SESSION[$key]['count'] >= $limit) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
    
    // ==========================================
    // VALIDACIÓN DE ARCHIVOS
    // ==========================================
    
    /**
     * Validar archivo subido
     */
    public function validateUploadedFile($file, $allowedTypes = [], $maxSize = MAX_UPLOAD_SIZE) {
        $errors = [];
        
        // Verificar si hay error en la subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
        }
        
        // Verificar tamaño
        if ($file['size'] > $maxSize) {
            $errors[] = "El archivo excede el tamaño máximo permitido (" . ($maxSize / 1024 / 1024) . "MB)";
        }
        
        // Verificar tipo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!empty($allowedTypes) && !in_array($extension, $allowedTypes)) {
            $errors[] = "Tipo de archivo no permitido. Permitidos: " . implode(', ', $allowedTypes);
        }
        
        // Verificar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (isset($allowedMimes[$extension]) && $mimeType !== $allowedMimes[$extension]) {
            $errors[] = "El tipo MIME del archivo no coincide con la extensión";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida'
        ];
        
        return $errors[$errorCode] ?? 'Error desconocido al subir archivo';
    }
    
    /**
     * Generar nombre de archivo seguro
     */
    public function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16));
        return $filename . '.' . $extension;
    }
    
    // ==========================================
    // LOGGING DE SEGURIDAD
    // ==========================================
    
    /**
     * Log de evento de seguridad
     */
    public function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logFile = LOGS_PATH . '/security_' . date('Y-m-d') . '.log';
        
        if (!is_dir(LOGS_PATH)) {
            mkdir(LOGS_PATH, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}