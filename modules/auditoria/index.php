<?php
/**
 * modules/auditoria/index.php
 * Módulo de Auditoría - Vista Principal
 * Muestra logs de auditoría del sistema
 */

$page_title = "Auditoría del Sistema";
require_once '../../includes/header.php';

// Verificar permisos - Solo administradores
if (!has_any_role(['Administrador', 'Auditor'])) {
    die('<div class="alert alert-danger">No tienes permisos para acceder a este módulo</div>');
}

// Parámetros de búsqueda y filtrado
$search = $_GET['search'] ?? '';
$usuario_filtro = $_GET['usuario'] ?? '';
$accion_filtro = $_GET['accion'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$resultado_filtro = $_GET['resultado'] ?? '';
$criticidad_filtro = $_GET['criticidad'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Construir query con filtros
$where_conditions = ["l.fecha_hora BETWEEN ? AND ?"];
$params = [$fecha_desde . ' 00:00:00', $fecha_hasta . ' 23:59:59'];

if (!empty($search)) {
    $where_conditions[] = "(l.descripcion LIKE ? OR l.tabla_afectada LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($usuario_filtro)) {
    $where_conditions[] = "l.id_usuario = ?";
    $params[] = $usuario_filtro;
}

if (!empty($accion_filtro)) {
    $where_conditions[] = "l.accion = ?";
    $params[] = $accion_filtro;
}

if (!empty($tabla_filtro)) {
    $where_conditions[] = "l.tabla_afectada = ?";
    $params[] = $tabla_filtro;
}

if (!empty($resultado_filtro)) {
    $where_conditions[] = "l.resultado = ?";
    $params[] = $resultado_filtro;
}

if (!empty($criticidad_filtro)) {
    $where_conditions[] = "l.criticidad = ?";
    $params[] = $criticidad_filtro;
}

$where_sql = implode(' AND ', $where_conditions);

// Contar total de registros
$count_query = "SELECT COUNT(*) as total FROM log_auditoria l WHERE $where_sql";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_pages = ceil($total_registros / $limit);

// Obtener logs
$query = "
    SELECT 
        l.id_log,
        l.id_usuario,
        l.fecha_hora,
        l.accion,
        l.tabla_afectada,
        l.registro_id,
        l.ip_address,
        l.navegador,
        l.resultado,
        l.codigo_error,
        l.descripcion,
        l.criticidad,
        u.username,
        CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo
    FROM log_auditoria l
    LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
    LEFT JOIN persona p ON u.id_persona = p.id_persona
    WHERE $where_sql
    ORDER BY l.fecha_hora DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener lista de usuarios para filtro
$stmt_usuarios = $pdo->query("
    SELECT u.id_usuario, u.username, CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo
    FROM usuario u
    INNER JOIN persona p ON u.id_persona = p.id_persona
    WHERE u.estado = 'activo'
    ORDER BY p.apellidos, p.nombres
");
$usuarios = $stmt_usuarios->fetchAll();

// Obtener lista de tablas para filtro
$stmt_tablas = $pdo->query("
    SELECT DISTINCT tabla_afectada 
    FROM log_auditoria 
    WHERE tabla_afectada IS NOT NULL 
    ORDER BY tabla_afectada
");
$tablas = $stmt_tablas->fetchAll();

// Estadísticas rápidas
$stats_query = "
    SELECT 
        COUNT(*) as total_hoy,
        SUM(CASE WHEN resultado = 'Éxito' THEN 1 ELSE 0 END) as exitosas,
        SUM(CASE WHEN resultado = 'Fallo' THEN 1 ELSE 0 END) as fallidas,
        SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as criticas,
        SUM(CASE WHEN accion = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as login_fallidos
    FROM log_auditoria
    WHERE DATE(fecha_hora) = CURDATE()
";
$stmt = $pdo->query($stats_query);
$stats = $stmt->fetch();

// Registrar acceso al módulo
$stmt = $pdo->prepare("
    INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, descripcion, ip_address, resultado)
    VALUES (?, 'SELECT', 'log_auditoria', 'Acceso al módulo de auditoría', ?, 'Éxito')
");
$stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
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
                            <i class="fas fa-shield-alt text-primary me-2"></i>
                            Auditoría del Sistema
                        </h1>
                        <p class="text-muted mb-0">
                            Total de eventos: <span class="fw-bold"><?php echo number_format($total_registros); ?></span>
                        </p>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button onclick="exportarExcel()" class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Exportar Excel
                        </button>
                        <button onclick="exportarPDF()" class="btn btn-danger">
                            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas del día -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Eventos Hoy</p>
                                <h3 class="mb-0 fw-bold"><?php echo number_format($stats['total_hoy']); ?></h3>
                            </div>
                            <i class="fas fa-calendar-day text-info fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Exitosas</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($stats['exitosas']); ?></h3>
                            </div>
                            <i class="fas fa-check-circle text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-danger border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Fallidas</p>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo number_format($stats['fallidas']); ?></h3>
                            </div>
                            <i class="fas fa-exclamation-circle text-danger fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Críticas</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($stats['criticas']); ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle text-warning fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-filter text-secondary me-2"></i>
                    Filtros de Búsqueda
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Búsqueda general -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar
                        </label>
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Descripción o tabla..."
                               class="form-control">
                    </div>
                    
                    <!-- Rango de fechas -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Desde
                        </label>
                        <input type="date" 
                               name="fecha_desde" 
                               value="<?php echo htmlspecialchars($fecha_desde); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Hasta
                        </label>
                        <input type="date" 
                               name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                               class="form-control">
                    </div>
                    
                    <!-- Usuario -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user me-2"></i>Usuario
                        </label>
                        <select name="usuario" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $usr): ?>
                            <option value="<?php echo $usr['id_usuario']; ?>" 
                                    <?php echo $usuario_filtro == $usr['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usr['nombre_completo'] . ' (' . $usr['username'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Acción -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-bolt me-2"></i>Acción
                        </label>
                        <select name="accion" class="form-select">
                            <option value="">Todas</option>
                            <option value="INSERT" <?php echo $accion_filtro == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                            <option value="UPDATE" <?php echo $accion_filtro == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                            <option value="DELETE" <?php echo $accion_filtro == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                            <option value="SELECT" <?php echo $accion_filtro == 'SELECT' ? 'selected' : ''; ?>>SELECT</option>
                            <option value="LOGIN" <?php echo $accion_filtro == 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                            <option value="LOGOUT" <?php echo $accion_filtro == 'LOGOUT' ? 'selected' : ''; ?>>LOGOUT</option>
                            <option value="LOGIN_FAILED" <?php echo $accion_filtro == 'LOGIN_FAILED' ? 'selected' : ''; ?>>LOGIN_FAILED</option>
                            <option value="EXECUTE" <?php echo $accion_filtro == 'EXECUTE' ? 'selected' : ''; ?>>EXECUTE</option>
                        </select>
                    </div>
                    
                    <!-- Tabla -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-table me-2"></i>Tabla
                        </label>
                        <select name="tabla" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($tablas as $tb): ?>
                            <option value="<?php echo htmlspecialchars($tb['tabla_afectada']); ?>" 
                                    <?php echo $tabla_filtro == $tb['tabla_afectada'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tb['tabla_afectada']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Resultado -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-check-circle me-2"></i>Resultado
                        </label>
                        <select name="resultado" class="form-select">
                            <option value="">Todos</option>
                            <option value="Éxito" <?php echo $resultado_filtro == 'Éxito' ? 'selected' : ''; ?>>Éxito</option>
                            <option value="Fallo" <?php echo $resultado_filtro == 'Fallo' ? 'selected' : ''; ?>>Fallo</option>
                            <option value="Bloqueado" <?php echo $resultado_filtro == 'Bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                            <option value="Error" <?php echo $resultado_filtro == 'Error' ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                    
                    <!-- Criticidad -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-exclamation-triangle me-2"></i>Criticidad
                        </label>
                        <select name="criticidad" class="form-select">
                            <option value="">Todas</option>
                            <option value="Baja" <?php echo $criticidad_filtro == 'Baja' ? 'selected' : ''; ?>>Baja</option>
                            <option value="Media" <?php echo $criticidad_filtro == 'Media' ? 'selected' : ''; ?>>Media</option>
                            <option value="Alta" <?php echo $criticidad_filtro == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            <option value="Crítica" <?php echo $criticidad_filtro == 'Crítica' ? 'selected' : ''; ?>>Crítica</option>
                        </select>
                    </div>
                    
                    <!-- Botones -->
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de logs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Registro de Auditoría
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-logs">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Fecha/Hora</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3 text-center">Acción</th>
                                <th class="px-4 py-3">Tabla/Módulo</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3">IP</th>
                                <th class="px-4 py-3 text-center">Resultado</th>
                                <th class="px-4 py-3 text-center">Criticidad</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                                    <p class="text-muted mt-3 mb-0">No se encontraron eventos de auditoría</p>
                                    <p class="text-muted small">Intenta con otros filtros de búsqueda</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): 
                                    // Determinar colores según resultado y criticidad
                                    $badge_resultado = [
                                        'Éxito' => 'success',
                                        'Fallo' => 'danger',
                                        'Bloqueado' => 'warning',
                                        'Error' => 'dark'
                                    ];
                                    
                                    $badge_criticidad = [
                                        'Baja' => 'secondary',
                                        'Media' => 'info',
                                        'Alta' => 'warning',
                                        'Crítica' => 'danger'
                                    ];
                                    
                                    $icono_accion = [
                                        'INSERT' => 'fa-plus-circle text-success',
                                        'UPDATE' => 'fa-edit text-primary',
                                        'DELETE' => 'fa-trash text-danger',
                                        'SELECT' => 'fa-eye text-info',
                                        'LOGIN' => 'fa-sign-in-alt text-success',
                                        'LOGOUT' => 'fa-sign-out-alt text-secondary',
                                        'LOGIN_FAILED' => 'fa-times-circle text-danger',
                                        'EXECUTE' => 'fa-cog text-warning',
                                        'EXPORT' => 'fa-download text-primary'
                                    ];
                                ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="small">
                                        <strong><?php echo date('d/m/Y', strtotime($log['fecha_hora'])); ?></strong>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo date('H:i:s', strtotime($log['fecha_hora'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($log['id_usuario']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle me-2" 
                                             style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($log['username'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($log['nombre_completo']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <i class="fas <?php echo $icono_accion[$log['accion']] ?? 'fa-circle text-secondary'; ?> me-1"></i>
                                    <span class="small"><?php echo htmlspecialchars($log['accion']); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($log['tabla_afectada']): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                        <?php echo htmlspecialchars($log['tabla_afectada']); ?>
                                    </span>
                                    <?php if ($log['registro_id']): ?>
                                        <small class="text-muted d-block">ID: <?php echo $log['registro_id']; ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-truncate" style="max-width: 300px;" 
                                         title="<?php echo htmlspecialchars($log['descripcion']); ?>">
                                        <?php echo htmlspecialchars($log['descripcion']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <small class="text-muted font-monospace">
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </small>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-<?php echo $badge_resultado[$log['resultado']] ?? 'secondary'; ?>">
                                        <?php echo htmlspecialchars($log['resultado']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-<?php echo $badge_criticidad[$log['criticidad']] ?? 'secondary'; ?>">
                                        <?php echo htmlspecialchars($log['criticidad']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="ver.php?id=<?php echo $log['id_log']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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
                        de <?php echo number_format($total_registros); ?> registros
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $params_url = http_build_query([
                                'search' => $search,
                                'usuario' => $usuario_filtro,
                                'accion' => $accion_filtro,
                                'tabla' => $tabla_filtro,
                                'resultado' => $resultado_filtro,
                                'criticidad' => $criticidad_filtro,
                                'fecha_desde' => $fecha_desde,
                                'fecha_hasta' => $fecha_hasta
                            ]);
                            
                            // Mostrar máximo 5 páginas
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $params_url; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif;
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $params_url; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;
                            
                            if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $params_url; ?>">
                                        <i class="fas fa-chevron-right"></i>
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

<script>
// Función para exportar a Excel
function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'exportar.php?' + params.toString();
}

// Función para exportar a PDF
function exportarPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open('exportar.php?' + params.toString(), '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>