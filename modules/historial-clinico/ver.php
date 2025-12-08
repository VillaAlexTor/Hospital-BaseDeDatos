<?php
/**
 * modules/historial-clinico/ver.php
 * Ver detalles completos del historial clínico
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

$page_title = "Ver Historial Clínico";
require_once '../../includes/header.php';

// Obtener datos del paciente
$query = "
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        pac.grupo_sanguineo,
        pac.factor_rh,
        pac.estado_paciente,
        pac.fecha_primera_consulta,
        per.nombres,
        per.apellidos,
        per.numero_documento,
        per.tipo_documento,
        per.fecha_nacimiento,
        per.genero,
        per.telefono,
        per.email,
        per.direccion,
        per.ciudad
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE pac.id_paciente = ?
";

$stmt = $pdo->prepare($query);
$stmt->execute([$id_paciente]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    header('Location: index.php');
    exit();
}

// Desencriptar datos
$paciente['nombres'] = decrypt_data($paciente['nombres']);
$paciente['apellidos'] = decrypt_data($paciente['apellidos']);
$paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
$paciente['telefono'] = decrypt_data($paciente['telefono']);
$paciente['email'] = decrypt_data($paciente['email']);
$paciente['direccion'] = decrypt_data($paciente['direccion']);
$fecha_nacimiento = decrypt_data($paciente['fecha_nacimiento']);

// Calcular edad
$edad = '';
if ($fecha_nacimiento) {
    try {
        $fecha_nac = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac)->y;
    } catch (Exception $e) {}
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

// Obtener consultas del paciente
$consultas_query = "
    SELECT 
        c.id_consulta,
        c.fecha_hora_atencion,
        c.diagnostico,
        c.tratamiento,
        c.proxima_cita,
        c.tipo_consulta,
        AES_DECRYPT(m.nombres, ?) as medico_nombres,
        AES_DECRYPT(m.apellidos, ?) as medico_apellidos,
        e.nombre as especialidad
    FROM consulta c
    INNER JOIN persona m ON c.id_medico = m.id_persona
    INNER JOIN especialidad e ON (SELECT id_especialidad FROM medico WHERE id_medico = c.id_medico LIMIT 1) = e.id_especialidad
    WHERE c.id_paciente = ?
    ORDER BY c.fecha_hora_atencion DESC
    LIMIT 10
";

$stmt = $pdo->prepare($consultas_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $id_paciente]);
$consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener internamientos
$internamientos_query = "
    SELECT 
        i.id_internamiento,
        i.fecha_ingreso,
        i.fecha_alta,
        i.diagnostico_ingreso,
        i.estado_internamiento,
        c.numero_cama,
        AES_DECRYPT(m.nombres, ?) as medico_nombres,
        AES_DECRYPT(m.apellidos, ?) as medico_apellidos
    FROM internamiento i
    LEFT JOIN cama c ON i.id_cama = c.id_cama
    LEFT JOIN persona m ON i.id_medico_responsable = m.id_persona
    WHERE i.id_paciente = ?
    ORDER BY i.fecha_ingreso DESC
    LIMIT 5
";

$stmt = $pdo->prepare($internamientos_query);
$stmt->execute([$clave_cifrado, $clave_cifrado, $id_paciente]);
$internamientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar totales
$total_consultas = count($consultas);
$total_internamientos = count($internamientos);
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
                            <i class="fas fa-file-medical text-primary me-2"></i>
                            Historial Clínico
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?>
                            <span class="ms-3">
                                <i class="fas fa-id-card me-1"></i>
                                HC: <strong><?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?></strong>
                            </span>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (has_any_role(['Administrador', 'Médico'])): ?>
                        <a href="editar.php?id=<?php echo $id_paciente; ?>" class="btn btn-success">
                            <i class="fas fa-edit me-2"></i>Editar
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Columna izquierda: Información del paciente -->
            <div class="col-lg-4 mb-4">
                <!-- Card: Datos personales -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>
                            Información Personal
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Avatar -->
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-3x"></i>
                            </div>
                            <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?></h5>
                            <p class="text-muted small mb-0">
                                <?php if ($edad): ?>
                                    <?php echo $edad; ?> años
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Datos -->
                        <div class="border-top pt-3">
                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-id-card me-1"></i>Documento
                                </label>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($paciente['tipo_documento']); ?>: 
                                    <?php echo htmlspecialchars($paciente['numero_documento']); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-venus-mars me-1"></i>Género
                                </label>
                                <div class="fw-bold">
                                    <?php echo $paciente['genero'] === 'M' ? 'Masculino' : 'Femenino'; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-birthday-cake me-1"></i>Fecha de Nacimiento
                                </label>
                                <div class="fw-bold">
                                    <?php if ($fecha_nacimiento): ?>
                                        <?php echo date('d/m/Y', strtotime($fecha_nacimiento)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No registrado</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-phone me-1"></i>Teléfono
                                </label>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($paciente['telefono'] ?: 'No registrado'); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-envelope me-1"></i>Email
                                </label>
                                <div class="fw-bold small">
                                    <?php echo htmlspecialchars($paciente['email'] ?: 'No registrado'); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-map-marker-alt me-1"></i>Dirección
                                </label>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($paciente['direccion'] ?: 'No registrado'); ?>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="text-muted small mb-1">
                                    <i class="fas fa-city me-1"></i>Ciudad
                                </label>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($paciente['ciudad'] ?: 'No registrado'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Información médica -->
                <div class="card shadow-sm border-start border-danger border-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-heartbeat text-danger me-2"></i>
                            Datos Médicos
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Grupo Sanguíneo</label>
                            <div>
                                <?php if ($paciente['grupo_sanguineo']): ?>
                                    <span class="badge bg-danger fs-6">
                                        <?php echo htmlspecialchars($paciente['grupo_sanguineo'] . $paciente['factor_rh']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">No registrado</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Estado del Paciente</label>
                            <div>
                                <?php 
                                    $estado_class = [
                                        'activo' => 'success',
                                        'inactivo' => 'warning',
                                        'fallecido' => 'dark'
                                    ];
                                    $class = $estado_class[$paciente['estado_paciente']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $class; ?>">
                                    <?php echo ucfirst($paciente['estado_paciente']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="text-muted small mb-1">Primera Consulta</label>
                            <div class="fw-bold">
                                <?php echo date('d/m/Y', strtotime($paciente['fecha_primera_consulta'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Historial médico -->
            <div class="col-lg-8">
                <!-- Card: Antecedentes médicos -->
                <?php if ($historial): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-notes-medical text-warning me-2"></i>
                            Antecedentes Médicos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Antecedentes Personales -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-primary border-bottom pb-2">
                                    <i class="fas fa-user-md me-2"></i>
                                    Antecedentes Personales
                                </h6>
                                <p class="text-muted small">
                                    <?php echo nl2br(htmlspecialchars($historial['antecedentes_personales'] ?: 'No registrado')); ?>
                                </p>
                            </div>

                            <!-- Antecedentes Familiares -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-info border-bottom pb-2">
                                    <i class="fas fa-users me-2"></i>
                                    Antecedentes Familiares
                                </h6>
                                <p class="text-muted small">
                                    <?php echo nl2br(htmlspecialchars($historial['antecedentes_familiares'] ?: 'No registrado')); ?>
                                </p>
                            </div>

                            <!-- Cirugías Previas -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-danger border-bottom pb-2">
                                    <i class="fas fa-procedures me-2"></i>
                                    Cirugías Previas
                                </h6>
                                <p class="text-muted small">
                                    <?php echo nl2br(htmlspecialchars($historial['cirugias_previas'] ?: 'No registrado')); ?>
                                </p>
                            </div>

                            <!-- Medicamentos Actuales -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-success border-bottom pb-2">
                                    <i class="fas fa-pills me-2"></i>
                                    Medicamentos Actuales
                                </h6>
                                <p class="text-muted small">
                                    <?php echo nl2br(htmlspecialchars($historial['medicamentos_actuales'] ?: 'No registrado')); ?>
                                </p>
                            </div>

                            <!-- Hábitos -->
                            <div class="col-12">
                                <h6 class="text-warning border-bottom pb-2">
                                    <i class="fas fa-heartbeat me-2"></i>
                                    Hábitos y Estilo de Vida
                                </h6>
                                <p class="text-muted small">
                                    <?php echo nl2br(htmlspecialchars($historial['habitos'] ?: 'No registrado')); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (isset($historial['ultima_actualizacion'])): ?>
                        <div class="border-top pt-3 mt-3">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Última actualización: <?php echo date('d/m/Y H:i', strtotime($historial['ultima_actualizacion'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No hay antecedentes médicos registrados para este paciente.
                    <?php if (has_any_role(['Administrador', 'Médico'])): ?>
                        <a href="editar.php?id=<?php echo $id_paciente; ?>" class="alert-link">Agregar información</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Card: Consultas recientes -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-stethoscope text-primary me-2"></i>
                            Consultas Recientes
                        </h5>
                        <span class="badge bg-primary"><?php echo $total_consultas; ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3">Fecha</th>
                                        <th class="px-4 py-3">Médico</th>
                                        <th class="px-4 py-3">Especialidad</th>
                                        <th class="px-4 py-3">Tipo</th>
                                        <th class="px-4 py-3">Diagnóstico</th>
                                        <th class="px-4 py-3">Próxima Cita</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($consultas)): ?>
                                        <?php foreach ($consultas as $consulta): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <small><?php echo date('d/m/Y', strtotime($consulta['fecha_hora_atencion'])); ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($consulta['fecha_hora_atencion'])); ?></small>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php echo htmlspecialchars($consulta['medico_nombres'] . ' ' . $consulta['medico_apellidos']); ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <small class="text-muted"><?php echo htmlspecialchars($consulta['especialidad']); ?></small>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($consulta['tipo_consulta']); ?></span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <small><?php echo htmlspecialchars(substr($consulta['diagnostico'] ?? 'Sin diagnóstico', 0, 50)) . (strlen($consulta['diagnostico'] ?? '') > 50 ? '...' : ''); ?></small>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php 
                                                        if ($consulta['proxima_cita']) {
                                                            echo '<span class="badge bg-warning text-dark">' . date('d/m/Y', strtotime($consulta['proxima_cita'])) . '</span>';
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Sin consultas registradas
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Card: Internamientos -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-procedures text-warning me-2"></i>
                            Internamientos
                        </h5>
                        <span class="badge bg-warning text-dark"><?php echo $total_internamientos; ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3">Ingreso</th>
                                        <th class="px-4 py-3">Alta</th>
                                        <th class="px-4 py-3">Diagnóstico</th>
                                        <th class="px-4 py-3">Cama</th>
                                        <th class="px-4 py-3">Médico</th>
                                        <th class="px-4 py-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($internamientos)): ?>
                                        <?php foreach ($internamientos as $internamiento): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <?php echo date('d/m/Y', strtotime($internamiento['fecha_ingreso'])); ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php 
                                                        if ($internamiento['fecha_alta']) {
                                                            echo date('d/m/Y', strtotime($internamiento['fecha_alta']));
                                                        } else {
                                                            echo '<span class="badge bg-warning text-dark">En curso</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <small><?php echo htmlspecialchars(substr($internamiento['diagnostico_ingreso'] ?? '', 0, 40)); ?></small>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($internamiento['numero_cama'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <small><?php echo htmlspecialchars($internamiento['medico_nombres'] . ' ' . $internamiento['medico_apellidos']); ?></small>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($internamiento['estado_internamiento']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Sin internamientos registrados
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>