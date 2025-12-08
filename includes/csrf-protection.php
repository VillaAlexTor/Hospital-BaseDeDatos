<?php
/**
 * CSRF Protection - Protección contra Cross-Site Request Forgery
 * Este archivo proporciona aliases en español para funciones CSRF definidas en config.php
 */

// Las funciones CSRF se definen en includes/config.php
// Este archivo proporciona aliases en español para compatibilidad

/**
 * Alias en español para generate_csrf_token()
 * @return string El token generado
 */
function generar_csrf_token() {
    return generate_csrf_token();
}

/**
 * Alias en español para verify_csrf_token()
 * @param string $token El token a validar
 * @return bool True si el token es válido
 */
function validar_csrf_token($token) {
    return verify_csrf_token($token);
}

/**
 * Genera el campo hidden HTML con el token CSRF
 * @return string HTML del campo input hidden
 */
function campo_csrf() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica el token CSRF desde $_POST
 * Si el token es inválido, termina la ejecución
 */
function verificar_csrf_post() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            // Token CSRF inválido
            
            // Registrar intento en auditoría
            try {
                global $pdo;
                
                $stmt = $pdo->prepare("INSERT INTO log_auditoria 
                    (id_usuario, accion, descripcion, ip_address, resultado, criticidad) 
                    VALUES (?, 'CSRF_ATTACK', 'Token CSRF inválido o ausente', ?, 'Bloqueado', 'Crítica')");
                $stmt->execute([
                    $_SESSION['user_id'] ?? null,
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (PDOException $e) {
                error_log("Error registrando intento CSRF: " . $e->getMessage());
            }
            
            // Mostrar error y terminar
            http_response_code(403);
            die('Error de seguridad: Token CSRF inválido. Esta acción ha sido registrada.');
        }
    }
}

/**
 * Genera meta tag para AJAX requests
 * @return string HTML del meta tag
 */
function meta_csrf() {
    $token = generar_csrf_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
?>