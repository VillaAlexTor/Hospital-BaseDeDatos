<?php
/**
 * TEMPORAL - Debug para registrar.php
 * Reemplaza TEMPORALMENTE tu registrar.php con este código
 */

// ACTIVAR REPORTE DE ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Verificar si hay output
if (headers_sent($file, $line)) {
    die("ERROR: Headers ya fueron enviados en $file línea $line");
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Registrar Personal";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para acceder aquí</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$mensaje = '';
$mensaje_tipo = '';
$es_edicion = !empty($id);

// Cargar datos si es edición
$data = [];
if ($es_edicion) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            per.tipo_documento,
            per.numero_documento,
            per.nombres,
            per.apellidos,
            per.telefono,
            per.email,
            per.ciudad,
            per.fecha_nacimiento,
            per.genero
        FROM personal p 
        INNER JOIN persona per ON p.id_personal = per.id_persona 
        WHERE p.id_personal = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Personal no encontrado. <a href="index.php">Volver al listado</a></div></div></main>';
        require_once '../../includes/footer.php';
        exit();
    }
    
    // Desencriptar datos sensibles
    $data['numero_documento'] = decrypt_data($data['numero_documento']);
    $data['nombres'] = decrypt_data($data['nombres']);
    $data['apellidos'] = decrypt_data($data['apellidos']);
    $data['telefono'] = decrypt_data($data['telefono']);
    $data['email'] = decrypt_data($data['email']);
    
    $page_title = "Editar Personal";
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    echo "<!-- DEBUG: POST recibido -->\n";
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
        echo "<!-- DEBUG: Token CSRF inválido -->\n";
    } else {
        try {
            echo "<!-- DEBUG: Iniciando transacción -->\n";
            $pdo->beginTransaction();

            // Validar campos requeridos
            $campos_requeridos = ['nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'tipo_personal'];
            foreach ($campos_requeridos as $campo) {
                if (empty($_POST[$campo])) {
                    throw new Exception("El campo " . ucfirst($campo) . " es requerido");
                }
            }

            echo "<!-- DEBUG: Campos validados -->\n";

            // Datos de persona
            $tipo_documento = sanitize_input($_POST['tipo_documento']);
            $numero_documento = sanitize_input($_POST['numero_documento']);
            $nombres = sanitize_input($_POST['nombres']);
            $apellidos = sanitize_input($_POST['apellidos']);
            $telefono = sanitize_input($_POST['telefono'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $ciudad = sanitize_input($_POST['ciudad'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $genero = $_POST['genero'] ?? null;

            // Datos de personal
            $codigo_empleado = sanitize_input($_POST['codigo_empleado'] ?? '');
            $tipo_personal = sanitize_input($_POST['tipo_personal']);
            $fecha_contratacion = $_POST['fecha_contratacion'] ?? null;
            $id_departamento = !empty($_POST['id_departamento']) ? (int)$_POST['id_departamento'] : null;
            $estado_laboral = sanitize_input($_POST['estado_laboral'] ?? 'Activo');

            echo "<!-- DEBUG: tipo_personal = $tipo_personal -->\n";

            // Validar email si se proporciona
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El formato del email no es válido');
            }

            // Validar que el número de documento no exista (excepto en edición)
            $check_doc = "SELECT COUNT(*) FROM persona WHERE numero_documento = ?";
            if ($es_edicion) {
                $check_doc .= " AND id_persona != ?";
                $stmt = $pdo->prepare($check_doc);
                $stmt->execute([encrypt_data($numero_documento), $id]);
            } else {
                $stmt = $pdo->prepare($check_doc);
                $stmt->execute([encrypt_data($numero_documento)]);
            }
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe una persona con ese número de documento');
            }

            echo "<!-- DEBUG: Documento validado -->\n";

            if ($es_edicion) {
                echo "<!-- DEBUG: Modo EDICIÓN -->\n";
                
                // Actualizar persona
                $stmt = $pdo->prepare("
                    UPDATE persona SET 
                        tipo_documento = ?,
                        numero_documento = ?,
                        nombres = ?,
                        apellidos = ?,
                        telefono = ?,
                        email = ?,
                        ciudad = ?,
                        fecha_nacimiento = ?,
                        genero = ?,
                        usuario_modifica = ?,
                        fecha_modificacion = NOW()
                    WHERE id_persona = ?
                ");
                $stmt->execute([
                    $tipo_documento,
                    encrypt_data($numero_documento),
                    encrypt_data($nombres),
                    encrypt_data($apellidos),
                    encrypt_data($telefono),
                    encrypt_data($email),
                    $ciudad,
                    $fecha_nacimiento,
                    $genero,
                    $_SESSION['user_id'],
                    $id
                ]);

                // Actualizar personal
                $stmt = $pdo->prepare("
                    UPDATE personal SET 
                        codigo_empleado = ?,
                        fecha_contratacion = ?,
                        tipo_personal = ?,
                        id_departamento = ?,
                        estado_laboral = ?
                    WHERE id_personal = ?
                ");
                $stmt->execute([
                    $codigo_empleado,
                    $fecha_contratacion,
                    $tipo_personal,
                    $id_departamento,
                    $estado_laboral,
                    $id
                ]);

                log_action('UPDATE', 'personal', $id, 'Edición de personal: ' . $nombres . ' ' . $apellidos);
                
                $pdo->commit();
                echo "<!-- DEBUG: Commit EDICIÓN exitoso -->\n";
                
                // Verificar si headers ya fueron enviados
                if (headers_sent($file, $line)) {
                    echo "<!-- ERROR: No se puede redirigir, headers enviados en $file línea $line -->\n";
                    echo "<script>window.location.href='index.php';</script>";
                    exit();
                }
                
                $_SESSION['success_message'] = 'Personal actualizado correctamente';
                echo "<!-- DEBUG: Redirigiendo a index.php -->\n";
                header('Location: index.php');
                exit();
                
            } else {
                echo "<!-- DEBUG: Modo REGISTRO NUEVO -->\n";
                
                // Crear persona
                $stmt = $pdo->prepare("
                    INSERT INTO persona (
                        tipo_documento, numero_documento, nombres, apellidos,
                        telefono, email, ciudad, fecha_nacimiento, genero,
                        estado, usuario_crea, fecha_registro
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?, NOW())
                ");
                $stmt->execute([
                    $tipo_documento,
                    encrypt_data($numero_documento),
                    encrypt_data($nombres),
                    encrypt_data($apellidos),
                    encrypt_data($telefono),
                    encrypt_data($email),
                    $ciudad,
                    $fecha_nacimiento,
                    $genero,
                    $_SESSION['user_id']
                ]);
                $new_id = $pdo->lastInsertId();
                echo "<!-- DEBUG: Persona creada con ID = $new_id -->\n";

                // Crear personal
                $stmt = $pdo->prepare("
                    INSERT INTO personal (
                        id_personal, codigo_empleado, fecha_contratacion,
                        tipo_personal, id_departamento, estado_laboral
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $new_id,
                    $codigo_empleado,
                    $fecha_contratacion,
                    $tipo_personal,
                    $id_departamento,
                    $estado_laboral
                ]);
                echo "<!-- DEBUG: Personal creado -->\n";

                log_action('INSERT', 'personal', $new_id, 'Registro de nuevo personal: ' . $nombres . ' ' . $apellidos);
                
                $pdo->commit();
                echo "<!-- DEBUG: Commit NUEVO exitoso -->\n";
                
                // Verificar si headers ya fueron enviados
                if (headers_sent($file, $line)) {
                    echo "<!-- ERROR: No se puede redirigir, headers enviados en $file línea $line -->\n";
                    if ($tipo_personal === 'Medico') {
                        echo "<script>window.location.href='registrar_medico.php?id=$new_id';</script>";
                    } else {
                        echo "<script>window.location.href='index.php';</script>";
                    }
                    exit();
                }
                
                // Decidir redirección según el tipo de personal
                if ($tipo_personal === 'Medico') {
                    echo "<!-- DEBUG: Es Médico, redirigiendo a registrar_medico.php?id=$new_id -->\n";
                    $_SESSION['success_message'] = 'Personal registrado correctamente. Complete los datos del médico.';
                    header('Location: registrar_medico.php?id=' . $new_id);
                    exit();
                } else {
                    echo "<!-- DEBUG: NO es Médico, redirigiendo a index.php -->\n";
                    $_SESSION['success_message'] = 'Personal registrado correctamente';
                    header('Location: index.php');
                    exit();
                }
            }

        } catch (Exception $e) {
            echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->\n";
            echo "<!-- DEBUG ERROR Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . " -->\n";
            $pdo->rollBack();
            $mensaje = 'Error: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Obtener departamentos para el select
$stmt = $pdo->prepare("SELECT id_departamento, nombre FROM departamento WHERE estado = 'Activo' ORDER BY nombre");
$stmt->execute();
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipos_documento = ['CI' => 'Cédula de Identidad', 'Pasaporte' => 'Pasaporte', 'Otro' => 'Otro'];
$tipos_personal = [
    'Medico' => 'Médico',
    'Administrativo' => 'Administrativo',
];
$estados_laborales = [
    'Activo' => 'Activo',
    'Inactivo' => 'Inactivo',
    'Vacaciones' => 'Vacaciones',
    'Licencia' => 'Licencia'
];
$generos = ['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'];
?>

<!-- RESTO DEL HTML IGUAL... -->
<!-- (El formulario y todo lo demás permanece igual) -->

<!-- Contenido Principal -->
<main>
    <div class="container-fluid">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h2 mb-2">
                            <i class="fas fa-<?php echo $es_edicion ? 'user-edit' : 'user-plus'; ?> text-primary me-2"></i>
                            <?php echo $es_edicion ? 'Editar' : 'Registrar'; ?> Personal
                        </h1>
                        <p class="text-muted mb-0">
                            <?php echo $es_edicion ? 'Modifica la información del personal' : 'Completa los datos del nuevo miembro del personal'; ?>
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje de resultado -->
        <?php if (!empty($mensaje)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $mensaje_tipo; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Datos del Personal -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-id-badge me-2"></i>
                        Datos del Personal
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-barcode me-1"></i>
                                Código de Empleado
                            </label>
                            <input 
                                type="text" 
                                name="codigo_empleado" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['codigo_empleado'] ?? ''); ?>"
                                placeholder="Ej: EMP-001"
                            >
                            <small class="text-muted">Código único del empleado</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-user-tag me-1"></i>
                                Tipo de Personal
                                <span class="text-danger">*</span>
                            </label>
                            <select name="tipo_personal" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($tipos_personal as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($data['tipo_personal']) && $data['tipo_personal'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>
                                Fecha de Contratación
                            </label>
                            <input 
                                type="date" 
                                name="fecha_contratacion" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['fecha_contratacion'] ?? ''); ?>"
                            >
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-toggle-on me-1"></i>
                                Estado Laboral
                            </label>
                            <select name="estado_laboral" class="form-select">
                                <?php foreach ($estados_laborales as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($data['estado_laboral']) && $data['estado_laboral'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">
                                <i class="fas fa-building me-1"></i>
                                Departamento
                            </label>
                            <select name="id_departamento" class="form-select">
                                <option value="">-- Sin departamento --</option>
                                <?php foreach ($departamentos as $dept): ?>
                                    <option value="<?php echo $dept['id_departamento']; ?>" <?php echo (isset($data['id_departamento']) && $data['id_departamento'] == $dept['id_departamento']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos Personales -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Datos Personales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-signature me-1"></i>
                                Nombres
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="nombres" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['nombres'] ?? ''); ?>"
                                required
                            >
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-signature me-1"></i>
                                Apellidos
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="apellidos" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['apellidos'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-id-card me-1"></i>
                                Tipo de Documento
                                <span class="text-danger">*</span>
                            </label>
                            <select name="tipo_documento" class="form-select" required>
                                <?php foreach ($tipos_documento as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($data['tipo_documento']) && $data['tipo_documento'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-hashtag me-1"></i>
                                Número de Documento
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="numero_documento" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['numero_documento'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-venus-mars me-1"></i>
                                Género
                            </label>
                            <select name="genero" class="form-select">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($generos as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($data['genero']) && $data['genero'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-birthday-cake me-1"></i>
                                Fecha de Nacimiento
                            </label>
                            <input 
                                type="date" 
                                name="fecha_nacimiento" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['fecha_nacimiento'] ?? ''); ?>"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos de Contacto -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-address-book me-2"></i>
                        Datos de Contacto
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-phone me-1"></i>
                                Teléfono
                            </label>
                            <input 
                                type="tel" 
                                name="telefono" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['telefono'] ?? ''); ?>"
                                placeholder="Ej: 70123456"
                            >
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-envelope me-1"></i>
                                Email
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>"
                                placeholder="ejemplo@correo.com"
                            >
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                Ciudad
                            </label>
                            <input 
                                type="text" 
                                name="ciudad" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data['ciudad'] ?? ''); ?>"
                                placeholder="Ej: La Paz"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $es_edicion ? 'Actualizar' : 'Registrar'; ?> Personal
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
// Validación adicional en el frontend
document.querySelector('form').addEventListener('submit', function(e) {
    const telefono = document.querySelector('[name="telefono"]').value;
    const email = document.querySelector('[name="email"]').value;
    
    // Validar teléfono si se proporciona
    if (telefono && !/^\d{7,15}$/.test(telefono.replace(/\s/g, ''))) {
        e.preventDefault();
        alert('El teléfono debe contener entre 7 y 15 dígitos');
        return false;
    }
    
    // Validar email si se proporciona
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        e.preventDefault();
        alert('El formato del email no es válido');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>