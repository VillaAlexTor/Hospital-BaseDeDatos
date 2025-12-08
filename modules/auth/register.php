<?php
/** modules/auth/register.php
 * Registro de nuevos usuarios (Solo Pacientes)
 */

$pageTitle = "Crear Cuenta";
require_once '../../includes/config.php';
require_once '../../includes/security-headers.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
$success = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            $pdo->beginTransaction();
            
            // ========== VALIDAR DATOS ==========
            $tipo_documento = sanitize_input($_POST['tipo_documento']);
            $numero_documento = sanitize_input($_POST['numero_documento']);
            $nombres = sanitize_input($_POST['nombres']);
            $apellidos = sanitize_input($_POST['apellidos']);
            $fecha_nacimiento = $_POST['fecha_nacimiento'];
            $genero = $_POST['genero'];
            $telefono = sanitize_input($_POST['telefono']);
            $email = sanitize_input($_POST['email']);
            $direccion = sanitize_input($_POST['direccion']);
            $ciudad = sanitize_input($_POST['ciudad']);
            
            // Datos de usuario
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validaciones
            if (strlen($username) < 4) {
                throw new Exception("El nombre de usuario debe tener al menos 4 caracteres");
            }
            
            if (strlen($password) < 8) {
                throw new Exception("La contraseña debe tener al menos 8 caracteres");
            }
            
            if ($password !== $confirm_password) {
                throw new Exception("Las contraseñas no coinciden");
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                throw new Exception("La contraseña debe contener al menos una mayúscula");
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                throw new Exception("La contraseña debe contener al menos una minúscula");
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                throw new Exception("La contraseña debe contener al menos un número");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email inválido");
            }
            
            // Validar que el documento no exista
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM persona 
                WHERE tipo_documento = ? AND numero_documento = ?
            ");
            $stmt->execute([$tipo_documento, encrypt_data($numero_documento)]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ya existe una persona registrada con este documento");
            }
            
            // Validar que el username no exista
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya está en uso");
            }
            
            // Validar que el email no exista
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM persona WHERE email = ?");
            $stmt->execute([encrypt_data($email)]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El email ya está registrado");
            }
            
            // ========== INSERTAR PERSONA ==========
            $stmt = $pdo->prepare("
                INSERT INTO persona (
                    tipo_documento, numero_documento, nombres, apellidos,
                    fecha_nacimiento, genero, telefono, email, direccion,
                    ciudad, pais, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bolivia', 'activo')
            ");
            
            $stmt->execute([
                $tipo_documento,
                encrypt_data($numero_documento),
                encrypt_data($nombres),
                encrypt_data($apellidos),
                encrypt_data($fecha_nacimiento),
                $genero,
                encrypt_data($telefono),
                encrypt_data($email),
                encrypt_data($direccion),
                $ciudad
            ]);
            
            $id_persona = $pdo->lastInsertId();
            
            // ========== INSERTAR PACIENTE ==========
            // Generar número de historia clínica
            $stmt = $pdo->query("SELECT COUNT(*) + 1 FROM paciente");
            $contador = $stmt->fetchColumn();
            $numero_historia = 'HC-' . date('Y') . '-' . str_pad($contador, 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO paciente (
                    id_paciente, numero_historia_clinica, 
                    fecha_primera_consulta, estado_paciente
                ) VALUES (?, ?, CURDATE(), 'activo')
            ");
            $stmt->execute([$id_persona, $numero_historia]);
            
            // ========== CREAR HISTORIAL CLÍNICO ==========
            $stmt = $pdo->prepare("
                INSERT INTO historial_clinico (id_paciente) VALUES (?)
            ");
            $stmt->execute([$id_persona]);
            
            // ========== CREAR USUARIO ==========
            // Generar salt y hash
            $salt = bin2hex(random_bytes(16));
            $password_hash = hash('sha256', $password . $salt);
            
            $stmt = $pdo->prepare("
                INSERT INTO usuario (
                    id_persona, username, password_hash, password_salt,
                    email_verificado, estado, requiere_cambio_password
                ) VALUES (?, ?, ?, ?, 0, 'activo', 0)
            ");
            $stmt->execute([$id_persona, $username, $password_hash, $salt]);
            
            $id_usuario = $pdo->lastInsertId();
            
            // ========== ASIGNAR ROL DE PACIENTE ==========
            $stmt = $pdo->prepare("
                INSERT INTO usuario_rol (id_usuario, id_rol, estado)
                VALUES (?, (SELECT id_rol FROM rol WHERE nombre = 'Paciente'), 'activo')
            ");
            $stmt->execute([$id_usuario]);
            
            // ========== AUDITORÍA ==========
            log_action('INSERT', 'usuario', $id_usuario, "Nuevo usuario registrado: $username - HC: $numero_historia");
            
            $pdo->commit();
            
            $success = "¡Cuenta creada exitosamente! Historia Clínica: <strong>$numero_historia</strong><br>Ya puedes iniciar sesión.";
            
            // Redirigir al login después de 3 segundos
            header("refresh:3;url=../../login.php");
            
            // Limpiar formulario
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
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
        .register-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="register-card max-w-4xl w-full p-8 rounded-2xl shadow-2xl my-8">
        <!-- Logo y Título -->
        <div class="text-center mb-8">
            <i class="fas fa-user-plus text-6xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">Crear Nueva Cuenta</h1>
            <p class="text-gray-600 mt-2">Registro de Pacientes</p>
        </div>
        
        <!-- Alertas -->
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $success; ?>
            <div class="mt-2 text-sm">
                Redirigiendo al login en 3 segundos...
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <!-- Formulario de Registro -->
        <form method="POST" action="" id="form-register">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- SECCIÓN 1: DATOS PERSONALES -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-user text-blue-600 mr-2"></i>
                    Datos Personales
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tipo de Documento -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Tipo de Documento <span class="text-red-500">*</span>
                        </label>
                        <select name="tipo_documento" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="CI">Cédula de Identidad</option>
                            <option value="Pasaporte">Pasaporte</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <!-- Número de Documento -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Número de Documento <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="numero_documento" required maxlength="20"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: 12345678">
                    </div>
                    
                    <!-- Nombres -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Nombres <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombres" required maxlength="100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: Juan Carlos">
                    </div>
                    
                    <!-- Apellidos -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Apellidos <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="apellidos" required maxlength="100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: Pérez López">
                    </div>
                    
                    <!-- Fecha de Nacimiento -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Fecha de Nacimiento <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="fecha_nacimiento" required
                                max="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Género -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Género <span class="text-red-500">*</span>
                        </label>
                        <select name="genero" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleccione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <!-- Teléfono -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Teléfono <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="telefono" required maxlength="20"
                                pattern="[0-9+\-\s()]+"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: 71234567">
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required maxlength="100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="ejemplo@email.com">
                    </div>
                    
                    <!-- Dirección -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Dirección <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="direccion" required maxlength="255"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: Av. 6 de Agosto #123">
                    </div>
                    
                    <!-- Ciudad -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Ciudad <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="ciudad" required maxlength="100"
                                value="La Paz"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            
            <!-- SECCIÓN 2: DATOS DE ACCESO -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-key text-green-600 mr-2"></i>
                    Datos de Acceso
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Username -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Nombre de Usuario <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" required 
                                minlength="4" maxlength="50"
                                pattern="[a-zA-Z0-9._-]+"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: juan.perez (solo letras, números, punto, guion)">
                        <small class="text-gray-600">Mínimo 4 caracteres. Solo letras, números y . _ -</small>
                    </div>
                    
                    <!-- Contraseña -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Contraseña <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                    minlength="8" maxlength="100"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Mínimo 8 caracteres">
                            <button type="button" onclick="togglePassword('password')"
                                    class="absolute right-3 top-3 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-eye" id="toggle-icon-pass"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirmar Contraseña -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Confirmar Contraseña <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                    minlength="8" maxlength="100"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Repite la contraseña">
                            <button type="button" onclick="togglePassword('confirm_password')"
                                    class="absolute right-3 top-3 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-eye" id="toggle-icon-confirm"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Requisitos de contraseña -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                    <h4 class="font-bold text-blue-800 mb-2">Requisitos de la contraseña:</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li id="req-length" class="flex items-center">
                            <i class="fas fa-circle text-gray-400 mr-2" style="font-size: 6px;"></i>
                            Mínimo 8 caracteres
                        </li>
                        <li id="req-upper" class="flex items-center">
                            <i class="fas fa-circle text-gray-400 mr-2" style="font-size: 6px;"></i>
                            Al menos una mayúscula (A-Z)
                        </li>
                        <li id="req-lower" class="flex items-center">
                            <i class="fas fa-circle text-gray-400 mr-2" style="font-size: 6px;"></i>
                            Al menos una minúscula (a-z)
                        </li>
                        <li id="req-number" class="flex items-center">
                            <i class="fas fa-circle text-gray-400 mr-2" style="font-size: 6px;"></i>
                            Al menos un número (0-9)
                        </li>
                        <li id="req-match" class="flex items-center">
                            <i class="fas fa-circle text-gray-400 mr-2" style="font-size: 6px;"></i>
                            Las contraseñas coinciden
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Términos y Condiciones -->
            <div class="mb-6">
                <label class="flex items-start">
                    <input type="checkbox" name="terminos" required
                            class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">
                        Acepto los <a href="#" class="text-blue-600 hover:underline">términos y condiciones</a> 
                        y la <a href="#" class="text-blue-600 hover:underline">política de privacidad</a> 
                        del Sistema Hospitalario <span class="text-red-500">*</span>
                    </span>
                </label>
            </div>
            
            <!-- Botones -->
            <div class="flex flex-col md:flex-row gap-4">
                <button type="submit" id="btn-submit" disabled
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition transform hover:scale-105 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:transform-none">
                    <i class="fas fa-user-plus mr-2"></i>Crear Mi Cuenta
                </button>
                <a href="../../login.php"
                    class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition text-center">
                    <i class="fas fa-arrow-left mr-2"></i>Volver al Login
                </a>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>¿Ya tienes cuenta? <a href="../../login.php" class="text-blue-600 hover:underline font-semibold">Inicia sesión aquí</a></p>
            <p class="mt-4">© <?php echo date('Y'); ?> Sistema Hospitalario</p>
            <p class="mt-2">
                <i class="fas fa-shield-alt text-green-600 mr-1"></i>
                Tu información está protegida con cifrado AES-256
            </p>
        </div>
    </div>
    
    <script>
        // Validación de contraseña en tiempo real
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('btn-submit');
        
        function validatePassword() {
            const pass = password.value;
            const confirm = confirmPassword.value;
            
            const hasLength = pass.length >= 8;
            const hasUpper = /[A-Z]/.test(pass);
            const hasLower = /[a-z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);
            const isMatch = pass === confirm && pass.length > 0;
            
            updateRequirement('req-length', hasLength);
            updateRequirement('req-upper', hasUpper);
            updateRequirement('req-lower', hasLower);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-match', isMatch);
            
            const allValid = hasLength && hasUpper && hasLower && hasNumber && isMatch;
            submitBtn.disabled = !allValid;
        }
        
        function updateRequirement(id, valid) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (valid) {
                icon.className = 'fas fa-check-circle text-green-500 mr-2';
                element.classList.add('text-green-700');
                element.classList.remove('text-blue-700');
            } else {
                icon.className = 'fas fa-circle text-gray-400 mr-2';
                icon.style.fontSize = '6px';
                element.classList.remove('text-green-700');
                element.classList.add('text-blue-700');
            }
        }
        
        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const iconId = fieldId === 'password' ? 'toggle-icon-pass' : 'toggle-icon-confirm';
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
        
        // Validación del formulario antes de enviar
        document.getElementById('form-register').addEventListener('submit', function(e) {
            const terminos = document.querySelector('input[name="terminos"]');
            if (!terminos.checked) {
                e.preventDefault();
                alert('Debes aceptar los términos y condiciones');
                return false;
            }
        });
    </script>
</body>
</html>