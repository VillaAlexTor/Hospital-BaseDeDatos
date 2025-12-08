<?php
/**
 * modules/personal/turnos.php
 * Asignación y listado de turnos para el personal
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Gestión de Turnos";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para gestionar turnos</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';
$id_personal = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Verificar que el ID de personal exista
if (!$id_personal) {
    echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No se especificó personal. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

// Procesar eliminación de asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        try {
            $id_asignacion = $_POST['id_asignacion'];
            $stmt = $pdo->prepare("DELETE FROM asignacion_turno WHERE id_asignacion = ?");
            $stmt->execute([$id_asignacion]);
            
            log_action('DELETE', 'asignacion_turno', $id_asignacion, 'Eliminación de asignación de turno');
            $mensaje = 'Asignación eliminada correctamente';
            $mensaje_tipo = 'success';
        } catch (Exception $e) {
            $mensaje = 'Error al eliminar asignación: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Procesar asignación de turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        try {
            $id_p = (int) $_POST['id_personal'];
            $id_turno = (int) $_POST['id_turno'];
            $fecha = $_POST['fecha'] ?? null;
            
            if (!$fecha) {
                throw new Exception('La fecha es requerida');
            }

            // Validar que la fecha no sea pasada
            if ($fecha < date('Y-m-d')) {
                throw new Exception('No se pueden asignar turnos en fechas pasadas');
            }

            // Verificar si ya existe una asignación para ese personal en esa fecha
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM asignacion_turno 
                WHERE id_personal = ? AND fecha = ?
            ");
            $stmt->execute([$id_p, $fecha]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($existe > 0) {
                throw new Exception('Ya existe una asignación de turno para esta fecha');
            }

            $stmt = $pdo->prepare("
                INSERT INTO asignacion_turno (id_personal, id_turno, fecha, registrado_por, fecha_registro) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$id_p, $id_turno, $fecha, $_SESSION['user_id']]);
            
            log_action('INSERT', 'asignacion_turno', $pdo->lastInsertId(), 'Asignación de turno');
            $mensaje = 'Turno asignado correctamente';
            $mensaje_tipo = 'success';
            
            // Limpiar POST
            $_POST = [];
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Obtener información del personal
$stmt = $pdo->prepare("
    SELECT 
        per.nombres, 
        per.apellidos,
        p.tipo_personal,
        p.codigo_empleado
    FROM persona per 
    INNER JOIN personal p ON per.id_persona = p.id_personal 
    WHERE p.id_personal = ?
");
$stmt->execute([$id_personal]);
$personal_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personal_info) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Personal no encontrado. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$nombre_personal = decrypt_data($personal_info['nombres']) . ' ' . decrypt_data($personal_info['apellidos']);

// Obtener turnos disponibles
$stmt = $pdo->prepare("SELECT id_turno, nombre, hora_inicio, hora_fin FROM turno WHERE estado = 'Activo' ORDER BY hora_inicio");
$stmt->execute();
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener asignaciones del personal (próximos 30 días + histórico de últimos 30 días)
$fecha_desde = date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = date('Y-m-d', strtotime('+30 days'));

$stmt = $pdo->prepare("
    SELECT 
        a.*,
        t.nombre as turno_nombre,
        t.hora_inicio,
        t.hora_fin,
        u.username as registrado_por_nombre
    FROM asignacion_turno a 
    LEFT JOIN turno t ON a.id_turno = t.id_turno
    LEFT JOIN usuario u ON a.registrado_por = u.id_usuario
    WHERE a.id_personal = ?
    AND a.fecha BETWEEN ? AND ?
    ORDER BY a.fecha DESC
");
$stmt->execute([$id_personal, $fecha_desde, $fecha_hasta]);
$asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar asignaciones por mes
$asignaciones_por_mes = [];
foreach ($asignaciones as $asig) {
    $mes = date('Y-m', strtotime($asig['fecha']));
    $asignaciones_por_mes[$mes][] = $asig;
}

// Calcular estadísticas
$total_asignaciones = count($asignaciones);
$asignaciones_futuras = 0;
$asignaciones_pasadas = 0;
$hoy = date('Y-m-d');

foreach ($asignaciones as $asig) {
    if ($asig['fecha'] >= $hoy) {
        $asignaciones_futuras++;
    } else {
        $asignaciones_pasadas++;
    }
}
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
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Gestión de Turnos
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i>
                            <strong><?php echo htmlspecialchars($nombre_personal); ?></strong>
                            <span class="mx-2">|</span>
                            <i class="fas fa-tag me-2"></i>
                            <?php echo htmlspecialchars($personal_info['tipo_personal']); ?>
                            <span class="mx-2">|</span>
                            <i class="fas fa-id-badge me-2"></i>
                            <?php echo htmlspecialchars($personal_info['codigo_empleado']); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
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

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Asignaciones</p>
                                <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($total_asignaciones); ?></h3>
                            </div>
                            <i class="fas fa-calendar-check text-primary fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Próximos Turnos</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($asignaciones_futuras); ?></h3>
                            </div>
                            <i class="fas fa-arrow-right text-success fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Turnos Completados</p>
                                <h3 class="mb-0 fw-bold text-info"><?php echo number_format($asignaciones_pasadas); ?></h3>
                            </div>
                            <i class="fas fa-check-double text-info fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario para asignar turno -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Asignar Nuevo Turno
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_personal" value="<?php echo $id_personal; ?>">
                    
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-clock me-1"></i>
                            Turno
                            <span class="text-danger">*</span>
                        </label>
                        <select name="id_turno" class="form-select" required>
                            <option value="">-- Seleccionar Turno --</option>
                            <?php foreach($turnos as $t): ?>
                                <option value="<?php echo $t['id_turno']; ?>">
                                    <?php echo htmlspecialchars($t['nombre']); ?> 
                                    (<?php echo date('H:i', strtotime($t['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($t['hora_fin'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-day me-1"></i>
                            Fecha
                            <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="date" 
                            name="fecha" 
                            class="form-control" 
                            min="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                        <small class="text-muted">No se permiten fechas pasadas</small>
                    </div>
                    
                    <div class="col-md-3">
                        <button name="assign" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Asignar Turno
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Turnos Disponibles (Referencia) -->
        <?php if (!empty($turnos)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Turnos Disponibles en el Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach($turnos as $turno): ?>
                        <div class="col-md-4">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="card-title fw-bold">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php echo htmlspecialchars($turno['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-0">
                                        <span class="badge bg-light text-dark border">
                                            <?php echo date('H:i', strtotime($turno['hora_inicio'])); ?>
                                            <i class="fas fa-arrow-right mx-1"></i>
                                            <?php echo date('H:i', strtotime($turno['hora_fin'])); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Asignaciones de Turnos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Asignaciones de Turnos
                </h5>
                <span class="badge bg-info">
                    <?php echo count($asignaciones); ?> asignaciones
                </span>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($asignaciones)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times text-muted" style="font-size: 4rem;"></i>
                        <p class="text-muted mt-3 mb-0 h5">No hay asignaciones de turnos</p>
                        <small class="text-muted">Utiliza el formulario superior para asignar turnos</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($asignaciones_por_mes as $mes => $asigs_mes): ?>
                        <div class="px-4 py-3 bg-light border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-calendar me-2"></i>
                                <?php 
                                    $fecha_mes = DateTime::createFromFormat('Y-m', $mes);
                                    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                    echo $meses[$fecha_mes->format('n') - 1] . ' ' . $fecha_mes->format('Y');
                                ?>
                                <span class="badge bg-secondary ms-2"><?php echo count($asigs_mes); ?> turnos</span>
                            </h6>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3">Fecha</th>
                                        <th class="px-4 py-3">Día</th>
                                        <th class="px-4 py-3">Turno</th>
                                        <th class="px-4 py-3">Horario</th>
                                        <th class="px-4 py-3">Registrado Por</th>
                                        <th class="px-4 py-3 text-center">Estado</th>
                                        <th class="px-4 py-3 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($asigs_mes as $a): ?>
                                        <?php
                                            $fecha_asig = new DateTime($a['fecha']);
                                            $hoy_dt = new DateTime();
                                            $es_futuro = $fecha_asig >= $hoy_dt;
                                            $es_hoy = $fecha_asig->format('Y-m-d') === $hoy_dt->format('Y-m-d');
                                            $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                                        ?>
                                        <tr class="<?php echo $es_hoy ? 'table-warning' : ''; ?>">
                                            <td class="px-4 py-3">
                                                <strong><?php echo $fecha_asig->format('d/m/Y'); ?></strong>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo $dias_semana[$fecha_asig->format('w')]; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <strong><?php echo htmlspecialchars($a['turno_nombre']); ?></strong>
                                            </td>
                                            <td class="px-4 py-3">
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($a['hora_inicio'])); ?>
                                                    <i class="fas fa-arrow-right mx-1"></i>
                                                    <?php echo date('H:i', strtotime($a['hora_fin'])); ?>
                                                </small>
                                            </td>
                                            <td class="px-4 py-3">
                                                <small>
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($a['registrado_por_nombre']); ?>
                                                </small>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($es_hoy): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-star me-1"></i>Hoy
                                                    </span>
                                                <?php elseif ($es_futuro): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-clock me-1"></i>Próximo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-check me-1"></i>Completado
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($es_futuro): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta asignación?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="eliminar">
                                                        <input type="hidden" name="id_asignacion" value="<?php echo $a['id_asignacion']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>