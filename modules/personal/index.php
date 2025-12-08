<?php
/**
 * modules/personal/index.php - INICIO DEL ARCHIVO
 * Agregar ANTES de todo
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

// IMPORTANTE: Capturar mensaje de sesión ANTES de cargar header
$mensaje = '';
$mensaje_tipo = 'success';

if (isset($_SESSION['success_message'])) {
    $mensaje = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Limpiar mensaje
}

if (isset($_SESSION['error_message'])) {
    $mensaje = $_SESSION['error_message'];
    $mensaje_tipo = 'danger';
    unset($_SESSION['error_message']); // Limpiar mensaje
}

$page_title = "Personal";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Recepcionista', 'Médico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para ver este módulo</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';

// Parámetros de búsqueda y paginación
$search = $_GET['search'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$estado = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir condiciones WHERE
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = '(per.nombres LIKE ? OR per.apellidos LIKE ? OR p.codigo_empleado LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($tipo)) {
    $where_conditions[] = 'p.tipo_personal = ?';
    $params[] = $tipo;
}

if (!empty($estado)) {
    $where_conditions[] = 'p.estado_laboral = ?';
    $params[] = $estado;
}

$where_sql = empty($where_conditions) ? '1=1' : implode(' AND ', $where_conditions);

// Contar total de registros
$count_sql = "
    SELECT COUNT(*) as total 
    FROM personal p 
    INNER JOIN persona per ON p.id_personal = per.id_persona 
    WHERE $where_sql
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, ceil($total / $limit));

// Obtener lista de personal
$sql = "
    SELECT 
        p.id_personal,
        p.codigo_empleado,
        p.tipo_personal,
        p.estado_laboral,
        p.fecha_contratacion,
        per.nombres,
        per.apellidos,
        per.ciudad,
        per.telefono,
        per.email
    FROM personal p 
    INNER JOIN persona per ON p.id_personal = per.id_persona 
    WHERE $where_sql 
    ORDER BY per.apellidos, per.nombres
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personal = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stats_query = "
    SELECT 
        COUNT(*) as total_personal,
        SUM(CASE WHEN tipo_personal = 'Medico' THEN 1 ELSE 0 END) as medicos,
        SUM(CASE WHEN tipo_personal = 'Enfermero' THEN 1 ELSE 0 END) as enfermeros,
        SUM(CASE WHEN tipo_personal = 'Administrativo' THEN 1 ELSE 0 END) as administrativos,
        SUM(CASE WHEN tipo_personal = 'Farmacia' THEN 1 ELSE 0 END) as farmacia,
        SUM(CASE WHEN estado_laboral = 'Activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estado_laboral = 'Inactivo' THEN 1 ELSE 0 END) as inactivos
    FROM personal
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute();
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Registrar en auditoría
log_action('SELECT', 'personal', null, "Listado de personal - Búsqueda: $search");

$tipos_personal = [
    'Medico' => 'Médico',
    'Administrativo' => 'Administrativo',
];

$estados_laborales = [
    'Activo' => 'Activo',
    'Inactivo' => 'Inactivo',
    'Vacaciones' => 'Vacaciones',
    'Licencia' => 'Licencia'
];
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
                            <i class="fas fa-user-tie text-primary me-2"></i>
                            Personal
                        </h1>
                        <p class="text-muted mb-0">Gestión de personal médico y administrativo</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (has_any_role(['Administrador'])): ?>
                        <a href="registrar.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Registrar Personal
                        </a>
                        <?php endif; ?>
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

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Personal</p>
                                <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($estadisticas['total_personal'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-users text-primary fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Médicos</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($estadisticas['medicos'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-user-md text-success fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Activos</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($estadisticas['activos'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-check-circle text-warning fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-filter text-primary me-2"></i>
                    Filtros de Búsqueda
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-1"></i>
                            Buscar
                        </label>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>" 
                            class="form-control" 
                            placeholder="Nombre, apellido o código..."
                        >
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-tag me-1"></i>
                            Tipo de Personal
                        </label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <?php foreach($tipos_personal as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $tipo === $k ? 'selected' : ''; ?>>
                                    <?php echo $v; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-toggle-on me-1"></i>
                            Estado Laboral
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach($estados_laborales as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $estado === $k ? 'selected' : ''; ?>>
                                    <?php echo $v; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Personal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Personal
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
                                <th class="px-4 py-3">Nombre Completo</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                                <th class="px-4 py-3">Contacto</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3">Fecha Contratación</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($personal)): ?>
                                <?php foreach ($personal as $p): ?>
                                    <?php 
                                        $nombres = decrypt_data($p['nombres']);
                                        $apellidos = decrypt_data($p['apellidos']);
                                        $nombre_completo = $nombres . ' ' . $apellidos;
                                        $telefono = decrypt_data($p['telefono']);
                                        $email = decrypt_data($p['email']);
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-secondary fs-6">
                                                <?php echo htmlspecialchars($p['codigo_empleado']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($nombre_completo); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($p['ciudad']); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $tipo_badges = [
                                                    'Medico' => 'success',
                                                    'Enfermero' => 'info',
                                                    'Administrativo' => 'warning',
                                                    'Farmacia' => 'primary'
                                                ];
                                                $tipo_icons = [
                                                    'Medico' => 'user-md',
                                                    'Enfermero' => 'user-nurse',
                                                    'Administrativo' => 'user-tie',
                                                    'Farmacia' => 'prescription-bottle'
                                                ];
                                                $badge = $tipo_badges[$p['tipo_personal']] ?? 'secondary';
                                                $icon = $tipo_icons[$p['tipo_personal']] ?? 'user';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>">
                                                <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                <?php echo htmlspecialchars($tipos_personal[$p['tipo_personal']] ?? $p['tipo_personal']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small>
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($telefono ?: 'N/A'); ?>
                                                <br>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($email ?: 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $estado_badges = [
                                                    'Activo' => 'success',
                                                    'Inactivo' => 'danger',
                                                    'Vacaciones' => 'info',
                                                    'Licencia' => 'warning'
                                                ];
                                                $badge_estado = $estado_badges[$p['estado_laboral']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_estado; ?>">
                                                <?php echo htmlspecialchars($p['estado_laboral']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo $p['fecha_contratacion'] ? date('d/m/Y', strtotime($p['fecha_contratacion'])) : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="horarios.php?id=<?php echo $p['id_personal']; ?>" 
                                                   class="btn btn-outline-secondary" 
                                                   title="Horarios">
                                                    <i class="fas fa-clock"></i>
                                                </a>
                                                <a href="turnos.php?id=<?php echo $p['id_personal']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Turnos">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </a>
                                                <?php if (has_any_role(['Administrador'])): ?>
                                                    <a href="registrar.php?id=<?php echo $p['id_personal']; ?>" 
                                                       class="btn btn-outline-success" 
                                                       title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-user-slash text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No se encontró personal</p>
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

<?php require_once '../../includes/footer.php'; ?>