<?php
/**
 * modules/internamiento/index.php
 * Lista de internamientos activos y finalizados
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Internamiento";
require_once '../../includes/header.php';

// Parámetros de búsqueda y filtros
$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir condiciones WHERE
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(AES_DECRYPT(p.nombres, ?) LIKE ? OR AES_DECRYPT(p.apellidos, ?) LIKE ? OR pac.numero_historia_clinica LIKE ?)";
    $search_param = "%$search%";
    $params[] = $clave_cifrado;
    $params[] = $search_param;
    $params[] = $clave_cifrado;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($estado)) {
    $where_conditions[] = "i.estado_internamiento = ?";
    $params[] = $estado;
} else {
    // Por defecto mostrar solo "En curso"
    $where_conditions[] = "i.estado_internamiento = 'En curso'";
}

$where_sql = empty($where_conditions) ? "1=1" : implode(' AND ', $where_conditions);

// Contar total de registros
$count_query = "
    SELECT COUNT(*) as total
    FROM internamiento i
    INNER JOIN paciente pac ON i.id_paciente = pac.id_paciente
    INNER JOIN persona p ON pac.id_paciente = p.id_persona
    WHERE $where_sql
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_pages = ceil($total_registros / $limit);

// Obtener internamientos
$query = "
    SELECT 
        i.id_internamiento,
        i.id_paciente,
        i.fecha_ingreso,
        i.hora_ingreso,
        i.fecha_alta,
        i.estado_internamiento,
        i.tipo_internamiento,
        i.diagnostico_ingreso,
        pac.numero_historia_clinica,
        AES_DECRYPT(p.nombres, ?) as nombres,
        AES_DECRYPT(p.apellidos, ?) as apellidos,
        AES_DECRYPT(m.nombres, ?) as medico_nombres,
        AES_DECRYPT(m.apellidos, ?) as medico_apellidos,
        c.numero_cama,
        sal.nombre as sala_nombre,
        (SELECT COUNT(*) FROM evolucion_medica WHERE id_internamiento = i.id_internamiento) as total_evoluciones
    FROM internamiento i
    INNER JOIN paciente pac ON i.id_paciente = pac.id_paciente
    INNER JOIN persona p ON pac.id_paciente = p.id_persona
    INNER JOIN medico med ON i.id_medico_responsable = med.id_medico
    INNER JOIN persona m ON med.id_medico = m.id_persona
    LEFT JOIN cama c ON i.id_cama = c.id_cama
    LEFT JOIN habitacion h ON c.id_habitacion = h.id_habitacion
    LEFT JOIN sala sal ON h.id_sala = sal.id_sala
    WHERE $where_sql
    ORDER BY i.fecha_ingreso DESC
    LIMIT ? OFFSET ?
";

$params_exec = array_merge([$clave_cifrado, $clave_cifrado, $clave_cifrado, $clave_cifrado], $params, [$limit, $offset]);

$stmt = $pdo->prepare($query);
$stmt->execute($params_exec);
$internamientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_query = "
    SELECT 
        SUM(CASE WHEN estado_internamiento = 'En curso' THEN 1 ELSE 0 END) as en_curso,
        SUM(CASE WHEN estado_internamiento = 'Alta médica' THEN 1 ELSE 0 END) as alta_medica,
        SUM(CASE WHEN estado_internamiento = 'Alta voluntaria' THEN 1 ELSE 0 END) as alta_voluntaria,
        SUM(CASE WHEN estado_internamiento = 'Fallecido' THEN 1 ELSE 0 END) as fallecidos,
        SUM(CASE WHEN estado_internamiento = 'Referido' THEN 1 ELSE 0 END) as referidos
    FROM internamiento
";
$stmt_stats = $pdo->prepare($stats_query);
$stmt_stats->execute();
$estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Estados posibles
$estados_internamiento = [
    'En curso' => 'En curso',
    'Alta médica' => 'Alta médica',
    'Alta voluntaria' => 'Alta voluntaria',
    'Referido' => 'Referido',
    'Fallecido' => 'Fallecido'
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
                            <i class="fas fa-procedures text-info me-2"></i>
                            Gestión de Internamientos
                        </h1>
                        <p class="text-muted mb-0">Administración de pacientes hospitalizados</p>
                    </div>
                    <div>
                        <?php if (has_any_role(['Administrador', 'Médico'])): ?>
                        <a href="registrar.php" class="btn btn-primary btn-lg shadow-sm">
                            <i class="fas fa-plus me-2"></i>Nuevo Internamiento
                        </a>
                        <?php endif; ?>
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
                                <p class="text-muted small mb-1">En Curso</p>
                                <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($estadisticas['en_curso'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-user-check text-primary fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Altas Médicas</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($estadisticas['alta_medica'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-door-open text-success fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Altas Voluntarias</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($estadisticas['alta_voluntaria'] ?? 0); ?></h3>
                            </div>
                            <i class="fas fa-hand-paper text-warning fa-2x opacity-50"></i>
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
                            <i class="fas fa-cross text-danger fa-2x opacity-50"></i>
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
                    Buscar Internamientos
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
                            placeholder="Nombre del paciente, historia clínica..."
                            class="form-control"
                        >
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-1"></i>
                            Estado
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">En curso (predeterminado)</option>
                            <?php foreach ($estados_internamiento as $valor => $texto): ?>
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

        <!-- Lista de internamientos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Lista de Internamientos
                </h5>
                <span class="badge bg-info">
                    <?php echo number_format($total_registros); ?> registros
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Paciente</th>
                                <th class="px-4 py-3">HC</th>
                                <th class="px-4 py-3">Médico Responsable</th>
                                <th class="px-4 py-3">Ingreso</th>
                                <th class="px-4 py-3 text-center">Ubicación</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3 text-center">Evoluciones</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($internamientos)): ?>
                                <?php foreach ($internamientos as $internamiento): 
                                    // Calcular días de internamiento
                                    $fecha_ingreso = new DateTime($internamiento['fecha_ingreso']);
                                    $fecha_actual = new DateTime();
                                    $dias_internado = $fecha_ingreso->diff($fecha_actual)->days;
                                ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($internamiento['nombres'] . ' ' . $internamiento['apellidos']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo $dias_internado; ?> día<?php echo $dias_internado != 1 ? 's' : ''; ?> internado
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($internamiento['numero_historia_clinica']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="small">
                                                <i class="fas fa-user-md text-primary me-1"></i>
                                                <?php echo htmlspecialchars($internamiento['medico_nombres'] . ' ' . $internamiento['medico_apellidos']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="small">
                                                <div>
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('d/m/Y', strtotime($internamiento['fecha_ingreso'])); ?>
                                                </div>
                                                <div class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($internamiento['hora_ingreso'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($internamiento['numero_cama']): ?>
                                            <div class="small">
                                                <div class="fw-bold">
                                                    <i class="fas fa-bed text-info me-1"></i>
                                                    Cama <?php echo htmlspecialchars($internamiento['numero_cama']); ?>
                                                </div>
                                                <div class="text-muted">
                                                    <?php echo htmlspecialchars($internamiento['sala_nombre'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $tipo_badges = [
                                                    'Programado' => 'bg-info',
                                                    'Emergencia' => 'bg-danger',
                                                    'Referencia' => 'bg-warning text-dark'
                                                ];
                                                $badge = $tipo_badges[$internamiento['tipo_internamiento']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($internamiento['tipo_internamiento']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php 
                                                $estado_badges = [
                                                    'En curso' => 'bg-success',
                                                    'Alta médica' => 'bg-info',
                                                    'Alta voluntaria' => 'bg-warning text-dark',
                                                    'Referido' => 'bg-primary',
                                                    'Fallecido' => 'bg-dark'
                                                ];
                                                $badge = $estado_badges[$internamiento['estado_internamiento']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($internamiento['estado_internamiento']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas fa-notes-medical me-1"></i>
                                                <?php echo $internamiento['total_evoluciones']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="evolucion.php?id=<?php echo $internamiento['id_internamiento']; ?>" 
                                                   class="btn btn-outline-primary"
                                                   title="Ver evoluciones">
                                                    <i class="fas fa-notes-medical"></i>
                                                </a>
                                                <a href="../pacientes/ver.php?id=<?php echo $internamiento['id_paciente']; ?>" 
                                                   class="btn btn-outline-info"
                                                   title="Ver paciente">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                                <?php if (has_any_role(['Administrador', 'Médico'])): ?>
                                                <a href="editar.php?id=<?php echo $internamiento['id_internamiento']; ?>" 
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
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-bed text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0 h5">No se encontraron internamientos</p>
                                        <p class="text-muted small">Intenta cambiar los filtros de búsqueda</p>
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