<?php
/**
 * modules/internamiento/registrar.php
 * Registrar nuevo internamiento
 */
// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Registrar Internamiento";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_paciente = $_POST['id_paciente'] ?? '';
    $id_medico = $_POST['id_medico'] ?? '';
    $id_cama = $_POST['id_cama'] ?? '';
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $hora_ingreso = $_POST['hora_ingreso'] ?? '';
    $tipo_internamiento = $_POST['tipo_internamiento'] ?? '';
    $motivo_internamiento = $_POST['motivo_internamiento'] ?? '';
    $diagnostico_ingreso = $_POST['diagnostico_ingreso'] ?? '';

    if (empty($id_paciente) || empty($id_medico) || empty($fecha_ingreso) || empty($tipo_internamiento)) {
        $mensaje = 'Todos los campos requeridos deben ser completados';
        $mensaje_tipo = 'danger';
    } else {
        try {
            // Verificar que el paciente no tenga otro internamiento activo
            $check_query = "SELECT id_internamiento FROM internamiento WHERE id_paciente = ? AND estado_internamiento = 'En curso'";
            $stmt = $pdo->prepare($check_query);
            $stmt->execute([$id_paciente]);
            
            if ($stmt->fetch()) {
                $mensaje = 'El paciente ya tiene un internamiento activo. No se puede registrar otro internamiento hasta que el actual finalice.';
                $mensaje_tipo = 'warning';
            } else {
                $insert_query = "
                    INSERT INTO internamiento (
                        id_paciente,
                        id_cama,
                        id_medico_responsable,
                        fecha_ingreso,
                        hora_ingreso,
                        motivo_internamiento,
                        diagnostico_ingreso,
                        tipo_internamiento,
                        estado_internamiento
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'En curso')
                ";
                
                $stmt = $pdo->prepare($insert_query);
                $stmt->execute([
                    $id_paciente,
                    $id_cama ?: null,
                    $id_medico,
                    $fecha_ingreso,
                    $hora_ingreso ?: null,
                    $motivo_internamiento,
                    $diagnostico_ingreso,
                    $tipo_internamiento
                ]);

                $id_internamiento = $pdo->lastInsertId();

                // Si se asignó cama, marcarla como ocupada
                if (!empty($id_cama)) {
                    $update_cama = "UPDATE cama SET estado_cama = 'Ocupada' WHERE id_cama = ?";
                    $stmt = $pdo->prepare($update_cama);
                    $stmt->execute([$id_cama]);
                }

                $mensaje = 'Internamiento registrado correctamente';
                $mensaje_tipo = 'success';
                
                // Registrar en auditoría
                log_action('INSERT', 'internamiento', $id_internamiento, "Internamiento registrado para paciente #$id_paciente");
                
                // Limpiar formulario
                $_POST = [];
            }
        } catch (Exception $e) {
            $mensaje = 'Error al registrar: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Obtener lista de pacientes
$pacientes_query = "
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        AES_DECRYPT(p.nombres, ?) as nombres,
        AES_DECRYPT(p.apellidos, ?) as apellidos,
        AES_DECRYPT(p.fecha_nacimiento, ?) as fecha_nacimiento
    FROM paciente pac
    INNER JOIN persona p ON pac.id_paciente = p.id_persona
    WHERE p.estado = 'activo' AND pac.estado_paciente = 'activo'
    ORDER BY p.apellidos, p.nombres
";
$stmt = $pdo->prepare($pacientes_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $clave_cifrado]);
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de médicos
$medicos_query = "
    SELECT 
        m.id_medico,
        AES_DECRYPT(p.nombres, ?) as nombres,
        AES_DECRYPT(p.apellidos, ?) as apellidos,
        e.nombre as especialidad
    FROM medico m
    INNER JOIN persona p ON m.id_medico = p.id_persona
    LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE p.estado = 'activo'
    ORDER BY p.apellidos, p.nombres
";
$stmt = $pdo->prepare($medicos_query);
$stmt->execute([$clave_cifrado, $clave_cifrado]);
$medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener camas disponibles agrupadas por sala
$camas_query = "
    SELECT 
        c.id_cama,
        c.numero_cama,
        sal.nombre as sala_nombre,
        h.numero_habitacion,
        h.tipo_habitacion,
        h.precio_dia
    FROM cama c
    INNER JOIN habitacion h ON c.id_habitacion = h.id_habitacion
    INNER JOIN sala sal ON h.id_sala = sal.id_sala
    WHERE c.estado_cama = 'Disponible'
    ORDER BY sal.nombre, h.numero_habitacion, c.numero_cama
";
$stmt = $pdo->prepare($camas_query);
$stmt->execute();
$camas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar camas por sala
$camas_por_sala = [];
foreach ($camas as $cama) {
    $sala = $cama['sala_nombre'];
    if (!isset($camas_por_sala[$sala])) {
        $camas_por_sala[$sala] = [];
    }
    $camas_por_sala[$sala][] = $cama;
}

$tipos_internamiento = [
    'Programado' => 'Programado',
    'Emergencia' => 'Emergencia',
    'Referencia' => 'Referencia'
];
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
                            <i class="fas fa-plus-circle text-success me-2"></i>
                            Registrar Nuevo Internamiento
                        </h1>
                        <p class="text-muted mb-0">Completa los datos del internamiento</p>
                    </div>
                    <div class="d-flex gap-2">
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
                    <?php if ($mensaje_tipo === 'success'): ?>
                        <a href="index.php" class="btn btn-sm btn-success ms-3">
                            <i class="fas fa-list me-1"></i>Ir a Internamientos
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Panel izquierdo: Información adicional -->
            <div class="col-lg-4 mb-4">
                <!-- Card: Ayuda -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Información Importante
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="fw-bold">
                                <i class="fas fa-check-circle text-success me-1"></i>
                                Campos Obligatorios
                            </h6>
                            <ul class="small mb-0">
                                <li>Paciente</li>
                                <li>Médico Responsable</li>
                                <li>Fecha de Ingreso</li>
                                <li>Tipo de Internamiento</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold">
                                <i class="fas fa-lightbulb text-warning me-1"></i>
                                Nota sobre Camas
                            </h6>
                            <p class="small mb-0">
                                La asignación de cama es opcional. Puede asignarla después desde la gestión de habitaciones si no hay disponibilidad inmediata.
                            </p>
                        </div>

                        <div class="mb-0">
                            <h6 class="fw-bold">
                                <i class="fas fa-user-check text-primary me-1"></i>
                                Pacientes Activos
                            </h6>
                            <p class="small mb-0">
                                Solo se muestran pacientes con estado activo en el sistema. El paciente no debe tener otro internamiento en curso.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card: Estadísticas rápidas -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar text-primary me-2"></i>
                            Disponibilidad
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <small class="text-muted d-block">Pacientes Activos</small>
                                <h4 class="mb-0 text-primary"><?php echo count($pacientes); ?></h4>
                            </div>
                            <i class="fas fa-users fa-2x text-primary opacity-25"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <small class="text-muted d-block">Médicos Disponibles</small>
                                <h4 class="mb-0 text-success"><?php echo count($medicos); ?></h4>
                            </div>
                            <i class="fas fa-user-md fa-2x text-success opacity-25"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">Camas Disponibles</small>
                                <h4 class="mb-0 text-info"><?php echo count($camas); ?></h4>
                            </div>
                            <i class="fas fa-bed fa-2x text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel derecho: Formulario -->
            <div class="col-lg-8">
                <form method="POST" id="formInternamiento">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-notes-medical text-primary me-2"></i>
                                Datos del Internamiento
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Paciente -->
                                <div class="col-md-6 mb-4">
                                    <label for="id_paciente" class="form-label fw-bold">
                                        <i class="fas fa-user text-primary me-1"></i>
                                        Paciente <span class="text-danger">*</span>
                                    </label>
                                    <select 
                                        id="id_paciente" 
                                        name="id_paciente" 
                                        class="form-select" 
                                        required
                                    >
                                        <option value="">-- Seleccionar Paciente --</option>
                                        <?php foreach ($pacientes as $paciente): 
                                            $edad = '';
                                            if ($paciente['fecha_nacimiento']) {
                                                $fecha_nac = new DateTime($paciente['fecha_nacimiento']);
                                                $hoy = new DateTime();
                                                $edad = ' - ' . $fecha_nac->diff($hoy)->y . ' años';
                                            }
                                        ?>
                                            <option value="<?php echo $paciente['id_paciente']; ?>"
                                                    data-hc="<?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?>">
                                                    <?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?> 
                                                    (HC: <?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?><?php echo $edad; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Seleccione el paciente a internar
                                    </small>
                                </div>

                                <!-- Médico Responsable -->
                                <div class="col-md-6 mb-4">
                                    <label for="id_medico" class="form-label fw-bold">
                                        <i class="fas fa-user-md text-success me-1"></i>
                                        Médico Responsable <span class="text-danger">*</span>
                                    </label>
                                    <select 
                                        id="id_medico" 
                                        name="id_medico" 
                                        class="form-select" 
                                        required
                                    >
                                        <option value="">-- Seleccionar Médico --</option>
                                        <?php foreach ($medicos as $medico): ?>
                                            <option value="<?php echo $medico['id_medico']; ?>">
                                                <?php echo htmlspecialchars($medico['nombres'] . ' ' . $medico['apellidos']); ?>
                                                <?php if ($medico['especialidad']): ?>
                                                    - <?php echo htmlspecialchars($medico['especialidad']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Médico a cargo del internamiento
                                    </small>
                                </div>

                                <!-- Tipo de Internamiento -->
                                <div class="col-md-6 mb-4">
                                    <label for="tipo_internamiento" class="form-label fw-bold">
                                        <i class="fas fa-clipboard-list text-info me-1"></i>
                                        Tipo de Internamiento <span class="text-danger">*</span>
                                    </label>
                                    <select 
                                        id="tipo_internamiento" 
                                        name="tipo_internamiento" 
                                        class="form-select" 
                                        required
                                    >
                                        <option value="">-- Seleccionar Tipo --</option>
                                        <?php foreach ($tipos_internamiento as $valor => $texto): ?>
                                            <option value="<?php echo $valor; ?>"><?php echo $texto; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Especifique el tipo de ingreso
                                    </small>
                                </div>

                                <!-- Cama -->
                                <div class="col-md-6 mb-4">
                                    <label for="id_cama" class="form-label fw-bold">
                                        <i class="fas fa-bed text-warning me-1"></i>
                                        Cama
                                    </label>
                                    <select 
                                        id="id_cama" 
                                        name="id_cama" 
                                        class="form-select"
                                    >
                                        <option value="">-- Sin asignar (asignar después) --</option>
                                        <?php foreach ($camas_por_sala as $sala => $camas_sala): ?>
                                            <optgroup label="<?php echo htmlspecialchars($sala); ?>">
                                                <?php foreach ($camas_sala as $cama): ?>
                                                    <option value="<?php echo $cama['id_cama']; ?>">
                                                        Hab. <?php echo htmlspecialchars($cama['numero_habitacion']); ?> - 
                                                        Cama <?php echo htmlspecialchars($cama['numero_cama']); ?> 
                                                        (<?php echo htmlspecialchars($cama['tipo_habitacion']); ?>) - 
                                                        $<?php echo number_format($cama['precio_dia'], 2); ?>/día
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Opcional: Puede asignarse después
                                    </small>
                                </div>

                                <!-- Fecha de Ingreso -->
                                <div class="col-md-6 mb-4">
                                    <label for="fecha_ingreso" class="form-label fw-bold">
                                        <i class="fas fa-calendar text-primary me-1"></i>
                                        Fecha de Ingreso <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        type="date" 
                                        id="fecha_ingreso" 
                                        name="fecha_ingreso" 
                                        class="form-control"
                                        value="<?php echo date('Y-m-d'); ?>"
                                        required
                                    >
                                </div>

                                <!-- Hora de Ingreso -->
                                <div class="col-md-6 mb-4">
                                    <label for="hora_ingreso" class="form-label fw-bold">
                                        <i class="fas fa-clock text-secondary me-1"></i>
                                        Hora de Ingreso
                                    </label>
                                    <input 
                                        type="time" 
                                        id="hora_ingreso" 
                                        name="hora_ingreso" 
                                        class="form-control"
                                        value="<?php echo date('H:i'); ?>"
                                    >
                                </div>

                                <!-- Motivo de Internamiento -->
                                <div class="col-12 mb-4">
                                    <label for="motivo_internamiento" class="form-label fw-bold">
                                        <i class="fas fa-comment-medical text-warning me-1"></i>
                                        Motivo del Internamiento
                                    </label>
                                    <textarea 
                                        id="motivo_internamiento" 
                                        name="motivo_internamiento" 
                                        class="form-control" 
                                        rows="3"
                                        placeholder="Describa el motivo del internamiento..."
                                    ><?php echo $_POST['motivo_internamiento'] ?? ''; ?></textarea>
                                    <small class="text-muted">
                                        Indique la razón principal del internamiento
                                    </small>
                                </div>

                                <!-- Diagnóstico de Ingreso -->
                                <div class="col-12 mb-3">
                                    <label for="diagnostico_ingreso" class="form-label fw-bold">
                                        <i class="fas fa-stethoscope text-danger me-1"></i>
                                        Diagnóstico de Ingreso
                                    </label>
                                    <textarea 
                                        id="diagnostico_ingreso" 
                                        name="diagnostico_ingreso" 
                                        class="form-control" 
                                        rows="4"
                                        placeholder="Describa el diagnóstico inicial del paciente..."
                                    ><?php echo $_POST['diagnostico_ingreso'] ?? ''; ?></textarea>
                                    <small class="text-muted">
                                        Incluya los diagnósticos preliminares al momento del ingreso
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Registrar Internamiento
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Confirmación antes de salir si hay cambios sin guardar
let formModified = false;
const form = document.getElementById('formInternamiento');
const inputs = form.querySelectorAll('input, textarea, select');

inputs.forEach(input => {
    input.addEventListener('input', () => {
        formModified = true;
    });
    input.addEventListener('change', () => {
        formModified = true;
    });
});

form.addEventListener('submit', () => {
    formModified = false;
});

window.addEventListener('beforeunload', (e) => {
    if (formModified) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Auto-completar hora actual si no está establecida
document.addEventListener('DOMContentLoaded', () => {
    const horaInput = document.getElementById('hora_ingreso');
    if (!horaInput.value) {
        const ahora = new Date();
        const hora = String(ahora.getHours()).padStart(2, '0');
        const minutos = String(ahora.getMinutes()).padStart(2, '0');
        horaInput.value = `${hora}:${minutos}`;
    }
});

// Validación de fecha (no permitir fechas futuras)
document.getElementById('fecha_ingreso').addEventListener('change', function() {
    const fechaSeleccionada = new Date(this.value);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    if (fechaSeleccionada > hoy) {
        alert('No se puede seleccionar una fecha futura para el ingreso');
        this.value = '<?php echo date('Y-m-d'); ?>';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>