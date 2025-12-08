    <?php
    /**
     * includes/header.php
     * Header - Cabecera HTML común para todas las páginas
     */

    // Asegurar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Incluir configuración si no está incluida
    if (!defined('SITE_NAME')) {
        require_once __DIR__ . '/config.php';
    }

    // Incluir protección CSRF
    require_once __DIR__ . '/csrf-protection.php';

    // Obtener información del usuario actual
    $nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
    $rol_usuario = $_SESSION['rol'] ?? 'Sin rol';
    $email_usuario = $_SESSION['email'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        
        <!-- CSRF Token para peticiones AJAX -->
        <?php echo meta_csrf(); ?>
        
        <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        
        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Custom CSS -->
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/custom.css">
        
        <!-- Dark Mode CSS (opcional) -->
        <?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/dark-mode.css">
        <?php endif; ?>
        
        <!-- Favicon -->
        <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/images/favicon.png">
        
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                font-size: 14px;
                overflow-x: hidden;
                background-color: #f8f9fa;
            }

            /* NAVBAR - Siempre visible arriba */
            .navbar {
                height: 65px !important;
                padding: 0.5rem 1rem !important;
                position: fixed;
                top: 0;
                left: 250px;
                right: 0;
                z-index: 1030;
                background-color: #343a40;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .navbar-brand {
                font-weight: 600;
                font-size: 1.1rem;
                padding: 0.3rem 0.5rem !important;
            }

            .navbar-dark .btn-dark {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }

            .dropdown-toggle {
                padding: 0.4rem 0.8rem !important;
            }

            /* SIDEBAR - Fijo a la izquierda, ocupa su propio espacio */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 250px;
                background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
                border-right: 2px solid #dee2e6;
                box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                z-index: 1040;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .sidebar-sticky {
                position: relative;
                top: 0;
                height: 100vh;
                padding-top: 1.5rem;
                padding-bottom: 2rem;
                overflow-x: hidden;
                overflow-y: auto;
            }

            /* MAIN - Contenido principal DESPLAZADO correctamente */
            main {
                margin-left: 250px !important;
                margin-top: 65px !important;
                padding: 25px !important;
                min-height: calc(100vh - 65px);
                background-color: #f8f9fa;
                width: calc(100% - 250px) !important;
            }

            /* Corregir TODOS los containers dentro de main */
            main .container-fluid {
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-top: 0 !important;
                margin: 0 !important;
                max-width: none !important;
            }

            /* Los elementos no necesitan z-index especial */
            main .row,
            main .col,
            main .card,
            main .alert {
                position: relative;
            }

            /* NAVEGACIÓN */
            .nav-link {
                color: #495057;
                padding: 12px 20px !important;
                margin: 5px 10px;
                border-radius: 6px;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .nav-link:hover {
                background-color: rgba(13, 110, 253, 0.08);
                color: #0d6efd;
                transform: translateX(5px);
            }

            .nav-link.active {
                background-color: #0d6efd;
                color: white;
                font-weight: 600;
                box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
            }

            .nav-link i {
                width: 22px;
                text-align: center;
                font-size: 1.1rem;
            }

            /* COMPONENTES */
            .user-dropdown {
                cursor: pointer;
            }

            .alert-dismissible {
                padding-right: 3rem;
            }

            .table-actions {
                white-space: nowrap;
            }

            .card {
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                border: none;
                margin-bottom: 1.5rem;
                background-color: white;
                transition: transform 0.2s;
            }

            .card:hover {
                transform: translateY(-5px);
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }

            .card-header {
                background-color: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
                font-weight: 600;
            }

            .badge {
                font-weight: 500;
            }

            .btn-group-sm .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }

            /* LOADING SPINNERS */
            .loading-spinner {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
            }

            .loading-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 9998;
            }

            /* Mensajes Flash - Ajustar posición debajo del navbar */
            .position-fixed.top-0.end-0 {
                margin-top: 75px !important;
                z-index: 1025 !important;
            }

            /* RESPONSIVE */
            @media (max-width: 768px) {
                .navbar {
                    left: 0;
                }
                
                main {
                    margin-left: 0 !important;
                    width: 100% !important;
                }
                
                .sidebar {
                    transform: translateX(-100%);
                    transition: transform 0.3s ease;
                }
                
                .sidebar.show {
                    transform: translateX(0);
                }
            }
        </style>
    </head>
    <body>
        <!-- Sidebar - Incluido aquí para que esté en todas las páginas -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Navbar -->
        <nav class="navbar navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">
                    <i class="bi bi-hospital"></i>
                    <?php echo SITE_NAME; ?>
                </a>
                
                <div class="d-flex align-items-center">
                    <!-- Notificaciones -->
                    <div class="dropdown me-3">
                        <button class="btn btn-dark position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if (isset($_SESSION['notificaciones_count']) && $_SESSION['notificaciones_count'] > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $_SESSION['notificaciones_count']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notificaciones</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><small>No hay notificaciones nuevas</small></a></li>
                        </ul>
                    </div>
                    
                    <!-- Usuario -->
                    <div class="dropdown">
                        <button class="btn btn-dark dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($nombre_usuario); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <span class="dropdown-item-text">
                                    <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($rol_usuario); ?></small>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay"></div>
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>

        <!-- Mensajes de Flash -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1025; margin-top: 70px;">
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 0;">
                <i class="bi bi-check-circle-fill"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']); 
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1025; margin-top: 70px;">
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 0;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['error_message']); 
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1025; margin-top: 70px;">
            <div class="alert alert-warning alert-dismissible fade show" role="alert" style="margin-bottom: 0;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['warning_message']); 
                    unset($_SESSION['warning_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1025; margin-top: 70px;">
            <div class="alert alert-info alert-dismissible fade show" role="alert" style="margin-bottom: 0;">
                <i class="bi bi-info-circle-fill"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['info_message']); 
                    unset($_SESSION['info_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div
        <?php endif; ?>