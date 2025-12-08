<?php
/**
 * modules/citas/programar.php
 * Programar Nueva Cita Médica
 */

$page_title = "Programar Nueva Cita";
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Verificar permisos
if (!tiene_permiso('citas', 'crear')) {
    $_SESSION['error_message'] = 'No tienes permisos para programar citas';
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';
$success = '';

// Si viene de la página de pacientes con ID
$paciente_preseleccionado = $_GET['paciente'] ?? '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            $pdo->beginTransaction();
            
            $id_paciente = (int)$_POST['id_paciente'];
            $id_medico = (int)$_POST['id_medico'];
            $fecha_cita = $_POST['fecha_cita'];
            $hora_cita = $_POST['hora_cita'];
            $tipo_cita = $_POST['tipo_cita'];
            $motivo_consulta = sanitize_input($_POST['motivo_consulta']);
            $observaciones = sanitize_input($_POST['observaciones'] ?? '');
            $consultorio = sanitize_input($_POST['consultorio'] ?? '');
            
            // Validaciones
            if (empty($id_paciente) || empty($id_medico) || empty($fecha_cita) || 
                empty($hora_cita) || empty($tipo_cita) || empty($motivo_consulta)) {
                throw new Exception("Todos los campos obligatorios deben ser completados");
            }
            
            if ($fecha_cita < date('Y-m-d')) {
                throw new Exception("No se pueden programar citas en fechas pasadas");
            }
            
            if (strlen($motivo_consulta) < 10) {
                throw new Exception("El motivo de consulta debe tener al menos 10 caracteres");
            }
            
            // Verificar disponibilidad
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM cita 
                WHERE id_medico = ? 
                AND fecha_cita = ? 
                AND hora_cita = ?
                AND estado_cita NOT IN ('Cancelada', 'No asistió')
            ");
            $stmt->execute([$id_medico, $fecha_cita, $hora_cita]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El médico ya tiene una cita programada en ese horario");
            }
            
            // Verificar horario del médico
            $dia_semana_map = [
                0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles',
                4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
            ];
            $dia_semana = $dia_semana_map[date('w', strtotime($fecha_cita))];
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*), consultorio FROM horario_medico
                WHERE id_medico = ?
                AND dia_semana = ?
                AND hora_inicio <= ?
                AND hora_fin >= ?
                AND activo = 1
                GROUP BY consultorio
            ");
            $stmt->execute([$id_medico, $dia_semana, $hora_cita, $hora_cita]);
            $horario = $stmt->fetch();
            
            if (!$horario || $horario['COUNT(*)'] == 0) {
                throw new Exception("El médico no atiende en ese día/horario");
            }
            
            // Si no se especificó consultorio, usar el del horario
            if (empty($consultorio) && !empty($horario['consultorio'])) {
                $consultorio = $horario['consultorio'];
            }
            
            // Obtener costo de consulta del médico
            $stmt = $pdo->prepare("SELECT costo_consulta FROM medico WHERE id_medico = ?");
            $stmt->execute([$id_medico]);
            $costo_consulta = $stmt->fetchColumn();
            
            // Insertar cita
            $stmt = $pdo->prepare("
                INSERT INTO cita (
                    id_paciente, id_medico, fecha_cita, hora_cita,
                    motivo_consulta, tipo_cita, estado_cita,
                    observaciones, consultorio, costo_cita, registrado_por
                ) VALUES (?, ?, ?, ?, ?, ?, 'Programada', ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $id_paciente,
                $id_medico,
                $fecha_cita,
                $hora_cita,
                $motivo_consulta,
                $tipo_cita,
                $observaciones,
                $consultorio,
                $costo_consulta,
                $_SESSION['user_id']
            ]);
            
            $id_cita = $pdo->lastInsertId();
            
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                          descripcion, ip_address, resultado)
                VALUES (?, 'INSERT', 'cita', ?, ?, ?, 'Éxito')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_cita,
                "Cita programada para el $fecha_cita a las $hora_cita",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Cita programada exitosamente para el " . 
                       date('d/m/Y', strtotime($fecha_cita)) . 
                       " a las " . substr($hora_cita, 0, 5);
            
            header('Location: index.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Obtener lista de pacientes activos
$stmt = $pdo->query("
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        per.nombres,
        per.apellidos,
        per.numero_documento
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE pac.estado_paciente = 'activo'
    ORDER BY per.apellidos, per.nombres
    LIMIT 1000
");
$pacientes = $stmt->fetchAll();

// Obtener lista de médicos activos
$stmt = $pdo->query("
    SELECT 
        m.id_medico,
        m.costo_consulta,
        per.nombres,
        per.apellidos,
        e.nombre as especialidad
    FROM medico m
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE p.estado_laboral = 'activo' AND m.disponible_consulta = 1
    ORDER BY per.apellidos, per.nombres
");
$medicos = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<!-- Contenido Principal -->
<main>
    <div class="container-fluid">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h2 mb-2">
                            <i class="fas fa-calendar-plus text-success me-2"></i>
                            Programar Nueva Cita
                        </h1>
                        <p class="text-muted mb-0">Complete el formulario para agendar una cita médica</p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Calendario
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="" id="formCita" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <!-- Columna Principal -->
                <div class="col-lg-8">
                    <!-- Sección Paciente -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-user text-primary me-2"></i>
                                Datos del Paciente
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-0">
                                <label for="id_paciente" class="form-label fw-bold">
                                    <i class="fas fa-search me-1"></i>
                                    Seleccionar Paciente <span class="text-danger">*</span>
                                </label>
                                <select name="id_paciente" id="id_paciente" class="form-select form-select-lg" required>
                                    <option value="">-- Buscar por nombre o historia clínica --</option>
                                    <?php foreach ($pacientes as $pac): ?>
                                        <option value="<?php echo $pac['id_paciente']; ?>" 
                                                data-historia="<?php echo htmlspecialchars($pac['numero_historia_clinica']); ?>"
                                                <?php echo $paciente_preseleccionado == $pac['id_paciente'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pac['apellidos']) . ' ' . htmlspecialchars($pac['nombres']); ?> - 
                                            HC: <?php echo htmlspecialchars($pac['numero_historia_clinica']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    ¿Paciente nuevo? 
                                    <a href="../pacientes/registrar.php" target="_blank" class="text-decoration-none">
                                        <strong>Registrar aquí</strong>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección Cita -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-check text-success me-2"></i>
                                Datos de la Cita
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Médico -->
                                <div class="col-md-6">
                                    <label for="id_medico" class="form-label fw-bold">
                                        <i class="fas fa-user-md me-1"></i>
                                        Médico <span class="text-danger">*</span>
                                    </label>
                                    <select name="id_medico" id="id_medico" class="form-select" required>
                                        <option value="">Seleccione un médico...</option>
                                        <?php foreach ($medicos as $med): ?>
                                            <option value="<?php echo $med['id_medico']; ?>"
                                                    data-especialidad="<?php echo htmlspecialchars($med['especialidad']); ?>"
                                                    data-costo="<?php echo $med['costo_consulta']; ?>">
                                                Dr(a). <?php echo htmlspecialchars($med['apellidos'] . ' ' . $med['nombres']); ?> - 
                                                <?php echo htmlspecialchars($med['especialidad']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Tipo de Cita -->
                                <div class="col-md-6">
                                    <label for="tipo_cita" class="form-label fw-bold">
                                        <i class="fas fa-stethoscope me-1"></i>
                                        Tipo de Cita <span class="text-danger">*</span>
                                    </label>
                                    <select name="tipo_cita" id="tipo_cita" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="Primera vez">Primera vez</option>
                                        <option value="Control">Control</option>
                                        <option value="Emergencia">Emergencia</option>
                                        <option value="Especializada">Especializada</option>
                                    </select>
                                </div>
                                
                                <!-- Fecha -->
                                <div class="col-md-4">
                                    <label for="fecha_cita" class="form-label fw-bold">
                                        <i class="fas fa-calendar me-1"></i>
                                        Fecha <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="fecha_cita" id="fecha_cita" 
                                           class="form-control" required
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <!-- Hora -->
                                <div class="col-md-4">
                                    <label for="hora_cita" class="form-label fw-bold">
                                        <i class="fas fa-clock me-1"></i>
                                        Hora <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="hora_cita" id="hora_cita" 
                                           class="form-control" required>
                                </div>
                                
                                <!-- Consultorio -->
                                <div class="col-md-4">
                                    <label for="consultorio" class="form-label fw-bold">
                                        <i class="fas fa-door-open me-1"></i>
                                        Consultorio
                                    </label>
                                    <input type="text" name="consultorio" id="consultorio" 
                                           class="form-control" placeholder="Ej: Consultorio 101">
                                </div>
                                
                                <!-- Motivo -->
                                <div class="col-md-12">
                                    <label for="motivo_consulta" class="form-label fw-bold">
                                        <i class="fas fa-notes-medical me-1"></i>
                                        Motivo de Consulta <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="motivo_consulta" id="motivo_consulta" 
                                              class="form-control" rows="4" required
                                              placeholder="Describa detalladamente el motivo de la consulta (mínimo 10 caracteres)..."></textarea>
                                    <div class="invalid-feedback">
                                        El motivo debe tener al menos 10 caracteres
                                    </div>
                                </div>
                                
                                <!-- Observaciones -->
                                <div class="col-md-12">
                                    <label for="observaciones" class="form-label fw-bold">
                                        <i class="fas fa-comment-medical me-1"></i>
                                        Observaciones Adicionales
                                    </label>
                                    <textarea name="observaciones" id="observaciones" 
                                              class="form-control" rows="2"
                                              placeholder="Información adicional, instrucciones especiales, etc."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Los campos con <span class="text-danger">*</span> son obligatorios
                                </small>
                                <div>
                                    <a href="index.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-calendar-check me-2"></i>Programar Cita
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Lateral -->
                <div class="col-lg-4">
                    <!-- Resumen -->
                    <div class="card shadow-sm mb-4 position-sticky" style="top: 90px;">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="mb-0">
                                <i class="fas fa-clipboard-check me-2"></i>
                                Resumen de la Cita
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-user me-1"></i>Paciente:
                                </small>
                                <strong id="resumen-paciente" class="text-dark">No seleccionado</strong>
                            </div>
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-user-md me-1"></i>Médico:
                                </small>
                                <strong id="resumen-medico" class="text-dark">No seleccionado</strong>
                            </div>
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-calendar-alt me-1"></i>Fecha y Hora:
                                </small>
                                <strong id="resumen-fecha" class="text-dark">No seleccionada</strong>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-stethoscope me-1"></i>Tipo:
                                </small>
                                <strong id="resumen-tipo" class="text-dark">No seleccionado</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Información -->
                    <div class="alert alert-info shadow-sm">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>
                            Información Importante
                        </h6>
                        <ul class="small mb-0 ps-3">
                            <li class="mb-2">Las citas se programan según disponibilidad del médico</li>
                            <li class="mb-2">Verifique el horario de atención antes de agendar</li>
                            <li class="mb-2">Puede reprogramar o cancelar con 24h de anticipación</li>
                            <li class="mb-0">Se enviará recordatorio al paciente por SMS/Email</li>
                        </ul>
                    </div>

                    <!-- Tips -->
                    <div class="card shadow-sm bg-light">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                Tips para agendar
                            </h6>
                            <ul class="small mb-0 ps-3">
                                <li class="mb-1">Consulte primero con el paciente</li>
                                <li class="mb-1">Verifique datos de contacto actualizados</li>
                                <li>Pregunte por alergias o condiciones especiales</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
// Actualizar resumen en tiempo real
function actualizarResumen() {
    // Paciente
    const paciente = document.getElementById('id_paciente');
    if (paciente.value) {
        const textoCompleto = paciente.options[paciente.selectedIndex].text;
        const nombre = textoCompleto.split(' - ')[0];
        document.getElementById('resumen-paciente').innerHTML = 
            '<i class="fas fa-check-circle text-success me-1"></i>' + nombre;
    } else {
        document.getElementById('resumen-paciente').innerHTML = 
            '<i class="fas fa-times-circle text-danger me-1"></i>No seleccionado';
    }
    
    // Médico
    const medico = document.getElementById('id_medico');
    if (medico.value) {
        document.getElementById('resumen-medico').innerHTML = 
            '<i class="fas fa-check-circle text-success me-1"></i>' + 
            medico.options[medico.selectedIndex].text;
    } else {
        document.getElementById('resumen-medico').innerHTML = 
            '<i class="fas fa-times-circle text-danger me-1"></i>No seleccionado';
    }
    
    // Fecha y Hora
    const fecha = document.getElementById('fecha_cita').value;
    const hora = document.getElementById('hora_cita').value;
    if (fecha && hora) {
        const fechaObj = new Date(fecha + 'T00:00:00');
        const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const fechaFormateada = fechaObj.toLocaleDateString('es-ES', opciones);
        document.getElementById('resumen-fecha').innerHTML = 
            '<i class="fas fa-check-circle text-success me-1"></i>' + 
            fechaFormateada.charAt(0).toUpperCase() + fechaFormateada.slice(1) + 
            '<br><small class="text-muted">a las ' + hora + ' hrs</small>';
    } else {
        document.getElementById('resumen-fecha').innerHTML = 
            '<i class="fas fa-times-circle text-danger me-1"></i>No seleccionada';
    }
    
    // Tipo
    const tipo = document.getElementById('tipo_cita');
    if (tipo.value) {
        const colores = {
            'Primera vez': 'primary',
            'Control': 'info',
            'Emergencia': 'danger',
            'Especializada': 'warning'
        };
        const color = colores[tipo.value] || 'secondary';
        document.getElementById('resumen-tipo').innerHTML = 
            '<span class="badge bg-' + color + '">' + tipo.value + '</span>';
    } else {
        document.getElementById('resumen-tipo').innerHTML = 
            '<i class="fas fa-times-circle text-danger me-1"></i>No seleccionado';
    }
}

// Event listeners para actualización en tiempo real
document.getElementById('id_paciente').addEventListener('change', actualizarResumen);
document.getElementById('id_medico').addEventListener('change', actualizarResumen);
document.getElementById('fecha_cita').addEventListener('change', actualizarResumen);
document.getElementById('hora_cita').addEventListener('change', actualizarResumen);
document.getElementById('tipo_cita').addEventListener('change', actualizarResumen);

// Validación del formulario
document.getElementById('formCita').addEventListener('submit', function(e) {
    const motivo = document.getElementById('motivo_consulta').value.trim();
    
    if (motivo.length < 10) {
        e.preventDefault();
        e.stopPropagation();
        alert('⚠️ El motivo de consulta debe tener al menos 10 caracteres');
        document.getElementById('motivo_consulta').focus();
        return false;
    }
    
    if (!confirm('¿Confirma que desea programar esta cita con los datos ingresados?')) {
        e.preventDefault();
        return false;
    }
});

// Si viene preseleccionado un paciente
<?php if ($paciente_preseleccionado): ?>
window.addEventListener('load', function() {
    actualizarResumen();
});
<?php endif; ?>

// Inicializar resumen al cargar
window.addEventListener('load', actualizarResumen);
</script>

<?php require_once '../../includes/footer.php'; ?>