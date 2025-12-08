<?php
/**
 * modules/historial-clinico/editar.php
 * Editar historial clínico del paciente
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

// Obtener ID del paciente ANTES de header
$id_paciente = $_GET['id'] ?? null;
if (!$id_paciente) {
    header('Location: index.php');
    exit();
}

$page_title = "Editar Historial Clínico";
require_once '../../includes/header.php';

// Procesar formulario
$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $antecedentes_personales = $_POST['antecedentes_personales'] ?? '';
    $antecedentes_familiares = $_POST['antecedentes_familiares'] ?? '';
    $cirugias_previas = $_POST['cirugias_previas'] ?? '';
    $medicamentos_actuales = $_POST['medicamentos_actuales'] ?? '';
    $habitos = $_POST['habitos'] ?? '';

    // Verificar si existe el historial clínico
    $check_query = "SELECT id_historial FROM historial_clinico WHERE id_paciente = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$id_paciente]);
    $historial_existe = $stmt->fetch(PDO::FETCH_ASSOC);

    try {
        if ($historial_existe) {
            // Actualizar historial existente
            $update_query = "
                UPDATE historial_clinico 
                SET 
                    antecedentes_personales = ?,
                    antecedentes_familiares = ?,
                    cirugias_previas = ?,
                    medicamentos_actuales = ?,
                    habitos = ?,
                    ultima_actualizacion = NOW()
                WHERE id_paciente = ?
            ";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([
                $antecedentes_personales,
                $antecedentes_familiares,
                $cirugias_previas,
                $medicamentos_actuales,
                $habitos,
                $id_paciente
            ]);
            $mensaje = 'Historial actualizado correctamente';
            $mensaje_tipo = 'success';
        } else {
            // Crear nuevo historial
            $insert_query = "
                INSERT INTO historial_clinico (
                    id_paciente,
                    antecedentes_personales,
                    antecedentes_familiares,
                    cirugias_previas,
                    medicamentos_actuales,
                    habitos,
                    fecha_creacion,
                    ultima_actualizacion
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([
                $id_paciente,
                $antecedentes_personales,
                $antecedentes_familiares,
                $cirugias_previas,
                $medicamentos_actuales,
                $habitos
            ]);
            $mensaje = 'Historial creado correctamente';
            $mensaje_tipo = 'success';
        }
        
        // Registrar en auditoría
        log_action('UPDATE', 'historial_clinico', $id_paciente, "Historial clínico actualizado");
        
    } catch (Exception $e) {
        $mensaje = 'Error al guardar: ' . $e->getMessage();
        $mensaje_tipo = 'danger';
    }
}

// Obtener datos del paciente
$query = "
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        pac.estado_paciente,
        AES_DECRYPT(per.nombres, ?) as nombres,
        AES_DECRYPT(per.apellidos, ?) as apellidos,
        AES_DECRYPT(per.numero_documento, ?) as numero_documento,
        per.tipo_documento,
        per.fecha_nacimiento
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE pac.id_paciente = ?
";

$stmt = $pdo->prepare($query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $clave_cifrado, $id_paciente]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    header('Location: index.php');
    exit();
}

// Obtener historial clínico
$historial_query = "
    SELECT 
        antecedentes_personales,
        antecedentes_familiares,
        cirugias_previas,
        medicamentos_actuales,
        habitos,
        fecha_creacion,
        ultima_actualizacion
    FROM historial_clinico
    WHERE id_paciente = ?
";

$stmt = $pdo->prepare($historial_query);
$stmt->execute([$id_paciente]);
$historial = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Contenido Principal -->
<main>
    <div class="container-fluid">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-2">
                            <i class="fas fa-edit text-success me-2"></i>
                            Editar Historial Clínico
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?>
                        </p>
                    </div>
                    <div>
                        <a href="ver.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>Lista
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
            <!-- Información del paciente (sidebar izquierdo) -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>
                            Información del Paciente
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-3x"></i>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Nombre Completo</label>
                            <div class="fw-bold">
                                <?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Documento</label>
                            <div class="fw-bold">
                                <?php echo htmlspecialchars($paciente['tipo_documento']); ?>: 
                                <?php echo htmlspecialchars($paciente['numero_documento']); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Historia Clínica</label>
                            <div>
                                <span class="badge bg-secondary fs-6">
                                    <?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Estado</label>
                            <div>
                                <?php 
                                $estado_class = [
                                    'activo' => 'success',
                                    'inactivo' => 'warning',
                                    'fallecido' => 'danger'
                                ];
                                $class = $estado_class[$paciente['estado_paciente']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $class; ?>">
                                    <?php echo ucfirst($paciente['estado_paciente']); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($historial && isset($historial['ultima_actualizacion'])): ?>
                        <div class="border-top pt-3 mt-3">
                            <label class="text-muted small mb-1">Última Actualización</label>
                            <div class="small">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($historial['ultima_actualizacion'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Formulario de edición (contenido principal) -->
            <div class="col-lg-8">
                <form method="POST" id="formHistorial">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-notes-medical text-warning me-2"></i>
                                Información Médica
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Antecedentes Personales -->
                            <div class="mb-4">
                                <label for="antecedentes_personales" class="form-label fw-bold">
                                    <i class="fas fa-user-md me-2 text-primary"></i>
                                    Antecedentes Personales
                                </label>
                                <textarea 
                                    name="antecedentes_personales" 
                                    id="antecedentes_personales"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Ej: Hipertensión arterial desde hace 5 años, Diabetes tipo 2, Asma bronquial..."
                                ><?php echo htmlspecialchars($historial['antecedentes_personales'] ?? ''); ?></textarea>
                                <small class="text-muted">Enfermedades previas, condiciones crónicas del paciente</small>
                            </div>

                            <!-- Antecedentes Familiares -->
                            <div class="mb-4">
                                <label for="antecedentes_familiares" class="form-label fw-bold">
                                    <i class="fas fa-users me-2 text-info"></i>
                                    Antecedentes Familiares
                                </label>
                                <textarea 
                                    name="antecedentes_familiares" 
                                    id="antecedentes_familiares"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Ej: Padre con diabetes, Madre con cáncer de mama, Hermano con hipertensión..."
                                ><?php echo htmlspecialchars($historial['antecedentes_familiares'] ?? ''); ?></textarea>
                                <small class="text-muted">Enfermedades hereditarias o condiciones en familiares directos</small>
                            </div>

                            <!-- Cirugías Previas -->
                            <div class="mb-4">
                                <label for="cirugias_previas" class="form-label fw-bold">
                                    <i class="fas fa-procedures me-2 text-danger"></i>
                                    Cirugías Previas
                                </label>
                                <textarea 
                                    name="cirugias_previas" 
                                    id="cirugias_previas"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Ej: Apendicectomía en 2010, Colecistectomía laparoscópica en 2015, Cesárea en 2018..."
                                ><?php echo htmlspecialchars($historial['cirugias_previas'] ?? ''); ?></textarea>
                                <small class="text-muted">Lista de procedimientos quirúrgicos realizados con fechas aproximadas</small>
                            </div>

                            <!-- Medicamentos Actuales -->
                            <div class="mb-4">
                                <label for="medicamentos_actuales" class="form-label fw-bold">
                                    <i class="fas fa-pills me-2 text-success"></i>
                                    Medicamentos Actuales
                                </label>
                                <textarea 
                                    name="medicamentos_actuales" 
                                    id="medicamentos_actuales"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Ej: Atorvastatina 20mg cada noche, Metformina 500mg cada 8 horas, Losartán 50mg diario..."
                                ><?php echo htmlspecialchars($historial['medicamentos_actuales'] ?? ''); ?></textarea>
                                <small class="text-muted">Medicamentos que el paciente está tomando actualmente con dosis y frecuencia</small>
                            </div>

                            <!-- Hábitos -->
                            <div class="mb-4">
                                <label for="habitos" class="form-label fw-bold">
                                    <i class="fas fa-heartbeat me-2 text-warning"></i>
                                    Hábitos y Estilo de Vida
                                </label>
                                <textarea 
                                    name="habitos" 
                                    id="habitos"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Ej: Fumador (10 cigarrillos/día), Consume alcohol ocasionalmente, Sedentario, Dieta alta en grasas..."
                                ><?php echo htmlspecialchars($historial['habitos'] ?? ''); ?></textarea>
                                <small class="text-muted">Hábitos como tabaquismo, consumo de alcohol, actividad física, alimentación</small>
                            </div>
                        </div>

                        <div class="card-footer bg-white">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="ver.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Información adicional -->
                <div class="card shadow-sm border-start border-info border-4">
                    <div class="card-body">
                        <h6 class="text-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Recomendaciones
                        </h6>
                        <ul class="small mb-0">
                            <li class="mb-2">Registre toda la información médica relevante del paciente</li>
                            <li class="mb-2">Sea específico con fechas, dosis y tratamientos</li>
                            <li class="mb-2">Actualice regularmente esta información</li>
                            <li class="mb-2">Los cambios quedan registrados en el sistema de auditoría</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Confirmación antes de salir si hay cambios sin guardar
let formModified = false;
const form = document.getElementById('formHistorial');
const inputs = form.querySelectorAll('textarea');

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