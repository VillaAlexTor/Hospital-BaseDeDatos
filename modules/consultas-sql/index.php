<?php
/**
 * modules/consultas-sql/index.php
 * Módulo: Consultas SQL - Index
 * Descripción: Panel principal para gestión de consultas SQL y análisis de BD
 * Seguridad: Solo accesible por administradores
 */

session_start();
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/security-headers.php';

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ../../index.php');
    exit();
}

$database = Database::getInstance();
$db = $database->getConnection();

// Obtener estadísticas de la base de datos
$estadisticas_bd = [];

try {
    // Total de tablas
    $query = "SELECT COUNT(*) as total FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
    $stmt = $db->query($query);
    $estadisticas_bd['total_tablas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Tamaño de la base de datos
    $query = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as tamanio_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
    $stmt = $db->query($query);
    $estadisticas_bd['tamanio_mb'] = $stmt->fetch(PDO::FETCH_ASSOC)['tamanio_mb'];
    
    // Total de registros aproximado
    $query = "SELECT SUM(table_rows) as total_registros
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
    $stmt = $db->query($query);
    $estadisticas_bd['total_registros'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_registros'];
    
    // Últimas consultas del log de auditoría
    $query = "SELECT COUNT(*) as total 
                FROM log_auditoria 
                WHERE accion = 'EXECUTE' 
                AND DATE(fecha_hora) = CURDATE()";
    $stmt = $db->query($query);
    $estadisticas_bd['consultas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    $estadisticas_bd = [
        'total_tablas' => 0,
        'tamanio_mb' => 0,
        'total_registros' => 0,
        'consultas_hoy' => 0
    ];
}

// Obtener información de tablas principales
$tablas_info = [];
try {
    $query = "SELECT 
                table_name,
                table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) as tamanio_mb,
                engine,
                table_collation,
                create_time,
                update_time
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 20";
    $stmt = $db->query($query);
    $tablas_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tablas_info = [];
}

// Obtener historial reciente de consultas ejecutadas
$historial_consultas = [];
try {
    $query = "SELECT 
                l.id_log,
                l.fecha_hora,
                l.accion,
                l.descripcion,
                l.resultado,
                l.ip_address,
                CONCAT(p.nombres, ' ', p.apellidos) as usuario
                FROM log_auditoria l
                LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
                LEFT JOIN persona p ON u.id_persona = p.id_persona
                WHERE l.accion = 'EXECUTE'
                ORDER BY l.fecha_hora DESC
                LIMIT 15";
    $stmt = $db->query($query);
    $historial_consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historial_consultas = [];
}

// Consultas predefinidas útiles
$consultas_utiles = [
    [
        'nombre' => 'Pacientes Activos',
        'descripcion' => 'Lista de pacientes con estado activo',
        'query' => 'SELECT p.id_paciente, p.numero_historia_clinica, pe.nombres, pe.apellidos, p.grupo_sanguineo, pe.telefono 
                    FROM paciente p 
                    JOIN persona pe ON p.id_paciente = pe.id_persona 
                    WHERE p.estado_paciente = \'activo\' 
                    LIMIT 100;'
    ],
    [
        'nombre' => 'Citas Programadas Hoy',
        'descripcion' => 'Citas programadas para el día de hoy',
        'query' => 'SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita, c.motivo_consulta,
                    CONCAT(pp.nombres, \' \', pp.apellidos) as paciente,
                    CONCAT(pm.nombres, \' \', pm.apellidos) as medico,
                    e.nombre as especialidad
                    FROM cita c
                    JOIN paciente pac ON c.id_paciente = pac.id_paciente
                    JOIN persona pp ON pac.id_paciente = pp.id_persona
                    JOIN medico m ON c.id_medico = m.id_medico
                    JOIN personal per ON m.id_medico = per.id_personal
                    JOIN persona pm ON per.id_personal = pm.id_persona
                    JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                    WHERE c.fecha_cita = CURDATE()
                    ORDER BY c.hora_cita;'
    ],
    [
        'nombre' => 'Inventario Bajo Stock',
        'descripcion' => 'Medicamentos con stock por debajo del mínimo',
        'query' => 'SELECT m.codigo_medicamento, m.nombre_generico, m.nombre_comercial, 
                    m.stock_actual, m.stock_minimo, m.punto_reorden,
                    c.nombre as categoria
                    FROM medicamento m
                    JOIN categoria_medicamento c ON m.id_categoria = c.id_categoria
                    WHERE m.stock_actual <= m.stock_minimo
                    AND m.estado = \'Activo\'
                    ORDER BY m.stock_actual ASC;'
    ],
    [
        'nombre' => 'Ocupación de Habitaciones',
        'descripcion' => 'Estado actual de habitaciones y camas',
        'query' => 'SELECT s.nombre as sala, h.numero_habitacion, h.tipo_habitacion,
                    h.numero_camas, h.camas_ocupadas, h.estado_habitacion,
                    h.precio_dia
                    FROM habitacion h
                    JOIN sala s ON h.id_sala = s.id_sala
                    ORDER BY s.nombre, h.numero_habitacion;'
    ],
    [
        'nombre' => 'Personal por Departamento',
        'descripción' => 'Distribución de personal por departamento',
        'query' => 'SELECT d.nombre as departamento, p.tipo_personal,
                    COUNT(*) as cantidad,
                    COUNT(CASE WHEN p.estado_laboral = \'activo\' THEN 1 END) as activos
                    FROM personal p
                    JOIN departamento d ON p.id_departamento = d.id_departamento
                    JOIN persona pe ON p.id_personal = pe.id_persona
                    GROUP BY d.nombre, p.tipo_personal
                    ORDER BY d.nombre, p.tipo_personal;'
    ],
    [
        'nombre' => 'Medicamentos Próximos a Vencer',
        'descripcion' => 'Lotes con vencimiento en los próximos 3 meses',
        'query' => 'SELECT m.nombre_generico, m.nombre_comercial,
                    l.numero_lote, l.fecha_vencimiento, l.cantidad_actual,
                    DATEDIFF(l.fecha_vencimiento, CURDATE()) as dias_restantes
                    FROM lote_medicamento l
                    JOIN medicamento m ON l.id_medicamento = m.id_medicamento
                    WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                    AND l.cantidad_actual > 0
                    ORDER BY l.fecha_vencimiento ASC;'
    ]
];

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-database"></i> Gestión de Consultas SQL
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="ejecutar.php" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i> Ejecutar Consulta
                    </a>
                </div>
            </div>

            <!-- Estadísticas de la Base de Datos -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Tablas</h6>
                                    <h2 class="mb-0"><?php echo number_format($estadisticas_bd['total_tablas']); ?></h2>
                                </div>
                                <i class="bi bi-table" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Tamaño BD</h6>
                                    <h2 class="mb-0"><?php echo $estadisticas_bd['tamanio_mb']; ?> MB</h2>
                                </div>
                                <i class="bi bi-hdd" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Registros</h6>
                                    <h2 class="mb-0"><?php echo number_format($estadisticas_bd['total_registros']); ?></h2>
                                </div>
                                <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Consultas Hoy</h6>
                                    <h2 class="mb-0"><?php echo number_format($estadisticas_bd['consultas_hoy']); ?></h2>
                                </div>
                                <i class="bi bi-clock-history" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consultas Predefinidas -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-bookmark-star"></i> Consultas Útiles Predefinidas
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($consultas_utiles as $index => $consulta): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-file-code"></i> <?php echo htmlspecialchars($consulta['nombre']); ?>
                                    </h6>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($consulta['descripcion']); ?>
                                    </p>
                                    <pre class="bg-light p-2 rounded small mb-2" style="max-height: 150px; overflow-y: auto;"><code><?php echo htmlspecialchars($consulta['query']); ?></code></pre>
                                    <button class="btn btn-sm btn-primary" onclick="copiarConsulta(<?php echo $index; ?>)">
                                        <i class="bi bi-clipboard"></i> Copiar
                                    </button>
                                    <a href="ejecutar.php" class="btn btn-sm btn-success">
                                        <i class="bi bi-play"></i> Ejecutar
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Información de Tablas -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-table"></i> Información de Tablas (Top 20 por tamaño)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tabla</th>
                                    <th class="text-end">Registros</th>
                                    <th class="text-end">Tamaño (MB)</th>
                                    <th>Motor</th>
                                    <th>Collation</th>
                                    <th>Última Actualización</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tablas_info as $tabla): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tabla['table_name']); ?></strong></td>
                                    <td class="text-end"><?php echo number_format($tabla['table_rows'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo $tabla['tamanio_mb']; ?></td>
                                    <td><?php echo htmlspecialchars($tabla['engine']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($tabla['table_collation']); ?></td>
                                    <td class="small">
                                        <?php echo $tabla['update_time'] ? date('d/m/Y H:i', strtotime($tabla['update_time'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="verEstructura('<?php echo htmlspecialchars($tabla['table_name']); ?>')">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Historial de Consultas Ejecutadas -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-clock-history"></i> Historial de Consultas Ejecutadas (Últimas 15)
                </div>
                <div class="card-body">
                    <?php if (count($historial_consultas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Consulta</th>
                                    <th>Resultado</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial_consultas as $consulta): ?>
                                <tr>
                                    <td class="small"><?php echo date('d/m/Y H:i:s', strtotime($consulta['fecha_hora'])); ?></td>
                                    <td><?php echo htmlspecialchars($consulta['usuario'] ?? 'Sistema'); ?></td>
                                    <td>
                                        <code class="small"><?php echo htmlspecialchars(substr($consulta['descripcion'], 0, 80)); ?>...</code>
                                    </td>
                                    <td>
                                        <?php if ($consulta['resultado'] === 'Éxito'): ?>
                                            <span class="badge bg-success">Éxito</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Error</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($consulta['ip_address']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay consultas ejecutadas en el historial.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Herramientas Rápidas -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-tools"></i> Herramientas Rápidas
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary w-100" onclick="exportarEstructura()">
                                <i class="bi bi-download"></i> Exportar Estructura
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-success w-100" onclick="analizarRendimiento()">
                                <i class="bi bi-speedometer2"></i> Analizar Rendimiento
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-info w-100" onclick="verificarIntegridad()">
                                <i class="bi bi-shield-check"></i> Verificar Integridad
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Modal para estructura de tabla -->
<div class="modal fade" id="modalEstructura" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Estructura de Tabla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="estructuraContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<textarea id="consultasTemp" style="display:none;"><?php echo htmlspecialchars(json_encode($consultas_utiles)); ?></textarea>

<script>
// Copiar consulta al portapapeles
function copiarConsulta(index) {
    const consultas = JSON.parse(document.getElementById('consultasTemp').value);
    const query = consultas[index].query;
    
    navigator.clipboard.writeText(query).then(() => {
        alert('Consulta copiada al portapapeles');
    }).catch(() => {
        // Fallback para navegadores antiguos
        const textarea = document.createElement('textarea');
        textarea.value = query;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Consulta copiada al portapapeles');
    });
}

// Ver estructura de tabla
function verEstructura(nombreTabla) {
    const modal = new bootstrap.Modal(document.getElementById('modalEstructura'));
    const content = document.getElementById('estructuraContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    modal.show();
    
    // Aquí iría una llamada AJAX para obtener la estructura
    setTimeout(() => {
        content.innerHTML = `
            <h6>Tabla: ${nombreTabla}</h6>
            <p class="text-muted">Para ver la estructura completa, ejecute:</p>
            <pre class="bg-light p-3 rounded"><code>DESCRIBE ${nombreTabla};</code></pre>
            <p class="text-muted">O para ver el CREATE TABLE:</p>
            <pre class="bg-light p-3 rounded"><code>SHOW CREATE TABLE ${nombreTabla};</code></pre>
        `;
    }, 500);
}

// Exportar estructura
function exportarEstructura() {
    alert('Funcionalidad de exportación en desarrollo. Use mysqldump o phpMyAdmin para exportar.');
}

// Analizar rendimiento
function analizarRendimiento() {
    alert('Ejecute EXPLAIN en sus consultas para análisis de rendimiento.');
}

// Verificar integridad
function verificarIntegridad() {
    if (confirm('¿Desea ejecutar CHECK TABLE en todas las tablas? Esto puede tomar varios minutos.')) {
        alert('Funcionalidad en desarrollo. Use CHECK TABLE manualmente.');
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>