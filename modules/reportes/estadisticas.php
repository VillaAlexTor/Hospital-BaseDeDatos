<?php
/**
 * modules/reportes/estadisticas.php
 * Página de estadísticas detalladas para reportes del sistema
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Estadísticas Detalladas";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor', 'Médico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para ver estadísticas</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';

// Período de análisis (por defecto últimos 30 días)
$periodo = $_GET['periodo'] ?? '30';
$fecha_desde = date('Y-m-d', strtotime("-$periodo days"));
$fecha_hasta = date('Y-m-d');

try {
    // ========== ESTADÍSTICAS GENERALES ==========
    
    // Total de pacientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM paciente");
    $total_pacientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Pacientes por género
    $stmt = $pdo->query("
        SELECT 
            genero,
            COUNT(*) as cantidad
        FROM persona per
        INNER JOIN paciente p ON per.id_persona = p.id_paciente
        WHERE genero IS NOT NULL
        GROUP BY genero
    ");
    $pacientes_genero = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pacientes por rango de edad
    $stmt = $pdo->query("
        SELECT 
            CASE
                WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18 THEN 'Menor de 18'
                WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 18 AND 35 THEN '18-35'
                WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 36 AND 55 THEN '36-55'
                WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) > 55 THEN 'Mayor de 55'
                ELSE 'Sin registro'
            END as rango_edad,
            COUNT(*) as cantidad
        FROM persona per
        INNER JOIN paciente p ON per.id_persona = p.id_paciente
        GROUP BY rango_edad
        ORDER BY 
            CASE rango_edad
                WHEN 'Menor de 18' THEN 1
                WHEN '18-35' THEN 2
                WHEN '36-55' THEN 3
                WHEN 'Mayor de 55' THEN 4
                ELSE 5
            END
    ");
    $pacientes_edad = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== ESTADÍSTICAS DE CITAS ==========
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_cita = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) as confirmadas,
            SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas
        FROM cita 
        WHERE fecha_cita BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $citas_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Citas por mes (últimos 6 meses)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(fecha_cita, '%Y-%m') as mes,
            COUNT(*) as cantidad
        FROM cita
        WHERE fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mes
        ORDER BY mes
    ");
    $citas_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== ESTADÍSTICAS DE INTERNAMIENTOS ==========
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_internamiento = 'En curso' THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado_internamiento = 'Alta médica' THEN 1 ELSE 0 END) as altas,
            AVG(CASE 
                WHEN fecha_alta IS NOT NULL 
                THEN DATEDIFF(fecha_alta, fecha_ingreso) 
                ELSE NULL 
            END) as promedio_dias
        FROM internamiento
        WHERE fecha_ingreso BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $internamiento_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // ========== ESTADÍSTICAS DE INVENTARIO ==========
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM alerta_medicamento 
        WHERE estado_alerta = 'Pendiente'
    ");
    $alertas_activas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("
        SELECT 
            tipo_alerta,
            COUNT(*) as cantidad
        FROM alerta_medicamento
        WHERE estado_alerta = 'Pendiente'
        GROUP BY tipo_alerta
    ");
    $alertas_por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Medicamentos más dispensados (últimos 30 días)
    $stmt = $pdo->query("
        SELECT 
            m.nombre_comercial,
            m.nombre_generico,
            SUM(dr.cantidad_surtida) as total_dispensado
        FROM detalle_receta dr
        INNER JOIN medicamento m ON dr.id_medicamento = m.id_medicamento
        INNER JOIN receta_medica rm ON dr.id_receta = rm.id_receta
        INNER JOIN documento_medico dm ON rm.id_documento = dm.id_documento
        WHERE dm.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY m.id_medicamento, m.nombre_comercial, m.nombre_generico
        ORDER BY total_dispensado DESC
        LIMIT 10
    ");
    $medicamentos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== ESTADÍSTICAS DE PERSONAL ==========
    
    $stmt = $pdo->query("
        SELECT 
            tipo_personal,
            COUNT(*) as cantidad,
            SUM(CASE WHEN estado_laboral = 'activo' THEN 1 ELSE 0 END) as activos
        FROM personal
        GROUP BY tipo_personal
    ");
    $personal_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== ESTADÍSTICAS FINANCIERAS (si existe tabla) ==========
        
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT rm.id_receta) as total_recetas,
            COALESCE(SUM(dr.cantidad_total * m.precio_unitario), 0) as monto_total
        FROM receta_medica rm
        INNER JOIN documento_medico dm ON rm.id_documento = dm.id_documento
        LEFT JOIN detalle_receta dr ON rm.id_receta = dr.id_receta
        LEFT JOIN medicamento m ON dr.id_medicamento = m.id_medicamento
        WHERE dm.fecha_emision BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $financiero_stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $mensaje = 'Error al obtener estadísticas: ' . $e->getMessage();
    $mensaje_tipo = 'danger';
    
    // Inicializar valores por defecto
    $total_pacientes = 0;
    $pacientes_genero = [];
    $pacientes_edad = [];
    $citas_stats = ['total' => 0, 'pendientes' => 0, 'confirmadas' => 0, 'completadas' => 0, 'canceladas' => 0];
    $citas_por_mes = [];
    $internamiento_stats = ['total' => 0, 'activos' => 0, 'altas' => 0, 'promedio_dias' => 0];
    $alertas_activas = 0;
    $alertas_por_tipo = [];
    $medicamentos_top = [];
    $personal_stats = [];
    $financiero_stats = ['total_recetas' => 0, 'monto_total' => 0];
}

log_action('VIEW', 'estadisticas', 'reporte', "Accedió a estadísticas detalladas - Período: $periodo días");
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
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Estadísticas Detalladas
                        </h1>
                        <p class="text-muted mb-0">Análisis completo del sistema y tendencias</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <a href="generar.php" class="btn btn-primary">
                            <i class="fas fa-file-export me-2"></i>Exportar Datos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje -->
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

        <!-- Selector de Período -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Período de Análisis
                        </label>
                    </div>
                    <div class="col-auto">
                        <select name="periodo" class="form-select" onchange="this.form.submit()">
                            <option value="7" <?php echo $periodo === '7' ? 'selected' : ''; ?>>Últimos 7 días</option>
                            <option value="30" <?php echo $periodo === '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                            <option value="90" <?php echo $periodo === '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                            <option value="180" <?php echo $periodo === '180' ? 'selected' : ''; ?>>Últimos 6 meses</option>
                            <option value="365" <?php echo $periodo === '365' ? 'selected' : ''; ?>>Último año</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <div class="alert alert-info mb-0 py-2 px-3">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Del <strong><?php echo date('d/m/Y', strtotime($fecha_desde)); ?></strong> 
                                al <strong><?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></strong>
                            </small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen General -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Pacientes</h6>
                        <h2 class="fw-bold text-primary mb-0"><?php echo number_format($total_pacientes); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Citas (Período)</h6>
                        <h2 class="fw-bold text-success mb-0"><?php echo number_format($citas_stats['total']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-info border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Internamientos</h6>
                        <h2 class="fw-bold text-info mb-0"><?php echo number_format($internamiento_stats['total']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Alertas Activas</h6>
                        <h2 class="fw-bold text-warning mb-0"><?php echo number_format($alertas_activas); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 1: Pacientes -->
        <div class="row g-3 mb-4">
            <!-- Pacientes por Género -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-venus-mars text-primary me-2"></i>
                            Pacientes por Género
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pacientes_genero)): ?>
                            <?php 
                                $total_genero = array_sum(array_column($pacientes_genero, 'cantidad'));
                                $colores = ['M' => 'primary', 'F' => 'danger', 'Otro' => 'info'];
                                $nombres = ['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'];
                            ?>
                            <?php foreach ($pacientes_genero as $pg): ?>
                                <?php 
                                    $porcentaje = ($pg['cantidad'] / $total_genero) * 100;
                                    $color = $colores[$pg['genero']] ?? 'secondary';
                                    $nombre = $nombres[$pg['genero']] ?? $pg['genero'];
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong><?php echo $nombre; ?></strong></span>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo number_format($pg['cantidad']); ?> (<?php echo number_format($porcentaje, 1); ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo number_format($porcentaje, 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sin datos disponibles</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pacientes por Edad -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-birthday-cake text-success me-2"></i>
                            Pacientes por Rango de Edad
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pacientes_edad)): ?>
                            <?php 
                                $total_edad = array_sum(array_column($pacientes_edad, 'cantidad'));
                            ?>
                            <?php foreach ($pacientes_edad as $index => $pe): ?>
                                <?php 
                                    $porcentaje = ($pe['cantidad'] / $total_edad) * 100;
                                    $colores_edad = ['success', 'info', 'warning', 'danger', 'secondary'];
                                    $color = $colores_edad[$index % count($colores_edad)];
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong><?php echo htmlspecialchars($pe['rango_edad']); ?></strong></span>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo number_format($pe['cantidad']); ?> (<?php echo number_format($porcentaje, 1); ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo number_format($porcentaje, 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sin datos disponibles</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 2: Citas y Estado -->
        <div class="row g-3 mb-4">
            <!-- Estados de Citas -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check text-success me-2"></i>
                            Estados de Citas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                            $estados_citas = [
                                ['label' => 'Completadas', 'valor' => $citas_stats['completadas'], 'color' => 'success'],
                                ['label' => 'Confirmadas', 'valor' => $citas_stats['confirmadas'], 'color' => 'info'],
                                ['label' => 'Pendientes', 'valor' => $citas_stats['pendientes'], 'color' => 'warning'],
                                ['label' => 'Canceladas', 'valor' => $citas_stats['canceladas'], 'color' => 'danger']
                            ];
                        ?>
                        <?php foreach ($estados_citas as $ec): ?>
                            <?php if ($citas_stats['total'] > 0): ?>
                                <?php $porcentaje = ($ec['valor'] / $citas_stats['total']) * 100; ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong><?php echo $ec['label']; ?></strong></span>
                                        <span class="badge bg-<?php echo $ec['color']; ?>"><?php echo number_format($ec['valor']); ?> (<?php echo number_format($porcentaje, 1); ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $ec['color']; ?>" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo number_format($porcentaje, 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($citas_stats['total'] === 0): ?>
                            <p class="text-muted text-center py-4">Sin citas en el período</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Estadísticas de Internamiento -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-bed text-info me-2"></i>
                            Estadísticas de Internamiento
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-hospital-user text-info fa-2x mb-2"></i>
                                    <h3 class="fw-bold text-info mb-0"><?php echo number_format($internamiento_stats['activos']); ?></h3>
                                    <small class="text-muted">Activos</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-sign-out-alt text-success fa-2x mb-2"></i>
                                    <h3 class="fw-bold text-success mb-0"><?php echo number_format($internamiento_stats['altas']); ?></h3>
                                    <small class="text-muted">Altas</small>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-calendar-day me-2"></i>
                                            <strong>Promedio de Estadía:</strong>
                                        </div>
                                        <span class="badge bg-info fs-6">
                                            <?php echo number_format($internamiento_stats['promedio_dias'] ?? 0, 1); ?> días
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 3: Top Medicamentos y Personal -->
        <div class="row g-3 mb-4">
            <!-- Top 10 Medicamentos -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-pills text-warning me-2"></i>
                            Top 10 Medicamentos Más Dispensados
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (!empty($medicamentos_top)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($medicamentos_top as $index => $med): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-warning me-2">#<?php echo $index + 1; ?></span>
                                                <strong><?php echo htmlspecialchars($med['nombre_comercial'] ?? $med['nombre_generico']); ?></strong>
                                            </div>
                                            <span class="badge bg-primary"><?php echo number_format($med['total_dispensado']); ?> unidades</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sin datos disponibles</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Personal por Tipo -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-user-tie text-primary me-2"></i>
                            Personal por Tipo
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($personal_stats)): ?>
                            <?php foreach ($personal_stats as $ps): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong><?php echo htmlspecialchars($ps['tipo_personal']); ?></strong></span>
                                        <div>
                                            <span class="badge bg-success me-1"><?php echo $ps['activos']; ?> activos</span>
                                            <span class="badge bg-secondary"><?php echo $ps['cantidad']; ?> total</span>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo ($ps['activos'] / $ps['cantidad']) * 100; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sin datos disponibles</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen Financiero -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-dollar-sign text-success me-2"></i>
                    Resumen Financiero (Período Seleccionado)
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                            <div>
                                <h6 class="text-muted mb-1">Total de Recetas</h6>
                                <h3 class="fw-bold mb-0"><?php echo number_format($financiero_stats['total_recetas']); ?></h3>
                            </div>
                            <i class="fas fa-file-prescription text-primary fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                            <div>
                                <h6 class="text-muted mb-1">Monto Total</h6>
                                <h3 class="fw-bold mb-0 text-success">$<?php echo number_format($financiero_stats['monto_total'], 2); ?></h3>
                            </div>
                            <i class="fas fa-money-bill-wave text-success fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>