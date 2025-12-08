<?php
/**
 * modules/inventario/medicamentos.php
 * Gestión de medicamentos en inventario
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Medicamentos";
require_once '../../includes/header.php';

$mensaje = '';
$mensaje_tipo = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'registrar') {
        $codigo = $_POST['codigo_medicamento'] ?? '';
        $nombre_generico = $_POST['nombre_generico'] ?? '';
        $nombre_comercial = $_POST['nombre_comercial'] ?? '';
        $id_categoria = $_POST['id_categoria'] ?? '';
        $presentacion = $_POST['presentacion'] ?? '';
        $concentracion = $_POST['concentracion'] ?? '';
        $precio = $_POST['precio_unitario'] ?? '';
        $stock_minimo = $_POST['stock_minimo'] ?? 10;

        if (empty($codigo) || empty($nombre_generico) || empty($id_categoria) || empty($presentacion) || empty($precio)) {
            $mensaje = 'Todos los campos requeridos deben completarse';
            $mensaje_tipo = 'danger';
        } else {
            try {
                // Verificar si el código ya existe
                $check = "SELECT id_medicamento FROM medicamento WHERE codigo_medicamento = ?";
                $stmt = $pdo->prepare($check);
                $stmt->execute([$codigo]);
                
                if ($stmt->fetch()) {
                    $mensaje = 'El código de medicamento ya existe en el sistema';
                    $mensaje_tipo = 'warning';
                } else {
                    $insert = "
                        INSERT INTO medicamento (
                            codigo_medicamento, nombre_generico, nombre_comercial,
                            id_categoria, presentacion, concentracion, precio_unitario,
                            stock_minimo, stock_actual, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'Activo')
                    ";
                    $stmt = $pdo->prepare($insert);
                    $stmt->execute([
                        $codigo, $nombre_generico, $nombre_comercial, 
                        $id_categoria, $presentacion, $concentracion, 
                        $precio, $stock_minimo
                    ]);
                    
                    $id_medicamento = $pdo->lastInsertId();
                    
                    $mensaje = 'Medicamento registrado correctamente';
                    $mensaje_tipo = 'success';
                    
                    // Registrar en auditoría
                    log_action('INSERT', 'medicamento', $id_medicamento, "Medicamento registrado: $nombre_generico");
                    
                    // Limpiar formulario
                    $_POST = [];
                }
            } catch (Exception $e) {
                $mensaje = 'Error al registrar: ' . $e->getMessage();
                $mensaje_tipo = 'danger';
            }
        }
    } elseif ($action === 'actualizar') {
        $id_medicamento = $_POST['id_medicamento'] ?? '';
        $precio = $_POST['precio_unitario'] ?? '';
        $stock_minimo = $_POST['stock_minimo'] ?? '';
        $nombre_comercial = $_POST['nombre_comercial'] ?? '';

        if (empty($id_medicamento)) {
            $mensaje = 'ID de medicamento requerido';
            $mensaje_tipo = 'danger';
        } else {
            try {
                $update = "
                    UPDATE medicamento 
                    SET precio_unitario = ?, 
                        stock_minimo = ?,
                        nombre_comercial = ?
                    WHERE id_medicamento = ?
                ";
                $stmt = $pdo->prepare($update);
                $stmt->execute([$precio, $stock_minimo, $nombre_comercial, $id_medicamento]);
                
                $mensaje = 'Medicamento actualizado correctamente';
                $mensaje_tipo = 'success';
                
                // Registrar en auditoría
                log_action('UPDATE', 'medicamento', $id_medicamento, "Medicamento actualizado");
            } catch (Exception $e) {
                $mensaje = 'Error al actualizar: ' . $e->getMessage();
                $mensaje_tipo = 'danger';
            }
        }
    }
}

// Parámetros de búsqueda
$search = $_GET['search'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$estado_stock = $_GET['estado_stock'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir query
$where_conditions = ["m.estado = 'Activo'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(m.nombre_generico LIKE ? OR m.nombre_comercial LIKE ? OR m.codigo_medicamento LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($categoria)) {
    $where_conditions[] = "m.id_categoria = ?";
    $params[] = $categoria;
}

if (!empty($estado_stock)) {
    if ($estado_stock === 'agotado') {
        $where_conditions[] = "m.stock_actual = 0";
    } elseif ($estado_stock === 'bajo') {
        $where_conditions[] = "m.stock_actual > 0 AND m.stock_actual < m.stock_minimo";
    } elseif ($estado_stock === 'normal') {
        $where_conditions[] = "m.stock_actual >= m.stock_minimo";
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Contar registros
$count = "SELECT COUNT(*) as total FROM medicamento m WHERE $where_sql";
$stmt = $pdo->prepare($count);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);

// Obtener medicamentos
$query = "
    SELECT 
        m.id_medicamento,
        m.codigo_medicamento,
        m.nombre_generico,
        m.nombre_comercial,
        m.presentacion,
        m.concentracion,
        m.precio_unitario,
        m.stock_actual,
        m.stock_minimo,
        c.nombre as categoria
    FROM medicamento m
    INNER JOIN categoria_medicamento c ON m.id_categoria = c.id_categoria
    WHERE $where_sql
    ORDER BY m.nombre_generico ASC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para filtro
$categorias_query = "SELECT id_categoria, nombre FROM categoria_medicamento WHERE estado = 'activa' ORDER BY nombre";
$stmt = $pdo->prepare($categorias_query);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Presentaciones disponibles
$presentaciones = ['Tableta', 'Cápsula', 'Jarabe', 'Suspensión', 'Inyectable', 'Ampolla', 'Crema', 'Ungüento', 'Gotas', 'Spray', 'Supositorio', 'Parche'];
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
                            <i class="fas fa-pills text-primary me-2"></i>
                            Catálogo de Medicamentos
                        </h1>
                        <p class="text-muted mb-0">Gestión completa del inventario de medicamentos</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Dashboard
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistrar">
                            <i class="fas fa-plus me-2"></i>Nuevo Medicamento
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

        <!-- Búsqueda y filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-search text-primary me-2"></i>
                    Buscar y Filtrar Medicamentos
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-keyboard me-1"></i>
                            Búsqueda
                        </label>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Nombre, código..."
                            class="form-control"
                        >
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tags me-1"></i>
                            Categoría
                        </label>
                        <select name="categoria" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-boxes me-1"></i>
                            Estado Stock
                        </label>
                        <select name="estado_stock" class="form-select">
                            <option value="">Todos</option>
                            <option value="agotado" <?php echo $estado_stock === 'agotado' ? 'selected' : ''; ?>>Agotado</option>
                            <option value="bajo" <?php echo $estado_stock === 'bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                            <option value="normal" <?php echo $estado_stock === 'normal' ? 'selected' : ''; ?>>Stock Normal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <a href="medicamentos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de medicamentos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Medicamentos
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
                                <th class="px-4 py-3">Código</th>
                                <th class="px-4 py-3">Medicamento</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Presentación</th>
                                <th class="px-4 py-3 text-center">Stock</th>
                                <th class="px-4 py-3 text-end">Precio</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($medicamentos)): ?>
                                <?php foreach ($medicamentos as $med): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <code class="bg-light px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($med['codigo_medicamento']); ?>
                                            </code>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($med['nombre_generico']); ?>
                                            </div>
                                            <?php if ($med['nombre_comercial']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-trademark me-1"></i>
                                                <?php echo htmlspecialchars($med['nombre_comercial']); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-light text-dark border">
                                                <?php echo htmlspecialchars($med['categoria']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="small">
                                                <?php echo htmlspecialchars($med['presentacion']); ?>
                                                <?php if ($med['concentracion']): ?>
                                                    <br><span class="text-muted"><?php echo htmlspecialchars($med['concentracion']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                if ($med['stock_actual'] == 0) {
                                                    echo '<span class="badge bg-danger fs-6">Agotado</span>';
                                                } elseif ($med['stock_actual'] < $med['stock_minimo']) {
                                                    echo '<span class="badge bg-warning fs-6">' . number_format($med['stock_actual']) . '</span>';
                                                } else {
                                                    echo '<span class="badge bg-success fs-6">' . number_format($med['stock_actual']) . '</span>';
                                                }
                                            ?>
                                            <br>
                                            <small class="text-muted">Mín: <?php echo number_format($med['stock_minimo']); ?></small>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <strong class="text-success">
                                                $<?php echo number_format($med['precio_unitario'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditar" 
                                                onclick="cargarEditar(
                                                    <?php echo $med['id_medicamento']; ?>, 
                                                    '<?php echo htmlspecialchars($med['nombre_generico']); ?>',
                                                    '<?php echo htmlspecialchars($med['nombre_comercial']); ?>',
                                                    <?php echo $med['precio_unitario']; ?>, 
                                                    <?php echo $med['stock_minimo']; ?>
                                                )"
                                                title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-pills text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No se encontraron medicamentos</p>
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

<!-- Modal para registrar medicamento -->
<div class="modal fade" id="modalRegistrar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Registrar Nuevo Medicamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="registrar">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="codigo" class="form-label fw-bold">
                                <i class="fas fa-barcode me-1"></i>
                                Código <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="codigo" 
                                name="codigo_medicamento" 
                                class="form-control" 
                                placeholder="Ej: MED-001"
                                required
                            >
                            <small class="text-muted">Código único del medicamento</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="categoria" class="form-label fw-bold">
                                <i class="fas fa-tags me-1"></i>
                                Categoría <span class="text-danger">*</span>
                            </label>
                            <select id="categoria" name="id_categoria" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="generico" class="form-label fw-bold">
                                <i class="fas fa-pills me-1"></i>
                                Nombre Genérico <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="generico" 
                                name="nombre_generico" 
                                class="form-control" 
                                placeholder="Ej: Paracetamol"
                                required
                            >
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="comercial" class="form-label fw-bold">
                                <i class="fas fa-trademark me-1"></i>
                                Nombre Comercial
                            </label>
                            <input 
                                type="text" 
                                id="comercial" 
                                name="nombre_comercial" 
                                class="form-control"
                                placeholder="Ej: Tylenol"
                            >
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="presentacion" class="form-label fw-bold">
                                <i class="fas fa-capsules me-1"></i>
                                Presentación <span class="text-danger">*</span>
                            </label>
                            <select id="presentacion" name="presentacion" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($presentaciones as $p): ?>
                                    <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="concentracion" class="form-label fw-bold">
                                <i class="fas fa-flask me-1"></i>
                                Concentración
                            </label>
                            <input 
                                type="text" 
                                id="concentracion" 
                                name="concentracion" 
                                class="form-control" 
                                placeholder="Ej: 500mg, 10ml"
                            >
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label fw-bold">
                                <i class="fas fa-dollar-sign me-1"></i>
                                Precio Unitario <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="precio" 
                                name="precio_unitario" 
                                class="form-control" 
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required
                            >
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="stock_minimo_reg" class="form-label fw-bold">
                                <i class="fas fa-boxes me-1"></i>
                                Stock Mínimo <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="stock_minimo_reg" 
                                name="stock_minimo" 
                                class="form-control" 
                                min="0"
                                value="10"
                                required
                            >
                            <small class="text-muted">Cantidad mínima para alertas</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Registrar Medicamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para editar medicamento -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Editar Medicamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="actualizar">
                    <input type="hidden" id="id_med" name="id_medicamento">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong id="med_nombre"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre_comercial_edit" class="form-label fw-bold">
                            <i class="fas fa-trademark me-1"></i>
                            Nombre Comercial
                        </label>
                        <input 
                            type="text" 
                            id="nombre_comercial_edit" 
                            name="nombre_comercial" 
                            class="form-control"
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label for="precio_edit" class="form-label fw-bold">
                            <i class="fas fa-dollar-sign me-1"></i>
                            Precio Unitario
                        </label>
                        <input 
                            type="number" 
                            id="precio_edit" 
                            name="precio_unitario" 
                            class="form-control" 
                            step="0.01"
                            min="0"
                            required
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label for="stock_min" class="form-label fw-bold">
                            <i class="fas fa-boxes me-1"></i>
                            Stock Mínimo
                        </label>
                        <input 
                            type="number" 
                            id="stock_min" 
                            name="stock_minimo" 
                            class="form-control" 
                            min="0"
                            required
                        >
                        <small class="text-muted">Cantidad mínima para generar alertas</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarEditar(id, nombre_generico, nombre_comercial, precio, stock_min) {
    document.getElementById('id_med').value = id;
    document.getElementById('med_nombre').textContent = nombre_generico;
    document.getElementById('nombre_comercial_edit').value = nombre_comercial || '';
    document.getElementById('precio_edit').value = precio;
    document.getElementById('stock_min').value = stock_min;
}

// Limpiar modales al cerrar
document.getElementById('modalRegistrar').addEventListener('hidden.bs.modal', function () {
    this.querySelector('form').reset();
});

document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
    this.querySelector('form').reset();
});

// Validación de formulario de registro
document.querySelector('#modalRegistrar form').addEventListener('submit', function(e) {
    const precio = parseFloat(document.getElementById('precio').value);
    const stockMin = parseInt(document.getElementById('stock_minimo_reg').value);
    
    if (precio <= 0) {
        e.preventDefault();
        alert('El precio debe ser mayor que 0');
        return false;
    }
    
    if (stockMin < 0) {
        e.preventDefault();
        alert('El stock mínimo no puede ser negativo');
        return false;
    }
});

// Validación de formulario de edición
document.querySelector('#modalEditar form').addEventListener('submit', function(e) {
    const precio = parseFloat(document.getElementById('precio_edit').value);
    const stockMin = parseInt(document.getElementById('stock_min').value);
    
    if (precio <= 0) {
        e.preventDefault();
        alert('El precio debe ser mayor que 0');
        return false;
    }
    
    if (stockMin < 0) {
        e.preventDefault();
        alert('El stock mínimo no puede ser negativo');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>