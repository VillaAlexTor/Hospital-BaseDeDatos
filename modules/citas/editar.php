<?php
/**
 * modules/citas/editar.php
 * Editar y gestionar cita médica
 */

require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Editar Cita";

// Verificar permisos
if (!tiene_permiso('citas', 'actualizar')) {
    $_SESSION['error_message'] = 'No tienes permisos para editar citas';
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';
$success = '';
$id_cita = $_GET['id'] ?? 0;

// Obtener datos de la cita
$stmt = $pdo->prepare("
    SELECT c.*,
        pac.numero_historia_clinica, pac.grupo_sanguineo,
        per_pac.nombres as paciente_nombres,
        per_pac.apellidos as paciente_apellidos,
        per_pac.telefono as paciente_telefono,
        per_pac.email as paciente_email,
        m.id_medico, m.costo_consulta,
        per_med.nombres as medico_nombres,
        per_med.apellidos as medico_apellidos,
        e.nombre as especialidad, e.id_especialidad
    FROM cita c
    INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
    INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per_med ON p.id_personal = per_med.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE c.id_cita = ?
");
$stmt->execute([$id_cita]);
$cita = $stmt->fetch();

if (!$cita) {
    $_SESSION['error_message'] = 'Cita no encontrada';
    header('Location: index.php');
    exit();
}

// Si es médico, verificar que sea su cita
if ($_SESSION['rol'] === 'Médico') {
    $stmt = $pdo->prepare("
        SELECT m.id_medico FROM medico m
        INNER JOIN personal p ON m.id_medico = p.id_personal
        INNER JOIN persona per ON p.id_personal = per.id_persona
        INNER JOIN usuario u ON per.id_persona = u.id_persona
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $id_medico_sesion = $stmt->fetchColumn();
    
    if ($cita['id_medico'] != $id_medico_sesion) {
        $_SESSION['error_message'] = 'No tienes permiso para editar esta cita';
        header('Location: index.php');
        exit();
    }
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $accion = $_POST['accion'] ?? '';
        
        try {
            $pdo->beginTransaction();

            switch ($accion) {
                case 'actualizar':
                    $fecha_cita = $_POST['fecha_cita'];
                    $hora_cita = $_POST['hora_cita'];
                    $id_medico = $_POST['id_medico'];
                    $tipo_cita = $_POST['tipo_cita'];
                    $motivo_consulta = sanitize_input($_POST['motivo_consulta']);
                    $observaciones = sanitize_input($_POST['observaciones'] ?? '');
                    $consultorio = sanitize_input($_POST['consultorio'] ?? '');

                    if ($fecha_cita < date('Y-m-d') && $cita['estado_cita'] !== 'Atendida') {
                        throw new Exception("No se pueden reprogramar citas en fechas pasadas");
                    }

                    // Verificar disponibilidad
                    if ($id_medico != $cita['id_medico'] || $fecha_cita != $cita['fecha_cita'] || $hora_cita != $cita['hora_cita']) {
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM cita 
                            WHERE id_medico = ? AND fecha_cita = ? AND hora_cita = ? 
                            AND id_cita != ? AND estado_cita NOT IN ('Cancelada', 'No asistió')
                        ");
                        $stmt->execute([$id_medico, $fecha_cita, $hora_cita, $id_cita]);
                        
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("El médico ya tiene una cita en ese horario");
                        }
                    }

                    $stmt = $pdo->prepare("
                        UPDATE cita SET 
                            id_medico = ?, fecha_cita = ?, hora_cita = ?,
                            tipo_cita = ?, motivo_consulta = ?, observaciones = ?, consultorio = ?
                        WHERE id_cita = ?
                    ");
                    $stmt->execute([
                        $id_medico, $fecha_cita, $hora_cita, $tipo_cita,
                        $motivo_consulta, $observaciones, $consultorio, $id_cita
                    ]);

                    log_action('UPDATE', 'cita', $id_cita, "Cita actualizada");
                    $success = "Cita actualizada exitosamente";
                    break;

                case 'confirmar':
                    $stmt = $pdo->prepare("UPDATE cita SET estado_cita = 'Confirmada' WHERE id_cita = ?");
                    $stmt->execute([$id_cita]);
                    log_action('UPDATE', 'cita', $id_cita, "Cita confirmada");
                    $success = "Cita confirmada exitosamente";
                    break;

                case 'cancelar':
                    $motivo_cancelacion = sanitize_input($_POST['motivo_cancelacion'] ?? '');
                    if (empty($motivo_cancelacion)) {
                        throw new Exception("Debe especificar el motivo de cancelación");
                    }

                    $stmt = $pdo->prepare("
                        UPDATE cita SET 
                            estado_cita = 'Cancelada',
                            fecha_cancelacion = NOW(),
                            motivo_cancelacion = ?
                        WHERE id_cita = ?
                    ");
                    $stmt->execute([$motivo_cancelacion, $id_cita]);
                    log_action('UPDATE', 'cita', $id_cita, "Cita cancelada");
                    $success = "Cita cancelada exitosamente";
                    break;

                case 'marcar_atendida':
                    $stmt = $pdo->prepare("UPDATE cita SET estado_cita = 'Atendida' WHERE id_cita = ?");
                    $stmt->execute([$id_cita]);
                    log_action('UPDATE', 'cita', $id_cita, "Cita marcada como atendida");
                    $success = "Cita marcada como atendida";
                    break;

                case 'marcar_no_asistio':
                    $stmt = $pdo->prepare("UPDATE cita SET estado_cita = 'No asistió' WHERE id_cita = ?");
                    $stmt->execute([$id_cita]);
                    log_action('UPDATE', 'cita', $id_cita, "Paciente no asistió");
                    $success = "Marcado como no asistió";
                    break;
            }

            $pdo->commit();
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM cita WHERE id_cita = ?");
            $stmt->execute([$id_cita]);
            $cita_temp = $stmt->fetch();
            $cita = array_merge($cita, $cita_temp);

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Obtener médicos
$stmt = $pdo->query("
    SELECT m.id_medico, m.costo_consulta,
        per.nombres, per.apellidos, e.nombre as especialidad
    FROM medico m
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE p.estado_laboral = 'activo' AND m.disponible_consulta = 1
    ORDER BY per.apellidos, per.nombres
");
$medicos = $stmt->fetchAll();

$puede_editar = in_array($cita['estado_cita'], ['Programada', 'Confirmada', 'En espera']) 
                && has_any_role(['Administrador', 'Recepcionista']);
$puede_cancelar = !in_array($cita['estado_cita'], ['Cancelada', 'Atendida']) 
                  && has_any_role(['Administrador', 'Recepcionista']);
$puede_cambiar_estado = has_any_role(['Administrador', 'Médico', 'Recepcionista']);

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
                            <i class="fas fa-edit text-primary me-2"></i>
                            Editar Cita Médica
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-hashtag me-1"></i>
                            ID de Cita: <strong><?php echo $id_cita; ?></strong>
                        </p>
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
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Columna principal -->
            <div class="col-lg-8">
                <!-- Estado Actual -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Estado Actual de la Cita
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $color_estado = [
                            'Programada' => 'warning',
                            'Confirmada' => 'success',
                            'Atendida' => 'info',
                            'Cancelada' => 'danger',
                            'En espera' => 'primary',
                            'No asistió' => 'secondary'
                        ];
                        $color = $color_estado[$cita['estado_cita']] ?? 'secondary';
                        
                        $icono_estado = [
                            'Programada' => 'fa-clock',
                            'Confirmada' => 'fa-check-circle',
                            'Atendida' => 'fa-user-check',
                            'Cancelada' => 'fa-times-circle',
                            'En espera' => 'fa-hourglass-half',
                            'No asistió' => 'fa-user-times'
                        ];
                        $icono = $icono_estado[$cita['estado_cita']] ?? 'fa-question-circle';
                        ?>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <p class="text-muted mb-2">Estado:</p>
                                <h3 class="mb-0">
                                    <span class="badge bg-<?php echo $color; ?> fs-5 px-4 py-2">
                                        <i class="fas <?php echo $icono; ?> me-2"></i>
                                        <?php echo $cita['estado_cita']; ?>
                                    </span>
                                </h3>
                            </div>
                            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                <small class="text-muted d-block">Fecha de Registro</small>
                                <p class="mb-0 fw-bold">
                                    <i class="fas fa-calendar-plus text-muted me-2"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($cita['fecha_registro'])); ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($cita['estado_cita'] === 'Cancelada' && !empty($cita['motivo_cancelacion'])): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Motivo de Cancelación
                            </h6>
                            <p class="mb-2"><?php echo htmlspecialchars($cita['motivo_cancelacion']); ?></p>
                            <small>
                                <i class="fas fa-clock me-1"></i>
                                Cancelada el: <?php echo date('d/m/Y H:i', strtotime($cita['fecha_cancelacion'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario de edición -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Datos de la Cita
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formEditarCita">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="accion" value="actualizar">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user-md me-1"></i>Médico <span class="text-danger">*</span>
                                    </label>
                                    <select name="id_medico" class="form-select" required <?php echo !$puede_editar ? 'disabled' : ''; ?>>
                                        <?php foreach ($medicos as $med): ?>
                                        <option value="<?php echo $med['id_medico']; ?>"
                                                <?php echo ($med['id_medico'] == $cita['id_medico']) ? 'selected' : ''; ?>>
                                            Dr(a). <?php echo htmlspecialchars($med['apellidos'] . ' ' . $med['nombres']); ?> - 
                                            <?php echo htmlspecialchars($med['especialidad']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-stethoscope me-1"></i>Tipo de Cita <span class="text-danger">*</span>
                                    </label>
                                    <select name="tipo_cita" class="form-select" required <?php echo !$puede_editar ? 'disabled' : ''; ?>>
                                        <option value="Primera vez" <?php echo ($cita['tipo_cita'] == 'Primera vez') ? 'selected' : ''; ?>>Primera vez</option>
                                        <option value="Control" <?php echo ($cita['tipo_cita'] == 'Control') ? 'selected' : ''; ?>>Control</option>
                                        <option value="Emergencia" <?php echo ($cita['tipo_cita'] == 'Emergencia') ? 'selected' : ''; ?>>Emergencia</option>
                                        <option value="Especializada" <?php echo ($cita['tipo_cita'] == 'Especializada') ? 'selected' : ''; ?>>Especializada</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-calendar me-1"></i>Fecha <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="fecha_cita" class="form-control" 
                                           value="<?php echo $cita['fecha_cita']; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           required <?php echo !$puede_editar ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-clock me-1"></i>Hora <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="hora_cita" class="form-control" 
                                           value="<?php echo substr($cita['hora_cita'], 0, 5); ?>" 
                                           required <?php echo !$puede_editar ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-door-open me-1"></i>Consultorio
                                    </label>
                                    <input type="text" name="consultorio" class="form-control" 
                                           value="<?php echo htmlspecialchars($cita['consultorio']); ?>"
                                           placeholder="Ej: Consultorio 101"
                                           <?php echo !$puede_editar ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-notes-medical me-1"></i>Motivo de Consulta <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="motivo_consulta" class="form-control" rows="3" 
                                              placeholder="Describa el motivo de la consulta..."
                                              required <?php echo !$puede_editar ? 'readonly' : ''; ?>><?php echo htmlspecialchars($cita['motivo_consulta']); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-comment-medical me-1"></i>Observaciones
                                    </label>
                                    <textarea name="observaciones" class="form-control" rows="3"
                                              placeholder="Información adicional o instrucciones..."
                                              <?php echo !$puede_editar ? 'readonly' : ''; ?>><?php echo htmlspecialchars($cita['observaciones']); ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4">

                            <?php if ($puede_editar): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Los campos marcados con <span class="text-danger">*</span> son obligatorios
                                </small>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-lock me-2"></i>
                                <strong>Cita no editable:</strong> Esta cita no puede ser editada debido a su estado actual.
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Cancelar Cita -->
                <?php if ($puede_cancelar): ?>
                <div class="card shadow-sm border-danger mb-4">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-times-circle me-2"></i>
                            Cancelar Cita
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formCancelar">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="accion" value="cancelar">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Motivo de Cancelación <span class="text-danger">*</span>
                                </label>
                                <textarea name="motivo_cancelacion" class="form-control" rows="3" 
                                          placeholder="Explique el motivo de la cancelación..."
                                          required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('¿Está seguro de cancelar esta cita? Esta acción no se puede deshacer.')">
                                <i class="fas fa-ban me-2"></i>Cancelar Cita
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Columna lateral -->
            <div class="col-lg-4">
                <!-- Información del Paciente -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-user text-primary me-2"></i>
                            Información del Paciente
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Nombre Completo</small>
                            <p class="mb-0 fw-bold">
                                <?php echo htmlspecialchars($cita['paciente_apellidos'] . ', ' . $cita['paciente_nombres']); ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Historia Clínica</small>
                            <p class="mb-0">
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($cita['numero_historia_clinica']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <?php if ($cita['grupo_sanguineo']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Grupo Sanguíneo</small>
                            <p class="mb-0">
                                <span class="badge bg-danger">
                                    <i class="fas fa-tint me-1"></i>
                                    <?php echo htmlspecialchars($cita['grupo_sanguineo']); ?>
                                </span>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cita['paciente_telefono']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Teléfono</small>
                            <p class="mb-0">
                                <i class="fas fa-phone text-muted me-2"></i>
                                <?php echo htmlspecialchars($cita['paciente_telefono']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <a href="../pacientes/ver.php?id=<?php echo $cita['id_paciente']; ?>" 
                           class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-user me-2"></i>Ver Ficha Completa
                        </a>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <?php if ($puede_cambiar_estado): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Acciones Rápidas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($cita['estado_cita'] === 'Programada'): ?>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="accion" value="confirmar">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-2"></i>Confirmar Cita
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (in_array($cita['estado_cita'], ['Programada', 'Confirmada', 'En espera'])): ?>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="accion" value="marcar_atendida">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-user-check me-2"></i>Marcar como Atendida
                            </button>
                        </form>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="accion" value="marcar_no_asistio">
                            <button type="submit" class="btn btn-warning w-100" 
                                    onclick="return confirm('¿Confirma que el paciente no asistió a la cita?')">
                                <i class="fas fa-user-times me-2"></i>Marcar No Asistió
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Información del Médico -->
                <div class="card shadow-sm mb-4 bg-light">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-user-md text-primary me-2"></i>
                            Médico Asignado
                        </h6>
                        <p class="mb-2">
                            <strong>Dr(a). <?php echo htmlspecialchars($cita['medico_apellidos'] . ' ' . $cita['medico_nombres']); ?></strong>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-stethoscope me-1"></i>
                            <?php echo htmlspecialchars($cita['especialidad']); ?>
                        </small>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Información Adicional
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <i class="fas fa-calendar-plus text-muted me-2"></i>
                                <strong>Registrada:</strong><br>
                                <span class="ms-4"><?php echo date('d/m/Y H:i', strtotime($cita['fecha_registro'])); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-bell text-muted me-2"></i>
                                <?php if ($cita['recordatorio_enviado']): ?>
                                    <span class="text-success"><strong>Recordatorio enviado</strong></span>
                                <?php else: ?>
                                    <span class="text-muted">Sin recordatorio</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>