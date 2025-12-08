<?php
/**
 * modules/personal/registrar_medico.php - CON DEBUG
 * Reemplaza TODO el archivo con este código TEMPORALMENTE
 */

// ACTIVAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- DEBUG START: Iniciando registrar_medico.php -->\n";

// Verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!-- DEBUG: Sesión iniciada -->\n";

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

echo "<!-- DEBUG: Usuario autenticado, ID=" . $_SESSION['user_id'] . " -->\n";

$page_title = "Registrar Médico";

echo "<!-- DEBUG: Antes de cargar header.php -->\n";

// Verificar si headers ya fueron enviados ANTES de cargar header.php
if (headers_sent($file, $line)) {
    die("ERROR CRÍTICO: Headers ya enviados en $file línea $line ANTES de cargar header.php");
}

require_once '../../includes/header.php';

echo "<!-- DEBUG: header.php cargado correctamente -->\n";

// Verificar permisos
if (!has_any_role(['Administrador'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para acceder aquí</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

echo "<!-- DEBUG: Permisos verificados -->\n";

$id_personal = isset($_GET['id']) ? (int)$_GET['id'] : null;
$mensaje = '';
$mensaje_tipo = '';

echo "<!-- DEBUG: ID Personal recibido = " . ($id_personal ?? 'NULL') . " -->\n";

// Verificar que el ID exista
if (!$id_personal) {
    echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No se especificó el personal. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

// Obtener información del personal
try {
    echo "<!-- DEBUG: Consultando información del personal -->\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            per.nombres,
            per.apellidos,
            m.id_medico
        FROM personal p
        INNER JOIN persona per ON p.id_personal = per.id_persona
        LEFT JOIN medico m ON p.id_personal = m.id_medico
        WHERE p.id_personal = ?
    ");
    $stmt->execute([$id_personal]);
    $personal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<!-- DEBUG: Query ejecutado -->\n";
    
    if (!$personal) {
        echo "<!-- DEBUG ERROR: Personal no encontrado en BD -->\n";
        echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Personal no encontrado. <a href="index.php">Volver al listado</a></div></div></main>';
        require_once '../../includes/footer.php';
        exit();
    }
    
    echo "<!-- DEBUG: Personal encontrado, tipo=" . $personal['tipo_personal'] . " -->\n";
    
    // Verificar que sea de tipo Medico
    if ($personal['tipo_personal'] !== 'Medico') {
        echo "<!-- DEBUG ERROR: Personal no es médico -->\n";
        echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Este personal no es de tipo Médico. <a href="index.php">Volver al listado</a></div></div></main>';
        require_once '../../includes/footer.php';
        exit();
    }
    
    echo "<!-- DEBUG: Desencriptando datos -->\n";
    
    // Verificar si ya está registrado como médico
    $ya_registrado = !empty($personal['id_medico']);
    $nombre_completo = decrypt_data($personal['nombres']) . ' ' . decrypt_data($personal['apellidos']);
    
    echo "<!-- DEBUG: Ya registrado como médico = " . ($ya_registrado ? 'SÍ' : 'NO') . " -->\n";
    echo "<!-- DEBUG: Nombre completo = " . $nombre_completo . " -->\n";
    
} catch (Exception $e) {
    echo "<!-- DEBUG ERROR CRÍTICO: " . $e->getMessage() . " -->\n";
    echo "<!-- DEBUG ERROR Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . " -->\n";
    die("Error al cargar información del personal: " . $e->getMessage());
}

// Cargar datos del médico si ya existe
$data_medico = [];
if ($ya_registrado) {
    echo "<!-- DEBUG: Cargando datos existentes del médico -->\n";
    $stmt = $pdo->prepare("SELECT * FROM medico WHERE id_medico = ?");
    $stmt->execute([$id_personal]);
    $data_medico = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- DEBUG: Datos del médico cargados -->\n";
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- DEBUG: POST recibido -->\n";
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
        echo "<!-- DEBUG ERROR: Token CSRF inválido -->\n";
    } else {
        try {
            echo "<!-- DEBUG: Iniciando transacción -->\n";
            $pdo->beginTransaction();

            // Datos del médico
            $id_especialidad = (int)$_POST['id_especialidad'];
            $numero_colegiatura = sanitize_input($_POST['numero_colegiatura']);
            $universidad = sanitize_input($_POST['universidad'] ?? '');
            $anios_experiencia = (int)($_POST['anios_experiencia'] ?? 0);
            $disponible_consulta = isset($_POST['disponible_consulta']) ? 1 : 0;
            $costo_consulta = !empty($_POST['costo_consulta']) ? (float)$_POST['costo_consulta'] : 0;

            echo "<!-- DEBUG: Datos capturados - Especialidad=$id_especialidad, Colegiatura=$numero_colegiatura -->\n";

            // Validar campos requeridos
            if (empty($id_especialidad)) {
                throw new Exception('Debe seleccionar una especialidad');
            }
            if (empty($numero_colegiatura)) {
                throw new Exception('El número de colegiatura es requerido');
            }

            echo "<!-- DEBUG: Validaciones pasadas -->\n";

            // Verificar que el número de colegiatura no exista
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM medico WHERE numero_colegiatura = ? AND id_medico != ?");
            $stmt->execute([$numero_colegiatura, $id_personal]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe un médico con ese número de colegiatura');
            }

            echo "<!-- DEBUG: Colegiatura única verificada -->\n";

            if ($ya_registrado) {
                echo "<!-- DEBUG: Actualizando médico existente -->\n";
                
                $stmt = $pdo->prepare("
                    UPDATE medico SET
                        id_especialidad = ?,
                        numero_colegiatura = ?,
                        universidad = ?,
                        anios_experiencia = ?,
                        disponible_consulta = ?,
                        costo_consulta = ?
                    WHERE id_medico = ?
                ");
                $stmt->execute([
                    $id_especialidad,
                    $numero_colegiatura,
                    $universidad,
                    $anios_experiencia,
                    $disponible_consulta,
                    $costo_consulta,
                    $id_personal
                ]);

                echo "<!-- DEBUG: UPDATE ejecutado -->\n";
                
                log_action('UPDATE', 'medico', $id_personal, 'Actualización de datos de médico: ' . $nombre_completo);
                
                $pdo->commit();
                echo "<!-- DEBUG: Commit exitoso -->\n";
                
                // Verificar headers
                if (headers_sent($file, $line)) {
                    echo "<!-- ERROR: Headers enviados en $file línea $line -->\n";
                    echo "<script>alert('Médico actualizado correctamente'); window.location.href='index.php';</script>";
                    exit();
                }
                
                $_SESSION['success_message'] = 'Datos del médico actualizados correctamente';
                echo "<!-- DEBUG: Redirigiendo a index.php -->\n";
                header('Location: index.php');
                exit();

            } else {
                echo "<!-- DEBUG: Insertando nuevo médico -->\n";
                
                $stmt = $pdo->prepare("
                    INSERT INTO medico (
                        id_medico,
                        id_especialidad,
                        numero_colegiatura,
                        universidad,
                        anios_experiencia,
                        disponible_consulta,
                        costo_consulta
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_personal,
                    $id_especialidad,
                    $numero_colegiatura,
                    $universidad,
                    $anios_experiencia,
                    $disponible_consulta,
                    $costo_consulta
                ]);

                echo "<!-- DEBUG: INSERT ejecutado -->\n";
                
                log_action('INSERT', 'medico', $id_personal, 'Registro completo de médico: ' . $nombre_completo);
                
                $pdo->commit();
                echo "<!-- DEBUG: Commit exitoso -->\n";
                
                // Verificar headers
                if (headers_sent($file, $line)) {
                    echo "<!-- ERROR: Headers enviados en $file línea $line -->\n";
                    echo "<script>alert('Médico registrado correctamente'); window.location.href='index.php';</script>";
                    exit();
                }
                
                $_SESSION['success_message'] = 'Médico registrado correctamente. Ahora puede gestionar horarios y citas.';
                echo "<!-- DEBUG: Redirigiendo a index.php -->\n";
                header('Location: index.php');
                exit();
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

echo "<!-- DEBUG: Cargando especialidades -->\n";

// Obtener especialidades
$stmt = $pdo->query("SELECT id_especialidad, nombre FROM especialidad WHERE estado = 'activa' ORDER BY nombre");
$especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<!-- DEBUG: " . count($especialidades) . " especialidades cargadas -->\n";
echo "<!-- DEBUG: Mostrando formulario -->\n";
?>
<!-- Contenido Principal -->
<main>
    <div class="container-fluid">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h2 mb-2">
                            <i class="fas fa-user-md text-primary me-2"></i>
                            <?php echo $ya_registrado ? 'Editar' : 'Completar Registro de'; ?> Médico
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i>
                            <strong><?php echo htmlspecialchars($nombre_completo); ?></strong>
                            <span class="mx-2">|</span>
                            <i class="fas fa-id-badge me-2"></i>
                            <?php echo htmlspecialchars($personal['codigo_empleado']); ?>
                        </p>
                        <?php if (!$ya_registrado): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Complete los siguientes datos para finalizar el registro como médico y poder gestionar horarios y citas.
                        </div>
                        <?php endif; ?>
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

            <!-- Información Profesional -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Información Profesional
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Especialidad -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-stethoscope me-1"></i>
                                Especialidad
                                <span class="text-danger">*</span>
                            </label>
                            <select name="id_especialidad" class="form-select" required>
                                <option value="">-- Seleccionar Especialidad --</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?php echo $esp['id_especialidad']; ?>" 
                                        <?php echo (isset($data_medico['id_especialidad']) && $data_medico['id_especialidad'] == $esp['id_especialidad']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($esp['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Especialidad médica principal</small>
                        </div>

                        <!-- Número de Colegiatura -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-id-card-alt me-1"></i>
                                Número de Colegiatura
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="numero_colegiatura" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data_medico['numero_colegiatura'] ?? ''); ?>"
                                placeholder="Ej: COL-12345"
                                required
                            >
                            <small class="text-muted">Número de registro profesional</small>
                        </div>

                        <!-- Universidad -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-university me-1"></i>
                                Universidad
                            </label>
                            <input 
                                type="text" 
                                name="universidad" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data_medico['universidad'] ?? ''); ?>"
                                placeholder="Ej: Universidad Mayor de San Andrés"
                            >
                            <small class="text-muted">Universidad donde se graduó</small>
                        </div>

                        <!-- Años de Experiencia -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-check me-1"></i>
                                Años de Experiencia
                            </label>
                            <input 
                                type="number" 
                                name="anios_experiencia" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data_medico['anios_experiencia'] ?? '0'); ?>"
                                min="0"
                                max="60"
                                placeholder="0"
                            >
                            <small class="text-muted">Años de práctica profesional</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración de Consultas -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Configuración de Consultas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Disponible para Consulta -->
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    name="disponible_consulta" 
                                    id="disponible_consulta"
                                    <?php echo (isset($data_medico['disponible_consulta']) && $data_medico['disponible_consulta']) || !$ya_registrado ? 'checked' : ''; ?>
                                >
                                <label class="form-check-label fw-bold" for="disponible_consulta">
                                    <i class="fas fa-user-check me-1"></i>
                                    Disponible para Consultas
                                </label>
                            </div>
                            <small class="text-muted">Permite agendar citas con este médico</small>
                        </div>

                        <!-- Costo de Consulta -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-dollar-sign me-1"></i>
                                Costo de Consulta (Bs.)
                            </label>
                            <input 
                                type="number" 
                                name="costo_consulta" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($data_medico['costo_consulta'] ?? '150'); ?>"
                                min="0"
                                step="0.01"
                                placeholder="150.00"
                            >
                            <small class="text-muted">Costo por consulta en bolivianos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Los campos marcados con <span class="text-danger">*</span> son obligatorios
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                <?php echo $ya_registrado ? 'Actualizar' : 'Completar Registro'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Información adicional -->
        <?php if ($ya_registrado): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-info">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-lightbulb text-info me-2"></i>
                            Próximos Pasos
                        </h6>
                        <ul class="mb-0">
                            <li class="mb-2">
                                <a href="horarios.php?id=<?php echo $id_personal; ?>" class="text-decoration-none">
                                    <i class="fas fa-clock me-1"></i>
                                    Gestionar Horarios de Atención
                                </a>
                            </li>
                            <li>
                                <a href="../citas/index.php" class="text-decoration-none">
                                    <i class="fas fa-calendar-check me-1"></i>
                                    Ver Citas Programadas
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Validación del formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const colegiatura = document.querySelector('[name="numero_colegiatura"]').value.trim();
    const especialidad = document.querySelector('[name="id_especialidad"]').value;
    
    if (!especialidad) {
        e.preventDefault();
        alert('Debe seleccionar una especialidad');
        return false;
    }
    
    if (colegiatura.length < 3) {
        e.preventDefault();
        alert('El número de colegiatura debe tener al menos 3 caracteres');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>