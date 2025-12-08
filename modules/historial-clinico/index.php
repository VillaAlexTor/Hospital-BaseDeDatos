<?php
/**
 * modules/historial-clinico/index.php
 * Gestión centralizada de historiales clínicos
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Historial Clínico";
require_once '../../includes/header.php';

// Parámetros de búsqueda y filtros
$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir condiciones WHERE
$where_conditions = ["per.estado = 'activo'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(per.nombres LIKE ? OR per.apellidos LIKE ? OR pac.numero_historia_clinica LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($estado)) {
    $where_conditions[] = "pac.estado_paciente = ?";
    $params[] = $estado;
}

$where_sql = implode(' AND ', $where_conditions);

// Contar total de registros
$count_query = "
    SELECT COUNT(*) as total
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE $where_sql
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_pages = ceil($total_registros / $limit);

// Obtener pacientes con historial clínico
$query = "
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        pac.grupo_sanguineo,
        pac.estado_paciente,
        pac.fecha_primera_consulta,
        AES_DECRYPT(per.nombres, ?) as nombres,
        AES_DECRYPT(per.apellidos, ?) as apellidos,
        AES_DECRYPT(per.numero_documento, ?) as numero_documento,
        per.tipo_documento,
        per.fecha_nacimiento,
        (SELECT COUNT(*) FROM consulta WHERE id_paciente = pac.id_paciente) as total_consultas,
        (SELECT COUNT(*) FROM internamiento WHERE id_paciente = pac.id_paciente) as total_internamientos,
        (SELECT MAX(fecha_hora_atencion) FROM consulta WHERE id_paciente = pac.id_paciente) as ultima_consulta
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE $where_sql
    ORDER BY per.apellidos ASC, per.nombres ASC
    LIMIT ? OFFSET ?
";

$params_exec = array_merge([$clave_cifrado, $clave_cifrado, $clave_cifrado], $params, [$limit, $offset]);

$stmt = $pdo->prepare($query);
$stmt->execute($params_exec);
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales
$stats_query = "
    SELECT 
        COUNT(*) as total_pacientes,
        SUM(CASE WHEN estado_paciente = 'activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estado_paciente = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
        SUM(CASE WHEN estado_paciente = 'fallecido' THEN 1 ELSE 0 END) as fallecidos
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE per.estado = 'activo'
";
$stmt_stats = $pdo->prepare($stats_query);
$stmt_stats->execute();
$estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Estados de paciente para filtros
$estados_paciente = [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo', 
    'fallecido' => 'Fallecido'
];
?>

<!-- Contenido Principal -->
<main>
    <div class="container-fluid">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-2">
                            <i class="fas fa-file-medical text-warning me-2"></i>
                            Historial Clínico
                        </h1>
                        <p class="text-muted mb-0">Gestión centralizada de historiales médicos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Pacientes</p>
                                <h3 class="mb-0 fw-bold"><?php echo number_format($estadisticas['total_pacientes'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Activos</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($estadisticas['activos'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-heartbeat text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Inactivos</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($estadisticas['inactivos'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-user-clock text-warning fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-danger border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Fallecidos</p>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo number_format($estadisticas['fallecidos'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-cross text-danger fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de búsqueda y filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-search text-secondary me-2"></i>
                    Buscar Pacientes
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-keyboard me-1"></i>
                            Búsqueda
                        </label>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Nombre, apellido, historia clínica..."
                            class="form-control"
                        >
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-1"></i>
                            Estado
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach ($estados_paciente as $valor => $texto): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $estado === $valor ? 'selected' : ''; ?>>
                                    <?php echo $texto; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de pacientes -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Pacientes
                </h5>
                <span class="badge bg-info">
                    <?php echo number_format($total_registros); ?> resultados
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Paciente</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Historia Clínica</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3">Estadísticas</th>
                                <th class="px-4 py-3 text-center">Última Consulta</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pacientes)): ?>
                                <?php foreach ($pacientes as $paciente): 
                                    // Calcular edad
                                    $edad = '';
                                    if ($paciente['fecha_nacimiento']) {
                                        try {
                                            $fecha_nac = decrypt_data($paciente['fecha_nacimiento']);
                                            if ($fecha_nac) {
                                                $fecha_nacimiento = new DateTime($fecha_nac);
                                                $edad = $fecha_nacimiento->diff(new DateTime())->y . ' años';
                                            }
                                        } catch (Exception $e) {
                                            // Silenciar error si falla
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?></div>
                                                    <?php if ($edad): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-birthday-cake me-1"></i><?php echo $edad; ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div><?php echo htmlspecialchars($paciente['tipo_documento']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($paciente['numero_documento']); ?></small>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $estado_badges = [
                                                    'activo' => 'bg-success',
                                                    'inactivo' => 'bg-warning',
                                                    'fallecido' => 'bg-danger'
                                                ];
                                                $badge = $estado_badges[$paciente['estado_paciente']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>">
                                                <?php echo $estados_paciente[$paciente['estado_paciente']] ?? $paciente['estado_paciente']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="small">
                                                <div class="mb-1">
                                                    <i class="fas fa-stethoscope text-primary me-1"></i>
                                                    <strong><?php echo $paciente['total_consultas']; ?></strong> consultas
                                                </div>
                                                <div>
                                                    <i class="fas fa-procedures text-warning me-1"></i>
                                                    <strong><?php echo $paciente['total_internamientos']; ?></strong> internamientos
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                if ($paciente['ultima_consulta']) {
                                                    echo '<span class="badge bg-info">' . 
                                                         date('d/m/Y', strtotime($paciente['ultima_consulta'])) . 
                                                         '</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Sin consultas</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="ver.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                                   class="btn btn-outline-primary"
                                                   title="Ver historial completo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../pacientes/ver.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                                   class="btn btn-outline-info"
                                                   title="Ver ficha del paciente">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                                <?php if (has_any_role(['Administrador', 'Médico'])): ?>
                                                <a href="editar.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                                   class="btn btn-outline-success"
                                                   title="Editar historial">
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
                                        <i class="fas fa-search text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No se encontraron pacientes</p>
                                        <p class="text-muted small">Intenta con otros criterios de búsqueda</p>
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
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $limit, $total_registros); ?> 
                        de <?php echo number_format($total_registros); ?> resultados
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
                            // Mostrar solo algunas páginas alrededor de la actual
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