<?php
/**
 * diagnostico.php
 * Script para verificar el estado de la base de datos
 */
require_once 'includes/config.php';
// Función para verificar tabla
function verificarTabla($pdo, $tabla) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
        $result = $stmt->fetch();
        return [
            'existe' => true,
            'total' => $result['total'],
            'error' => null
        ];
    } catch (PDOException $e) {
        return [
            'existe' => false,
            'total' => 0,
            'error' => $e->getMessage()
        ];
    }
}
// Función para obtener estructura de tabla
function obtenerEstructura($pdo, $tabla) {
    try {
        $stmt = $pdo->query("DESCRIBE {$tabla}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Base de Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-success { background-color: #d1e7dd; }
        .table-danger { background-color: #f8d7da; }
        .table-warning { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">
            <i class="bi bi-database"></i>
            Diagnóstico de Base de Datos - Sistema Hospitalario
        </h1>
        <!-- Información de Conexión -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de Conexión</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
                        <p><strong>Base de Datos:</strong> <?php echo DB_NAME; ?></p>
                        <p><strong>Usuario:</strong> <?php echo DB_USER; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado Conexión:</strong> 
                            <span class="badge bg-success">Conectado</span>
                        </p>
                        <p><strong>Versión MySQL:</strong> 
                            <?php 
                            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                            echo $version;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Resumen General -->
        <?php
        $tablas_principales = [
            'persona', 'paciente', 'personal', 'medico', 'usuario',
            'cita', 'consulta', 'internamiento', 'medicamento',
            'departamento', 'especialidad', 'rol', 'log_auditoria'
        ];
        $resumen = [];
        $total_registros = 0;
        $tablas_vacias = 0;
        $tablas_con_datos = 0;
        foreach ($tablas_principales as $tabla) {
            $info = verificarTabla($pdo, $tabla);
            $resumen[$tabla] = $info;
            if ($info['existe']) {
                $total_registros += $info['total'];
                if ($info['total'] == 0) {
                    $tablas_vacias++;
                } else {
                    $tablas_con_datos++;
                }
            }
        }
        ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Resumen General</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3><?php echo count($tablas_principales); ?></h3>
                        <p>Tablas Verificadas</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-success"><?php echo $tablas_con_datos; ?></h3>
                        <p>Con Datos</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-warning"><?php echo $tablas_vacias; ?></h3>
                        <p>Vacías</p>
                    </div>
                </div>
                <hr>
                <p class="mb-0"><strong>Total de Registros:</strong> <?php echo number_format($total_registros); ?></p>
            </div>
        </div>
        <!-- Estado de Tablas -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Estado de Tablas Principales</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Tabla</th>
                                <th>Estado</th>
                                <th class="text-end">Registros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen as $tabla => $info): ?>
                            <tr class="<?php 
                                if (!$info['existe']) echo 'table-danger';
                                elseif ($info['total'] == 0) echo 'table-warning';
                                else echo 'table-success';
                            ?>">
                                <td><strong><?php echo $tabla; ?></strong></td>
                                <td>
                                    <?php if ($info['existe']): ?>
                                        <span class="badge bg-success">Existe</span>
                                        <?php if ($info['total'] == 0): ?>
                                            <span class="badge bg-warning text-dark">Vacía</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No Existe</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo number_format($info['total']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($info['existe']): ?>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="verEstructura('<?php echo $tabla; ?>')">
                                            Ver Estructura
                                        </button>
                                        <?php if ($info['total'] > 0): ?>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="verDatos('<?php echo $tabla; ?>')">
                                            Ver Datos
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Verificación de Datos Específicos -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">Verificación de Datos para Dashboard</h5>
            </div>
            <div class="card-body">
                <?php
                // Verificar pacientes activos
                $stmt = $pdo->query("SELECT COUNT(*) FROM paciente WHERE estado_paciente = 'activo'");
                $pacientes_activos = $stmt->fetchColumn();
                // Verificar citas de hoy
                $stmt = $pdo->query("SELECT COUNT(*) FROM cita WHERE fecha_cita = CURDATE()");
                $citas_hoy = $stmt->fetchColumn();
                // Verificar personal activo
                $stmt = $pdo->query("SELECT COUNT(*) FROM personal WHERE estado_laboral = 'activo'");
                $personal_activo = $stmt->fetchColumn();
                // Verificar usuarios
                $stmt = $pdo->query("SELECT COUNT(*) FROM usuario WHERE estado = 'activo'");
                $usuarios_activos = $stmt->fetchColumn();
                // Verificar roles
                $stmt = $pdo->query("SELECT COUNT(*) FROM rol WHERE estado = 'activo'");
                $roles_activos = $stmt->fetchColumn();
                ?>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 <?php echo $pacientes_activos > 0 ? 'bg-success-subtle' : 'bg-warning-subtle'; ?>">
                            <h4><?php echo $pacientes_activos; ?></h4>
                            <p class="mb-0">Pacientes Activos</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 <?php echo $citas_hoy > 0 ? 'bg-success-subtle' : 'bg-warning-subtle'; ?>">
                            <h4><?php echo $citas_hoy; ?></h4>
                            <p class="mb-0">Citas de Hoy</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 <?php echo $personal_activo > 0 ? 'bg-success-subtle' : 'bg-warning-subtle'; ?>">
                            <h4><?php echo $personal_activo; ?></h4>
                            <p class="mb-0">Personal Activo</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 <?php echo $usuarios_activos > 0 ? 'bg-success-subtle' : 'bg-warning-subtle'; ?>">
                            <h4><?php echo $usuarios_activos; ?></h4>
                            <p class="mb-0">Usuarios Activos</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 <?php echo $roles_activos > 0 ? 'bg-success-subtle' : 'bg-warning-subtle'; ?>">
                            <h4><?php echo $roles_activos; ?></h4>
                            <p class="mb-0">Roles Activos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Recomendaciones -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Recomendaciones y Acciones</h5>
            </div>
            <div class="card-body">
                <?php if ($tablas_vacias > 0): ?>
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle"></i> Tablas Vacías Detectadas</h6>
                    <p>Hay <?php echo $tablas_vacias; ?> tabla(s) sin datos. Esto puede causar que el dashboard muestre valores en 0.</p>
                    <strong>Acciones sugeridas:</strong>
                    <ul>
                        <?php if ($resumen['paciente']['total'] == 0): ?>
                        <li>Registrar pacientes en: <code>/modules/pacientes/registrar.php</code></li>
                        <?php endif; ?>
                        <?php if ($resumen['personal']['total'] == 0): ?>
                        <li>Registrar personal en: <code>/modules/personal/registrar.php</code></li>
                        <?php endif; ?>
                        <?php if ($resumen['cita']['total'] == 0): ?>
                        <li>Programar citas en: <code>/modules/citas/programar.php</code></li>
                        <?php endif; ?>
                        <?php if ($resumen['rol']['total'] == 0): ?>
                        <li>Crear roles del sistema (ejecutar script de inserción de datos iniciales)</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <h6><i class="bi bi-check-circle"></i> Base de Datos Correcta</h6>
                    <p>Todas las tablas principales contienen datos. El sistema debería funcionar correctamente.</p>
                </div>
                <?php endif; ?>
                <?php if ($total_registros == 0): ?>
                <div class="alert alert-danger">
                    <h6><i class="bi bi-x-circle"></i> Base de Datos Vacía</h6>
                    <p>La base de datos no contiene ningún registro. Necesitas:</p>
                    <ol>
                        <li>Ejecutar el script SQL de creación de base de datos</li>
                        <li>Ejecutar el script SQL de datos iniciales (roles, departamentos, especialidades)</li>
                        <li>Registrar al menos un usuario administrador</li>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Información de Sesión -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Información de Sesión Actual</h5>
            </div>
            <div class="card-body">
                <pre><?php print_r($_SESSION); ?></pre>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">Volver al Sistema</a>
            <a href="modules/dashboard/admin.php" class="btn btn-secondary">Ir al Dashboard Admin</a>
        </div>
    </div>
    <script>
    function verEstructura(tabla) {
        alert('Ver estructura de: ' + tabla);
        // Aquí puedes implementar un modal con AJAX
    }
    function verDatos(tabla) {
        alert('Ver primeros registros de: ' + tabla);
        // Aquí puedes implementar un modal con AJAX
    }
    </script>
</body>
</html>