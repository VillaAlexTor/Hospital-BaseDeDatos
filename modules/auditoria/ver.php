<?php
/**
 * modules/auditoria/ver.php
 * Vista detallada de un evento de auditoría
 */

$page_title = "Detalle de Auditoría";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Auditor'])) {
    die('<div class="alert alert-danger">No tienes permisos para acceder a este módulo</div>');
}

// Obtener ID del log
$id_log = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_log) {
    $_SESSION['error_message'] = 'ID de log inválido';
    header('Location: index.php');
    exit();
}

// Obtener detalles del log
$query = "
    SELECT 
        l.*,
        u.username,
        CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
        p.email,
        s.navegador as sesion_navegador,
        s.sistema_operativo,
        s.dispositivo,
        s.ubicacion_geografica
    FROM log_auditoria l
    LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
    LEFT JOIN persona p ON u.id_persona = p.id_persona
    LEFT JOIN sesion s ON l.id_sesion = s.id_sesion
    WHERE l.id_log = ?
";

$stmt = $pdo->prepare($query);
$stmt->execute([$id_log]);
$log = $stmt->fetch();

if (!$log) {
    $_SESSION['error_message'] = 'Log no encontrado';
    header('Location: index.php');
    exit();
}

// Obtener eventos relacionados (misma sesión o mismo usuario en un rango de tiempo)
$stmt = $pdo->prepare("
    SELECT l.*, u.username
    FROM log_auditoria l
    LEFT JOIN usuario u ON l.id_usuario = u.id_usuario
    WHERE l.id_log != ?
    AND (
        l.id_sesion = ? 
        OR (l.id_usuario = ? AND l.fecha_hora BETWEEN DATE_SUB(?, INTERVAL 5 MINUTE) AND DATE_ADD(?, INTERVAL 5 MINUTE))
    )
    ORDER BY l.fecha_hora DESC
    LIMIT 10
");
$stmt->execute([
    $id_log,
    $log['id_sesion'] ?? 0,
    $log['id_usuario'] ?? 0,
    $log['fecha_hora'],
    $log['fecha_hora']
]);
$eventos_relacionados = $stmt->fetchAll();

// Parsear JSON si existe
$valores_anteriores = null;
$valores_nuevos = null;

if ($log['valores_anteriores']) {
    $valores_anteriores = json_decode($log['valores_anteriores'], true);
}

if ($log['valores_nuevos']) {
    $valores_nuevos = json_decode($log['valores_nuevos'], true);
}
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
                            <i class="fas fa-file-alt text-primary me-2"></i>
                            Detalle de Evento de Auditoría
                        </h1>
                        <p class="text-muted mb-0">Log ID: #<?php echo $id_log; ?></p>
                    </div>
                    
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al listado
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Columna principal -->
            <div class="col-lg-8">
                <!-- Información general -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Información General
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">FECHA Y HORA</label>
                                <div class="fw-bold">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['fecha_hora'])); ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">ACCIÓN</label>
                                <div>
                                    <span class="badge bg-primary fs-6">
                                        <?php echo htmlspecialchars($log['accion']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">TABLA AFECTADA</label>
                                <div class="fw-bold">
                                    <?php if ($log['tabla_afectada']): ?>
                                        <i class="fas fa-table text-secondary me-2"></i>
                                        <?php echo htmlspecialchars($log['tabla_afectada']); ?>
                                        <?php if ($log['registro_id']): ?>
                                            <span class="text-muted small">(ID: <?php echo $log['registro_id']; ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">RESULTADO</label>
                                <div>
                                    <?php
                                    $badge_resultado = [
                                        'Éxito' => 'success',
                                        'Fallo' => 'danger',
                                        'Bloqueado' => 'warning',
                                        'Error' => 'dark'
                                    ];
                                    $color = $badge_resultado[$log['resultado']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo htmlspecialchars($log['resultado']); ?>
                                    </span>
                                    <?php if ($log['codigo_error']): ?>
                                        <span class="text-muted small d-block mt-1">
                                            Código: <?php echo htmlspecialchars($log['codigo_error']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold">CRITICIDAD</label>
                                <div>
                                    <?php
                                    $badge_criticidad = [
                                        'Baja' => 'secondary',
                                        'Media' => 'info',
                                        'Alta' => 'warning',
                                        'Crítica' => 'danger'
                                    ];
                                    $color_crit = $badge_criticidad[$log['criticidad']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color_crit; ?> fs-6">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo htmlspecialchars($log['criticidad']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold">DESCRIPCIÓN</label>
                                <div class="alert alert-light border">
                                    <?php echo nl2br(htmlspecialchars($log['descripcion'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cambios realizados -->
                <?php if ($valores_anteriores || $valores_nuevos): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Cambios Realizados
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 30%;">Campo</th>
                                        <th style="width: 35%;">Valor Anterior</th>
                                        <th style="width: 35%;">Valor Nuevo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $campos = array_unique(array_merge(
                                        array_keys($valores_anteriores ?? []),
                                        array_keys($valores_nuevos ?? [])
                                    ));
                                    
                                    foreach ($campos as $campo):
                                        $anterior = $valores_anteriores[$campo] ?? null;
                                        $nuevo = $valores_nuevos[$campo] ?? null;
                                        $cambio = ($anterior !== $nuevo);
                                    ?>
                                    <tr class="<?php echo $cambio ? 'table-warning' : ''; ?>">
                                        <td class="fw-bold">
                                            <?php if ($cambio): ?>
                                                <i class="fas fa-edit text-warning me-1"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($campo); ?>
                                        </td>
                                        <td>
                                            <?php if ($anterior !== null): ?>
                                                <code class="text-danger"><?php echo htmlspecialchars(json_encode($anterior, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">NULL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($nuevo !== null): ?>
                                                <code class="text-success"><?php echo htmlspecialchars(json_encode($nuevo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">NULL</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Eventos relacionados -->
                <?php if (!empty($eventos_relacionados)): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>
                            Eventos Relacionados
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($eventos_relacionados as $evento): ?>
                            <a href="ver.php?id=<?php echo $evento['id_log']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2"><?php echo htmlspecialchars($evento['accion']); ?></span>
                                        <span class="small"><?php echo htmlspecialchars($evento['descripcion']); ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('H:i:s', strtotime($evento['fecha_hora'])); ?>
                                    </small>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Columna lateral -->
            <div class="col-lg-4">
                <!-- Información del usuario -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>
                            Usuario
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($log['id_usuario']): ?>
                        <div class="text-center mb-3">
                            <div class="avatar bg-primary text-white rounded-circle mx-auto" 
                                 style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                <?php echo strtoupper(substr($log['username'], 0, 2)); ?>
                            </div>
                        </div>
                        <div class="text-center mb-3">
                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($log['nombre_completo']); ?></h6>
                            <p class="text-muted mb-1">@<?php echo htmlspecialchars($log['username']); ?></p>
                            <?php if ($log['email']): ?>
                            <small class="text-muted">
                                <i class="fas fa-envelope me-1"></i>
                                <?php echo htmlspecialchars($log['email']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="border-top pt-3">
                            <a href="index.php?usuario=<?php echo $log['id_usuario']; ?>" 
                               class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-search me-2"></i>Ver actividad del usuario
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-robot fa-3x mb-3"></i>
                            <p class="mb-0">Evento del sistema</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información de sesión -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-desktop me-2"></i>
                            Sesión
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">IP ADDRESS</label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control font-monospace" 
                                       value="<?php echo htmlspecialchars($log['ip_address']); ?>" 
                                       readonly>
                                <button class="btn btn-outline-secondary" 
                                        onclick="copyToClipboard('<?php echo $log['ip_address']; ?>')"
                                        title="Copiar IP">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if ($log['navegador']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NAVEGADOR</label>
                            <div class="small">
                                <i class="fas fa-browser me-1"></i>
                                <?php echo htmlspecialchars(substr($log['navegador'], 0, 100)); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($log['sistema_operativo']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">SISTEMA OPERATIVO</label>
                            <div class="small">
                                <i class="fas fa-laptop me-1"></i>
                                <?php echo htmlspecialchars($log['sistema_operativo']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($log['ubicacion_geografica']): ?>
                        <div>
                            <label class="form-label text-muted small fw-bold">UBICACIÓN</label>
                            <div class="small">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($log['ubicacion_geografica']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Acciones
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button onclick="window.print()" class="btn btn-outline-dark">
                                <i class="fas fa-print me-2"></i>Imprimir
                            </button>
                            <button onclick="exportarJSON()" class="btn btn-outline-primary">
                                <i class="fas fa-file-code me-2"></i>Exportar JSON
                            </button>
                            <?php if ($log['ip_address']): ?>
                            <a href="index.php?search=<?php echo urlencode($log['ip_address']); ?>" 
                               class="btn btn-outline-info">
                                <i class="fas fa-search me-2"></i>Buscar por IP
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function exportarJSON() {
    const data = <?php echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>;
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'auditoria_<?php echo $id_log; ?>.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('JSON exportado correctamente', 'success');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('IP copiada al portapapeles', 'success');
    }).catch(err => {
        console.error('Error al copiar:', err);
    });
}

// Estilos de impresión
window.addEventListener('beforeprint', () => {
    document.querySelector('.navbar')?.classList.add('d-none');
    document.querySelector('.sidebar')?.classList.add('d-none');
});

window.addEventListener('afterprint', () => {
    document.querySelector('.navbar')?.classList.remove('d-none');
    document.querySelector('.sidebar')?.classList.remove('d-none');
});
</script>

<?php require_once '../../includes/footer.php'; ?>