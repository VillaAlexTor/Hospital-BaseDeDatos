<?php
/**
 * modules/reportes/index.php
 * Panel de reportes: enlaces a generación y estadísticas
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Reportes y Estadísticas";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor', 'Médico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para acceder a reportes</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';

// Parámetros de fecha
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Validar fechas
if ($from > $to) {
    $mensaje = 'La fecha "Desde" no puede ser mayor que la fecha "Hasta"';
    $mensaje_tipo = 'warning';
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}

// Estadísticas generales en el período seleccionado
try {
    // Pacientes nuevos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM paciente 
        WHERE fecha_primera_consulta BETWEEN ? AND ?
        AND estado_paciente = 'activo'
    ");
    $stmt->execute([$from, $to]);
    $pacientes_nuevos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de pacientes activos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM paciente WHERE estado_paciente = 'activo'");
    $stmt->execute();
    $total_pacientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Citas en el período
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
            SUM(CASE WHEN estado_cita = 'Pendiente' THEN 1 ELSE 0 END) as pendientes
        FROM cita 
        WHERE fecha_cita BETWEEN ? AND ?
    ");
    $stmt->execute([$from, $to]);
    $citas_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Internamientos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_internamiento = 'En curso' THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado_internamiento = 'Alta médica' THEN 1 ELSE 0 END) as altas
        FROM internamiento 
        WHERE fecha_ingreso BETWEEN ? AND ?
    ");
    $stmt->execute([$from, $to]);
    $internamiento_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recetas dispensadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM documento_medico 
        WHERE tipo_documento = 'Receta'
        AND fecha_emision BETWEEN ? AND ?
    ");
    $stmt->execute([$from, $to]);
    $recetas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Movimientos de inventario
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN tipo_movimiento = 'Entrada' THEN 1 ELSE 0 END) as entradas,
            SUM(CASE WHEN tipo_movimiento = 'Salida' THEN 1 ELSE 0 END) as salidas
        FROM movimiento_inventario 
        WHERE DATE(fecha_hora) BETWEEN ? AND ?
    ");
    $stmt->execute([$from, $to]);
    $inventario_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Personal activo
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM personal WHERE estado_laboral = 'activo'");
    $stmt->execute();
    $personal_activo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Top 5 médicos con más citas
    $stmt = $pdo->prepare("
        SELECT 
            per.nombres,
            per.apellidos,
            COUNT(c.id_cita) as total_citas
        FROM cita c
        INNER JOIN personal p ON c.id_medico = p.id_personal
        INNER JOIN persona per ON p.id_personal = per.id_persona
        WHERE c.fecha_cita BETWEEN ? AND ?
        GROUP BY c.id_medico, per.nombres, per.apellidos
        ORDER BY total_citas DESC
        LIMIT 5
    ");
    $stmt->execute([$from, $to]);
    $top_medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Especialidades más demandadas
    $stmt = $pdo->prepare("
        SELECT 
            e.nombre as especialidad,
            COUNT(c.id_cita) as total_citas
        FROM cita c
        INNER JOIN personal p ON c.id_medico = p.id_personal
        INNER JOIN medico m ON p.id_personal = m.id_medico
        INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        WHERE c.fecha_cita BETWEEN ? AND ?
        GROUP BY m.id_especialidad, e.nombre
        ORDER BY total_citas DESC
        LIMIT 5
    ");
    $stmt->execute([$from, $to]);
    $top_especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $mensaje = 'Error al obtener estadísticas: ' . $e->getMessage();
    $mensaje_tipo = 'danger';
    $pacientes_nuevos = $total_pacientes = 0;
    $citas_stats = ['total' => 0, 'completadas' => 0, 'canceladas' => 0, 'pendientes' => 0];
    $internamiento_stats = ['total' => 0, 'activos' => 0, 'altas' => 0];
    $recetas = 0;
    $inventario_stats = ['total' => 0, 'entradas' => 0, 'salidas' => 0];
    $personal_activo = 0;
    $top_medicos = [];
    $top_especialidades = [];
}

log_action('SELECT', 'reporte', null, "Acceso a panel de reportes desde $from a $to");

// Calcular días del período
$fecha_inicio = new DateTime($from);
$fecha_fin = new DateTime($to);
$dias_periodo = $fecha_inicio->diff($fecha_fin)->days + 1;
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
                            <i class="fas fa-chart-pie text-primary me-2"></i>
                            Reportes y Estadísticas
                        </h1>
                        <p class="text-muted mb-0">Panel de control y análisis de datos del sistema</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="generar.php" class="btn btn-primary">
                            <i class="fas fa-file-export me-2"></i>Generar Reporte
                        </a>
                        <a href="estadisticas.php" class="btn btn-success">
                            <i class="fas fa-chart-line me-2"></i>Estadísticas Detalladas
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

        <!-- Filtro de fechas -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Período de Análisis
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-day me-1"></i>
                            Fecha Desde
                        </label>
                        <input 
                            type="date" 
                            name="from" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($from); ?>"
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-day me-1"></i>
                            Fecha Hasta
                        </label>
                        <input 
                            type="date" 
                            name="to" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($to); ?>"
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync-alt me-2"></i>Actualizar
                        </button>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-info mb-0 py-2">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong><?php echo $dias_periodo; ?></strong> días de análisis
                            </small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas Principales -->
        <div class="row g-3 mb-4">
            <!-- Pacientes -->
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted mb-0">Pacientes Nuevos</h6>
                            <i class="fas fa-user-plus text-primary fa-2x opacity-50"></i>
                        </div>
                        <h2 class="mb-1 fw-bold text-primary"><?php echo number_format($pacientes_nuevos); ?></h2>
                        <small class="text-muted">
                            <i class="fas fa-users me-1"></i>
                            Total activos: <?php echo number_format($total_pacientes); ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Citas -->
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted mb-0">Total Citas</h6>
                            <i class="fas fa-calendar-check text-success fa-2x opacity-50"></i>
                        </div>
                        <h2 class="mb-1 fw-bold text-success"><?php echo number_format($citas_stats['total']); ?></h2>
                        <small class="text-success me-2">
                            <i class="fas fa-check-circle me-1"></i><?php echo $citas_stats['completadas']; ?> completadas
                        </small>
                        <small class="text-warning">
                            <i class="fas fa-clock me-1"></i><?php echo $citas_stats['pendientes']; ?> pendientes
                        </small>
                    </div>
                </div>
            </div>

            <!-- Internamientos -->
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-info border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted mb-0">Internamientos</h6>
                            <i class="fas fa-bed text-info fa-2x opacity-50"></i>
                        </div>
                        <h2 class="mb-1 fw-bold text-info"><?php echo number_format($internamiento_stats['total']); ?></h2>
                        <small class="text-info me-2">
                            <i class="fas fa-hospital-user me-1"></i><?php echo $internamiento_stats['activos']; ?> activos
                        </small>
                        <small class="text-muted">
                            <i class="fas fa-sign-out-alt me-1"></i><?php echo $internamiento_stats['altas']; ?> altas
                        </small>
                    </div>
                </div>
            </div>

            <!-- Recetas -->
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted mb-0">Recetas Emitidas</h6>
                            <i class="fas fa-prescription text-warning fa-2x opacity-50"></i>
                        </div>
                        <h2 class="mb-1 fw-bold text-warning"><?php echo number_format($recetas); ?></h2>
                        <small class="text-muted">
                            <i class="fas fa-pills me-1"></i>
                            En el período seleccionado
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas Secundarias -->
        <div class="row g-3 mb-4">
            <!-- Inventario -->
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">
                            <i class="fas fa-boxes text-primary me-2"></i>
                            Movimientos de Inventario
                        </h6>
                        <h3 class="fw-bold text-primary mb-3"><?php echo number_format($inventario_stats['total']); ?></h3>
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted d-block">Entradas</small>
                                <span class="badge bg-success"><?php echo number_format($inventario_stats['entradas']); ?></span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Salidas</small>
                                <span class="badge bg-danger"><?php echo number_format($inventario_stats['salidas']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal -->
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">
                            <i class="fas fa-user-tie text-success me-2"></i>
                            Personal Activo
                        </h6>
                        <h3 class="fw-bold text-success mb-3"><?php echo number_format($personal_activo); ?></h3>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Personal en estado activo en el sistema
                        </small>
                    </div>
                </div>
            </div>

            <!-- Promedio diario -->
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">
                            <i class="fas fa-chart-line text-info me-2"></i>
                            Promedio Diario de Citas
                        </h6>
                        <h3 class="fw-bold text-info mb-3">
                            <?php echo number_format($dias_periodo > 0 ? $citas_stats['total'] / $dias_periodo : 0, 1); ?>
                        </h3>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Basado en <?php echo $dias_periodo; ?> días
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y Rankings -->
        <div class="row g-3 mb-4">
            <!-- Top Médicos -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy text-warning me-2"></i>
                            Top 5 Médicos (por Citas)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_medicos)): ?>
                            <?php foreach ($top_medicos as $index => $medico): ?>
                                <?php
                                    $nombres = decrypt_data($medico['nombres']);
                                    $apellidos = decrypt_data($medico['apellidos']);
                                    $max_citas = $top_medicos[0]['total_citas'];
                                    $porcentaje = ($medico['total_citas'] / $max_citas) * 100;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="badge bg-warning me-2">#<?php echo $index + 1; ?></span>
                                            <strong><?php echo htmlspecialchars($nombres . ' ' . $apellidos); ?></strong>
                                        </div>
                                        <span class="badge bg-primary"><?php echo number_format($medico['total_citas']); ?> citas</span>
                                    </div>
                                    <div class="progress" style="height: 20px;">
                                        <div 
                                            class="progress-bar bg-gradient" 
                                            style="width: <?php echo $porcentaje; ?>%; background: linear-gradient(90deg, #4f46e5, #7c3aed);"
                                            role="progressbar">
                                            <?php echo number_format($porcentaje, 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">Sin datos en el período seleccionado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Especialidades -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-star text-success me-2"></i>
                            Especialidades Más Demandadas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_especialidades)): ?>
                            <?php foreach ($top_especialidades as $index => $esp): ?>
                                <?php
                                    $max_esp = $top_especialidades[0]['total_citas'];
                                    $porcentaje_esp = ($esp['total_citas'] / $max_esp) * 100;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="badge bg-success me-2">#<?php echo $index + 1; ?></span>
                                            <strong><?php echo htmlspecialchars($esp['especialidad']); ?></strong>
                                        </div>
                                        <span class="badge bg-info"><?php echo number_format($esp['total_citas']); ?> citas</span>
                                    </div>
                                    <div class="progress" style="height: 20px;">
                                        <div 
                                            class="progress-bar" 
                                            style="width: <?php echo $porcentaje_esp; ?>%; background: linear-gradient(90deg, #059669, #10b981);"
                                            role="progressbar">
                                            <?php echo number_format($porcentaje_esp, 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-stethoscope text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">Sin datos en el período seleccionado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-bolt text-warning me-2"></i>
                    Accesos Rápidos a Reportes
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="generar.php?tipo=pacientes" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-users fa-2x d-block mb-2"></i>
                            <span>Reporte de Pacientes</span>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="generar.php?tipo=citas" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-calendar-check fa-2x d-block mb-2"></i>
                            <span>Reporte de Citas</span>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="generar.php?tipo=inventario" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-boxes fa-2x d-block mb-2"></i>
                            <span>Reporte de Inventario</span>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="generar.php?tipo=financiero" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-dollar-sign fa-2x d-block mb-2"></i>
                            <span>Reporte Financiero</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>