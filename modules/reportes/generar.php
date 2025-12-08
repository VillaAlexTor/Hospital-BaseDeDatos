<?php
/**
 * modules/reportes/generar.php
 * Generador de reportes: seleccionar tipo, rango de fechas y exportar CSV/Excel
 */

// Verificar autenticación ANTES de header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Generar Reportes";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor', 'Médico'])) {
    echo '<main><div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle me-2"></i>No tienes permisos para generar reportes</div></div></main>';
    require_once '../../includes/footer.php';
    exit();
}

$mensaje = '';
$mensaje_tipo = '';
$output = null;

// Tipos de reportes disponibles
$tipos_reportes = [
    'pacientes' => [
        'nombre' => 'Pacientes',
        'icono' => 'users',
        'color' => 'primary',
        'descripcion' => 'Listado de pacientes registrados'
    ],
    'citas' => [
        'nombre' => 'Citas Médicas',
        'icono' => 'calendar-check',
        'color' => 'success',
        'descripcion' => 'Historial de citas programadas'
    ],
    'internamientos' => [
        'nombre' => 'Internamientos',
        'icono' => 'bed',
        'color' => 'info',
        'descripcion' => 'Registro de internamientos'
    ],
    'inventario' => [
        'nombre' => 'Inventario',
        'icono' => 'boxes',
        'color' => 'warning',
        'descripcion' => 'Estado actual del inventario'
    ],
    'recetas' => [
        'nombre' => 'Recetas',
        'icono' => 'prescription',
        'color' => 'danger',
        'descripcion' => 'Recetas emitidas'
    ],
    'personal' => [
        'nombre' => 'Personal',
        'icono' => 'user-tie',
        'color' => 'secondary',
        'descripcion' => 'Listado de personal activo'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token CSRF inválido';
        $mensaje_tipo = 'danger';
    } else {
        $tipo = $_POST['tipo'] ?? '';
        $from = $_POST['from'] ?? null;
        $to = $_POST['to'] ?? null;
        $formato = $_POST['formato'] ?? 'vista';

        // Validar tipo de reporte
        if (!isset($tipos_reportes[$tipo])) {
            $mensaje = 'Tipo de reporte inválido';
            $mensaje_tipo = 'danger';
        } elseif (empty($from) || empty($to)) {
            $mensaje = 'Debe especificar el rango de fechas';
            $mensaje_tipo = 'warning';
        } elseif (strtotime($from) > strtotime($to)) {
            $mensaje = 'La fecha "Desde" no puede ser mayor que la fecha "Hasta"';
            $mensaje_tipo = 'warning';
        } else {
            try {
                $headers = [];
                $rows = [];

                // Generar reporte según tipo
                switch ($tipo) {
                    case 'pacientes':
                        $stmt = $pdo->prepare("
                            SELECT 
                                p.id_paciente,
                                p.numero_historia_clinica,
                                per.tipo_documento,
                                per.numero_documento,
                                per.nombres,
                                per.apellidos,
                                per.fecha_nacimiento,
                                per.genero,
                                per.telefono,
                                per.email,
                                per.ciudad,
                                p.fecha_primera_consulta,
                                p.estado_paciente
                            FROM paciente p
                            INNER JOIN persona per ON p.id_paciente = per.id_persona
                            WHERE p.fecha_primera_consulta BETWEEN ? AND ?
                            ORDER BY per.apellidos, per.nombres
                        ");
                        $stmt->execute([$from, $to]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID', 'Historia Clínica', 'Tipo Doc', 'Número Doc', 
                            'Nombres', 'Apellidos', 'F. Nacimiento', 'Género',
                            'Teléfono', 'Email', 'Ciudad', 'Primera Consulta', 'Estado Paciente'
                        ];
                        
                        // Desencriptar datos sensibles
                        foreach ($rows as &$row) {
                            $row['numero_documento'] = decrypt_data($row['numero_documento']);
                            $row['nombres'] = decrypt_data($row['nombres']);
                            $row['apellidos'] = decrypt_data($row['apellidos']);
                            $row['telefono'] = decrypt_data($row['telefono']);
                            $row['email'] = decrypt_data($row['email']);
                        }
                        break;

                    case 'citas':
                        $stmt = $pdo->prepare("
                            SELECT 
                                c.id_cita,
                                c.fecha_cita,
                                c.hora_cita,
                                c.estado_cita,
                                c.motivo_consulta,
                                per_pac.nombres as paciente_nombres,
                                per_pac.apellidos as paciente_apellidos,
                                per_med.nombres as medico_nombres,
                                per_med.apellidos as medico_apellidos,
                                e.nombre as especialidad
                            FROM cita c
                            INNER JOIN paciente p ON c.id_paciente = p.id_paciente
                            INNER JOIN persona per_pac ON p.id_paciente = per_pac.id_persona
                            LEFT JOIN personal pers ON c.id_medico = pers.id_personal
                            LEFT JOIN persona per_med ON pers.id_personal = per_med.id_persona
                            LEFT JOIN medico m ON c.id_medico = m.id_medico
                            LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                            WHERE c.fecha_cita BETWEEN ? AND ?
                            ORDER BY c.fecha_cita DESC, c.hora_cita DESC
                        ");
                        $stmt->execute([$from, $to]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID', 'Fecha', 'Hora', 'Estado', 'Motivo',
                            'Paciente', 'Médico', 'Especialidad'
                        ];
                        
                        // Procesar datos
                        foreach ($rows as &$row) {
                            $row['paciente_nombres'] = decrypt_data($row['paciente_nombres']);
                            $row['paciente_apellidos'] = decrypt_data($row['paciente_apellidos']);
                            $row['medico_nombres'] = decrypt_data($row['medico_nombres']);
                            $row['medico_apellidos'] = decrypt_data($row['medico_apellidos']);
                            $row['paciente'] = $row['paciente_nombres'] . ' ' . $row['paciente_apellidos'];
                            $row['medico'] = $row['medico_nombres'] . ' ' . $row['medico_apellidos'];
                        }
                        break;

                    case 'internamientos':
                        $stmt = $pdo->prepare("
                            SELECT 
                                i.id_internamiento,
                                i.fecha_ingreso,
                                i.fecha_alta,
                                i.motivo_internamiento,
                                i.estado_internamiento,
                                per.nombres,
                                per.apellidos,
                                h.numero_habitacion,
                                h.tipo_habitacion,
                                DATEDIFF(IFNULL(i.fecha_alta, CURDATE()), i.fecha_ingreso) as dias_internamiento
                            FROM internamiento i
                            INNER JOIN paciente p ON i.id_paciente = p.id_paciente
                            INNER JOIN persona per ON p.id_paciente = per.id_persona
                            LEFT JOIN cama c ON i.id_cama = c.id_cama
                            LEFT JOIN habitacion h ON c.id_habitacion = h.id_habitacion
                            WHERE i.fecha_ingreso BETWEEN ? AND ?
                            ORDER BY i.fecha_ingreso DESC
                        ");
                        $stmt->execute([$from, $to]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID', 'F. Ingreso', 'F. Alta', 'Motivo', 'Estado',
                            'Nombres', 'Apellidos', 'Habitación', 'Tipo', 'Días'
                        ];
                        
                        foreach ($rows as &$row) {
                            $row['nombres'] = decrypt_data($row['nombres']);
                            $row['apellidos'] = decrypt_data($row['apellidos']);
                        }
                        break;

                    case 'inventario':
                        $stmt = $pdo->query("
                            SELECT 
                                m.id_medicamento,
                                m.codigo_medicamento,
                                m.nombre_generico,
                                m.nombre_comercial,
                                m.presentacion,
                                m.concentracion,
                                m.stock_actual,
                                m.stock_minimo,
                                m.punto_reorden,
                                m.precio_unitario,
                                c.nombre as categoria,
                                m.estado
                            FROM medicamento m
                            LEFT JOIN categoria_medicamento c ON m.id_categoria = c.id_categoria
                            ORDER BY m.nombre_generico
                        ");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID', 'Código', 'Genérico', 'Comercial', 'Presentación',
                            'Concentración', 'Stock Actual', 'Stock Mín', 'Punto Reorden',
                            'Precio', 'Categoría', 'Estado'
                        ];
                        break;

                    case 'recetas':
                        $stmt = $pdo->prepare("
                            SELECT 
                                rm.id_receta,
                                dm.fecha_emision,
                                rm.estado_receta,
                                pac.numero_historia_clinica,
                                per_pac.nombres as paciente_nombres,
                                per_pac.apellidos as paciente_apellidos,
                                per_med.nombres as medico_nombres,
                                per_med.apellidos as medico_apellidos
                            FROM receta_medica rm
                            INNER JOIN documento_medico dm ON rm.id_documento = dm.id_documento
                            INNER JOIN consulta c ON dm.id_consulta = c.id_consulta
                            INNER JOIN paciente pac ON c.id_paciente = pac.id_paciente
                            INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
                            INNER JOIN medico med ON c.id_medico = med.id_medico
                            INNER JOIN persona per_med ON med.id_medico = per_med.id_persona
                            WHERE dm.fecha_emision BETWEEN ? AND ?
                            ORDER BY dm.fecha_emision DESC
                        ");
                        $stmt->execute([$from, $to]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID Receta', 'HC', 'Fecha', 'Estado', 'Paciente', 'Médico'
                        ];
                        
                        foreach ($rows as &$row) {
                            $row['paciente_nombres'] = decrypt_data($row['paciente_nombres']);
                            $row['paciente_apellidos'] = decrypt_data($row['paciente_apellidos']);
                            $row['medico_nombres'] = decrypt_data($row['medico_nombres']);
                            $row['medico_apellidos'] = decrypt_data($row['medico_apellidos']);
                            $row['paciente'] = $row['paciente_nombres'] . ' ' . $row['paciente_apellidos'];
                            $row['medico'] = $row['medico_nombres'] . ' ' . $row['medico_apellidos'];
                        }
                        break;  

                    case 'personal':
                        $stmt = $pdo->query("
                            SELECT 
                                p.id_personal,
                                p.codigo_empleado,
                                per.nombres,
                                per.apellidos,
                                p.tipo_personal,
                                p.fecha_contratacion,
                                p.estado_laboral,
                                per.telefono,
                                per.email,
                                per.ciudad
                            FROM personal p
                            INNER JOIN persona per ON p.id_personal = per.id_persona
                            WHERE p.estado_laboral = 'activo'
                            ORDER BY per.apellidos, per.nombres
                        ");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $headers = [
                            'ID', 'Código', 'Nombres', 'Apellidos', 'Tipo',
                            'F. Contratación', 'Estado', 'Teléfono', 'Email', 'Ciudad'
                        ];
                        
                        foreach ($rows as &$row) {
                            $row['nombres'] = decrypt_data($row['nombres']);
                            $row['apellidos'] = decrypt_data($row['apellidos']);
                            $row['telefono'] = decrypt_data($row['telefono']);
                            $row['email'] = decrypt_data($row['email']);
                        }
                        break;
                }

                // Exportar según formato
                if ($formato === 'csv') {
                    $filename = "reporte_{$tipo}_" . date('Ymd_His') . ".csv";
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    
                    $out = fopen('php://output', 'w');
                    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
                    fputcsv($out, $headers);
                    
                    foreach ($rows as $row) {
                        fputcsv($out, array_values($row));
                    }
                    fclose($out);
                    
                    log_action('EXPORT', 'reporte', $tipo, "Exportado CSV: $tipo de $from a $to");
                    exit();
                    
                } elseif ($formato === 'excel') {
                    $filename = "reporte_{$tipo}_" . date('Ymd_His') . ".xls";
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    
                    echo "<table border='1'>";
                    echo "<thead><tr>";
                    foreach ($headers as $h) {
                        echo "<th>" . htmlspecialchars($h) . "</th>";
                    }
                    echo "</tr></thead><tbody>";
                    
                    foreach ($rows as $row) {
                        echo "<tr>";
                        foreach ($row as $cell) {
                            echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    
                    log_action('EXPORT', 'reporte', $tipo, "Exportado Excel: $tipo de $from a $to");
                    exit();
                }

                // Vista previa
                $output = ['headers' => $headers, 'rows' => $rows];
                $mensaje = 'Reporte generado correctamente. ' . count($rows) . ' registros encontrados.';
                $mensaje_tipo = 'success';
                
                log_action('VIEW', 'reporte', $tipo, "Generado reporte $tipo de $from a $to");

            } catch (Exception $e) {
                $mensaje = 'Error generando reporte: ' . $e->getMessage();
                $mensaje_tipo = 'danger';
                ErrorHandler::logSecure('report_error', $_SESSION['user_id'] ?? null, $e->getMessage(), 'error');
            }
        }
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
                            <i class="fas fa-file-export text-primary me-2"></i>
                            Generar Reportes
                        </h1>
                        <p class="text-muted mb-0">Selecciona el tipo de reporte, rango de fechas y formato de exportación</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <a href="estadisticas.php" class="btn btn-success">
                            <i class="fas fa-chart-bar me-2"></i>Ver Estadísticas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje -->
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

        <!-- Selector de Tipo de Reporte -->
        <div class="row g-3 mb-4">
            <?php foreach ($tipos_reportes as $key => $info): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card shadow-sm h-100 tipo-reporte-card" data-tipo="<?php echo $key; ?>">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-<?php echo $info['color']; ?> bg-opacity-10 p-3 me-3">
                                <i class="fas fa-<?php echo $info['icono']; ?> text-<?php echo $info['color']; ?> fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($info['nombre']); ?></h5>
                                <p class="card-text text-muted small mb-0"><?php echo htmlspecialchars($info['descripcion']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulario de Generación -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Configuración del Reporte
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formReporte">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-file-alt me-1"></i>
                                Tipo de Reporte
                                <span class="text-danger">*</span>
                            </label>
                            <select name="tipo" id="tipoReporte" class="form-select" required>
                                <option value="">-- Seleccione un tipo --</option>
                                <?php foreach ($tipos_reportes as $key => $info): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($info['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-day me-1"></i>
                                Fecha Desde
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="from" 
                                class="form-control" 
                                value="<?php echo date('Y-m-01'); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-day me-1"></i>
                                Fecha Hasta
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="to" 
                                class="form-control" 
                                value="<?php echo date('Y-m-d'); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-file-download me-1"></i>
                                Formato
                            </label>
                            <select name="formato" class="form-select">
                                <option value="vista">Vista Previa</option>
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-play me-2"></i>Generar Reporte
                            </button>
                            <button type="reset" class="btn btn-secondary btn-lg">
                                <i class="fas fa-redo me-2"></i>Limpiar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Vista Previa del Reporte -->
        <?php if ($output): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-table text-success me-2"></i>
                    Vista Previa del Reporte
                </h5>
                <span class="badge bg-success">
                    <?php echo count($output['rows']); ?> registros
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <?php foreach ($output['headers'] as $h): ?>
                                    <th class="px-3 py-2"><?php echo htmlspecialchars($h); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($output['rows'])): ?>
                                <?php foreach ($output['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td class="px-3 py-2">
                                                <?php echo htmlspecialchars($cell ?? '-'); ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($output['headers']); ?>" class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-3 mb-0">No se encontraron registros en el rango especificado</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($output['rows'])): ?>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Total de registros: <strong><?php echo count($output['rows']); ?></strong>
                    </div>
                    <div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($_POST['tipo'] ?? ''); ?>">
                            <input type="hidden" name="from" value="<?php echo htmlspecialchars($_POST['from'] ?? ''); ?>">
                            <input type="hidden" name="to" value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>">
                            <input type="hidden" name="formato" value="csv">
                            <button type="submit" class="btn btn-sm btn-success me-2">
                                <i class="fas fa-file-csv me-1"></i>Descargar CSV
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($_POST['tipo'] ?? ''); ?>">
                            <input type="hidden" name="from" value="<?php echo htmlspecialchars($_POST['from'] ?? ''); ?>">
                            <input type="hidden" name="to" value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>">
                            <input type="hidden" name="formato" value="excel">
                            <button type="submit" class="btn btn-sm btn-info">
                                <i class="fas fa-file-excel me-1"></i>Descargar Excel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
    .tipo-reporte-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .tipo-reporte-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
        border-color: var(--bs-primary);
    }
    
    .tipo-reporte-card.selected {
        border-color: var(--bs-primary);
        background-color: rgba(13, 110, 253, 0.05);
    }
</style>

<script>
// Seleccionar tipo de reporte al hacer click en la card
document.querySelectorAll('.tipo-reporte-card').forEach(card => {
    card.addEventListener('click', function() {
        const tipo = this.getAttribute('data-tipo');
        document.getElementById('tipoReporte').value = tipo;
        
        // Remover clase selected de todas las cards
        document.querySelectorAll('.tipo-reporte-card').forEach(c => c.classList.remove('selected'));
        
        // Agregar clase selected a la card clickeada
        this.classList.add('selected');
    });
});

// Validación de fechas
document.getElementById('formReporte').addEventListener('submit', function(e) {
    const from = new Date(document.querySelector('[name="from"]').value);
    const to = new Date(document.querySelector('[name="to"]').value);
    
    if (from > to) {
        e.preventDefault();
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>