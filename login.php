<?php
/**
 * Página de Login
 */
require_once 'includes/config.php';
$error = '';
$success = '';
// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit();
}
// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            } elseif ($usuario['cuenta_bloqueada']) {
                $error = 'Cuenta bloqueada. Contacte al administrador.';
            } elseif ($usuario['estado'] !== 'activo') {
                $error = 'Cuenta inactiva. Contacte al administrador.';
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
                    $_SESSION['nombre_completo'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                    $_SESSION['rol'] = $usuario['rol'] ?? 'Usuario';
                    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['last_activity'] = time();
                    // Registrar login en auditoría
                    $stmt = $pdo->prepare("INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado) VALUES (?, 'LOGIN', 'Inicio de sesión exitoso', ?, 'Éxito')");
                    $stmt->execute([$usuario['id_usuario'], $_SERVER['REMOTE_ADDR']]);
                    // Redirigir al dashboard
                    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
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
// Mensaje de éxito si viene de logout
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 40px;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background: white;
            border-right: none;
            border: 2px solid #e0e0e0;
            border-radius: 8px 0 0 8px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <i class="bi bi-hospital"></i>
                    <h2><?php echo SITE_NAME; ?></h2>
                    <p class="mb-0">Ingrese sus credenciales</p>
                </div>
                <div class="login-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                        class="form-control" 
                                        id="username" 
                                        name="username" 
                                        placeholder="Ingrese su usuario"
                                        required 
                                        autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                        class="form-control" 
                                        id="password" 
                                        name="password" 
                                        placeholder="Ingrese su contraseña"
                                        required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Recordar mi sesión
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-login w-100">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Iniciar Sesión
                        </button>
                    </form>
                    <div class="text-center mt-4">
                        <a href="#" class="text-decoration-none">¿Olvidó su contraseña?</a>
                    </div>
                    <!-- Datos de prueba -->
                    <div class="alert alert-info mt-4 small">
                        <strong>Datos de prueba:</strong><br>
                        <small>
                            Usuario: admin<br>
                            Contraseña: admin123<br>
                        </small>
                        <small>
                            Usuario: PACIENTE<br>
                            Usuario: tola<br>
                            Contraseña: tola12345<br>
                        </small>
                        <small>
                            Usuario: DOCTOR<br>
                            Usuario: martinez<br>
                            Contraseña: martinez123
                        </small>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3 text-white">
                <small>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</small>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>