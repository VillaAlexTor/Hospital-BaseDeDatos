<?php
/**
 * modules/inventario/index.php
 * Dashboard del inventario de medicamentos
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

// Habilitar reporte de errores para debugging (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Inventario";

try {
    require_once '../../includes/header.php';

    // Estadísticas del inventario
    $stats_query = "
        SELECT 
            COUNT(*) as total_medicamentos,
            SUM(stock_actual) as total_stock_unidades,
            COUNT(CASE WHEN stock_actual < stock_minimo THEN 1 END) as stock_bajo,
            COUNT(CASE WHEN stock_actual = 0 THEN 1 END) as agotados,
            SUM(stock_actual * precio_unitario) as valor_total_inventario
        FROM medicamento
        WHERE estado = 'Activo'
    ";
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute();
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Últimos movimientos
    $movimientos_query = "
        SELECT 
            mi.id_movimiento,
            mi.tipo_movimiento,
            mi.cantidad,
            mi.fecha_hora,
            m.nombre_generico,
            m.nombre_comercial,
            u.username
        FROM movimiento_inventario mi
        INNER JOIN medicamento m ON mi.id_medicamento = m.id_medicamento
        INNER JOIN usuario u ON mi.id_usuario_registra = u.id_usuario
        ORDER BY mi.fecha_hora DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($movimientos_query);
    $stmt->execute();
    $ultimos_movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CORREGIDO: Alertas de medicamentos próximos a vencer
    // Como no existe tabla lote_medicamento, mostraremos alertas generales
    $vencimiento_query = "
        SELECT 
            a.id_alerta,
            a.descripcion,
            a.fecha_generacion,
            m.nombre_generico,
            m.nombre_comercial,
            m.stock_actual
        FROM alerta_medicamento a
        INNER JOIN medicamento m ON a.id_medicamento = m.id_medicamento
        WHERE a.tipo_alerta IN ('Próximo a vencer', 'Vencido')
        AND a.estado_alerta = 'Pendiente'
        ORDER BY a.fecha_generacion DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($vencimiento_query);
    $stmt->execute();
    $proximos_vencimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Medicamentos más dispensados
    $dispensados_query = "
        SELECT 
            m.id_medicamento,
            m.nombre_generico,
            m.nombre_comercial,
            COALESCE(SUM(dr.cantidad_surtida), 0) as total_dispensado
        FROM medicamento m
        LEFT JOIN detalle_receta dr ON m.id_medicamento = dr.id_medicamento
        WHERE m.estado = 'Activo'
        GROUP BY m.id_medicamento, m.nombre_generico, m.nombre_comercial
        HAVING total_dispensado > 0
        ORDER BY total_dispensado DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($dispensados_query);
    $stmt->execute();
    $mas_dispensados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categorías de medicamentos
    $categorias_query = "
        SELECT 
            COUNT(m.id_medicamento) as cantidad,
            cm.nombre as categoria
        FROM medicamento m
        INNER JOIN categoria_medicamento cm ON m.id_categoria = cm.id_categoria
        WHERE m.estado = 'Activo'
        GROUP BY cm.id_categoria, cm.nombre
        ORDER BY cantidad DESC
    ";
    $stmt = $pdo->prepare($categorias_query);
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Capturar y mostrar error
    die('<div class="alert alert-danger m-5"><h4>Error en Inventario</h4><p>' . $e->getMessage() . '</p></div>');
}
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
                            <i class="fas fa-warehouse text-primary me-2"></i>
                            Inventario de Medicamentos
                        </h1>
                        <p class="text-muted mb-0">Dashboard de control de stock y medicamentos</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="medicamentos.php" class="btn btn-primary">
                            <i class="fas fa-pills me-2"></i>Medicamentos
                        </a>
                        <a href="movimientos.php" class="btn btn-info">
                            <i class="fas fa-exchange-alt me-2"></i>Movimientos
                        </a>
                        <a href="alertas.php" class="btn btn-warning">
                            <i class="fas fa-bell me-2"></i>Alertas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas principales -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Medicamentos</p>
                                <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($estadisticas['total_medicamentos'] ?? 0); ?></h3>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-pills text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Unidades en Stock</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($estadisticas['total_stock_unidades'] ?? 0); ?></h3>
                            </div>
                            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-boxes text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Stock Bajo</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($estadisticas['stock_bajo'] ?? 0); ?></h3>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-danger border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Agotados</p>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo number_format($estadisticas['agotados'] ?? 0); ?></h3>
                            </div>
                            <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-ban text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card de valor total del inventario -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2">
                                    <i class="fas fa-dollar-sign me-2"></i>
                                    Valor Total del Inventario
                                </h6>
                                <h2 class="mb-0 fw-bold">
                                    $<?php echo number_format($estadisticas['valor_total_inventario'] ?? 0, 2); ?>
                                </h2>
                            </div>
                            <i class="fas fa-chart-line fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Últimos movimientos -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-info me-2"></i>
                            Últimos Movimientos
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3">Medicamento</th>
                                        <th class="px-4 py-3">Tipo</th>
                                        <th class="px-4 py-3 text-center">Cantidad</th>
                                        <th class="px-4 py-3">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($ultimos_movimientos)): ?>
                                        <?php foreach ($ultimos_movimientos as $mov): ?>
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <div class="fw-bold small">
                                                        <?php echo htmlspecialchars($mov['nombre_comercial'] ?? $mov['nombre_generico']); ?>
                                                    </div>
                                                    <small class="text-muted">Por: <?php echo htmlspecialchars($mov['username']); ?></small>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php 
                                                        $tipo_badges = [
                                                            'Entrada' => 'success',
                                                            'Salida' => 'danger',
                                                            'Ajuste' => 'info',
                                                            'Devolución' => 'warning',
                                                            'Vencimiento' => 'dark'
                                                        ];
                                                        $badge = $tipo_badges[$mov['tipo_movimiento']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge; ?>">
                                                        <?php echo htmlspecialchars($mov['tipo_movimiento']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo number_format($mov['cantidad']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <small>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d/m/y H:i', strtotime($mov['fecha_hora'])); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mt-3 mb-0">Sin movimientos recientes</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="movimientos.php" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-eye me-2"></i>Ver Todos los Movimientos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alertas pendientes -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            Alertas Pendientes
                        </h5>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($proximos_vencimientos)): ?>
                            <?php foreach ($proximos_vencimientos as $venc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1 fw-bold">
                                                <?php echo htmlspecialchars($venc['nombre_comercial'] ?? $venc['nombre_generico']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($venc['descripcion']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-boxes me-1"></i>
                                            Stock: <strong><?php echo number_format($venc['stock_actual']); ?></strong> unidades
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($venc['fecha_generacion'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-0">Sin alertas pendientes</p>
                                <small class="text-muted">Todo está en orden</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="alertas.php" class="btn btn-sm btn-warning w-100">
                            <i class="fas fa-bell me-2"></i>Ver Todas las Alertas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Medicamentos más dispensados -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            Medicamentos Más Dispensados
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mas_dispensados)): ?>
                            <?php 
                                // Encontrar el máximo para calcular porcentajes
                                $max_dispensado = max(array_column($mas_dispensados, 'total_dispensado'));
                            ?>
                            <?php foreach ($mas_dispensados as $index => $med): ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-primary me-2">#<?php echo $index + 1; ?></span>
                                            <strong><?php echo htmlspecialchars($med['nombre_comercial'] ?? $med['nombre_generico']); ?></strong>
                                        </div>
                                        <span class="badge bg-success">
                                            <?php echo number_format($med['total_dispensado']); ?> unidades
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div 
                                            class="progress-bar bg-success" 
                                            role="progressbar" 
                                            style="width: <?php echo ($med['total_dispensado'] / $max_dispensado * 100); ?>%"
                                            aria-valuenow="<?php echo $med['total_dispensado']; ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="<?php echo $max_dispensado; ?>">
                                            <?php echo number_format(($med['total_dispensado'] / $max_dispensado * 100), 1); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line text-muted" style="font-size: 4rem;"></i>
                                <p class="text-muted mt-3 mb-0">Sin datos de dispensación</p>
                                <small class="text-muted">No hay registros de medicamentos dispensados</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Categorías -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-tags text-info me-2"></i>
                            Categorías de Medicamentos
                        </h5>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                        <?php if (!empty($categorias)): ?>
                            <?php foreach ($categorias as $cat): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-folder text-info me-2"></i>
                                        <span><?php echo htmlspecialchars($cat['categoria']); ?></span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill fs-6">
                                        <?php echo number_format($cat['cantidad']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-5">
                                <i class="fas fa-tags text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-0">Sin categorías registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Accesos Rápidos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="medicamentos.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                    <span>Nuevo Medicamento</span>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="movimientos.php?tipo=entrada" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-arrow-down fa-2x d-block mb-2"></i>
                                    <span>Registrar Entrada</span>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="movimientos.php?tipo=salida" class="btn btn-outline-danger w-100 py-3">
                                    <i class="fas fa-arrow-up fa-2x d-block mb-2"></i>
                                    <span>Registrar Salida</span>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="alertas.php" class="btn btn-outline-warning w-100 py-3">
                                    <i class="fas fa-bell fa-2x d-block mb-2"></i>
                                    <span>Ver Alertas</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Auto-refresh cada 5 minutos para mantener datos actualizados
setTimeout(() => {
    location.reload();
}, 300000);

// Animación de números al cargar
document.addEventListener('DOMContentLoaded', () => {
    const statNumbers = document.querySelectorAll('.card-body h3');
    statNumbers.forEach(num => {
        num.style.opacity = '0';
        setTimeout(() => {
            num.style.transition = 'opacity 0.5s ease-in';
            num.style.opacity = '1';
        }, 100);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>