<?php
/**
 * modules/dashboard/admin.php
 * Dashboard Administrativo - CORREGIDO
 */

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Dashboard Administrativo";

// Verificar que sea administrador
if ($_SESSION['rol'] !== 'Administrador') {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a este dashboard';
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit;
}

// ==================== MODO DEBUG ====================
$debug_mode = false; // Cambiar a false en producción
$debug_info = [];

// ==================== ESTADÍSTICAS PRINCIPALES ====================
try {
    // Total de pacientes activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM paciente WHERE estado_paciente = 'activo'");
    $total_pacientes = $stmt->fetchColumn();
    if ($debug_mode) $debug_info['total_pacientes'] = $total_pacientes;

    // Total de citas hoy
    $stmt = $pdo->query("SELECT COUNT(*) FROM cita WHERE fecha_cita = CURDATE() AND estado_cita NOT IN ('Cancelada')");
    $citas_hoy = $stmt->fetchColumn();
    if ($debug_mode) $debug_info['citas_hoy'] = $citas_hoy;

    // Internamientos activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM internamiento WHERE estado_internamiento = 'En curso'");
    $internamientos_activos = $stmt->fetchColumn();
    if ($debug_mode) $debug_info['internamientos_activos'] = $internamientos_activos;

    // Personal activo
    $stmt = $pdo->query("SELECT COUNT(*) FROM personal WHERE estado_laboral = 'activo'");
    $personal_activo = $stmt->fetchColumn();
    if ($debug_mode) $debug_info['personal_activo'] = $personal_activo;

    // ==================== VERIFICAR SI HAY DATOS EN LAS TABLAS ====================
    if ($debug_mode) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM persona");
        $debug_info['total_personas'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM paciente");
        $debug_info['total_pacientes_tabla'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM cita");
        $debug_info['total_citas_tabla'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuario");
        $debug_info['total_usuarios'] = $stmt->fetchColumn();
    }

    // ==================== ALERTAS Y NOTIFICACIONES ====================

    // Medicamentos con stock bajo
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM medicamento 
        WHERE stock_actual <= stock_minimo AND estado = 'Activo'
    ");
    $medicamentos_stock_bajo = $stmt->fetchColumn();

    // No usamos lote_medicamento - tabla eliminada del sistema
    $medicamentos_por_vencer = 0;

    // Citas pendientes de confirmación
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM cita 
        WHERE estado_cita = 'Programada' 
        AND fecha_cita >= CURDATE()
    ");
    $citas_pendientes = $stmt->fetchColumn();

    // ==================== GRÁFICO: CITAS POR MES (últimos 6 meses) ====================
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(fecha_cita, '%Y-%m') as mes,
            COUNT(*) as total
        FROM cita
        WHERE fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha_cita, '%Y-%m')
        ORDER BY mes
    ");
    $citas_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== GRÁFICO: CITAS POR ESTADO (este mes) ====================
    $stmt = $pdo->query("
        SELECT 
            estado_cita,
            COUNT(*) as total
        FROM cita
        WHERE MONTH(fecha_cita) = MONTH(CURDATE())
        AND YEAR(fecha_cita) = YEAR(CURDATE())
        GROUP BY estado_cita
        ORDER BY total DESC
    ");
    $citas_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== TOP 5 MÉDICOS (más citas este mes) ====================
    $stmt = $pdo->query("
        SELECT 
            per.nombres,
            per.apellidos,
            e.nombre as especialidad,
            COUNT(c.id_cita) as total_citas,
            SUM(CASE WHEN c.estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas
        FROM cita c
        INNER JOIN medico m ON c.id_medico = m.id_medico
        INNER JOIN personal p ON m.id_medico = p.id_personal
        INNER JOIN persona per ON p.id_personal = per.id_persona
        INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        WHERE MONTH(c.fecha_cita) = MONTH(CURDATE())
        AND YEAR(c.fecha_cita) = YEAR(CURDATE())
        GROUP BY m.id_medico, per.nombres, per.apellidos, e.nombre
        ORDER BY total_citas DESC
        LIMIT 5
    ");
    $top_medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== ÚLTIMAS ACTIVIDADES ====================
    $stmt = $pdo->query("
        SELECT 
            l.fecha_hora,
            l.accion,
            l.tabla_afectada,
            l.descripcion,
            u.username
        FROM log_auditoria l
        LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
        WHERE l.resultado = 'Éxito'
        ORDER BY l.fecha_hora DESC
        LIMIT 10
    ");
    $ultimas_actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== PRÓXIMAS CITAS (hoy) ====================
    $stmt = $pdo->query("
        SELECT 
            c.hora_cita,
            c.estado_cita,
            per_pac.nombres as paciente_nombres,
            per_pac.apellidos as paciente_apellidos,
            per_med.nombres as medico_nombres,
            per_med.apellidos as medico_apellidos,
            e.nombre as especialidad
        FROM cita c
        INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
        INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
        INNER JOIN medico m ON c.id_medico = m.id_medico
        INNER JOIN personal p ON m.id_medico = p.id_personal
        INNER JOIN persona per_med ON p.id_personal = per_med.id_persona
        INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        WHERE c.fecha_cita = CURDATE()
        AND c.estado_cita NOT IN ('Cancelada', 'Atendida')
        ORDER BY c.hora_cita
        LIMIT 8
    ");
    $proximas_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en dashboard administrativo: " . $e->getMessage());
    if ($debug_mode) {
        $debug_info['error'] = $e->getMessage();
        $debug_info['sql_error'] = $e->getCode();
    }
    
    $total_pacientes = $citas_hoy = $internamientos_activos = $personal_activo = 0;
    $medicamentos_stock_bajo = $citas_pendientes = 0;
    $citas_por_mes = $citas_por_estado = $top_medicos = $ultimas_actividades = $proximas_citas = [];
}

require_once '../../includes/header.php';
?>

<?php require_once '../../includes/sidebar.php'; ?>

<main class="container-fluid px-4">
    <!-- Panel de Debug (solo si está activado) -->
    <?php if ($debug_mode && !empty($debug_info)): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <h5 class="alert-heading">
            <i class="bi bi-bug"></i> Modo Debug Activado
        </h5>
        <pre class="mb-0" style="font-size: 0.85rem;"><?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?></pre>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Encabezado -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-speedometer2"></i>
            Dashboard Administrativo
        </h1>
        <div class="text-muted">
            <i class="bi bi-calendar3"></i>
            <?php echo format_date_es('%A, %d de %B de %Y', time()); ?>
        </div>
    </div>

    <!-- Mensaje de bienvenida -->
    <div class="alert alert-info mb-4">
        <h5 class="alert-heading">
            <i class="bi bi-person-circle"></i>
            ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario'); ?>!
        </h5>
        <p class="mb-0">
            Rol: <strong><?php echo htmlspecialchars($_SESSION['rol'] ?? 'Sin rol'); ?></strong> | 
            Vista: <strong>Administrativo</strong>
        </p>
    </div>

    <!-- Mensaje si no hay datos -->
    <?php if ($total_pacientes == 0 && $citas_hoy == 0 && $personal_activo == 0 && $internamientos_activos == 0): ?>
    <div class="alert alert-warning">
        <h5 class="alert-heading">
            <i class="bi bi-exclamation-triangle"></i>
            No hay datos disponibles
        </h5>
        <p>Parece que la base de datos está vacía o no se han registrado datos aún.</p>
        <hr>
        <p class="mb-0">
            <strong>Acciones sugeridas:</strong>
            <ul>
                <li>Registrar pacientes en el módulo de <a href="../pacientes/registrar.php">Pacientes</a></li>
                <li>Registrar personal en el módulo de <a href="../personal/registrar.php">Personal</a></li>
                <li>Programar citas en el módulo de <a href="../citas/programar.php">Citas</a></li>
            </ul>
        </p>
    </div>
    <?php endif; ?>

    <!-- Tarjetas de Estadísticas Principales -->
    <div class="row mb-4">
        <!-- Total Pacientes -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Pacientes
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_pacientes); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/modules/pacientes/index.php" class="btn btn-sm btn-outline-primary">
                            Ver todos <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Citas Hoy -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Citas Hoy
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($citas_hoy); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/modules/citas/index.php" class="btn btn-sm btn-outline-success">
                            Ver calendario <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Internamientos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Internamientos
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($internamientos_activos); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-hospital text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/modules/internamiento/index.php" class="btn btn-sm btn-outline-info">
                            Ver detalles <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Activo -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Personal Activo
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($personal_activo); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-badge text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/modules/personal/index.php" class="btn btn-sm btn-outline-warning">
                            Ver personal <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas Importantes -->
    <?php if ($medicamentos_stock_bajo > 0 || $citas_pendientes > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <h5 class="alert-heading">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Alertas Pendientes
                </h5>
                <ul class="mb-0">
                    <?php if ($medicamentos_stock_bajo > 0): ?>
                    <li>
                        <strong><?php echo $medicamentos_stock_bajo; ?></strong> medicamento(s) con stock bajo
                        <a href="<?php echo SITE_URL; ?>/modules/inventario/alertas.php" class="alert-link">Ver detalles</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($citas_pendientes > 0): ?>
                    <li>
                        <strong><?php echo $citas_pendientes; ?></strong> cita(s) pendientes de confirmar
                        <a href="<?php echo SITE_URL; ?>/modules/citas/index.php?estado=Programada" class="alert-link">Ver citas</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fila de Gráficos -->
    <div class="row mb-4">
        <!-- Gráfico: Citas por Mes -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-graph-up"></i>
                        Evolución de Citas (Últimos 6 meses)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($citas_por_mes)): ?>
                        <p class="text-center text-muted py-4">No hay datos de citas disponibles</p>
                    <?php else: ?>
                        <canvas id="citasPorMesChart" height="80"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gráfico: Citas por Estado -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="bi bi-pie-chart"></i>
                        Estado de Citas (Este mes)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($citas_por_estado)): ?>
                        <p class="text-center text-muted py-4">No hay datos de citas este mes</p>
                    <?php else: ?>
                        <canvas id="citasPorEstadoChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila de Tablas -->
    <div class="row mb-4">
        <!-- Top Médicos -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="bi bi-trophy"></i>
                        Top 5 Médicos (Este mes)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Médico</th>
                                    <th>Especialidad</th>
                                    <th class="text-center">Citas</th>
                                    <th class="text-center">Atendidas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $posicion = 1;
                                foreach ($top_medicos as $medico): 
                                    $porcentaje = $medico['total_citas'] > 0 ? round(($medico['atendidas'] / $medico['total_citas']) * 100) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $posicion++; ?></span>
                                    </td>
                                    <td>
                                        <strong>Dr(a). <?php echo htmlspecialchars(decrypt_data($medico['apellidos'])); ?></strong>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($medico['especialidad']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $medico['total_citas']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">
                                            <?php echo $medico['atendidas']; ?> (<?php echo $porcentaje; ?>%)
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($top_medicos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No hay datos disponibles
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximas Citas Hoy -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="bi bi-clock-history"></i>
                        Próximas Citas Hoy
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($proximas_citas)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <p class="mt-2">No hay citas programadas para hoy</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($proximas_citas as $cita): 
                            $color_estado = [
                                'Programada' => 'warning',
                                'Confirmada' => 'success',
                                'En espera' => 'info'
                            ];
                            $badge_color = $color_estado[$cita['estado_cita']] ?? 'secondary';
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-clock"></i>
                                        <?php echo substr($cita['hora_cita'], 0, 5); ?>
                                    </h6>
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars(decrypt_data($cita['paciente_apellidos']) . ' ' . decrypt_data($cita['paciente_nombres'])); ?></strong>
                                    </p>
                                    <small class="text-muted">
                                        Dr(a). <?php echo htmlspecialchars(decrypt_data($cita['medico_apellidos'])); ?> - 
                                        <?php echo htmlspecialchars($cita['especialidad']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo $cita['estado_cita']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimas Actividades -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-secondary">
                        <i class="bi bi-activity"></i>
                        Actividad Reciente del Sistema
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_actividades as $actividad): ?>
                                <tr>
                                    <td class="text-muted small">
                                        <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_hora'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($actividad['username'] ?? 'Sistema'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($actividad['accion']); ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?php echo htmlspecialchars(substr($actividad['descripcion'], 0, 80)); ?>
                                        <?php if (strlen($actividad['descripcion']) > 80) echo '...'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($ultimas_actividades)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay actividad registrada
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?php echo SITE_URL; ?>/modules/auditoria/index.php" class="btn btn-sm btn-outline-secondary">
                            Ver historial completo <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    /* Estilos adicionales para el dashboard administrativo */
    .border-left-primary {
        border-left: 0.25rem solid #4e73df !important;
    }
    
    .border-left-success {
        border-left: 0.25rem solid #1cc88a !important;
    }
    
    .border-left-info {
        border-left: 0.25rem solid #36b9cc !important;
    }
    
    .border-left-warning {
        border-left: 0.25rem solid #f6c23e !important;
    }
    
    .text-gray-800 {
        color: #5a5c69 !important;
    }
    
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Gráfico de Citas por Mes
const ctxMes = document.getElementById('citasPorMesChart');
if (ctxMes) {
    new Chart(ctxMes, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($citas_por_mes, 'mes')); ?>,
            datasets: [{
                label: 'Citas',
                data: <?php echo json_encode(array_column($citas_por_mes, 'total')); ?>,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
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
                        precision: 0
                    }
                }
            }
        }
    });
}

// Gráfico de Citas por Estado
const ctxEstado = document.getElementById('citasPorEstadoChart');
if (ctxEstado) {
    new Chart(ctxEstado, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($citas_por_estado, 'estado_cita')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($citas_por_estado, 'total')); ?>,
                backgroundColor: [
                    'rgba(34, 197, 94, 0.8)',   // Verde - Confirmada
                    'rgba(251, 191, 36, 0.8)',  // Amarillo - Programada
                    'rgba(168, 85, 247, 0.8)',  // Morado - Atendida
                    'rgba(239, 68, 68, 0.8)',   // Rojo - Cancelada
                    'rgba(59, 130, 246, 0.8)',  // Azul - En espera
                    'rgba(156, 163, 175, 0.8)'  // Gris - No asistió
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// Auto-cerrar alertas después de 5 segundos
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php require_once '../../includes/footer.php'; ?>



