<?php
/** modules/pacientes/index.php */
$page_title = "Gestión de Pacientes";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Médico', 'Recepcionista'])) {
    die('<div class="alert alert-danger">No tienes permisos para acceder a este módulo</div>');
}

// Parámetros de búsqueda y filtrado
$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';
$grupo_sanguineo = $_GET['grupo_sanguineo'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir query con filtros
$where_conditions = ["pac.estado_paciente != 'inactivo'"];
$params = [];

if (!empty($search)) {
    // Buscar por historia clínica (no encriptada) o desencriptar nombres/apellidos
    $where_conditions[] = "(
        pac.numero_historia_clinica LIKE ? 
        OR AES_DECRYPT(per.nombres, ?) LIKE ? 
        OR AES_DECRYPT(per.apellidos, ?) LIKE ?
    )";
    $search_param = "%$search%";
    
    // Obtener la llave de encriptación
    $encryption_key = ENCRYPTION_KEY; // Asegúrate que esta constante esté definida
    
    $params[] = $search_param; // Para historia_clinica
    $params[] = $encryption_key; // Para desencriptar nombres
    $params[] = $search_param; // Para buscar en nombres
    $params[] = $encryption_key; // Para desencriptar apellidos
    $params[] = $search_param; // Para buscar en apellidos
}

if (!empty($estado)) {
    $where_conditions[] = "pac.estado_paciente = ?";
    $params[] = $estado;
}

if (!empty($grupo_sanguineo)) {
    $where_conditions[] = "pac.grupo_sanguineo = ?";
    $params[] = $grupo_sanguineo;
}

$where_sql = implode(' AND ', $where_conditions);

// Contar total de registros
$count_query = "
    SELECT COUNT(*) as total
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    WHERE $where_sql
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_pages = ceil($total_registros / $limit);

// Obtener pacientes con JOIN para evitar N+1 query
$query = "
    SELECT 
        pac.id_paciente,
        pac.numero_historia_clinica,
        pac.grupo_sanguineo,
        pac.factor_rh,
        pac.estado_paciente,
        pac.fecha_primera_consulta,
        per.tipo_documento,
        per.numero_documento,
        per.nombres,
        per.apellidos,
        per.genero,
        per.fecha_nacimiento,
        per.telefono,
        per.email,
        per.ciudad,
        COUNT(DISTINCT c.id_consulta) as total_consultas
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    LEFT JOIN consulta c ON pac.id_paciente = c.id_paciente
    WHERE $where_sql
    GROUP BY pac.id_paciente
    ORDER BY per.apellidos ASC, per.nombres ASC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pacientes = $stmt->fetchAll();

// Registrar acceso en auditoría
log_action('SELECT', 'paciente', null, "Listado de pacientes - Búsqueda: $search");
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
                            <i class="fas fa-users text-primary me-2"></i>
                            Gestión de Pacientes
                        </h1>
                        <p class="text-muted mb-0">
                            Total de pacientes: <span class="fw-bold"><?php echo number_format($total_registros); ?></span>
                        </p>
                    </div>
                    
                    <?php if (has_any_role(['Administrador', 'Recepcionista'])): ?>
                    <div>
                        <a href="registrar.php" class="btn btn-primary btn-lg shadow-sm">
                            <i class="fas fa-plus me-2"></i>Nuevo Paciente
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Búsqueda -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar
                        </label>
                        <input type="text" 
                                name="search" 
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Nombre, apellido o historia clínica..."
                                class="form-control">
                    </div>
                    
                    <!-- Estado -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Estado
                        </label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="activo" <?php echo $estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="fallecido" <?php echo $estado === 'fallecido' ? 'selected' : ''; ?>>Fallecido</option>
                        </select>
                    </div>
                    
                    <!-- Grupo Sanguíneo -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tint me-2"></i>Grupo Sanguíneo
                        </label>
                        <select name="grupo_sanguineo" class="form-select">
                            <option value="">Todos</option>
                            <?php 
                            $grupos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($grupos as $grupo): 
                            ?>
                                <option value="<?php echo $grupo; ?>" <?php echo $grupo_sanguineo === $grupo ? 'selected' : ''; ?>>
                                    <?php echo $grupo; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botones -->
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($estado) || !empty($grupo_sanguineo)): ?>
                <div class="mt-3">
                    <a href="index.php" class="text-primary">
                        <i class="fas fa-times me-1"></i>Limpiar filtros
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Pacientes Activos</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM paciente WHERE estado_paciente = 'activo'");
                                    echo number_format($stmt->fetchColumn());
                                    ?>
                                </h3>
                            </div>
                            <i class="fas fa-user-check text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Consultas Hoy</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM cita WHERE fecha_cita = CURDATE() AND estado_cita NOT IN ('Cancelada')");
                                    echo number_format($stmt->fetchColumn());
                                    ?>
                                </h3>
                            </div>
                            <i class="fas fa-calendar-day text-primary fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-purple border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Nuevos este mes</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM paciente WHERE MONTH(fecha_primera_consulta) = MONTH(CURDATE())");
                                    echo number_format($stmt->fetchColumn());
                                    ?>
                                </h3>
                            </div>
                            <i class="fas fa-user-plus text-purple fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Con Historial</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(DISTINCT id_paciente) FROM historial_clinico");
                                    echo number_format($stmt->fetchColumn());
                                    ?>
                                </h3>
                            </div>
                            <i class="fas fa-file-medical text-warning fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de pacientes -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Listado de Pacientes
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Historia Clínica</th>
                                <th class="px-4 py-3">Paciente</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Contacto</th>
                                <th class="px-4 py-3 text-center">Grupo Sang.</th>
                                <th class="px-4 py-3 text-center">Consultas</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pacientes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                                    <p class="text-muted mt-3 mb-0">No se encontraron pacientes</p>
                                    <?php if (!empty($search) || !empty($estado)): ?>
                                        <p class="text-muted small">Intenta con otros filtros de búsqueda</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($pacientes as $paciente): 
                                    // Desencriptar datos sensibles
                                    $nombres = decrypt_data($paciente['nombres']);
                                    $apellidos = decrypt_data($paciente['apellidos']);
                                    $telefono = decrypt_data($paciente['telefono']);
                                    $email = decrypt_data($paciente['email']);
                                    
                                    // Calcular edad
                                    $edad = '';
                                    if (!empty($paciente['fecha_nacimiento'])) {
                                        $fecha_nac = decrypt_data($paciente['fecha_nacimiento']);
                                        if ($fecha_nac) {
                                            $fecha_nac_obj = new DateTime($fecha_nac);
                                            $hoy = new DateTime();
                                            $edad = $hoy->diff($fecha_nac_obj)->y . ' años';
                                        }
                                    }
                                ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($paciente['numero_historia_clinica'] ?? 'Sin asignar'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($nombres . ' ' . $apellidos); ?></div>
                                            <small class="text-muted">
                                                <?php echo $paciente['genero'] === 'M' ? 'Masculino' : 'Femenino'; ?>
                                                <?php echo $edad ? ' - ' . $edad : ''; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div><?php echo htmlspecialchars($paciente['tipo_documento']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars(decrypt_data($paciente['numero_documento'])); ?></small>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="small">
                                        <i class="fas fa-phone text-muted me-1"></i>
                                        <?php echo htmlspecialchars($telefono ?: 'No registrado'); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($email ?: 'No registrado'); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($paciente['grupo_sanguineo']): ?>
                                    <span class="badge bg-danger">
                                        <?php echo $paciente['grupo_sanguineo'] . $paciente['factor_rh']; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted small">No registrado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-info"><?php echo $paciente['total_consultas']; ?></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $badge_class = [
                                        'activo' => 'bg-success',
                                        'inactivo' => 'bg-secondary',
                                        'fallecido' => 'bg-dark'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $badge_class[$paciente['estado_paciente']]; ?>">
                                        <?php echo ucfirst($paciente['estado_paciente']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="ver.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="historial.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-outline-purple" 
                                           title="Historial">
                                            <i class="fas fa-file-medical"></i>
                                        </a>
                                        <?php if (has_any_role(['Administrador', 'Recepcionista'])): ?>
                                        <a href="editar.php?id=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-outline-success" 
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="../citas/programar.php?paciente=<?php echo $paciente['id_paciente']; ?>" 
                                           class="btn btn-outline-warning" 
                                           title="Agendar cita">
                                            <i class="fas fa-calendar-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $limit, $total_registros); ?> 
                        de <?php echo number_format($total_registros); ?> registros
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&estado=<?php echo $estado; ?>&grupo_sanguineo=<?php echo $grupo_sanguineo; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
    .text-purple {
        color: #6f42c1 !important;
    }
    
    .bg-purple {
        background-color: #6f42c1 !important;
    }
    
    .border-purple {
        border-color: #6f42c1 !important;
    }
    
    .btn-outline-purple {
        color: #6f42c1;
        border-color: #6f42c1;
    }
    
    .btn-outline-purple:hover {
        color: #fff;
        background-color: #6f42c1;
        border-color: #6f42c1;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>