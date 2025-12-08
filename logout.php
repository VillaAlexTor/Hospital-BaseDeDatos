<?php
/**
 * Logout - Cerrar sesión
 */
require_once 'includes/config.php';
// Registrar logout en auditoría si hay usuario activo
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_auditoria 
            (id_usuario, accion, descripcion, ip_address, resultado) 
            VALUES (?, 'LOGOUT', 'Cierre de sesión', ?, 'Éxito')
        ");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        error_log("Error al registrar logout: " . $e->getMessage());
    }
}
// Destruir todas las variables de sesión
$_SESSION = array();
// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}
// Destruir la sesión
session_destroy();
// Iniciar nueva sesión para el mensaje
session_start();
$_SESSION['success_message'] = 'Sesión cerrada exitosamente';
// Redirigir al login
header('Location: ' . SITE_URL . '/login.php');
exit();