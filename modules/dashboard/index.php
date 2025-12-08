<?php
/**
 * modules/dashboard/index.php
 * Dashboard Principal - ACTUALIZADO
 * Redirige al dashboard específico según el rol
 */

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Redirigir según el rol del usuario
$rol = $_SESSION['rol'] ?? 'Usuario';

switch ($rol) {
    case 'Administrador':
        header('Location: admin.php');
        exit();
        
    case 'Médico':
        header('Location: medico.php');
        exit();
        
    case 'Paciente':
        header('Location: paciente.php');
        exit();
        
    default:
        // Dashboard genérico para otros roles
        break;
}

// Si llegó aquí, mostrar dashboard genérico
$page_title = "Dashboard";
require_once '../../includes/header.php';
?>

<?php require_once '../../includes/sidebar.php'; ?>

<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </h1>
    </div>

    <div class="alert alert-info">
        <h5 class="alert-heading">
            <i class="bi bi-person-circle"></i>
            Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario'); ?>
        </h5>
        <p>Rol: <strong><?php echo htmlspecialchars($rol); ?></strong></p>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-info-circle text-muted" style="font-size: 4rem;"></i>
                    <h3 class="mt-3">Panel de Control</h3>
                    <p class="text-muted">Selecciona una opción del menú lateral para comenzar</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>