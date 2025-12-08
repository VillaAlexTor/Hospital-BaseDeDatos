<?php
/**
 * modules/consultas-sql/ejecutar.php
 * Módulo: Consultas SQL - Ejecutar
 * Descripción: Permite ejecutar consultas SQL de forma controlada y segura
 * Seguridad: Solo accesible por administradores con permisos especiales
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

// Verificar permiso específico para ejecutar SQL
// TODO: Implementar verificación de permiso 'Ejecutar' en módulo 'ConsultasSQL'

$database = Database::getInstance();
$db = $database->getConnection();

$resultado = null;
$error = null;
$query_ejecutada = '';
$tiempo_ejecucion = 0;
$filas_afectadas = 0;
$tipo_query = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } else {
        
        $query = trim($_POST['query']);
        $query_ejecutada = $query;
        
        // Validaciones de seguridad
        if (empty($query)) {
            $error = "La consulta no puede estar vacía";
        } else {
            
            // Detectar tipo de consulta
            $query_upper = strtoupper($query);
            
            if (strpos($query_upper, 'SELECT') === 0) {
                $tipo_query = 'SELECT';
            } elseif (strpos($query_upper, 'INSERT') === 0) {
                $tipo_query = 'INSERT';
            } elseif (strpos($query_upper, 'UPDATE') === 0) {
                $tipo_query = 'UPDATE';
            } elseif (strpos($query_upper, 'DELETE') === 0) {
                $tipo_query = 'DELETE';
            } elseif (strpos($query_upper, 'CREATE') === 0) {
                $tipo_query = 'CREATE';
            } elseif (strpos($query_upper, 'ALTER') === 0) {
                $tipo_query = 'ALTER';
            } elseif (strpos($query_upper, 'DROP') === 0) {
                $tipo_query = 'DROP';
            } else {
                $tipo_query = 'OTHER';
            }
            
            // Restricciones de seguridad
            $operaciones_peligrosas = ['DROP DATABASE', 'DROP SCHEMA', 'TRUNCATE TABLE'];
            foreach ($operaciones_peligrosas as $operacion) {
                if (stripos($query_upper, $operacion) !== false) {
                    $error = "Operación no permitida: $operacion";
                    break;
                }
            }
            
            if (!$error) {
                try {
                    $db->beginTransaction();
                    
                    $inicio = microtime(true);
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $fin = microtime(true);
                    
                    $tiempo_ejecucion = round(($fin - $inicio) * 1000, 2);
                    
                    if ($tipo_query === 'SELECT') {
                        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $filas_afectadas = count($resultado);
                    } else {
                        $filas_afectadas = $stmt->rowCount();
                        $resultado = "Consulta ejecutada exitosamente";
                    }
                    
                    // Confirmar transacción
                    $db->commit();
                    
                    // Registrar en auditoría
                    $audit_query = "INSERT INTO log_auditoria 
                                   (id_usuario, accion, tabla_afectada, descripcion, ip_address, resultado) 
                                   VALUES (?, 'EXECUTE', 'MANUAL_QUERY', ?, ?, 'Éxito')";
                    $audit_stmt = $db->prepare($audit_query);
                    $audit_stmt->execute([
                        $_SESSION['user_id'],
                        substr($query, 0, 500),
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = "Error en la consulta: " . $e->getMessage();
                    
                    // Registrar error en auditoría
                    $audit_query = "INSERT INTO log_auditoria 
                                   (id_usuario, accion, tabla_afectada, descripcion, ip_address, resultado, codigo_error) 
                                   VALUES (?, 'EXECUTE', 'MANUAL_QUERY', ?, ?, 'Error', ?)";
                    $audit_stmt = $db->prepare($audit_query);
                    $audit_stmt->execute([
                        $_SESSION['user_id'],
                        substr($query, 0, 500),
                        $_SERVER['REMOTE_ADDR'],
                        $e->getCode()
                    ]);
                }
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-code-square"></i> Ejecutar Consulta SQL
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Advertencia de Seguridad -->
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>¡ATENCIÓN!</strong> Esta herramienta permite ejecutar consultas SQL directamente en la base de datos. 
                Use con extrema precaución. Todas las acciones son registradas en el log de auditoría.
            </div>

            <!-- Formulario de Consulta -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-terminal"></i> Editor de Consultas
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="sqlForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="query" class="form-label">Consulta SQL</label>
                            <textarea 
                                class="form-control font-monospace" 
                                id="query" 
                                name="query" 
                                rows="10" 
                                placeholder="SELECT * FROM tabla WHERE condicion;"
                                required
                                spellcheck="false"
                            ><?php echo htmlspecialchars($query_ejecutada ?? ''); ?></textarea>
                            <div class="form-text">
                                Escriba su consulta SQL. Operaciones como DROP DATABASE no están permitidas.
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmExecute" required>
                                    <label class="form-check-label" for="confirmExecute">
                                        Confirmo que he revisado la consulta
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-circle"></i> Ejecutar Consulta
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('query').value='';">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resultados -->
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-x-circle-fill"></i>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($resultado !== null && !$error): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-check-circle-fill"></i> Resultado de la Consulta
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Tipo:</strong> <?php echo htmlspecialchars($tipo_query); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Tiempo:</strong> <?php echo $tiempo_ejecucion; ?> ms
                            </div>
                            <div class="col-md-4">
                                <strong>Filas afectadas:</strong> <?php echo $filas_afectadas; ?>
                            </div>
                        </div>

                        <?php if (is_array($resultado) && count($resultado) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach (array_keys($resultado[0]) as $columna): ?>
                                                <th><?php echo htmlspecialchars($columna); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultado as $fila): ?>
                                            <tr>
                                                <?php foreach ($fila as $valor): ?>
                                                    <td><?php echo htmlspecialchars($valor ?? 'NULL'); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($resultado) >= 100): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle"></i>
                                    Se muestran los primeros 100 resultados. Use LIMIT en su consulta para mejores resultados.
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif (is_string($resultado)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                <?php echo htmlspecialchars($resultado); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                La consulta no devolvió resultados.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Consultas de Ejemplo -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="bi bi-lightbulb"></i> Consultas de Ejemplo
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Consultas SELECT</h6>
                            <pre class="bg-light p-2 rounded"><code>SELECT * FROM paciente LIMIT 10;

SELECT COUNT(*) as total FROM cita 
WHERE estado_cita = 'Programada';

SELECT p.nombres, p.apellidos, c.fecha_cita 
FROM paciente p 
JOIN cita c ON p.id_paciente = c.id_paciente;</code></pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Consultas de Modificación</h6>
                            <pre class="bg-light p-2 rounded"><code>UPDATE cita 
SET estado_cita = 'Confirmada' 
WHERE id_cita = 1;

INSERT INTO departamento (nombre, descripcion) 
VALUES ('Nuevo Dept', 'Descripción');

DELETE FROM cita 
WHERE id_cita = 1 
AND estado_cita = 'Cancelada';</code></pre>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
document.getElementById('sqlForm').addEventListener('submit', function(e) {
    if (!document.getElementById('confirmExecute').checked) {
        e.preventDefault();
        alert('Debe confirmar que ha revisado la consulta antes de ejecutarla.');
        return false;
    }
    
    const query = document.getElementById('query').value.trim().toUpperCase();
    
    // Advertencia para operaciones destructivas
    if (query.includes('DELETE') || query.includes('DROP') || query.includes('TRUNCATE')) {
        if (!confirm('¿Está seguro de ejecutar esta operación? Esta acción puede ser irreversible.')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Atajos de teclado
document.getElementById('query').addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter para ejecutar
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        if (document.getElementById('confirmExecute').checked) {
            document.getElementById('sqlForm').submit();
        } else {
            alert('Debe confirmar la ejecución primero.');
        }
    }
    
    // Tab para indentar
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>