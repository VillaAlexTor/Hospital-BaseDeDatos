<?php
/**
 * modules/inventario/movimientos.php
 * Registro de movimientos de inventario
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Movimientos de Inventario";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar registro de movimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_movimiento = $_POST['tipo_movimiento'] ?? '';
    $id_medicamento = $_POST['id_medicamento'] ?? '';
    $cantidad = $_POST['cantidad'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $costo_unitario = $_POST['costo_unitario'] ?? 0;

    if (empty($tipo_movimiento) || empty($id_medicamento) || empty($cantidad) || empty($motivo)) {
        $mensaje = 'Todos los campos requeridos deben completarse';
        $mensaje_tipo = 'danger';
    } else {
        try {
            // Obtener stock anterior
            $stock_query = "SELECT stock_actual FROM medicamento WHERE id_medicamento = ?";
            $stmt = $pdo->prepare($stock_query);
            $stmt->execute([$id_medicamento]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            $stock_anterior = $stock['stock_actual'] ?? 0;

            // Calcular nuevo stock
            $stock_posterior = $stock_anterior;
            if ($tipo_movimiento === 'Entrada') {
                $stock_posterior = $stock_anterior + $cantidad;
            } elseif (in_array($tipo_movimiento, ['Salida', 'Merma', 'Vencimiento'])) {
                $stock_posterior = $stock_anterior - $cantidad;
            }

            // Insertar movimiento
            $insert = "
                INSERT INTO movimiento_inventario (
                    tipo_movimiento, id_medicamento, cantidad, motivo, 
                    costo_unitario, costo_total, stock_anterior, stock_posterior,
                    id_usuario_registra, fecha_hora
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmt = $pdo->prepare($insert);
            $costo_total = $cantidad * $costo_unitario;
            $stmt->execute([
                $tipo_movimiento, $id_medicamento, $cantidad, $motivo,
                $costo_unitario, $costo_total, $stock_anterior, $stock_posterior,
                $_SESSION['user_id']
            ]);

            // Actualizar stock del medicamento
            $update = "UPDATE medicamento SET stock_actual = ? WHERE id_medicamento = ?";
            $stmt = $pdo->prepare($update);
            $stmt->execute([$stock_posterior, $id_medicamento]);

            $mensaje = 'Movimiento registrado correctamente';
            $mensaje_tipo = 'success';
            $_POST = [];
            
            // Registrar en auditoría
            log_action('INSERT', 'movimiento_inventario', $pdo->lastInsertId(), "Movimiento registrado: $tipo_movimiento");
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Parámetros de filtro
$search = $_GET['search'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir query de filtros
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(m.nombre_generico LIKE ? OR m.nombre_comercial LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($tipo)) {
    $where_conditions[] = "mi.tipo_movimiento = ?";
    $params[] = $tipo;
}

if (!empty($fecha_inicio)) {
    $where_conditions[] = "DATE(mi.fecha_hora) >= ?";
    $params[] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $where_conditions[] = "DATE(mi.fecha_hora) <= ?";
    $params[] = $fecha_fin;
}

$where_sql = empty($where_conditions) ? "1=1" : implode(' AND ', $where_conditions);

// Contar total
$count = "SELECT COUNT(*) as total FROM movimiento_inventario mi INNER JOIN medicamento m ON mi.id_medicamento = m.id_medicamento WHERE $where_sql";
$stmt = $pdo->prepare($count);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);

// Obtener movimientos
$query = "
    SELECT 
        mi.id_movimiento,
        mi.tipo_movimiento,
        mi.cantidad,
        mi.fecha_hora,
        mi.motivo,
        mi.costo_unitario,
        mi.costo_total,
        mi.stock_anterior,
        mi.stock_posterior,
        m.nombre_generico,
        m.nombre_comercial,
        u.username
    FROM movimiento_inventario mi
    INNER JOIN medicamento m ON mi.id_medicamento = m.id_medicamento
    INNER JOIN usuario u ON mi.id_usuario_registra = u.id_usuario
    WHERE $where_sql
    ORDER BY mi.fecha_hora DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener medicamentos para el formulario
$medicamentos_query = "SELECT id_medicamento, nombre_generico, nombre_comercial, precio_unitario FROM medicamento WHERE estado = 'Activo' ORDER BY nombre_generico";
$stmt = $pdo->prepare($medicamentos_query);
$stmt->execute();
$medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipos_movimiento = ['Entrada', 'Salida', 'Ajuste', 'Devolución', 'Merma', 'Vencimiento', 'Transferencia'];
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
                            <i class="fas fa-exchange-alt text-info me-2"></i>
                            Movimientos de Inventario
                        </h1>
                        <p class="text-muted mb-0">Registro de entrada y salida de medicamentos</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                            <i class="fas fa-plus me-2"></i>Registrar Movimiento
                        </button>
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

        <!-- Filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-filter text-primary me-2"></i>
                    Filtros
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-pills me-1"></i>
                            Medicamento
                        </label>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Nombre o código..."
                            class="form-control"
                        >
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>
                            Tipo
                        </label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($tipos_movimiento as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo $tipo === $t ? 'selected' : ''; ?>>
                                    <?php echo $t; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-1"></i>
                            Desde
                        </label>
                        <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" class="form-control">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-1"></i>
                            Hasta
                        </label>
                        <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" class="form-control">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="movimientos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de movimientos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Movimientos
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
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Medicamento</th>
                                <th class="px-4 py-3 text-center">Cantidad</th>
                                <th class="px-4 py-3 text-center">Stock Ant.</th>
                                <th class="px-4 py-3 text-center">Stock Post.</th>
                                <th class="px-4 py-3 text-end">Costo Unit.</th>
                                <th class="px-4 py-3 text-end">Total</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($mov['fecha_hora'])); ?>
                                                <br>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($mov['fecha_hora'])); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php 
                                                $tipo_badges = [
                                                    'Entrada' => 'success',
                                                    'Salida' => 'danger',
                                                    'Ajuste' => 'info',
                                                    'Devolución' => 'warning',
                                                    'Merma' => 'secondary',
                                                    'Vencimiento' => 'dark',
                                                    'Transferencia' => 'primary'
                                                ];
                                                $badge = $tipo_badges[$mov['tipo_movimiento']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($mov['tipo_movimiento']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold small">
                                                <?php echo htmlspecialchars($mov['nombre_comercial'] ?? $mov['nombre_generico']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($mov['nombre_generico']); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge bg-light text-dark border fs-6">
                                                <?php echo number_format($mov['cantidad']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <small><?php echo number_format($mov['stock_anterior']); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <small class="fw-bold"><?php echo number_format($mov['stock_posterior']); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <small>$<?php echo number_format($mov['costo_unitario'], 2); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <strong>$<?php echo number_format($mov['costo_total'], 2); ?></strong>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($mov['username']); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($mov['motivo'], 0, 40) . (strlen($mov['motivo']) > 40 ? '...' : '')); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No se encontraron movimientos</p>
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

<!-- Modal para registrar movimiento -->
<div class="modal fade" id="modalMovimiento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Registrar Movimiento de Inventario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tipo" class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>
                            Tipo de Movimiento
                            <span class="text-danger">*</span>
                        </label>
                        <select id="tipo" name="tipo_movimiento" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($tipos_movimiento as $t): ?>
                                <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="medicamento" class="form-label fw-bold">
                            <i class="fas fa-pills me-1"></i>
                            Medicamento
                            <span class="text-danger">*</span>
                        </label>
                        <select id="medicamento" name="id_medicamento" class="form-select" required onchange="actualizarPrecio()">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($medicamentos as $med): ?>
                                <option value="<?php echo $med['id_medicamento']; ?>" data-precio="<?php echo $med['precio_unitario']; ?>">
                                    <?php echo htmlspecialchars($med['nombre_comercial'] ?? $med['nombre_generico']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cantidad" class="form-label fw-bold">
                            <i class="fas fa-sort-numeric-up me-1"></i>
                            Cantidad
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" id="cantidad" name="cantidad" class="form-control" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="costo" class="form-label fw-bold">
                            <i class="fas fa-dollar-sign me-1"></i>
                            Costo Unitario
                        </label>
                        <input type="number" id="costo" name="costo_unitario" class="form-control" step="0.01" value="0">
                        <small class="text-muted">Se auto-completa con el precio del medicamento</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivo" class="form-label fw-bold">
                            <i class="fas fa-comment me-1"></i>
                            Motivo
                            <span class="text-danger">*</span>
                        </label>
                        <textarea id="motivo" name="motivo" class="form-control" rows="3" required placeholder="Describa el motivo del movimiento"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function actualizarPrecio() {
    const select = document.getElementById('medicamento');
    const option = select.options[select.selectedIndex];
    const precio = option.getAttribute('data-precio');
    document.getElementById('costo').value = precio || '0';
}

// Limpiar formulario al cerrar modal
document.getElementById('modalMovimiento').addEventListener('hidden.bs.modal', function () {
    document.querySelector('#modalMovimiento form').reset();
});
</script>

<?php require_once '../../includes/footer.php'; ?>