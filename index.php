<?php
/** 
 * index.php 
*/
// Iniciar sesión de manera segura
session_start();
// Si el usuario ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Verificar que la sesión sea válida
    if (isset($_SESSION['last_activity'])) {
        $session_timeout = 3600; // 1 hora
        if (time() - $_SESSION['last_activity'] > $session_timeout) {
            // Sesión expirada
            session_destroy();
            header('Location: login.php?timeout=1');
            exit;
        }
    }
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
    // Redirigir al dashboard correspondiente según el rol
    header('Location: modules/dashboard/index.php');
    exit;
} else {
    // Usuario no autenticado, redirigir al login
    header('Location: login.php');
    exit;
}
?>
<!-- 
    NOTA: Este archivo index.php es solo un redirector.
    La página de login y el dashboard son las principales.
-->