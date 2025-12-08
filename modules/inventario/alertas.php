<?php
/**
 * modules/inventario/alertas.php
 * Gestión de alertas de inventario
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Alertas de Inventario";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar atención de alerta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id_alerta = $_POST['id_alerta'] ?? '';
    $action = $_POST['action'];

    if ($action === 'atender') {
        $observaciones = $_POST['observaciones'] ?? '';
        try {
            $update = "
                UPDATE alerta_medicamento 
                SET estado_alerta = 'Atendida', 
                    fecha_atencion = NOW(),
                    atendido_por = ?,
                    observaciones_atencion = ?
                WHERE id_alerta = ?
            ";
            $stmt = $pdo->prepare($update);
            $stmt->execute([$_SESSION['user_id'], $observaciones, $id_alerta]);
            
            $mensaje = 'Alerta marcada como atendida correctamente';
            $mensaje_tipo = 'success';
            
            // Registrar en auditoría
            log_action('UPDATE', 'alerta_medicamento', $id_alerta, "Alerta atendida");
        } catch (Exception $e) {
            $mensaje = 'Error al atender alerta: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    } elseif ($action === 'ignorar') {
        try {
            $update = "UPDATE alerta_medicamento SET estado_alerta = 'Ignorada' WHERE id_alerta = ?";
            $stmt = $pdo->prepare($update);
            $stmt->execute([$id_alerta]);
            
            $mensaje = 'Alerta ignorada correctamente';
            $mensaje_tipo = 'info';
            
            // Registrar en auditoría
            log_action('UPDATE', 'alerta_medicamento', $id_alerta, "Alerta ignorada");
        } catch (Exception $e) {
            $mensaje = 'Error al ignorar alerta: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Parámetros de filtro
$estado = $_GET['estado'] ?? 'Pendiente';
$tipo = $_GET['tipo'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir query
$where_conditions = [];
$params = [];

if (!empty($estado)) {
    $where_conditions[] = "a.estado_alerta = ?";
    $params[] = $estado;
}

if (!empty($tipo)) {
    $where_conditions[] = "a.tipo_alerta = ?";
    $params[] = $tipo;
}

$where_sql = empty($where_conditions) ? "1=1" : implode(' AND ', $where_conditions);

// Contar total
$count = "SELECT COUNT(*) as total FROM alerta_medicamento a WHERE $where_sql";
$stmt = $pdo->prepare($count);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);

// Obtener alertas
$query = "
    SELECT 
        a.id_alerta,
        a.tipo_alerta,
        a.descripcion,
        a.fecha_generacion,
        a.estado_alerta,
        m.nombre_generico,
        m.nombre_comercial,
        m.stock_actual,
        m.stock_minimo
    FROM alerta_medicamento a
    INNER JOIN medicamento m ON a.id_medicamento = m.id_medicamento
    WHERE $where_sql
    ORDER BY a.fecha_generacion DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas de alertas
$stats_query = "
    SELECT 
        SUM(CASE WHEN estado_alerta = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_alerta = 'En revisión' THEN 1 ELSE 0 END) as en_revision,
        SUM(CASE WHEN estado_alerta = 'Atendida' THEN 1 ELSE 0 END) as atendidas,
        SUM(CASE WHEN estado_alerta = 'Ignorada' THEN 1 ELSE 0 END) as ignoradas,
        SUM(CASE WHEN tipo_alerta = 'Stock bajo' THEN 1 ELSE 0 END) as stock_bajo,
        SUM(CASE WHEN tipo_alerta = 'Próximo a vencer' THEN 1 ELSE 0 END) as proximo_vencer,
        SUM(CASE WHEN tipo_alerta = 'Vencido' THEN 1 ELSE 0 END) as vencido,
        SUM(CASE WHEN tipo_alerta = 'Stock mínimo' THEN 1 ELSE 0 END) as stock_minimo_alcanzado
    FROM alerta_medicamento
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute();
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

$tipos_alerta = [
    'Stock bajo' => 'Stock bajo',
    'Stock mínimo' => 'Stock mínimo',
    'Próximo a vencer' => 'Próximo a vencer',
    'Vencido' => 'Vencido',
    'Faltante' => 'Faltante'
];

$estados_alerta = [
    'Pendiente' => 'Pendiente',
    'En revisión' => 'En revisión',
    'Atendida' => 'Atendida',
    'Ignorada' => 'Ignorada'
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
                            <i class="fas fa-bell text-warning me-2"></i>
                            Alertas de Inventario
                        </h1>
                        <p class="text-muted mb-0">Monitoreo y gestión de alertas de stock y vencimientos</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
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
                    <i class="fas fa-<?php echo $mensaje_tipo === 'success' ? 'check-circle' : ($mensaje_tipo === 'info' ? 'info-circle' : 'exclamation-triangle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas de alertas -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Pendientes</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($estadisticas['pendientes'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-exclamation-circle text-warning fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-danger border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Stock Bajo</p>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo number_format($estadisticas['stock_bajo'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-box-open text-danger fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Próx. Vencer</p>
                                <h3 class="mb-0 fw-bold text-info"><?php echo number_format($estadisticas['proximo_vencer'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-calendar-times text-info fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-dark border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Vencidos</p>
                                <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($estadisticas['vencido'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-times-circle text-dark fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Atendidas</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($estadisticas['atendidas'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-check-circle text-success fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-start border-secondary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Ignoradas</p>
                                <h3 class="mb-0 fw-bold text-secondary"><?php echo number_format($estadisticas['ignoradas'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-ban text-secondary fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-filter text-primary me-2"></i>
                    Filtrar Alertas
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-list me-1"></i>
                            Estado
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach ($estados_alerta as $valor => $texto): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $estado === $valor ? 'selected' : ''; ?>>
                                    <?php echo $texto; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>
                            Tipo de Alerta
                        </label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_alerta as $valor => $texto): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $tipo === $valor ? 'selected' : ''; ?>>
                                    <?php echo $texto; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="alertas.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de alertas -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Alertas
                </h5>
                <span class="badge bg-info">
                    <?php echo number_format($total); ?> registros
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Medicamento</th>
                                <th class="px-4 py-3 text-center">Stock</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3">Fecha Generación</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alertas)): ?>
                                <?php foreach ($alertas as $alerta): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($alerta['nombre_comercial'] ?? $alerta['nombre_generico']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($alerta['nombre_generico']); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div>
                                                <span class="badge bg-<?php echo $alerta['stock_actual'] < $alerta['stock_minimo'] ? 'danger' : 'info'; ?>">
                                                    Actual: <?php echo number_format($alerta['stock_actual']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">Mín: <?php echo number_format($alerta['stock_minimo']); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $tipo_badges = [
                                                    'Stock bajo' => 'warning',
                                                    'Stock mínimo' => 'danger',
                                                    'Próximo a vencer' => 'info',
                                                    'Vencido' => 'dark',
                                                    'Faltante' => 'danger'
                                                ];
                                                $badge = $tipo_badges[$alerta['tipo_alerta']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>">
                                                <i class="fas fa-<?php echo $alerta['tipo_alerta'] === 'Vencido' ? 'times-circle' : ($alerta['tipo_alerta'] === 'Stock bajo' ? 'exclamation-triangle' : 'info-circle'); ?> me-1"></i>
                                                <?php echo htmlspecialchars($alerta['tipo_alerta']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small><?php echo htmlspecialchars(substr($alerta['descripcion'], 0, 50) . (strlen($alerta['descripcion']) > 50 ? '...' : '')); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $estado_badges = [
                                                    'Pendiente' => 'warning',
                                                    'En revisión' => 'info',
                                                    'Atendida' => 'success',
                                                    'Ignorada' => 'secondary'
                                                ];
                                                $badge = $estado_badges[$alerta['estado_alerta']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($alerta['estado_alerta']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($alerta['fecha_generacion'])); ?>
                                                <br>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($alerta['fecha_generacion'])); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($alerta['estado_alerta'] === 'Pendiente'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalAtender" 
                                                        onclick="setAlertaId(<?php echo $alerta['id_alerta']; ?>)"
                                                        title="Atender">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-outline-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalIgnorar"
                                                        onclick="setAlertaIdIgnorar(<?php echo $alerta['id_alerta']; ?>)"
                                                        title="Ignorar">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="fas fa-check-circle me-1"></i>Procesada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No hay alertas que mostrar</p>
                                        <small class="text-muted">Intenta cambiar los filtros de búsqueda</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $limit, $total); ?> 
                        de <?php echo number_format($total); ?> resultados
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left me-1"></i>Anterior
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                </li>
                                <?php if ($start > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        <?php echo $total_pages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Siguiente<i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal para atender alerta -->
<div class="modal fade" id="modalAtender" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Atender Alerta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="atender">
                    <input type="hidden" id="id_alerta" name="id_alerta">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Esta alerta será marcada como <strong>Atendida</strong> y se registrará la fecha y hora de atención.
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label fw-bold">
                            <i class="fas fa-comment me-1"></i>
                            Observaciones
                        </label>
                        <textarea 
                            id="observaciones" 
                            name="observaciones" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Describa las acciones tomadas para resolver esta alerta..."
                            required
                        ></textarea>
                        <small class="text-muted">
                            Incluya detalles sobre cómo se resolvió la situación
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Marcar como Atendida
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ignorar alerta -->
<div class="modal fade" id="modalIgnorar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-ban me-2"></i>
                    Ignorar Alerta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="ignorar">
                    <input type="hidden" id="id_alerta_ignorar" name="id_alerta">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¿Estás seguro?</strong>
                        <p class="mb-0 mt-2">Esta alerta será marcada como <strong>Ignorada</strong> y no aparecerá en las alertas pendientes.</p>
                    </div>
                    
                    <p class="text-muted">
                        Solo ignora alertas si estás seguro de que no requieren atención o si ya fueron manejadas por otro medio.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-ban me-2"></i>Sí, Ignorar Alerta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funciones para establecer ID de alerta en los modales
function setAlertaId(id) {
    document.getElementById('id_alerta').value = id;
    document.getElementById('observaciones').value = '';
}

function setAlertaIdIgnorar(id) {
    document.getElementById('id_alerta_ignorar').value = id;
}

// Limpiar formulario al cerrar modal de atender
document.getElementById('modalAtender').addEventListener('hidden.bs.modal', function () {
    document.getElementById('observaciones').value = '';
    document.getElementById('id_alerta').value = '';
});

// Confirmación antes de ignorar alerta
document.querySelectorAll('[data-bs-target="#modalIgnorar"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const alertaId = this.onclick.toString().match(/\d+/)[0];
        setAlertaIdIgnorar(alertaId);
    });
});

// Auto-refresh cada 2 minutos para actualizar alertas
setTimeout(() => {
    location.reload();
}, 120000);
</script>

<?php require_once '../../includes/footer.php'; ?>