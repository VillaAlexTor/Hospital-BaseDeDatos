<?php
/**
 * modules/citas/index.php
 * Vista principal de gestión de citas médicas
 */

require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Gestión de Citas";

// Verificar permisos
if (!tiene_permiso('citas', 'leer')) {
    $_SESSION['error_message'] = 'No tienes permisos para ver citas';
    header('Location: ../dashboard/index.php');
    exit();
}

// Parámetros de vista
$vista = $_GET['vista'] ?? 'calendario';
$fecha_seleccionada = $_GET['fecha'] ?? date('Y-m-d');
$medico_filtro = $_GET['medico'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';

// Si es médico, solo ver sus propias citas
$id_medico_sesion = null;
if ($_SESSION['rol'] === 'Médico') {
    $stmt = $pdo->prepare("
        SELECT m.id_medico 
        FROM medico m
        INNER JOIN personal p ON m.id_medico = p.id_personal
        INNER JOIN persona per ON p.id_personal = per.id_persona
        INNER JOIN usuario u ON per.id_persona = u.id_persona
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $id_medico_sesion = $stmt->fetchColumn();
    $medico_filtro = $id_medico_sesion;
}

// Obtener lista de médicos para el filtro
$stmt_medicos = $pdo->query("
    SELECT m.id_medico, per.nombres, per.apellidos, e.nombre as especialidad
    FROM medico m
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE p.estado_laboral = 'activo'
    ORDER BY per.apellidos, per.nombres
");
$medicos = $stmt_medicos->fetchAll();

// Obtener citas del mes
$mes_actual = date('Y-m', strtotime($fecha_seleccionada));
$primer_dia = date('Y-m-01', strtotime($fecha_seleccionada));
$ultimo_dia = date('Y-m-t', strtotime($fecha_seleccionada));

$where_conditions = ["c.fecha_cita BETWEEN ? AND ?"];
$params = [$primer_dia, $ultimo_dia];

if (!empty($medico_filtro)) {
    $where_conditions[] = "c.id_medico = ?";
    $params[] = $medico_filtro;
}

if (!empty($estado_filtro)) {
    $where_conditions[] = "c.estado_cita = ?";
    $params[] = $estado_filtro;
}

$where_sql = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT 
        c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita, 
        c.tipo_cita, c.motivo_consulta, c.consultorio,
        pac.numero_historia_clinica,
        per_pac.nombres as paciente_nombres,
        per_pac.apellidos as paciente_apellidos,
        per_med.nombres as medico_nombres,
        per_med.apellidos as medico_apellidos,
        e.nombre as especialidad
    FROM cita c
    INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
    INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per_med ON p.id_personal = per_med.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE $where_sql
    ORDER BY c.fecha_cita, c.hora_cita
");
$stmt->execute($params);
$citas = $stmt->fetchAll();

// Agrupar citas por fecha
$citas_por_fecha = [];
foreach ($citas as $cita) {
    $fecha = $cita['fecha_cita'];
    if (!isset($citas_por_fecha[$fecha])) {
        $citas_por_fecha[$fecha] = [];
    }
    $citas_por_fecha[$fecha][] = $cita;
}

// Estadísticas del día
$where_stats = ["c.fecha_cita BETWEEN ? AND ?"];
$params_stats = [$primer_dia, $ultimo_dia];

if (!empty($medico_filtro)) {
    $where_stats[] = "c.id_medico = ?";
    $params_stats[] = $medico_filtro;
}

if (!empty($estado_filtro)) {
    $where_stats[] = "c.estado_cita = ?";
    $params_stats[] = $estado_filtro;
}

$where_stats_sql = implode(' AND ', $where_stats);

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_cita = 'Programada' THEN 1 ELSE 0 END) as programadas,
        SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas,
        SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
        SUM(CASE WHEN fecha_cita = CURDATE() THEN 1 ELSE 0 END) as citas_hoy
    FROM cita c
    WHERE $where_stats_sql
");
$stmt->execute($params_stats);
$stats_mes = $stmt->fetch();

// Estadísticas solo del día de hoy
$stmt_hoy = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_cita = 'Programada' THEN 1 ELSE 0 END) as programadas,
        SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas
    FROM cita 
    WHERE fecha_cita = CURDATE()
");
$stmt_hoy->execute();
$stats_hoy = $stmt_hoy->fetch();

// Usar stats_hoy para las tarjetas, pero mostrar stats_mes como alternativa
$mostrar_stats = ($stats_hoy['total'] > 0) ? $stats_hoy : $stats_mes;
$titulo_periodo = ($stats_hoy['total'] > 0) ? 'Hoy' : 'Este Mes';
require_once '../../includes/header.php';
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
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Gestión de Citas Médicas
                        </h1>
                        <p class="text-muted mb-0">Sistema de agendamiento y control de citas</p>
                    </div>
                    <?php if (tiene_permiso('citas', 'crear')): ?>
                    <div>
                        <a href="programar.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus me-2"></i>Nueva Cita
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estadísticas del período (actualizado) -->
        <div class="row g-3 mb-4">
            <div class="col-12 mb-2">
                <h6 class="text-muted">
                    <i class="fas fa-chart-line me-2"></i>
                    Estadísticas: <?php echo $titulo_periodo; ?>
                    <?php if ($stats_hoy['total'] == 0): ?>
                        <small class="text-warning ms-2">
                            <i class="fas fa-info-circle"></i> No hay citas para hoy. Mostrando datos del mes.
                        </small>
                    <?php endif; ?>
                </h6>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Citas</p>
                                <h3 class="mb-0 fw-bold"><?php echo number_format($mostrar_stats['total']); ?></h3>
                            </div>
                            <i class="fas fa-calendar-day text-primary fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Confirmadas</p>
                                <h3 class="mb-0 fw-bold text-success"><?php echo number_format($mostrar_stats['confirmadas']); ?></h3>
                            </div>
                            <i class="fas fa-check-circle text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Atendidas</p>
                                <h3 class="mb-0 fw-bold text-info"><?php echo number_format($mostrar_stats['atendidas']); ?></h3>
                            </div>
                            <i class="fas fa-user-check text-info fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Pendientes</p>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($mostrar_stats['programadas']); ?></h3>
                            </div>
                            <i class="fas fa-clock text-warning fa-2x"></i>
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
                <form method="GET" class="row g-3">
                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-md me-1"></i>Médico
                        </label>
                        <select name="medico" class="form-select" <?php echo ($id_medico_sesion ? 'disabled' : ''); ?>>
                            <option value="">Todos los médicos</option>
                            <?php foreach ($medicos as $med): ?>
                            <option value="<?php echo $med['id_medico']; ?>" 
                                    <?php echo ($medico_filtro == $med['id_medico']) ? 'selected' : ''; ?>>
                                Dr(a). <?php echo htmlspecialchars($med['apellidos'] . ' ' . $med['nombres']); ?> - 
                                <?php echo htmlspecialchars($med['especialidad']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-info-circle me-1"></i>Estado
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="Programada" <?php echo ($estado_filtro == 'Programada') ? 'selected' : ''; ?>>Programada</option>
                            <option value="Confirmada" <?php echo ($estado_filtro == 'Confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                            <option value="En espera" <?php echo ($estado_filtro == 'En espera') ? 'selected' : ''; ?>>En espera</option>
                            <option value="Atendida" <?php echo ($estado_filtro == 'Atendida') ? 'selected' : ''; ?>>Atendida</option>
                            <option value="Cancelada" <?php echo ($estado_filtro == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-1"></i>Fecha
                        </label>
                        <input type="date" name="fecha" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_seleccionada); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Selector de Vista -->
        <div class="card shadow-sm">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs border-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($vista === 'calendario') ? 'active' : ''; ?>" 
                           href="?vista=calendario&fecha=<?php echo $fecha_seleccionada; ?>&medico=<?php echo $medico_filtro; ?>&estado=<?php echo $estado_filtro; ?>">
                            <i class="fas fa-calendar-alt me-2"></i>Calendario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($vista === 'lista') ? 'active' : ''; ?>" 
                           href="?vista=lista&fecha=<?php echo $fecha_seleccionada; ?>&medico=<?php echo $medico_filtro; ?>&estado=<?php echo $estado_filtro; ?>">
                            <i class="fas fa-list me-2"></i>Lista
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <?php if ($vista === 'calendario'): ?>
                    <!-- VISTA CALENDARIO -->
                    <?php
                    // Calcular días del calendario
                    $primer_dia_semana = date('w', strtotime($primer_dia)); // 0=domingo
                    $total_dias = date('t', strtotime($fecha_seleccionada));
                    
                    // Navegación de mes
                    $mes_anterior = date('Y-m-d', strtotime($fecha_seleccionada . ' -1 month'));
                    $mes_siguiente = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 month'));
                    
                    // Nombres de meses en español
                    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    $mes_nombre = $meses[date('n', strtotime($fecha_seleccionada))];
                    $anio = date('Y', strtotime($fecha_seleccionada));
                    ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?vista=calendario&fecha=<?php echo $mes_anterior; ?>&medico=<?php echo $medico_filtro; ?>&estado=<?php echo $estado_filtro; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-chevron-left me-2"></i>Anterior
                        </a>
                        <h4 class="mb-0 fw-bold"><?php echo $mes_nombre . ' ' . $anio; ?></h4>
                        <a href="?vista=calendario&fecha=<?php echo $mes_siguiente; ?>&medico=<?php echo $medico_filtro; ?>&estado=<?php echo $estado_filtro; ?>" 
                           class="btn btn-outline-secondary">
                            Siguiente<i class="fas fa-chevron-right ms-2"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered calendar-table mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center py-3">Domingo</th>
                                    <th class="text-center py-3">Lunes</th>
                                    <th class="text-center py-3">Martes</th>
                                    <th class="text-center py-3">Miércoles</th>
                                    <th class="text-center py-3">Jueves</th>
                                    <th class="text-center py-3">Viernes</th>
                                    <th class="text-center py-3">Sábado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                <?php
                                // Espacios en blanco antes del primer día
                                for ($i = 0; $i < $primer_dia_semana; $i++) {
                                    echo '<td class="bg-light border"></td>';
                                }

                                // Días del mes
                                for ($dia = 1; $dia <= $total_dias; $dia++) {
                                    $fecha_actual = sprintf('%s-%02d', $mes_actual, $dia);
                                    $es_hoy = ($fecha_actual === date('Y-m-d'));
                                    $citas_dia = $citas_por_fecha[$fecha_actual] ?? [];
                                    $num_citas = count($citas_dia);
                                    
                                    $bg_class = $es_hoy ? 'bg-primary bg-opacity-10 border-primary' : '';
                                    ?>
                                    <td class="calendar-day <?php echo $bg_class; ?>" style="height: 130px; vertical-align: top; position: relative;">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge <?php echo $es_hoy ? 'bg-primary' : 'bg-secondary'; ?> fw-bold">
                                                <?php echo $dia; ?>
                                            </span>
                                            <?php if ($es_hoy): ?>
                                                <span class="badge bg-info">Hoy</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($num_citas > 0): ?>
                                            <div class="small">
                                                <?php 
                                                $mostrar = array_slice($citas_dia, 0, 3);
                                                foreach ($mostrar as $cita): 
                                                    $color_estado = [
                                                        'Programada' => 'warning',
                                                        'Confirmada' => 'success',
                                                        'Atendida' => 'info',
                                                        'Cancelada' => 'danger',
                                                        'En espera' => 'primary'
                                                    ];
                                                    $color = $color_estado[$cita['estado_cita']] ?? 'secondary';
                                                ?>
                                                <div class="mb-1">
                                                    <a href="editar.php?id=<?php echo $cita['id_cita']; ?>" 
                                                       class="text-decoration-none d-block p-2 rounded bg-<?php echo $color; ?> bg-opacity-10 border border-<?php echo $color; ?> hover-shadow">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clock me-1 text-<?php echo $color; ?>" style="font-size: 0.7rem;"></i>
                                                            <small class="text-truncate flex-grow-1">
                                                                <strong><?php echo substr($cita['hora_cita'], 0, 5); ?></strong> - 
                                                                <?php echo htmlspecialchars($cita['paciente_apellidos']); ?>
                                                            </small>
                                                        </div>
                                                    </a>
                                                </div>
                                                <?php endforeach; ?>
                                                
                                                <?php if ($num_citas > 3): ?>
                                                    <small class="text-muted fst-italic">
                                                        <i class="fas fa-plus-circle me-1"></i><?php echo ($num_citas - 3); ?> más
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted fst-italic">Sin citas</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                    // Nueva fila cada 7 días
                                    if (($dia + $primer_dia_semana) % 7 == 0 && $dia < $total_dias) {
                                        echo '</tr><tr>';
                                    }
                                }

                                // Espacios en blanco después del último día
                                $ultimo_dia_semana = ($primer_dia_semana + $total_dias) % 7;
                                if ($ultimo_dia_semana != 0) {
                                    for ($i = $ultimo_dia_semana; $i < 7; $i++) {
                                        echo '<td class="bg-light border"></td>';
                                    }
                                }
                                ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Leyenda -->
                    <div class="mt-4">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-info-circle me-2"></i>Leyenda de Estados
                        </h6>
                        <div class="d-flex flex-wrap gap-3">
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-3 py-2">
                                <i class="fas fa-circle me-1"></i>Programada
                            </span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2">
                                <i class="fas fa-circle me-1"></i>Confirmada
                            </span>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-3 py-2">
                                <i class="fas fa-circle me-1"></i>Atendida
                            </span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2">
                                <i class="fas fa-circle me-1"></i>En espera
                            </span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2">
                                <i class="fas fa-circle me-1"></i>Cancelada
                            </span>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- VISTA LISTA -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3">Fecha/Hora</th>
                                    <th class="px-4 py-3">Paciente</th>
                                    <th class="px-4 py-3">Médico</th>
                                    <th class="px-4 py-3">Especialidad</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3 text-center">Estado</th>
                                    <th class="px-4 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citas)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-calendar-times text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0">No hay citas programadas para este período</p>
                                        <p class="text-muted small">Intenta cambiar los filtros o la fecha seleccionada</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $cita): 
                                        $color_estado = [
                                            'Programada' => 'warning',
                                            'Confirmada' => 'success',
                                            'Atendida' => 'info',
                                            'Cancelada' => 'danger',
                                            'En espera' => 'primary'
                                        ];
                                        $color = $color_estado[$cita['estado_cita']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="fw-bold">
                                                <i class="fas fa-calendar text-primary me-2"></i>
                                                <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo substr($cita['hora_cita'], 0, 5); ?>
                                            </small>
                                        </td>
                                        <td class="px-4">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($cita['paciente_apellidos'] . ', ' . $cita['paciente_nombres']); ?>
                                            </div>
                                            <small class="text-muted">
                                                HC: <?php echo htmlspecialchars($cita['numero_historia_clinica']); ?>
                                            </small>
                                        </td>
                                        <td class="px-4">
                                            <i class="fas fa-user-md text-primary me-2"></i>
                                            Dr(a). <?php echo htmlspecialchars($cita['medico_apellidos'] . ' ' . $cita['medico_nombres']); ?>
                                        </td>
                                        <td class="px-4">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                                <?php echo htmlspecialchars($cita['especialidad']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4"><?php echo htmlspecialchars($cita['tipo_cita']); ?></td>
                                        <td class="px-4 text-center">
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo htmlspecialchars($cita['estado_cita']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 text-center">
                                            <a href="editar.php?id=<?php echo $cita['id_cita']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Ver/Editar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
.calendar-table {
    table-layout: fixed;
}

.calendar-table td {
    min-width: 130px;
    width: 14.28%; /* 100% / 7 días */
}

.calendar-day {
    position: relative;
    padding: 10px;
    cursor: default;
}

.calendar-day:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.hover-shadow:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

/* Mejorar tabs */
.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1rem 1.5rem;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
    background-color: transparent;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    background-color: transparent;
    border-bottom-color: #0d6efd;
    font-weight: 600;
}
</style>

<?php require_once '../../includes/footer.php'; ?>