<?php
/**
 * modules/internamiento/evolucion.php
 * Registro de evoluciones médicas
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$id_internamiento = $_GET['id'] ?? null;
if (!$id_internamiento) {
    header('Location: index.php');
    exit();
}

$page_title = "Evolución Médica";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar nuevo registro de evolución
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_medico = $_POST['id_medico'] ?? '';
    $nota_evolucion = $_POST['nota_evolucion'] ?? '';
    $condicion_general = $_POST['condicion_general'] ?? '';
    $plan_tratamiento = $_POST['plan_tratamiento'] ?? '';

    if (empty($id_medico) || empty($nota_evolucion) || empty($condicion_general)) {
        $mensaje = 'Todos los campos requeridos deben ser completados';
        $mensaje_tipo = 'danger';
    } else {
        try {
            // Procesar signos vitales como JSON
            $signos_json = null;
            $signos_array = [
                'presion_arterial' => $_POST['presion_arterial'] ?? '',
                'frecuencia_cardiaca' => $_POST['frecuencia_cardiaca'] ?? '',
                'temperatura' => $_POST['temperatura'] ?? '',
                'frecuencia_respiratoria' => $_POST['frecuencia_respiratoria'] ?? '',
                'saturacion_oxigeno' => $_POST['saturacion_oxigeno'] ?? ''
            ];
            
            // Solo guardar si hay al menos un signo vital
            if (array_filter($signos_array)) {
                $signos_json = json_encode($signos_array);
            }

            $insert_query = "
                INSERT INTO evolucion_medica (
                    id_internamiento,
                    id_medico,
                    nota_evolucion,
                    condicion_general,
                    signos_vitales,
                    plan_tratamiento,
                    fecha_hora
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([
                $id_internamiento,
                $id_medico,
                $nota_evolucion,
                $condicion_general,
                $signos_json,
                $plan_tratamiento
            ]);

            $mensaje = 'Evolución médica registrada correctamente';
            $mensaje_tipo = 'success';
            
            // Registrar en auditoría
            log_action('INSERT', 'evolucion_medica', $pdo->lastInsertId(), "Evolución médica registrada para internamiento #$id_internamiento");
            
            // Limpiar formulario
            $_POST = [];
        } catch (Exception $e) {
            $mensaje = 'Error al registrar: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Obtener datos del internamiento
$internamiento_query = "
    SELECT 
        i.id_internamiento,
        i.id_paciente,
        i.fecha_ingreso,
        i.hora_ingreso,
        i.estado_internamiento,
        i.diagnostico_ingreso,
        pac.numero_historia_clinica,
        AES_DECRYPT(p.nombres, ?) as nombres,
        AES_DECRYPT(p.apellidos, ?) as apellidos,
        c.numero_cama,
        sal.nombre as sala_nombre
    FROM internamiento i
    INNER JOIN paciente pac ON i.id_paciente = pac.id_paciente
    INNER JOIN persona p ON pac.id_paciente = p.id_persona
    LEFT JOIN cama c ON i.id_cama = c.id_cama
    LEFT JOIN habitacion h ON c.id_habitacion = h.id_habitacion
    LEFT JOIN sala sal ON h.id_sala = sal.id_sala
    WHERE i.id_internamiento = ?
";
$stmt = $pdo->prepare($internamiento_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $id_internamiento]);
$internamiento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$internamiento) {
    header('Location: index.php');
    exit();
}

// Calcular días de internamiento
$fecha_ingreso = new DateTime($internamiento['fecha_ingreso']);
$fecha_actual = new DateTime();
$dias_internado = $fecha_ingreso->diff($fecha_actual)->days;

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

// Obtener evoluciones anteriores
$evoluciones_query = "
    SELECT 
        e.id_evolucion,
        e.fecha_hora,
        AES_DECRYPT(m.nombres, ?) as medico_nombres,
        AES_DECRYPT(m.apellidos, ?) as medico_apellidos,
        e.condicion_general,
        e.nota_evolucion,
        e.plan_tratamiento,
        e.signos_vitales,
        esp.nombre as especialidad
    FROM evolucion_medica e
    INNER JOIN medico med ON e.id_medico = med.id_medico
    INNER JOIN persona m ON med.id_medico = m.id_persona
    LEFT JOIN especialidad esp ON med.id_especialidad = esp.id_especialidad
    WHERE e.id_internamiento = ?
    ORDER BY e.fecha_hora DESC
    LIMIT 20
";
$stmt = $pdo->prepare($evoluciones_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $id_internamiento]);
$evoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$condiciones = [
    'Estable' => 'Estable',
    'Mejoría' => 'Mejoría',
    'Sin cambios' => 'Sin cambios',
    'Delicado' => 'Delicado',
    'Deterioro' => 'Deterioro',
    'Crítico' => 'Crítico'
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
                            <i class="fas fa-notes-medical text-primary me-2"></i>
                            Evolución Médica
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($internamiento['nombres'] . ' ' . $internamiento['apellidos']); ?>
                            <span class="ms-3">
                                <i class="fas fa-id-card me-1"></i>
                                HC: <strong><?php echo htmlspecialchars($internamiento['numero_historia_clinica']); ?></strong>
                            </span>
                        </p>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Panel izquierdo: Info del internamiento -->
            <div class="col-lg-4 mb-4">
                <!-- Card: Info del paciente -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-bed me-2"></i>
                            Información del Internamiento
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Paciente</label>
                            <div class="fw-bold">
                                <?php echo htmlspecialchars($internamiento['nombres'] . ' ' . $internamiento['apellidos']); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Historia Clínica</label>
                            <div>
                                <span class="badge bg-secondary fs-6">
                                    <?php echo htmlspecialchars($internamiento['numero_historia_clinica']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Fecha de Ingreso</label>
                            <div class="fw-bold">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d/m/Y', strtotime($internamiento['fecha_ingreso'])); ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('H:i', strtotime($internamiento['hora_ingreso'])); ?>
                                </small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Días Internado</label>
                            <div class="fw-bold text-info">
                                <i class="fas fa-hourglass-half me-1"></i>
                                <?php echo $dias_internado; ?> día<?php echo $dias_internado != 1 ? 's' : ''; ?>
                            </div>
                        </div>

                        <?php if ($internamiento['numero_cama']): ?>
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Ubicación</label>
                            <div class="fw-bold">
                                <i class="fas fa-bed text-info me-1"></i>
                                Cama <?php echo htmlspecialchars($internamiento['numero_cama']); ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($internamiento['sala_nombre']); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Estado</label>
                            <div>
                                <?php 
                                    $estado_class = [
                                        'En curso' => 'success',
                                        'Alta médica' => 'info',
                                        'Alta voluntaria' => 'warning',
                                        'Fallecido' => 'dark'
                                    ];
                                    $class = $estado_class[$internamiento['estado_internamiento']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $class; ?>">
                                    <?php echo ucfirst($internamiento['estado_internamiento']); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($internamiento['diagnostico_ingreso']): ?>
                        <div class="mb-0">
                            <label class="text-muted small mb-1">Diagnóstico de Ingreso</label>
                            <div class="small">
                                <?php echo htmlspecialchars($internamiento['diagnostico_ingreso']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card: Evoluciones anteriores -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-history text-info me-2"></i>
                            Evoluciones Anteriores
                            <span class="badge bg-info float-end"><?php echo count($evoluciones); ?></span>
                        </h6>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                        <?php if (!empty($evoluciones)): ?>
                            <?php foreach ($evoluciones as $evolucion): 
                                $signos = json_decode($evolucion['signos_vitales'], true);
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold small">
                                                <?php echo htmlspecialchars($evolucion['medico_nombres'] . ' ' . $evolucion['medico_apellidos']); ?>
                                            </div>
                                            <?php if ($evolucion['especialidad']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($evolucion['especialidad']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('d/m/y H:i', strtotime($evolucion['fecha_hora'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <?php 
                                            $condicion_class = [
                                                'Estable' => 'success',
                                                'Mejoría' => 'info',
                                                'Sin cambios' => 'secondary',
                                                'Delicado' => 'warning',
                                                'Deterioro' => 'danger',
                                                'Crítico' => 'danger'
                                            ];
                                            $class = $condicion_class[$evolucion['condicion_general']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo htmlspecialchars($evolucion['condicion_general']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="mb-2 small">
                                        <?php echo htmlspecialchars(strlen($evolucion['nota_evolucion']) > 100 ? substr($evolucion['nota_evolucion'], 0, 100) . '...' : $evolucion['nota_evolucion']); ?>
                                    </p>
                                    
                                    <?php if ($signos): ?>
                                    <div class="border-top pt-2 mt-2">
                                        <small class="text-muted d-block mb-1"><strong>Signos Vitales:</strong></small>
                                        <div class="small">
                                            <?php if (!empty($signos['presion_arterial'])): ?>
                                                <span class="badge bg-light text-dark me-1">PA: <?php echo htmlspecialchars($signos['presion_arterial']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($signos['frecuencia_cardiaca'])): ?>
                                                <span class="badge bg-light text-dark me-1">FC: <?php echo htmlspecialchars($signos['frecuencia_cardiaca']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($signos['temperatura'])): ?>
                                                <span class="badge bg-light text-dark me-1">T°: <?php echo htmlspecialchars($signos['temperatura']); ?>°C</span>
                                            <?php endif; ?>
                                            <?php if (!empty($signos['saturacion_oxigeno'])): ?>
                                                <span class="badge bg-light text-dark">SpO2: <?php echo htmlspecialchars($signos['saturacion_oxigeno']); ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-5">
                                <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-0">Sin evoluciones anteriores</p>
                                <small class="text-muted">Registra la primera evolución</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel derecho: Formulario -->
            <div class="col-lg-8">
                <form method="POST" id="formEvolucion">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle text-success me-2"></i>
                                Registrar Nueva Evolución
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Médico -->
                                <div class="col-md-6 mb-4">
                                    <label for="id_medico" class="form-label fw-bold">
                                        <i class="fas fa-user-md text-primary me-1"></i>
                                        Médico <span class="text-danger">*</span>
                                    </label>
                                    <select id="id_medico" name="id_medico" class="form-select" required>
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
                                </div>

                                <!-- Condición General -->
                                <div class="col-md-6 mb-4">
                                    <label for="condicion_general" class="form-label fw-bold">
                                        <i class="fas fa-heartbeat text-danger me-1"></i>
                                        Condición General <span class="text-danger">*</span>
                                    </label>
                                    <select id="condicion_general" name="condicion_general" class="form-select" required>
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($condiciones as $valor => $texto): ?>
                                            <option value="<?php echo $valor; ?>"><?php echo $texto; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Nota de Evolución -->
                            <div class="mb-4">
                                <label for="nota_evolucion" class="form-label fw-bold">
                                    <i class="fas fa-notes-medical text-warning me-1"></i>
                                    Nota de Evolución <span class="text-danger">*</span>
                                </label>
                                <textarea 
                                    id="nota_evolucion" 
                                    name="nota_evolucion" 
                                    class="form-control" 
                                    rows="5"
                                    placeholder="Describa detalladamente la evolución clínica del paciente..."
                                    required
                                ><?php echo $_POST['nota_evolucion'] ?? ''; ?></textarea>
                                <small class="text-muted">Incluya síntomas, signos, respuesta al tratamiento, etc.</small>
                            </div>

                            <!-- Signos Vitales -->
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-heartbeat text-info me-2"></i>
                                Signos Vitales (Opcional)
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="presion_arterial" class="form-label">
                                        <i class="fas fa-tachometer-alt me-1"></i>
                                        Presión Arterial
                                    </label>
                                    <input 
                                        type="text" 
                                        id="presion_arterial" 
                                        name="presion_arterial" 
                                        class="form-control"
                                        placeholder="120/80 mmHg"
                                    >
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="frecuencia_cardiaca" class="form-label">
                                        <i class="fas fa-heartbeat me-1"></i>
                                        Frecuencia Cardíaca
                                    </label>
                                    <input 
                                        type="number" 
                                        id="frecuencia_cardiaca" 
                                        name="frecuencia_cardiaca" 
                                        class="form-control"
                                        placeholder="72 bpm"
                                        min="0"
                                        max="300"
                                    >
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="temperatura" class="form-label">
                                        <i class="fas fa-thermometer-half me-1"></i>
                                        Temperatura
                                    </label>
                                    <input 
                                        type="number" 
                                        id="temperatura" 
                                        name="temperatura" 
                                        class="form-control"
                                        step="0.1"
                                        placeholder="37.5 °C"
                                        min="30"
                                        max="45"
                                    >
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="frecuencia_respiratoria" class="form-label">
                                        <i class="fas fa-lungs me-1"></i>
                                        Frecuencia Respiratoria
                                    </label>
                                    <input 
                                        type="number" 
                                        id="frecuencia_respiratoria" 
                                        name="frecuencia_respiratoria" 
                                        class="form-control"
                                        placeholder="16 resp/min"
                                        min="0"
                                        max="100"
                                    >
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="saturacion_oxigeno" class="form-label">
                                        <i class="fas fa-wind me-1"></i>
                                        Saturación O₂
                                    </label>
                                    <input 
                                        type="number" 
                                        id="saturacion_oxigeno" 
                                        name="saturacion_oxigeno" 
                                        class="form-control"
                                        min="0"
                                        max="100"
                                        placeholder="98 %"
                                    >
                                </div>
                            </div>

                            <!-- Plan de Tratamiento -->
                            <div class="mb-3 mt-3">
                                <label for="plan_tratamiento" class="form-label fw-bold">
                                    <i class="fas fa-clipboard-list text-success me-1"></i>
                                    Plan de Tratamiento
                                </label>
                                <textarea 
                                    id="plan_tratamiento" 
                                    name="plan_tratamiento" 
                                    class="form-control" 
                                    rows="4"
                                    placeholder="Describa el plan de tratamiento, medicación, órdenes médicas..."
                                ><?php echo $_POST['plan_tratamiento'] ?? ''; ?></textarea>
                                <small class="text-muted">Incluya medicamentos, procedimientos, estudios solicitados, etc.</small>
                            </div>

                            <input type="hidden" name="signos_vitales" value="1">
                        </div>

                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Registrar Evolución
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
const form = document.getElementById('formEvolucion');
const inputs = form.querySelectorAll('input, textarea, select');

inputs.forEach(input => {
    input.addEventListener('input', () => {
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
</script>

<?php require_once '../../includes/footer.php'; ?>