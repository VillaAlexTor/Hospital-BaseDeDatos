<?php
/**
 * verificar-estados.php
 * Script para verificar y corregir estados de registros
 */
require_once 'includes/config.php';
$auto_corregir = false; // Cambiar a true para aplicar correcciones automáticamente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Estados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">
            <i class="bi bi-gear"></i>
            Verificación y Corrección de Estados
        </h1>
        <!-- PACIENTES -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-people"></i> Pacientes
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        estado_paciente,
                        COUNT(*) as total
                    FROM paciente
                    GROUP BY estado_paciente
                ");
                $estados_pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Total de pacientes
                $stmt = $pdo->query("SELECT COUNT(*) FROM paciente");
                $total_pacientes = $stmt->fetchColumn();
                ?>
                <h6>Distribución de Estados:</h6>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados_pacientes as $estado): 
                            $porcentaje = ($estado['total'] / $total_pacientes) * 100;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $estado['estado_paciente'] === 'activo' ? 'success' : 'warning'; 
                                ?>">
                                    <?php echo $estado['estado_paciente'] ?? 'NULL'; ?>
                                </span>
                            </td>
                            <td><?php echo $estado['total']; ?></td>
                            <td><?php echo number_format($porcentaje, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                // Verificar si hay pacientes sin estado 'activo'
                $stmt = $pdo->query("
                    SELECT COUNT(*) FROM paciente 
                    WHERE estado_paciente IS NULL OR estado_paciente != 'activo'
                ");
                $pacientes_incorrectos = $stmt->fetchColumn();
                ?>
                <?php if ($pacientes_incorrectos > 0): ?>
                <div class="alert alert-warning">
                    <h6>
                        <i class="bi bi-exclamation-triangle"></i>
                        Problema Detectado
                    </h6>
                    <p>
                        Hay <strong><?php echo $pacientes_incorrectos; ?></strong> paciente(s) 
                        que NO tienen estado 'activo'.
                    </p>
                    <?php if (!$auto_corregir): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="accion" value="corregir_pacientes">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-wrench"></i>
                            Corregir Estados de Pacientes
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    Todos los pacientes tienen estado correcto
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- PERSONAL -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Personal
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        estado_laboral,
                        COUNT(*) as total
                    FROM personal
                    GROUP BY estado_laboral
                ");
                $estados_personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->query("SELECT COUNT(*) FROM personal");
                $total_personal = $stmt->fetchColumn();
                ?>
                <h6>Distribución de Estados:</h6>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados_personal as $estado): 
                            $porcentaje = ($estado['total'] / $total_personal) * 100;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $estado['estado_laboral'] === 'activo' ? 'success' : 'secondary'; 
                                ?>">
                                    <?php echo $estado['estado_laboral'] ?? 'NULL'; ?>
                                </span>
                            </td>
                            <td><?php echo $estado['total']; ?></td>
                            <td><?php echo number_format($porcentaje, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $stmt = $pdo->query("
                    SELECT COUNT(*) FROM personal 
                    WHERE estado_laboral IS NULL OR estado_laboral != 'activo'
                ");
                $personal_incorrecto = $stmt->fetchColumn();
                ?>
                <?php if ($personal_incorrecto > 0): ?>
                <div class="alert alert-warning">
                    <h6>
                        <i class="bi bi-exclamation-triangle"></i>
                        Problema Detectado
                    </h6>
                    <p>
                        Hay <strong><?php echo $personal_incorrecto; ?></strong> empleado(s) 
                        que NO tienen estado 'activo'.
                    </p>
                    <?php if (!$auto_corregir): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="accion" value="corregir_personal">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-wrench"></i>
                            Corregir Estados de Personal
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    Todo el personal tiene estado correcto
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- CITAS -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check"></i> Citas
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        estado_cita,
                        COUNT(*) as total
                    FROM cita
                    GROUP BY estado_cita
                ");
                $estados_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->query("SELECT COUNT(*) FROM cita");
                $total_citas = $stmt->fetchColumn();
                ?>
                <h6>Distribución de Estados:</h6>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados_citas as $estado): 
                            $porcentaje = ($estado['total'] / $total_citas) * 100;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php 
                                    $color = [
                                        'Programada' => 'warning',
                                        'Confirmada' => 'success',
                                        'Atendida' => 'primary',
                                        'Cancelada' => 'danger'
                                    ];
                                    echo $color[$estado['estado_cita']] ?? 'secondary';
                                ?>">
                                    <?php echo $estado['estado_cita'] ?? 'NULL'; ?>
                                </span>
                            </td>
                            <td><?php echo $estado['total']; ?></td>
                            <td><?php echo number_format($porcentaje, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Estados de citas son correctos (pueden variar según programación)
                </div>
            </div>
        </div>
        <!-- USUARIOS -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-person-circle"></i> Usuarios
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        estado,
                        COUNT(*) as total
                    FROM usuario
                    GROUP BY estado
                ");
                $estados_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->query("SELECT COUNT(*) FROM usuario");
                $total_usuarios = $stmt->fetchColumn();
                ?>
                <h6>Distribución de Estados:</h6>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados_usuarios as $estado): 
                            $porcentaje = ($estado['total'] / $total_usuarios) * 100;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $estado['estado'] === 'activo' ? 'success' : 'danger'; 
                                ?>">
                                    <?php echo $estado['estado'] ?? 'NULL'; ?>
                                </span>
                            </td>
                            <td><?php echo $estado['total']; ?></td>
                            <td><?php echo number_format($porcentaje, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $stmt = $pdo->query("
                    SELECT COUNT(*) FROM usuario 
                    WHERE estado IS NULL OR estado != 'activo'
                ");
                $usuarios_incorrectos = $stmt->fetchColumn();
                ?>
                <?php if ($usuarios_incorrectos > 0): ?>
                <div class="alert alert-warning">
                    <h6>
                        <i class="bi bi-exclamation-triangle"></i>
                        Problema Detectado
                    </h6>
                    <p>
                        Hay <strong><?php echo $usuarios_incorrectos; ?></strong> usuario(s) 
                        que NO tienen estado 'activo'.
                    </p>
                    <?php if (!$auto_corregir): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="accion" value="corregir_usuarios">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-wrench"></i>
                            Corregir Estados de Usuarios
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    Todos los usuarios tienen estado correcto
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- RESUMEN GENERAL -->
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-clipboard-check"></i> Resumen General
                </h5>
            </div>
            <div class="card-body">
                <?php
                $problemas = 0;
                if ($pacientes_incorrectos > 0) $problemas++;
                if ($personal_incorrecto > 0) $problemas++;
                if ($usuarios_incorrectos > 0) $problemas++;
                ?>
                <?php if ($problemas === 0): ?>
                <div class="alert alert-success">
                    <h4>
                        <i class="bi bi-check-circle-fill"></i>
                        ¡Sistema Correcto!
                    </h4>
                    <p class="mb-0">
                        Todos los estados están configurados correctamente. 
                        El dashboard debería mostrar los datos correctamente.
                    </p>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <h4>
                        <i class="bi bi-x-circle-fill"></i>
                        Se detectaron <?php echo $problemas; ?> problema(s)
                    </h4>
                    <p>Usa los botones de corrección arriba para solucionarlos.</p>
                    <hr>
                    <h6>Corrección Automática de Todo:</h6>
                    <form method="POST">
                        <input type="hidden" name="accion" value="corregir_todo">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="bi bi-tools"></i>
                            Corregir Todos los Problemas
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house"></i>
                Volver al Sistema
            </a>
            <a href="diagnostico.php" class="btn btn-secondary">
                <i class="bi bi-database"></i>
                Ver Diagnóstico Completo
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// PROCESAR CORRECCIONES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    try {
        switch ($accion) {
            case 'corregir_pacientes':
                $stmt = $pdo->prepare("
                    UPDATE paciente 
                    SET estado_paciente = 'activo' 
                    WHERE estado_paciente IS NULL OR estado_paciente = ''
                ");
                $stmt->execute();
                $afectados = $stmt->rowCount();
                echo "<script>
                    alert('✓ Se corrigieron {$afectados} paciente(s)');
                    window.location.href = 'verificar-estados.php';
                </script>";
                break;
            case 'corregir_personal':
                $stmt = $pdo->prepare("
                    UPDATE personal 
                    SET estado_laboral = 'activo' 
                    WHERE estado_laboral IS NULL OR estado_laboral = ''
                ");
                $stmt->execute();
                $afectados = $stmt->rowCount();
                echo "<script>
                    alert('✓ Se corrigieron {$afectados} empleado(s)');
                    window.location.href = 'verificar-estados.php';
                </script>";
                break;
            case 'corregir_usuarios':
                $stmt = $pdo->prepare("
                    UPDATE usuario 
                    SET estado = 'activo' 
                    WHERE estado IS NULL OR estado = ''
                ");
                $stmt->execute();
                $afectados = $stmt->rowCount();
                echo "<script>
                    alert('✓ Se corrigieron {$afectados} usuario(s)');
                    window.location.href = 'verificar-estados.php';
                </script>";
                break;
            case 'corregir_todo':
                // Corregir pacientes
                $stmt = $pdo->prepare("
                    UPDATE paciente 
                    SET estado_paciente = 'activo' 
                    WHERE estado_paciente IS NULL OR estado_paciente = ''
                ");
                $stmt->execute();
                $total_pacientes = $stmt->rowCount();
                // Corregir personal
                $stmt = $pdo->prepare("
                    UPDATE personal 
                    SET estado_laboral = 'activo' 
                    WHERE estado_laboral IS NULL OR estado_laboral = ''
                ");
                $stmt->execute();
                $total_personal = $stmt->rowCount();
                // Corregir usuarios
                $stmt = $pdo->prepare("
                    UPDATE usuario 
                    SET estado = 'activo' 
                    WHERE estado IS NULL OR estado = ''
                ");
                $stmt->execute();
                $total_usuarios = $stmt->rowCount();
                $total = $total_pacientes + $total_personal + $total_usuarios;
                echo "<script>
                    alert('✓ Corrección completa:\\n- Pacientes: {$total_pacientes}\\n- Personal: {$total_personal}\\n- Usuarios: {$total_usuarios}\\n\\nTotal: {$total} registros corregidos');
                    window.location.href = 'verificar-estados.php';
                </script>";
                break;
        }
    } catch (PDOException $e) {
        echo "<script>
            alert('✗ Error al corregir: " . addslashes($e->getMessage()) . "');
            window.location.href = 'verificar-estados.php';
        </script>";
    }
}
?>