<?php
/**
 * modules/auth/recovery.php
 * Recuperación de contraseña
 */

$pageTitle = "Recuperar Contraseña";
require_once '../../includes/config.php';
require_once '../../includes/security-headers.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
$success = '';
$step = 1; // 1: Solicitar email, 2: Código enviado, 3: Nueva contraseña

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        
        // PASO 1: Solicitar recuperación
        if (isset($_POST['action']) && $_POST['action'] === 'request') {
            $email = sanitize_input($_POST['email'] ?? '');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido';
            } else {
                try {
                    // Buscar usuario por email
                    $stmt = $pdo->prepare("
                        SELECT u.id_usuario, u.username, p.nombres, p.apellidos
                        FROM usuario u
                        JOIN persona p ON u.id_persona = p.id_persona
                        WHERE p.email = ?
                        AND u.estado = 'activo'
                        LIMIT 1
                    ");
                    $stmt->execute([encrypt_data($email)]);
                    $usuario = $stmt->fetch();
                    
                    if ($usuario) {
                        // Generar token de recuperación
                        $token = bin2hex(random_bytes(32));
                        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        // Guardar token
                        $stmt = $pdo->prepare("
                            UPDATE usuario 
                            SET token_recuperacion = ?, 
                                token_expiracion = ?
                            WHERE id_usuario = ?
                        ");
                        $stmt->execute([$token, $expiracion, $usuario['id_usuario']]);
                        
                        // En un sistema real, aquí se enviaría el email
                        // Por ahora, mostrar el token en pantalla (solo desarrollo)
                        
                        $success = "Se ha enviado un enlace de recuperación a tu email.<br><br>";
                        
                        if (defined('DEBUG_MODE') && DEBUG_MODE) {
                            $link = SITE_URL . "/modules/auth/recovery.php?token=" . $token;
                            $success .= "<strong>Modo Desarrollo:</strong><br>";
                            $success .= "Token: <code>$token</code><br>";
                            $success .= "<a href='$link' class='text-blue-600 hover:underline'>Click aquí para recuperar</a>";
                        }
                        
                        // Registrar en auditoría
                        $stmt = $pdo->prepare("
                            INSERT INTO log_auditoria 
                            (id_usuario, accion, descripcion, ip_address, resultado)
                            VALUES (?, 'RECOVERY', 'Solicitud de recuperación de contraseña', ?, 'Éxito')
                        ");
                        $stmt->execute([$usuario['id_usuario'], $_SERVER['REMOTE_ADDR']]);
                        
                        $step = 2;
                    } else {
                        // Por seguridad, mostrar el mismo mensaje aunque no exista
                        $success = "Si el email existe en nuestro sistema, recibirás un enlace de recuperación.";
                        $step = 2;
                    }
                    
                } catch (PDOException $e) {
                    error_log("Error en recovery: " . $e->getMessage());
                    $error = 'Error del sistema. Intente más tarde.';
                }
            }
        }
        
        // PASO 2: Resetear contraseña
        elseif (isset($_POST['action']) && $_POST['action'] === 'reset') {
            $token = sanitize_input($_POST['token'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validaciones
            if (empty($token) || empty($new_password) || empty($confirm_password)) {
                $error = 'Todos los campos son requeridos';
            } elseif (strlen($new_password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Las contraseñas no coinciden';
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error = 'La contraseña debe contener al menos una mayúscula';
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error = 'La contraseña debe contener al menos una minúscula';
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $error = 'La contraseña debe contener al menos un número';
            } else {
                try {
                    // Verificar token
                    $stmt = $pdo->prepare("
                        SELECT id_usuario, username 
                        FROM usuario 
                        WHERE token_recuperacion = ?
                        AND token_expiracion > NOW()
                        AND estado = 'activo'
                    ");
                    $stmt->execute([$token]);
                    $usuario = $stmt->fetch();
                    
                    if ($usuario) {
                        // Generar nuevo salt y hash
                        $salt = bin2hex(random_bytes(16));
                        $password_hash = hash('sha256', $new_password . $salt);
                        
                        // Actualizar contraseña
                        $stmt = $pdo->prepare("
                            UPDATE usuario 
                            SET password_hash = ?,
                                password_salt = ?,
                                token_recuperacion = NULL,
                                token_expiracion = NULL,
                                fecha_ultimo_cambio_password = NOW(),
                                intentos_fallidos = 0,
                                cuenta_bloqueada = 0
                            WHERE id_usuario = ?
                        ");
                        $stmt->execute([$password_hash, $salt, $usuario['id_usuario']]);
                        
                        // Registrar en auditoría
                        $stmt = $pdo->prepare("
                            INSERT INTO log_auditoria 
                            (id_usuario, accion, descripcion, ip_address, resultado)
                            VALUES (?, 'UPDATE', 'Contraseña restablecida exitosamente', ?, 'Éxito')
                        ");
                        $stmt->execute([$usuario['id_usuario'], $_SERVER['REMOTE_ADDR']]);
                        
                        $success = "¡Contraseña restablecida exitosamente!<br>Ya puedes iniciar sesión con tu nueva contraseña.";
                        
                        // Redirigir al login después de 3 segundos
                        header("refresh:3;url=login.php");
                        
                    } else {
                        $error = 'Token inválido o expirado. Solicita una nueva recuperación.';
                    }
                    
                } catch (PDOException $e) {
                    error_log("Error reseteando password: " . $e->getMessage());
                    $error = 'Error del sistema. Intente más tarde.';
                }
            }
        }
    }
}

// Si viene con token en URL, mostrar formulario de reset
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);
    
    // Verificar que el token sea válido
    try {
        $stmt = $pdo->prepare("
            SELECT username 
            FROM usuario 
            WHERE token_recuperacion = ?
            AND token_expiracion > NOW()
        ");
        $stmt->execute([$token]);
        
        if ($stmt->fetch()) {
            $step = 3; // Mostrar formulario de nueva contraseña
        } else {
            $error = 'Token inválido o expirado';
            $step = 1;
        }
    } catch (PDOException $e) {
        error_log("Error verificando token: " . $e->getMessage());
        $error = 'Error del sistema';
        $step = 1;
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
        .recovery-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="recovery-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
        <!-- Logo y Título -->
        <div class="text-center mb-8">
            <i class="fas fa-key text-6xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">Recuperar Contraseña</h1>
            <p class="text-gray-600 mt-2">
                <?php 
                    if ($step === 1) echo 'Ingresa tu email para continuar';
                    elseif ($step === 2) echo 'Revisa tu correo electrónico';
                    elseif ($step === 3) echo 'Ingresa tu nueva contraseña';
                ?>
            </p>
        </div>
        
        <!-- Alertas -->
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
            <div class="flex items-start">
                <i class="fas fa-check-circle mr-2 mt-1"></i>
                <span><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
        <!-- PASO 1: Solicitar recuperación -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="request">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Email Registrado
                </label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="email" 
                           name="email" 
                           required
                           autofocus
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="ejemplo@email.com">
                </div>
                <p class="text-sm text-gray-600 mt-2">
                    Te enviaremos un enlace para restablecer tu contraseña
                </p>
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
                <i class="fas fa-paper-plane mr-2"></i>Enviar Enlace
            </button>
        </form>
        
        <?php elseif ($step === 2): ?>
        <!-- PASO 2: Email enviado -->
        <div class="text-center py-8">
            <i class="fas fa-envelope-open-text text-6xl text-green-600 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Email Enviado</h3>
            <p class="text-gray-600 mb-6">
                Revisa tu bandeja de entrada y sigue las instrucciones para restablecer tu contraseña.
            </p>
            <p class="text-sm text-gray-500">
                Si no recibes el email en los próximos minutos, revisa tu carpeta de spam.
            </p>
        </div>
        
        <?php elseif ($step === 3): ?>
        <!-- PASO 3: Nueva contraseña -->
        <form method="POST" action="" id="resetForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Nueva Contraseña
                </label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="password" 
                           name="new_password" 
                           id="new_password"
                           required
                           minlength="8"
                           class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Mínimo 8 caracteres">
                    <button type="button" 
                            onclick="togglePassword('new_password', 'icon1')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600">
                        <i class="fas fa-eye" id="icon1"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Confirmar Contraseña
                </label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password"
                           required
                           minlength="8"
                           class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Repite la contraseña">
                    <button type="button" 
                            onclick="togglePassword('confirm_password', 'icon2')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600">
                        <i class="fas fa-eye" id="icon2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Requisitos -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="font-bold text-blue-800 mb-2 text-sm">Requisitos:</h4>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li id="req-length"><i class="fas fa-circle mr-2" style="font-size: 4px;"></i>Mínimo 8 caracteres</li>
                    <li id="req-upper"><i class="fas fa-circle mr-2" style="font-size: 4px;"></i>Una mayúscula</li>
                    <li id="req-lower"><i class="fas fa-circle mr-2" style="font-size: 4px;"></i>Una minúscula</li>
                    <li id="req-number"><i class="fas fa-circle mr-2" style="font-size: 4px;"></i>Un número</li>
                    <li id="req-match"><i class="fas fa-circle mr-2" style="font-size: 4px;"></i>Las contraseñas coinciden</li>
                </ul>
            </div>
            
            <button type="submit" 
                    id="submitBtn"
                    disabled
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition transform hover:scale-105 shadow-lg disabled:bg-gray-400 disabled:cursor-not-allowed disabled:transform-none">
                <i class="fas fa-check mr-2"></i>Restablecer Contraseña
            </button>
        </form>
        <?php endif; ?>
        
        <!-- Volver al login -->
        <div class="mt-6 text-center">
            <a href="login.php" class="text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-2"></i>Volver al Login
            </a>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>© <?php echo date('Y'); ?> Sistema Hospitalario</p>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        <?php if ($step === 3): ?>
        // Validación en tiempo real
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        
        function validate() {
            const pass = newPass.value;
            const confirm = confirmPass.value;
            
            const checks = {
                length: pass.length >= 8,
                upper: /[A-Z]/.test(pass),
                lower: /[a-z]/.test(pass),
                number: /[0-9]/.test(pass),
                match: pass === confirm && pass.length > 0
            };
            
            updateCheck('req-length', checks.length);
            updateCheck('req-upper', checks.upper);
            updateCheck('req-lower', checks.lower);
            updateCheck('req-number', checks.number);
            updateCheck('req-match', checks.match);
            
            const allValid = Object.values(checks).every(v => v);
            submitBtn.disabled = !allValid;
        }
        
        function updateCheck(id, valid) {
            const el = document.getElementById(id);
            const icon = el.querySelector('i');
            
            if (valid) {
                icon.className = 'fas fa-check-circle text-green-500 mr-2';
                el.classList.add('text-green-700');
            } else {
                icon.className = 'fas fa-circle mr-2';
                icon.style.fontSize = '4px';
                el.classList.remove('text-green-700');
            }
        }
        
        newPass.addEventListener('input', validate);
        confirmPass.addEventListener('input', validate);
        <?php endif; ?>
    </script>
</body>
</html>