<?php
/**
 * modules/dashboard/medico.php
 * Dashboard Médico - DISEÑO MEJORADO
 */

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Mi Panel Médico";

// Verificar que sea médico
if ($_SESSION['rol'] !== 'Médico') {
    $_SESSION['warning_message'] = 'Acceso no autorizado';
    header('Location: index.php');
    exit;
}

// Obtener ID del médico logueado
$stmt = $pdo->prepare("
    SELECT m.id_medico, e.nombre as especialidad
    FROM medico m
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN usuario u ON per.id_persona = u.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE u.id_usuario = ?
");
$stmt->execute([$_SESSION['user_id']]);
$medico_info = $stmt->fetch();

if (!$medico_info) {
    die('<div class="alert alert-danger">No se encontró información del médico</div>');
}

$id_medico = $medico_info['id_medico'];
$especialidad = $medico_info['especialidad'];

// ==================== ESTADÍSTICAS DEL MÉDICO ====================

// Citas de hoy
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cita 
    WHERE id_medico = ? 
    AND fecha_cita = CURDATE()
    AND estado_cita NOT IN ('Cancelada', 'No asistió')
");
$stmt->execute([$id_medico]);
$citas_hoy = $stmt->fetchColumn();

// Citas pendientes (próximos 7 días)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cita 
    WHERE id_medico = ? 
    AND fecha_cita BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND estado_cita IN ('Programada', 'Confirmada')
");
$stmt->execute([$id_medico]);
$citas_proximas = $stmt->fetchColumn();

// Total pacientes atendidos este mes
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT id_paciente) FROM consulta 
    WHERE id_medico = ? 
    AND MONTH(fecha_hora_atencion) = MONTH(CURDATE())
    AND YEAR(fecha_hora_atencion) = YEAR(CURDATE())
");
$stmt->execute([$id_medico]);
$pacientes_mes = $stmt->fetchColumn();

// Pacientes en seguimiento
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT c.id_paciente) 
    FROM consulta c
    WHERE c.id_medico = ?
    AND c.proxima_cita IS NOT NULL
    AND c.proxima_cita >= CURDATE()
");
$stmt->execute([$id_medico]);
$pacientes_seguimiento = $stmt->fetchColumn();

// ==================== AGENDA DE HOY ====================
$stmt = $pdo->prepare("
    SELECT 
        c.id_cita,
        c.hora_cita,
        c.estado_cita,
        c.tipo_cita,
        c.motivo_consulta,
        pac.numero_historia_clinica,
        pac.grupo_sanguineo,
        per.nombres as paciente_nombres,
        per.apellidos as paciente_apellidos,
        per.telefono as paciente_telefono,
        (SELECT COUNT(*) FROM consulta WHERE id_paciente = pac.id_paciente AND id_medico = ?) as consultas_previas
    FROM cita c
    INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE c.id_medico = ?
    AND c.fecha_cita = CURDATE()
    ORDER BY c.hora_cita
");
$stmt->execute([$id_medico, $id_medico]);
$agenda_hoy = $stmt->fetchAll();

// ==================== PRÓXIMAS CITAS (7 días) ====================
$stmt = $pdo->prepare("
    SELECT 
        c.id_cita,
        c.fecha_cita,
        c.hora_cita,
        c.estado_cita,
        c.tipo_cita,
        per.nombres as paciente_nombres,
        per.apellidos as paciente_apellidos,
        pac.numero_historia_clinica
    FROM cita c
    INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE c.id_medico = ?
    AND c.fecha_cita BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND c.estado_cita NOT IN ('Cancelada', 'No asistió')
    ORDER BY c.fecha_cita, c.hora_cita
    LIMIT 10
");
$stmt->execute([$id_medico]);
$proximas_citas = $stmt->fetchAll();

// ==================== PACIENTES RECIENTES ====================
$stmt = $pdo->prepare("
    SELECT 
        c.fecha_hora_atencion,
        c.diagnostico,
        c.proxima_cita,
        per.nombres as paciente_nombres,
        per.apellidos as paciente_apellidos,
        pac.numero_historia_clinica,
        pac.id_paciente
    FROM consulta c
    INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE c.id_medico = ?
    ORDER BY c.fecha_hora_atencion DESC
    LIMIT 8
");
$stmt->execute([$id_medico]);
$consultas_recientes = $stmt->fetchAll();

// ==================== ESTADÍSTICAS MENSUALES ====================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_citas,
        SUM(CASE WHEN estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas,
        SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
        SUM(CASE WHEN estado_cita = 'No asistió' THEN 1 ELSE 0 END) as no_asistieron
    FROM cita
    WHERE id_medico = ?
    AND MONTH(fecha_cita) = MONTH(CURDATE())
    AND YEAR(fecha_cita) = YEAR(CURDATE())
");
$stmt->execute([$id_medico]);
$stats_mes = $stmt->fetch();

// ==================== GRÁFICO: CITAS POR DÍA (última semana) ====================
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(fecha_cita, '%d/%m') as dia,
        COUNT(*) as total
    FROM cita
    WHERE id_medico = ?
    AND fecha_cita BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
    GROUP BY DATE(fecha_cita)
    ORDER BY fecha_cita
");
$stmt->execute([$id_medico]);
$citas_semana = $stmt->fetchAll();

// ==================== HORARIO DE HOY ====================
$dia_semana_map = [
    0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles',
    4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
];
$dia_hoy = $dia_semana_map[date('w')];

$stmt = $pdo->prepare("
    SELECT hora_inicio, hora_fin, consultorio
    FROM horario_medico
    WHERE id_medico = ?
    AND dia_semana = ?
    AND activo = 1
");
$stmt->execute([$id_medico, $dia_hoy]);
$horario_hoy = $stmt->fetchAll();

// Registrar auditoría
log_action('SELECT', 'dashboard', null, 'Acceso al dashboard médico');

require_once '../../includes/header.php';
?>

<?php require_once '../../includes/sidebar.php'; ?>

<main class="container-fluid px-4">
    <!-- Encabezado de Bienvenida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-lg shadow-lg p-6">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-6 fw-bold mb-2">
                            <i class="fas fa-user-md me-3"></i>
                            Mi Panel Médico
                        </h1>
                        <p class="lead mb-1">
                            Dr(a). <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Médico'); ?>
                        </p>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-stethoscope me-2"></i>
                            <strong><?php echo $especialidad; ?></strong>
                        </p>
                        <p class="small mt-2 opacity-75">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo format_date_es('%A, %d de %B de %Y'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <?php if (!empty($horario_hoy)): ?>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <div class="fw-bold mb-2">
                                    <i class="fas fa-clock me-2"></i>Horario de Hoy
                                </div>
                                <?php foreach ($horario_hoy as $horario): ?>
                                <div class="small">
                                    <?php echo substr($horario['hora_inicio'], 0, 5); ?> - 
                                    <?php echo substr($horario['hora_fin'], 0, 5); ?>
                                    <?php if ($horario['consultorio']): ?>
                                        <br><small><?php echo $horario['consultorio']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <i class="fas fa-info-circle"></i>
                                <div class="small">Sin horario asignado hoy</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="small text-white-50 mb-1 text-uppercase fw-bold">Citas Hoy</p>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $citas_hoy; ?></h2>
                            <p class="small mb-0 opacity-75">Programadas</p>
                        </div>
                        <div class="opacity-50">
                            <i class="fas fa-calendar-day fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="small text-white-50 mb-1 text-uppercase fw-bold">Próximos 7 días</p>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $citas_proximas; ?></h2>
                            <p class="small mb-0 opacity-75">Citas programadas</p>
                        </div>
                        <div class="opacity-50">
                            <i class="fas fa-calendar-week fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="small text-white-50 mb-1 text-uppercase fw-bold">Pacientes</p>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $pacientes_mes; ?></h2>
                            <p class="small mb-0 opacity-75">Este mes</p>
                        </div>
                        <div class="opacity-50">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="small text-white-50 mb-1 text-uppercase fw-bold">Seguimiento</p>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $pacientes_seguimiento; ?></h2>
                            <p class="small mb-0 opacity-75">Con control</p>
                        </div>
                        <div class="opacity-50">
                            <i class="fas fa-heartbeat fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen Mensual -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-4">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Resumen del Mes Actual
                    </h5>
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded">
                                <h3 class="text-primary fw-bold mb-1"><?php echo $stats_mes['total_citas']; ?></h3>
                                <p class="small text-muted mb-0">Total de Citas</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded">
                                <h3 class="text-success fw-bold mb-1"><?php echo $stats_mes['atendidas']; ?></h3>
                                <p class="small text-muted mb-0">Atendidas</p>
                                <small class="text-muted">
                                    <?php echo $stats_mes['total_citas'] > 0 ? round(($stats_mes['atendidas'] / $stats_mes['total_citas']) * 100) : 0; ?>%
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded">
                                <h3 class="text-danger fw-bold mb-1"><?php echo $stats_mes['canceladas']; ?></h3>
                                <p class="small text-muted mb-0">Canceladas</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 border rounded">
                                <h3 class="text-secondary fw-bold mb-1"><?php echo $stats_mes['no_asistieron']; ?></h3>
                                <p class="small text-muted mb-0">No Asistieron</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="row">
        <!-- Columna Izquierda -->
        <div class="col-lg-8">
            <!-- Agenda de Hoy -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                            Mi Agenda de Hoy
                        </h5>
                        <span class="badge bg-primary rounded-pill">
                            <?php echo count($agenda_hoy); ?> cita(s)
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($agenda_hoy)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-4x mb-3 opacity-50"></i>
                        <h5>No tienes citas programadas para hoy</h5>
                        <p class="small">Disfruta tu día libre 😊</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($agenda_hoy as $cita): 
                            $color_clase = [
                                'Programada' => 'warning',
                                'Confirmada' => 'success',
                                'En espera' => 'info',
                                'Atendida' => 'secondary'
                            ];
                            $color = $color_clase[$cita['estado_cita']] ?? 'secondary';
                        ?>
                        <div class="list-group-item border-start border-<?php echo $color; ?> border-3 hover-shadow transition">
                            <div class="row align-items-center">
                                <div class="col-md-9">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <h4 class="mb-0 fw-bold text-dark">
                                            <?php echo substr($cita['hora_cita'], 0, 5); ?>
                                        </h4>
                                        <span class="badge bg-<?php echo $color; ?> rounded-pill">
                                            <?php echo $cita['estado_cita']; ?>
                                        </span>
                                        <?php if ($cita['tipo_cita'] === 'Emergencia'): ?>
                                        <span class="badge bg-danger rounded-pill">
                                            <i class="fas fa-exclamation-triangle me-1"></i>URGENTE
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h6 class="mb-2">
                                        <?php echo decrypt_data($cita['paciente_nombres']) . ' ' . decrypt_data($cita['paciente_apellidos']); ?>
                                    </h6>
                                    
                                    <div class="row small text-muted">
                                        <div class="col-md-6">
                                            <i class="fas fa-id-card me-1"></i>
                                            HC: <?php echo $cita['numero_historia_clinica']; ?>
                                        </div>
                                        <?php if ($cita['grupo_sanguineo']): ?>
                                        <div class="col-md-6">
                                            <i class="fas fa-tint me-1"></i>
                                            <?php echo $cita['grupo_sanguineo']; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-6">
                                            <i class="fas fa-stethoscope me-1"></i>
                                            <?php echo $cita['tipo_cita']; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <i class="fas fa-history me-1"></i>
                                            <?php echo $cita['consultas_previas']; ?> consulta(s) previa(s)
                                        </div>
                                    </div>
                                    
                                    <?php if ($cita['motivo_consulta']): ?>
                                    <div class="mt-2 p-2 bg-light rounded small">
                                        <strong>Motivo:</strong> <?php echo htmlspecialchars($cita['motivo_consulta']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-3 text-end">
                                    <a href="../citas/ver.php?id=<?php echo $cita['id_cita']; ?>" 
                                       class="btn btn-sm btn-outline-primary d-block mb-2">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </a>
                                    <?php if ($cita['estado_cita'] !== 'Atendida'): ?>
                                    <a href="../consultas/registrar.php?cita=<?php echo $cita['id_cita']; ?>" 
                                       class="btn btn-sm btn-success d-block">
                                        <i class="fas fa-notes-medical me-1"></i>Atender
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Consultas Recientes -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fas fa-history text-purple me-2"></i>
                        Consultas Recientes
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Fecha</th>
                                    <th class="border-0">Paciente</th>
                                    <th class="border-0">HC</th>
                                    <th class="border-0">Próxima Cita</th>
                                    <th class="border-0 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consultas_recientes)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                        <p class="mb-0">No hay consultas registradas</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($consultas_recientes as $consulta): ?>
                                    <tr>
                                        <td class="small"><?php echo date('d/m/Y', strtotime($consulta['fecha_hora_atencion'])); ?></td>
                                        <td><?php echo decrypt_data($consulta['paciente_nombres']) . ' ' . decrypt_data($consulta['paciente_apellidos']); ?></td>
                                        <td><code><?php echo $consulta['numero_historia_clinica']; ?></code></td>
                                        <td class="small">
                                            <?php if ($consulta['proxima_cita']): ?>
                                                <span class="badge bg-success">
                                                    <?php echo date('d/m/Y', strtotime($consulta['proxima_cita'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Sin programar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="../pacientes/historial.php?id=<?php echo $consulta['id_paciente']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-medical me-1"></i>Historial
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="col-lg-4">
            <!-- Próximas Citas -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fas fa-calendar-alt text-success me-2"></i>
                        Próximas Citas (7 días)
                    </h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($proximas_citas)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-check fa-3x mb-3 opacity-50"></i>
                        <p class="small mb-0">No hay citas programadas</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($proximas_citas as $cita): ?>
                        <div class="border-start border-primary border-3 bg-light p-3 rounded mb-2 hover-shadow transition">
                            <div class="small text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo format_date_es('%A, %d de %B', strtotime($cita['fecha_cita'])); ?>
                            </div>
                            <div class="fw-bold text-dark">
                                <?php echo substr($cita['hora_cita'], 0, 5); ?> - 
                                <?php echo decrypt_data($cita['paciente_apellidos']); ?>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-id-card me-1"></i>
                                HC: <?php echo $cita['numero_historia_clinica']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-0 text-center">
                    <a href="../citas/index.php" class="btn btn-sm btn-outline-primary">
                        Ver todas mis citas <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Gráfico Semanal -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fas fa-chart-line text-warning me-2"></i>
                        Actividad Semanal
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="actividadSemanalChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px);
}

.transition {
    transition: all 0.3s ease;
}

.card {
    transition: all 0.3s ease;
}

.list-group-item {
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Gráfico de Actividad Semanal
const ctx = document.getElementById('actividadSemanalChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($citas_semana, 'dia')); ?>,
        datasets: [{
            label: 'Citas',
            data: <?php echo json_encode(array_column($citas_semana, 'total')); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>