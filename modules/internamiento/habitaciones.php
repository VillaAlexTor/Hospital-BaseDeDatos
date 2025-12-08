<?php
/**
 * modules/internamiento/habitaciones.php
 * Gestión de habitaciones y camas para internamiento
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

$page_title = "Gestionar Habitación";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar cambio de cama
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cama_nueva = $_POST['id_cama'] ?? '';

    if (empty($id_cama_nueva)) {
        $mensaje = 'Debe seleccionar una cama';
        $mensaje_tipo = 'danger';
    } else {
        try {
            // Obtener ID de cama anterior
            $old_cama_query = "SELECT id_cama FROM internamiento WHERE id_internamiento = ?";
            $stmt = $pdo->prepare($old_cama_query);
            $stmt->execute([$id_internamiento]);
            $old_cama = $stmt->fetch(PDO::FETCH_ASSOC);

            // Actualizar internamiento con nueva cama
            $update_query = "UPDATE internamiento SET id_cama = ? WHERE id_internamiento = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([$id_cama_nueva, $id_internamiento]);

            // Si había cama anterior, marcar como disponible
            if ($old_cama && $old_cama['id_cama']) {
                $liberar_cama = "UPDATE cama SET estado_cama = 'Disponible' WHERE id_cama = ?";
                $stmt = $pdo->prepare($liberar_cama);
                $stmt->execute([$old_cama['id_cama']]);
            }

            // Marcar nueva cama como ocupada
            $ocupar_cama = "UPDATE cama SET estado_cama = 'Ocupada' WHERE id_cama = ?";
            $stmt = $pdo->prepare($ocupar_cama);
            $stmt->execute([$id_cama_nueva]);

            $mensaje = 'Cama asignada correctamente';
            $mensaje_tipo = 'success';
            
            // Registrar en auditoría
            log_action('UPDATE', 'internamiento', $id_internamiento, "Cama asignada/cambiada para internamiento #$id_internamiento");
            
            // Limpiar formulario
            $_POST = [];
        } catch (Exception $e) {
            $mensaje = 'Error al cambiar cama: ' . $e->getMessage();
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
        i.fecha_alta,
        i.estado_internamiento,
        i.tipo_internamiento,
        i.diagnostico_ingreso,
        pac.numero_historia_clinica,
        AES_DECRYPT(p.nombres, ?) as nombres,
        AES_DECRYPT(p.apellidos, ?) as apellidos,
        c.id_cama as cama_actual,
        c.numero_cama,
        h.numero_habitacion,
        h.tipo_habitacion,
        h.precio_dia,
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

// Obtener camas disponibles y ocupadas
$camas_query = "
    SELECT 
        c.id_cama,
        c.numero_cama,
        c.estado_cama,
        sal.nombre as sala_nombre,
        h.numero_habitacion,
        h.tipo_habitacion,
        h.precio_dia
    FROM cama c
    INNER JOIN habitacion h ON c.id_habitacion = h.id_habitacion
    INNER JOIN sala sal ON h.id_sala = sal.id_sala
    WHERE c.estado_cama IN ('Disponible', 'Ocupada')
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

// Obtener evoluciones médicas recientes
$evoluciones_query = "
    SELECT 
        e.id_evolucion,
        e.fecha_hora,
        AES_DECRYPT(m.nombres, ?) as medico_nombres,
        AES_DECRYPT(m.apellidos, ?) as medico_apellidos,
        e.condicion_general,
        e.nota_evolucion,
        esp.nombre as especialidad
    FROM evolucion_medica e
    INNER JOIN medico med ON e.id_medico = med.id_medico
    INNER JOIN persona m ON med.id_medico = m.id_persona
    LEFT JOIN especialidad esp ON med.id_especialidad = esp.id_especialidad
    WHERE e.id_internamiento = ?
    ORDER BY e.fecha_hora DESC
    LIMIT 5
";
$stmt = $pdo->prepare($evoluciones_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $id_internamiento]);
$evoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <i class="fas fa-door-open text-primary me-2"></i>
                            Gestionar Habitación
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
                        <a href="evolucion.php?id=<?php echo $id_internamiento; ?>" class="btn btn-info">
                            <i class="fas fa-notes-medical me-2"></i>Evolución
                        </a>
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
                <!-- Card: Info del internamiento -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
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

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Tipo de Internamiento</label>
                            <div>
                                <?php 
                                    $tipo_badges = [
                                        'Programado' => 'info',
                                        'Emergencia' => 'danger',
                                        'Referencia' => 'warning'
                                    ];
                                    $badge = $tipo_badges[$internamiento['tipo_internamiento']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo htmlspecialchars($internamiento['tipo_internamiento']); ?>
                                </span>
                            </div>
                        </div>

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

                        <?php if ($internamiento['cama_actual']): ?>
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Ubicación Actual</label>
                            <div class="alert alert-info mb-0 py-2">
                                <div class="fw-bold">
                                    <i class="fas fa-bed me-1"></i>
                                    Cama <?php echo htmlspecialchars($internamiento['numero_cama']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($internamiento['sala_nombre']); ?> - 
                                    Hab. <?php echo htmlspecialchars($internamiento['numero_habitacion']); ?>
                                    (<?php echo htmlspecialchars($internamiento['tipo_habitacion']); ?>)
                                </small>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        <i class="fas fa-dollar-sign me-1"></i>
                                        $<?php echo number_format($internamiento['precio_dia'], 2); ?>/día
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Ubicación Actual</label>
                            <div class="alert alert-warning mb-0 py-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Sin cama asignada
                            </div>
                        </div>
                        <?php endif; ?>

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

                <!-- Card: Evoluciones recientes -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-history text-info me-2"></i>
                            Evoluciones Recientes
                            <span class="badge bg-info float-end"><?php echo count($evoluciones); ?></span>
                        </h6>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($evoluciones)): ?>
                            <?php foreach ($evoluciones as $evolucion): ?>
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
                                    
                                    <p class="mb-0 small">
                                        <?php echo htmlspecialchars(strlen($evolucion['nota_evolucion']) > 80 ? substr($evolucion['nota_evolucion'], 0, 80) . '...' : $evolucion['nota_evolucion']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-5">
                                <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-0">Sin evoluciones registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($evoluciones)): ?>
                    <div class="card-footer bg-white">
                        <a href="evolucion.php?id=<?php echo $id_internamiento; ?>" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-eye me-2"></i>Ver Todas las Evoluciones
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel derecho: Formulario de asignación de cama -->
            <div class="col-lg-8">
                <form method="POST" id="formCama">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-exchange-alt text-success me-2"></i>
                                <?php echo $internamiento['cama_actual'] ? 'Cambiar Cama' : 'Asignar Cama'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Nota:</strong> Al asignar una nueva cama, la cama anterior (si existe) será liberada automáticamente.
                            </div>

                            <div class="mb-4">
                                <label for="id_cama" class="form-label fw-bold">
                                    <i class="fas fa-bed text-primary me-1"></i>
                                    Seleccionar Cama <span class="text-danger">*</span>
                                </label>
                                <select 
                                    id="id_cama" 
                                    name="id_cama" 
                                    class="form-select form-select-lg" 
                                    required
                                >
                                    <option value="">-- Seleccionar Cama --</option>
                                    <?php foreach ($camas_por_sala as $sala => $camas_sala): ?>
                                        <optgroup label="<?php echo htmlspecialchars($sala); ?>">
                                            <?php foreach ($camas_sala as $cama): ?>
                                                <?php 
                                                    $es_cama_actual = ($cama['id_cama'] == $internamiento['cama_actual']);
                                                    $es_disponible = ($cama['estado_cama'] === 'Disponible');
                                                    $disabled = (!$es_cama_actual && !$es_disponible) ? 'disabled' : '';
                                                ?>
                                                <option 
                                                    value="<?php echo $cama['id_cama']; ?>" 
                                                    <?php echo $es_cama_actual ? 'selected' : ''; ?>
                                                    <?php echo $disabled; ?>
                                                >
                                                    Hab. <?php echo htmlspecialchars($cama['numero_habitacion']); ?> - 
                                                    Cama <?php echo htmlspecialchars($cama['numero_cama']); ?> 
                                                    (<?php echo htmlspecialchars($cama['tipo_habitacion']); ?>) - 
                                                    <?php echo $cama['estado_cama']; ?> - 
                                                    $<?php echo number_format($cama['precio_dia'], 2); ?>/día
                                                    <?php echo $es_cama_actual ? ' [ACTUAL]' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Las camas ocupadas (excepto la actual) no están disponibles para selección.
                                </small>
                            </div>

                            <!-- Vista previa de la cama seleccionada -->
                            <div id="camaPreview" class="d-none">
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-eye text-info me-2"></i>
                                    Vista Previa de la Cama Seleccionada
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Sala</small>
                                            <div class="fw-bold" id="preview-sala"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Habitación</small>
                                            <div class="fw-bold" id="preview-habitacion"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Tipo</small>
                                            <div class="fw-bold" id="preview-tipo"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Número de Cama</small>
                                            <div class="fw-bold" id="preview-numero"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Estado</small>
                                            <div class="fw-bold" id="preview-estado"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded">
                                            <small class="text-muted d-block mb-1">Precio/Día</small>
                                            <div class="fw-bold text-success" id="preview-precio"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $internamiento['cama_actual'] ? 'Cambiar Cama' : 'Asignar Cama'; ?>
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
// Vista previa de cama seleccionada
document.getElementById('id_cama').addEventListener('change', function() {
    const select = this;
    const selectedOption = select.options[select.selectedIndex];
    const preview = document.getElementById('camaPreview');
    
    if (select.value) {
        const text = selectedOption.text;
        
        // Parsear el texto de la opción
        const matches = text.match(/Hab\. (\d+) - Cama (\d+) \(([^)]+)\) - ([^-]+) - \$([0-9.]+)/);
        
        if (matches) {
            const sala = selectedOption.parentElement.label;
            const habitacion = matches[1];
            const numeroCama = matches[2];
            const tipo = matches[3];
            const estado = matches[4].trim();
            const precio = matches[5];
            
            document.getElementById('preview-sala').textContent = sala;
            document.getElementById('preview-habitacion').textContent = 'Habitación ' + habitacion;
            document.getElementById('preview-tipo').textContent = tipo;
            document.getElementById('preview-numero').textContent = 'Cama ' + numeroCama;
            document.getElementById('preview-estado').textContent = estado;
            document.getElementById('preview-precio').textContent = '$' + precio;
            
            preview.classList.remove('d-none');
        }
    } else {
        preview.classList.add('d-none');
    }
});

// Confirmación antes de salir si hay cambios sin guardar
let formModified = false;
const form = document.getElementById('formCama');
const selectCama = document.getElementById('id_cama');

selectCama.addEventListener('change', () => {
    formModified = true;
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

// Mostrar vista previa si hay una cama seleccionada al cargar
window.addEventListener('DOMContentLoaded', () => {
    const selectCama = document.getElementById('id_cama');
    if (selectCama.value) {
        selectCama.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>