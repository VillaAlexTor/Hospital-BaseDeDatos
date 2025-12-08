<?php
/**
 * modules/auditoria/exportar.php
 * Exportar datos de auditoría a Excel o PDF
 */

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor'])) {
    die('No tienes permisos para exportar datos');
}

$formato = $_GET['export'] ?? 'excel';

// Obtener filtros (mismo código que index.php)
$search = $_GET['search'] ?? '';
$usuario_filtro = $_GET['usuario'] ?? '';
$accion_filtro = $_GET['accion'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$resultado_filtro = $_GET['resultado'] ?? '';
$criticidad_filtro = $_GET['criticidad'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Construir query
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

// Obtener datos (LÍMITE DE 10,000 REGISTROS)
$query = "
    SELECT 
        l.id_log,
        l.fecha_hora,
        l.accion,
        l.tabla_afectada,
        l.registro_id,
        l.descripcion,
        l.ip_address,
        l.navegador,
        l.resultado,
        l.codigo_error,
        l.criticidad,
        u.username,
        CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo
    FROM log_auditoria l
    LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
    LEFT JOIN persona p ON u.id_persona = p.id_persona
    WHERE $where_sql
    ORDER BY l.fecha_hora DESC
    LIMIT 10000
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Registrar exportación
$stmt = $pdo->prepare("
    INSERT INTO log_auditoria (id_usuario, accion, descripcion, ip_address, resultado)
    VALUES (?, 'EXPORT', ?, ?, 'Éxito')
");
$stmt->execute([
    $_SESSION['user_id'],
    "Exportación de auditoría a $formato: " . count($logs) . " registros",
    $_SERVER['REMOTE_ADDR']
]);

// ==========================================
// EXPORTAR A EXCEL (CSV)
// ==========================================
if ($formato === 'excel') {
    $filename = 'auditoria_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'ID',
        'Fecha/Hora',
        'Usuario',
        'Nombre Completo',
        'Acción',
        'Tabla',
        'Registro ID',
        'Descripción',
        'IP Address',
        'Navegador',
        'Resultado',
        'Código Error',
        'Criticidad'
    ]);
    
    // Datos
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id_log'],
            $log['fecha_hora'],
            $log['username'] ?? 'Sistema',
            $log['nombre_completo'] ?? 'N/A',
            $log['accion'],
            $log['tabla_afectada'] ?? '',
            $log['registro_id'] ?? '',
            $log['descripcion'],
            $log['ip_address'],
            substr($log['navegador'] ?? '', 0, 100),
            $log['resultado'],
            $log['codigo_error'] ?? '',
            $log['criticidad']
        ]);
    }
    
    fclose($output);
    exit();
}

// ==========================================
// EXPORTAR A PDF
// ==========================================
if ($formato === 'pdf') {
    // HTML simple que se puede convertir a PDF con navegador
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Auditoría - <?php echo date('d/m/Y H:i'); ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                padding: 20px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            
            .header h1 {
                font-size: 20px;
                margin-bottom: 10px;
            }
            
            .header p {
                color: #666;
                margin: 5px 0;
            }
            
            .filters {
                background-color: #f8f9fa;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            
            .filters h3 {
                font-size: 14px;
                margin-bottom: 10px;
            }
            
            .filters p {
                margin: 5px 0;
                color: #495057;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th {
                background-color: #343a40;
                color: white;
                padding: 10px;
                text-align: left;
                font-size: 9px;
                font-weight: bold;
            }
            
            td {
                border: 1px solid #dee2e6;
                padding: 8px;
                font-size: 8px;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 8px;
                font-weight: bold;
            }
            
            .badge-success { background-color: #28a745; color: white; }
            .badge-danger { background-color: #dc3545; color: white; }
            .badge-warning { background-color: #ffc107; color: black; }
            .badge-info { background-color: #17a2b8; color: white; }
            .badge-secondary { background-color: #6c757d; color: white; }
            
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
                font-size: 9px;
                border-top: 1px solid #dee2e6;
                padding-top: 15px;
            }
            
            @media print {
                body { padding: 10px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <!-- Encabezado -->
        <div class="header">
            <h1>🛡️ REPORTE DE AUDITORÍA DEL SISTEMA</h1>
            <p><strong><?php echo APP_NAME; ?></strong></p>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>Por: <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
        </div>
        
        <!-- Filtros aplicados -->
        <div class="filters">
            <h3>📋 Filtros Aplicados</h3>
            <p><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></p>
            <?php if ($usuario_filtro): ?>
                <p><strong>Usuario:</strong> ID <?php echo $usuario_filtro; ?></p>
            <?php endif; ?>
            <?php if ($accion_filtro): ?>
                <p><strong>Acción:</strong> <?php echo htmlspecialchars($accion_filtro); ?></p>
            <?php endif; ?>
            <?php if ($resultado_filtro): ?>
                <p><strong>Resultado:</strong> <?php echo htmlspecialchars($resultado_filtro); ?></p>
            <?php endif; ?>
            <?php if ($criticidad_filtro): ?>
                <p><strong>Criticidad:</strong> <?php echo htmlspecialchars($criticidad_filtro); ?></p>
            <?php endif; ?>
            <p><strong>Total de registros:</strong> <?php echo number_format(count($logs)); ?></p>
        </div>
        
        <!-- Botón de impresión (solo en pantalla) -->
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
                🖨️ Imprimir / Guardar como PDF
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px;">
                ❌ Cerrar
            </button>
        </div>
        
        <!-- Tabla de datos -->
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 12%;">Fecha/Hora</th>
                    <th style="width: 12%;">Usuario</th>
                    <th style="width: 8%;">Acción</th>
                    <th style="width: 10%;">Tabla</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 10%;">IP</th>
                    <th style="width: 8%;">Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $badge_resultado = [
                        'Éxito' => 'success',
                        'Fallo' => 'danger',
                        'Bloqueado' => 'warning',
                        'Error' => 'dark'
                    ];
                    $color = $badge_resultado[$log['resultado']] ?? 'secondary';
                ?>
                <tr>
                    <td><?php echo $log['id_log']; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($log['fecha_hora'])); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></td>
                    <td><?php echo htmlspecialchars($log['accion']); ?></td>
                    <td><?php echo htmlspecialchars($log['tabla_afectada'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars(substr($log['descripcion'], 0, 100)); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $color; ?>">
                            <?php echo htmlspecialchars($log['resultado']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <p>Este reporte contiene <?php echo number_format(count($logs)); ?> eventos de auditoría</p>
            <p>Sistema Hospitalario - Módulo de Auditoría</p>
            <p>Documento confidencial - Solo para uso interno</p>
        </div>
        
        <script>
            // Auto-imprimir al cargar (opcional)
            // window.onload = () => window.print();
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Si no es formato válido
die('Formato de exportación no válido');