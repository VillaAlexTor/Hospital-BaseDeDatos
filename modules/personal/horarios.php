<?php
/**
 * modules/personal/horarios.php
 * Gestión de horarios de personal (médicos)
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Horarios de Personal";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Médico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para acceder a horarios</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';
$id_personal = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Verificar que el ID de personal exista y sea medico
if (!$id_personal) {
    echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No se especificó personal. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

// Procesar eliminación de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        try {
            $id_horario = $_POST['id_horario'];
            $stmt = $pdo->prepare("DELETE FROM horario_medico WHERE id_horario = ?");
            $stmt->execute([$id_horario]);
            
            log_action('DELETE', 'horario_medico', $id_horario, 'Eliminación de horario');
            $mensaje = 'Horario eliminado correctamente';
            $mensaje_tipo = 'success';
        } catch (Exception $e) {
            $mensaje = 'Error al eliminar horario: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Procesar activación/desactivación de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        try {
            $id_horario = $_POST['id_horario'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            $stmt = $pdo->prepare("UPDATE horario_medico SET activo = ? WHERE id_horario = ?");
            $stmt->execute([$nuevo_estado, $id_horario]);
            
            log_action('UPDATE', 'horario_medico', $id_horario, 'Cambio de estado de horario');
            $mensaje = 'Estado del horario actualizado correctamente';
            $mensaje_tipo = 'success';
        } catch (Exception $e) {
            $mensaje = 'Error al actualizar estado: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// Procesar creación de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        try {
            $dia = $_POST['dia_semana'];
            $hora_inicio = $_POST['hora_inicio'];
            $hora_fin = $_POST['hora_fin'];
            $id_medico = $_POST['id_medico'] ?? $id_personal;

            // Validar que hora_fin sea mayor que hora_inicio
            if ($hora_fin <= $hora_inicio) {
                throw new Exception('La hora de fin debe ser mayor que la hora de inicio');
            }

            // Verificar si ya existe un horario para ese día que se solape
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM horario_medico 
                WHERE id_medico = ? 
                AND dia_semana = ? 
                AND activo = 1
                AND (
                    (hora_inicio <= ? AND hora_fin > ?) OR
                    (hora_inicio < ? AND hora_fin >= ?) OR
                    (hora_inicio >= ? AND hora_fin <= ?)
                )
            ");
            $stmt->execute([
                $id_medico, $dia, 
                $hora_inicio, $hora_inicio,
                $hora_fin, $hora_fin,
                $hora_inicio, $hora_fin
            ]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($existe > 0) {
                throw new Exception('Ya existe un horario que se solapa con el horario especificado');
            }

            $stmt = $pdo->prepare("
                INSERT INTO horario_medico (id_medico, dia_semana, hora_inicio, hora_fin, activo) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$id_medico, $dia, $hora_inicio, $hora_fin]);
            
            log_action('INSERT', 'horario_medico', $pdo->lastInsertId(), 'Creación de horario');
            $mensaje = 'Horario agregado correctamente';
            $mensaje_tipo = 'success';
            
            // Limpiar POST para evitar reenvío
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
        p.codigo_empleado,
        m.id_medico
    FROM persona per 
    INNER JOIN personal p ON per.id_persona = p.id_personal 
    LEFT JOIN medico m ON p.id_personal = m.id_medico
    WHERE p.id_personal = ?
");
$stmt->execute([$id_personal]);
$personal_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personal_info) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Personal no encontrado. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

// Verificar si es médico
if ($personal_info['tipo_personal'] !== 'Medico') {
    echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Los horarios solo pueden asignarse a personal de tipo Médico. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}
// Verificar si existe en la tabla medico
if (empty($personal_info['id_medico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Este médico no está registrado en la tabla de médicos. Debe completar primero su registro como médico. <a href="index.php">Volver al listado</a></div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}
$nombre_personal = decrypt_data($personal_info['nombres']) . ' ' . decrypt_data($personal_info['apellidos']);
$id_medico = $personal_info['id_medico']; // Usar este ID en lugar de id_personal
// Obtener horarios del personal (usa id_medico ahora)
$stmt = $pdo->prepare("
    SELECT * 
    FROM horario_medico 
    WHERE id_medico = ? 
    ORDER BY 
        FIELD(dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'),
        hora_inicio
");
$stmt->execute([$id_medico]); // Cambiar $id_personal por $id_medico
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar horarios por día
$horarios_por_dia = [];
foreach ($horarios as $h) {
    if ($h['activo']) {
        $horarios_por_dia[$h['dia_semana']] = ($horarios_por_dia[$h['dia_semana']] ?? 0) + 1;
    }
}

$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
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
                            <i class="fas fa-clock text-primary me-2"></i>
                            Horarios de Trabajo
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

        <!-- Resumen de horarios por día -->
        <div class="row g-3 mb-4">
            <?php foreach ($dias_semana as $dia): ?>
            <div class="col-lg-1-7 col-md-3 col-sm-6">
                <div class="card shadow-sm border-start <?php echo isset($horarios_por_dia[$dia]) ? 'border-success border-4' : 'border-secondary border-2'; ?>">
                    <div class="card-body text-center py-2">
                        <small class="text-muted d-block"><?php echo $dia; ?></small>
                        <h5 class="mb-0 <?php echo isset($horarios_por_dia[$dia]) ? 'text-success' : 'text-muted'; ?>">
                            <?php echo isset($horarios_por_dia[$dia]) ? $horarios_por_dia[$dia] : '0'; ?>
                        </h5>
                        <small class="text-muted"><?php echo isset($horarios_por_dia[$dia]) && $horarios_por_dia[$dia] === 1 ? 'horario' : 'horarios'; ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulario para agregar horario -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Agregar Nuevo Horario
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_medico" value="<?php echo $id_medico; ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-day me-1"></i>
                            Día de la Semana
                            <span class="text-danger">*</span>
                        </label>
                        <select name="dia_semana" class="form-select" required>
                            <?php foreach($dias_semana as $dia): ?>
                                <option value="<?php echo $dia; ?>"><?php echo $dia; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-clock me-1"></i>
                            Hora de Inicio
                            <span class="text-danger">*</span>
                        </label>
                        <input type="time" name="hora_inicio" class="form-control" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-clock me-1"></i>
                            Hora de Fin
                            <span class="text-danger">*</span>
                        </label>
                        <input type="time" name="hora_fin" class="form-control" required>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-plus me-2"></i>Agregar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de horarios -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list text-info me-2"></i>
                    Horarios Registrados
                </h5>
                <span class="badge bg-info">
                    <?php echo count($horarios); ?> horarios
                </span>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($horarios)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times text-muted" style="font-size: 4rem;"></i>
                        <p class="text-muted mt-3 mb-0 h5">No hay horarios definidos</p>
                        <small class="text-muted">Utiliza el formulario superior para agregar horarios</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3">Día</th>
                                    <th class="px-4 py-3">Hora de Inicio</th>
                                    <th class="px-4 py-3">Hora de Fin</th>
                                    <th class="px-4 py-3">Duración</th>
                                    <th class="px-4 py-3 text-center">Estado</th>
                                    <th class="px-4 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($horarios as $h): ?>
                                    <?php
                                        // Calcular duración
                                        $inicio = new DateTime($h['hora_inicio']);
                                        $fin = new DateTime($h['hora_fin']);
                                        $duracion = $inicio->diff($fin);
                                        $horas = $duracion->h;
                                        $minutos = $duracion->i;
                                        $duracion_texto = $horas . 'h ' . $minutos . 'm';
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-primary fs-6">
                                                <i class="fas fa-calendar-day me-1"></i>
                                                <?php echo htmlspecialchars($h['dia_semana']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <strong><?php echo date('H:i', strtotime($h['hora_inicio'])); ?></strong>
                                        </td>
                                        <td class="px-4 py-3">
                                            <strong><?php echo date('H:i', strtotime($h['hora_fin'])); ?></strong>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas fa-hourglass-half me-1"></i>
                                                <?php echo $duracion_texto; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id_horario" value="<?php echo $h['id_horario']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?php echo $h['activo'] ? 0 : 1; ?>">
                                                <button 
                                                    type="submit" 
                                                    class="badge bg-<?php echo $h['activo'] ? 'success' : 'secondary'; ?> border-0"
                                                    style="cursor: pointer;"
                                                    title="Click para <?php echo $h['activo'] ? 'desactivar' : 'activar'; ?>">
                                                    <i class="fas fa-<?php echo $h['activo'] ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                    <?php echo $h['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este horario?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="eliminar">
                                                <input type="hidden" name="id_horario" value="<?php echo $h['id_horario']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
    /* Hacer que los días de la semana tengan el mismo ancho */
    .col-lg-1-7 {
        flex: 0 0 14.2857%;
        max-width: 14.2857%;
    }
    
    @media (max-width: 992px) {
        .col-lg-1-7 {
            flex: 0 0 25%;
            max-width: 25%;
        }
    }
    
    @media (max-width: 576px) {
        .col-lg-1-7 {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>