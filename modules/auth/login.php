<?php
/**
 * modules/auth/login.php
 * Página de login alternativa (puede ser la misma que /login.php)
 * Esta versión está en el módulo auth
 */

$pageTitle = "Iniciar Sesión";
require_once '../../includes/config.php';
require_once '../../includes/security-headers.php';
require_once '../../config/rate-limiter.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
$success = '';

// Mostrar mensaje de éxito si viene del registro
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar rate limiting
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if ($rate_limiter && $rate_limiter->isLimited($remote_ip)) {
        $error = 'Demasiados intentos. Intente más tarde.';
        ErrorHandler::logSecure('login_rate_limit', null, "IP: $remote_ip", 'warning');
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor ingrese usuario y contraseña';
        } else {
            try {
                // Buscar usuario
                $stmt = $pdo->prepare("
                    SELECT u.id_usuario, u.username, u.password_hash, u.password_salt,
                           u.cuenta_bloqueada, u.estado, u.intentos_fallidos,
                           p.id_persona, p.nombres, p.apellidos, p.email,
                           r.nombre as rol
                    FROM usuario u
                    JOIN persona p ON u.id_persona = p.id_persona
                    LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
                    LEFT JOIN rol r ON ur.id_rol = r.id_rol
                    WHERE u.username = ?
                    LIMIT 1
                ");
                $stmt->execute([$username]);
                $usuario = $stmt->fetch();
                
                if (!$usuario) {
                    $error = 'Usuario o contraseña incorrectos';
                    ErrorHandler::logSecure('login_failed', null, "Usuario no encontrado: $username", 'warning');
                } elseif ($usuario['cuenta_bloqueada']) {
                    $error = 'Cuenta bloqueada. Contacte al administrador.';
                    ErrorHandler::logSecure('login_blocked_account', null, "Usuario: $username", 'warning');
                } elseif ($usuario['estado'] !== 'activo') {
                    $error = 'Cuenta inactiva. Contacte al administrador.';
                    ErrorHandler::logSecure('login_inactive_account', null, "Usuario: $username", 'info');
                } else {
                    // Verificar contraseña
                    $password_hash = hash('sha256', $password . $usuario['password_salt']);
                    
                    if ($password_hash === $usuario['password_hash']) {
                        // Login exitoso
                        
                        // Resetear intentos fallidos
                        $stmt = $pdo->prepare("UPDATE usuario SET intentos_fallidos = 0, ultimo_acceso = NOW(), ultima_ip = ? WHERE id_usuario = ?");
                        $stmt->execute([$_SERVER['REMOTE_ADDR'], $usuario['id_usuario']]);
                        
                        // Establecer variables de sesión
                        $_SESSION['user_id'] = $usuario['id_usuario'];
                        $_SESSION['username'] = $usuario['username'];
                        $_SESSION['nombre_completo'] = decrypt_data($usuario['nombres']) . ' ' . decrypt_data($usuario['apellidos']);
                        $_SESSION['rol'] = $usuario['rol'] ?? 'Usuario';
                        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                        $_SESSION['last_activity'] = time();
                        
                        // Registrar login en auditoría
                        $stmt = $pdo->prepare("INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado) VALUES (?, 'LOGIN', 'Inicio de sesión exitoso', ?, 'Éxito')");
                        $stmt->execute([$usuario['id_usuario'], $_SERVER['REMOTE_ADDR']]);
                        ErrorHandler::logSecure('login_success', $usuario['id_usuario'], "Usuario: $username desde IP: {$_SERVER['REMOTE_ADDR']}", 'info');
                        
                        // Redirigir al dashboard
                        header('Location: ../dashboard/index.php');
                        exit();
                    } else {
                        // Contraseña incorrecta
                        $intentos = $usuario['intentos_fallidos'] + 1;
                        
                        // Actualizar intentos fallidos
                        $stmt = $pdo->prepare("UPDATE usuario SET intentos_fallidos = ? WHERE id_usuario = ?");
                        $stmt->execute([$intentos, $usuario['id_usuario']]);
                        
                        // Bloquear cuenta si alcanza el máximo
                        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
                            $stmt = $pdo->prepare("UPDATE usuario SET cuenta_bloqueada = 1, fecha_bloqueo = NOW() WHERE id_usuario = ?");
                            $stmt->execute([$usuario['id_usuario']]);
                            $error = 'Cuenta bloqueada por múltiples intentos fallidos. Contacte al administrador.';
                        } else {
                            $error = 'Usuario o contraseña incorrectos. Intentos restantes: ' . (MAX_LOGIN_ATTEMPTS - $intentos);
                        }
                        
                        // Registrar intento fallido
                        $stmt = $pdo->prepare("INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado) VALUES (?, 'LOGIN_FAILED', 'Intento de login fallido', ?, 'Fallo')");
                        $stmt->execute([$usuario['id_usuario'], $_SERVER['REMOTE_ADDR']]);
                    }
                }
            } catch (PDOException $e) {
                error_log("Error en login: " . $e->getMessage());
                $error = 'Error del sistema. Por favor intente más tarde.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Sistema Hospitalario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
        }
        .input-with-icon {
            padding-left: 2.75rem;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="login-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
        <!-- Logo y Título -->
        <div class="text-center mb-8">
            <i class="fas fa-hospital text-6xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">Sistema Hospitalario</h1>
            <p class="text-gray-600 mt-2">Gestión Integral de Salud</p>
        </div>
        
        <!-- Alertas -->
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Formulario de Login -->
        <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Usuario -->
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Usuario
                </label>
                <div class="relative">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" 
                           name="username" 
                           required 
                           autofocus
                           class="input-with-icon w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ingrese su usuario"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Contraseña -->
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Contraseña
                </label>
                <div class="relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" 
                           name="password" 
                           id="password"
                           required
                           class="input-with-icon w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ingrese su contraseña">
                    <button type="button" 
                            onclick="togglePassword()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-eye" id="toggle-icon"></i>
                    </button>
                </div>
            </div>
            
            <!-- Recordar sesión y Olvidó contraseña -->
            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" 
                           name="remember" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Recordar sesión</span>
                </label>
                <a href="recovery.php" class="text-sm text-blue-600 hover:underline">
                    ¿Olvidó su contraseña?
                </a>
            </div>
            
            <!-- Botón de Login -->
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
                <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
            </button>
        </form>
        
        <!-- Separador -->
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">O</span>
            </div>
        </div>
        
        <!-- Registrarse -->
        <div class="text-center">
            <p class="text-gray-600 mb-3">¿No tienes cuenta?</p>
            <a href="register.php" 
               class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
                <i class="fas fa-user-plus mr-2"></i>Crear Cuenta Nueva
            </a>
        </div>
        
        <!-- Info de prueba (solo en desarrollo) -->
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h4 class="font-bold text-yellow-800 mb-2">
                <i class="fas fa-info-circle mr-2"></i>Datos de Prueba
            </h4>
            <div class="text-sm text-yellow-700 space-y-1">
                <p><strong>Usuario:</strong> admin</p>
                <p><strong>Contraseña:</strong> admin123</p>
                <p class="text-xs mt-2 text-yellow-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Solo visible en modo desarrollo
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p class="mb-2">
                <i class="fas fa-shield-alt text-green-600 mr-1"></i>
                Conexión segura y cifrada
            </p>
            <p>© <?php echo date('Y'); ?> Sistema Hospitalario</p>
            <p class="mt-2">
                <a href="#" class="text-blue-600 hover:underline">Términos de Servicio</a> | 
                <a href="#" class="text-blue-600 hover:underline">Política de Privacidad</a>
            </p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Prevenir múltiples envíos
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Iniciando sesión...';
            
            // Reactivar después de 3 segundos por si hay error
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión';
            }, 3000);
        });
        
        // Auto-focus en campo de usuario
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });
    </script>
</body>
</html>