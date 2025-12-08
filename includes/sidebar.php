<?php
/**
 * includes/sidebar.php
 * Sidebar - Barra lateral de navegación
 * Muestra el menú según el rol del usuario
 */

// Obtener la página actual para marcar el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Función helper para verificar si un menú está activo
function is_active($module, $page = '') {
    global $current_module, $current_page;
    if ($page) {
        return ($current_module === $module && $current_page === $page) ? 'active' : '';
    }
    return ($current_module === $module) ? 'active' : '';
}

// Definir menús según rol
$rol = $_SESSION['rol'] ?? 'Invitado';
?>

<nav id="sidebarMenu" class="sidebar">
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            
            <!-- Dashboard - Visible para todos -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_module === 'dashboard' || $current_page === 'index.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/modules/dashboard/index.php">
                    <i class="bi bi-house-door"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <?php if ($rol === 'Administrador' || $rol === 'Médico' || $rol === 'Recepcionista'): ?>
            <!-- Pacientes -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('pacientes'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/pacientes/index.php">
                    <i class="bi bi-people"></i>
                    <span>Pacientes</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador' || $rol === 'Médico' || $rol === 'Recepcionista'): ?>
            <!-- Citas -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('citas'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/citas/index.php">
                    <i class="bi bi-calendar-check"></i>
                    <span>Citas Médicas</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador' || $rol === 'Médico'): ?>
            <!-- Historia Clínica -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('historial-clinico'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/historial-clinico/index.php">
                    <i class="bi bi-file-medical"></i>
                    <span>Historia Clínica</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador' || $rol === 'Médico' || $rol === 'Enfermero'): ?>
            <!-- Internamiento -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('internamiento'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/internamiento/index.php">
                    <i class="bi bi-hospital"></i>
                    <span>Internamiento</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador' || $rol === 'Farmacia'): ?>
            <!-- Inventario -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('inventario'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/inventario/index.php">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventario</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador'): ?>
            <!-- Personal -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('personal'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/personal/index.php">
                    <i class="bi bi-person-badge"></i>
                    <span>Personal</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($rol === 'Administrador' || $rol === 'Médico'): ?>
            <!-- Reportes -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('reportes'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/reportes/index.php">
                    <i class="bi bi-graph-up"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Separador -->
            <?php if ($rol === 'Administrador'): ?>
            <li class="nav-item">
                <hr class="sidebar-divider">
            </li>

            <!-- Administración -->
            <li class="nav-item">
                <h6 class="sidebar-heading">
                    <span>Administración</span>
                </h6>
            </li>

            <!-- 🆕 GESTIÓN DE USUARIOS -->
            <li class="nav-item">
                <a class="nav-link <?php echo is_active('usuarios'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/usuarios/index.php">
                    <i class="bi bi-people-fill"></i>
                    <span>Gestión de Usuarios</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo is_active('auditoria'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/auditoria/index.php">
                    <i class="bi bi-list-check"></i>
                    <span>Auditoría</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo is_active('consultas-sql'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/consultas-sql/index.php">
                    <i class="bi bi-database"></i>
                    <span>Consultas SQL</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo is_active('backups'); ?>" 
                   href="<?php echo SITE_URL; ?>/modules/backups/index.php">
                    <i class="bi bi-cloud-arrow-down"></i>
                    <span>Backups</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Separador final -->
            <li class="nav-item">
                <hr class="sidebar-divider">
            </li>

            <!-- Cerrar Sesión -->
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/logout.php" 
                   onclick="return confirm('¿Está seguro que desea cerrar sesión?');">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>

        </ul>

        <!-- Información adicional al final -->
        <div class="sidebar-footer">
            <div class="sidebar-info">
                <small class="text-muted d-block">
                    <i class="bi bi-clock"></i>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </small>
                <small class="text-muted d-block">
                    <i class="bi bi-person"></i>
                    <span><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario'); ?></span>
                </small>
                <small class="text-muted d-block">
                    <i class="bi bi-tag"></i>
                    <span><?php echo htmlspecialchars($rol); ?></span>
                </small>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Estilos del Sidebar */
    .sidebar {
        background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    }

    .sidebar-sticky {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    .sidebar .nav {
        flex: 1;
        padding: 0;
        margin: 0;
    }

    .sidebar .nav-item {
        list-style: none;
        margin: 0;
    }

    .sidebar .nav-link {
        font-weight: 400;
        color: #ecf0f1;
        padding: 14px 20px !important;
        margin: 3px 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }
    
    .sidebar .nav-link:hover {
        color: #fff;
        background-color: rgba(52, 152, 219, 0.3);
        transform: translateX(8px);
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
    }
    
    .sidebar .nav-link.active {
        color: #fff;
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        transform: translateX(5px);
    }
    
    .sidebar .nav-link i {
        width: 24px;
        text-align: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .sidebar .nav-link span {
        flex: 1;
    }

    /* Badge de NUEVO */
    .sidebar .nav-link .badge {
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.9; }
    }

    .sidebar-heading {
        font-size: 0.7rem;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.1em;
        color: #95a5a6 !important;
        padding: 20px 20px 8px 20px !important;
        margin: 10px 0 5px 0 !important;
        border-top: 1px solid rgba(236, 240, 241, 0.1);
    }

    .sidebar-divider {
        margin: 15px 10px;
        border-color: rgba(236, 240, 241, 0.1);
        opacity: 0.3;
    }

    .sidebar .text-danger {
        color: #e74c3c !important;
    }

    .sidebar .text-danger:hover {
        background-color: rgba(231, 76, 60, 0.2) !important;
        color: #fff !important;
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 15px 10px 20px 10px;
    }

    .sidebar-info {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(236, 240, 241, 0.1);
        border-radius: 8px;
        padding: 15px;
    }

    .sidebar-info small {
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.8;
        color: #bdc3c7;
        font-size: 0.75rem;
    }

    .sidebar-info i {
        width: 16px;
        text-align: center;
        color: #3498db;
    }

    /* Scrollbar personalizado para el sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(52, 152, 219, 0.5);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(52, 152, 219, 0.8);
    }
</style>