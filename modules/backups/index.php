<?php
/**
 * modules/backups/index.php
 * Módulo de Gestión de Backups
 * Solo accesible para Administradores
 */

// Configuración de la sesión y permisos
session_start();
require_once '../../config/database.php';
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Verificar que sea administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit();
}

$page_title = 'Gestión de Backups';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <?php require_once '../../includes/sidebar.php'; ?>
        </div>

        <!-- Contenido Principal -->
        <div class="col-md-10 p-4">
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-cloud-arrow-down text-primary"></i>
                        Gestión de Backups
                    </h2>
                    <p class="text-muted mb-0">
                        Administración de respaldos de la base de datos
                    </p>
                </div>
                <button class="btn btn-primary" onclick="BackupsModule.showCreateModal()">
                    <i class="bi bi-plus-circle"></i>
                    Crear Backup
                </button>
            </div>

            <!-- Alertas -->
            <div id="alertContainer"></div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Backups</h6>
                                    <h3 class="mb-0 mt-2" id="stat-total">0</h3>
                                </div>
                                <i class="bi bi-database fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Completados</h6>
                                    <h3 class="mb-0 mt-2" id="stat-completed">0</h3>
                                </div>
                                <i class="bi bi-check-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">En Proceso</h6>
                                    <h3 class="mb-0 mt-2" id="stat-processing">0</h3>
                                </div>
                                <i class="bi bi-hourglass-split fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Tamaño Total</h6>
                                    <h3 class="mb-0 mt-2" id="stat-size">0 MB</h3>
                                </div>
                                <i class="bi bi-hdd fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Backup</label>
                            <select class="form-select" id="filterType">
                                <option value="">Todos</option>
                                <option value="Completo">Completo</option>
                                <option value="Incremental">Incremental</option>
                                <option value="Diferencial">Diferencial</option>
                                <option value="Transaccional">Transaccional</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="filterStatus">
                                <option value="">Todos</option>
                                <option value="Completado">Completado</option>
                                <option value="En proceso">En proceso</option>
                                <option value="Fallido">Fallido</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="filterDateFrom">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="filterDateTo">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="BackupsModule.applyFilters()">
                            <i class="bi bi-funnel"></i> Aplicar Filtros
                        </button>
                        <button class="btn btn-secondary" onclick="BackupsModule.clearFilters()">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Backups -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul"></i>
                        Lista de Backups
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="backupsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Fecha Inicio</th>
                                    <th>Duración</th>
                                    <th>Tamaño</th>
                                    <th>Estado</th>
                                    <th>Cifrado</th>
                                    <th>Comprimido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="backupsTableBody">
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Cargando backups...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Mostrando <span id="showing-start">0</span> a <span id="showing-end">0</span> 
                            de <span id="total-records">0</span> registros
                        </div>
                        <nav>
                            <ul class="pagination mb-0" id="pagination">
                                <!-- Paginación generada dinámicamente -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Crear Backup -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i>
                    Crear Nuevo Backup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createBackupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Backup <span class="text-danger">*</span></label>
                        <select class="form-select" id="backupType" required>
                            <option value="">Seleccione...</option>
                            <option value="Completo">Completo - Toda la base de datos</option>
                            <option value="Incremental">Incremental - Solo cambios desde último backup</option>
                            <option value="Diferencial">Diferencial - Cambios desde último backup completo</option>
                            <option value="Transaccional">Transaccional - Registro de transacciones</option>
                        </select>
                        <small class="form-text text-muted">
                            El backup completo puede tardar varios minutos
                        </small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="cifrado" checked>
                            <label class="form-check-label" for="cifrado">
                                Cifrar backup (Recomendado)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="comprimido" checked>
                            <label class="form-check-label" for="comprimido">
                                Comprimir backup
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" rows="3" 
                            placeholder="Motivo o notas adicionales..."></textarea>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nota:</strong> El proceso de backup puede tomar tiempo dependiendo 
                        del tamaño de la base de datos. No cierre esta ventana hasta que finalice.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnCreateBackup">
                        <i class="bi bi-cloud-arrow-down"></i>
                        Crear Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalles del Backup -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle"></i>
                    Detalles del Backup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="backupDetails">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<!-- Modal: Restaurar Backup -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i>
                    Restaurar Backup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="restoreBackupForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>¡ADVERTENCIA!</strong>
                        <p class="mb-0">Esta acción reemplazará los datos actuales de la base de datos 
                        con el contenido del backup seleccionado. Esta operación no se puede deshacer.</p>
                    </div>

                    <input type="hidden" id="restoreBackupId">

                    <div class="mb-3">
                        <label class="form-label">Tipo de Restauración</label>
                        <select class="form-select" id="restorationType" required>
                            <option value="Completa">Completa - Restaurar toda la base de datos</option>
                            <option value="Parcial">Parcial - Seleccionar tablas específicas</option>
                        </select>
                    </div>

                    <div class="mb-3" id="tablesContainer" style="display:none;">
                        <label class="form-label">Tablas a Restaurar</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllTables">
                            <label class="form-check-label" for="selectAllTables">
                                <strong>Seleccionar todas</strong>
                            </label>
                        </div>
                        <hr>
                        <div id="tablesList" class="overflow-auto" style="max-height: 200px;">
                            <!-- Lista de tablas generada dinámicamente -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motivo de la Restauración <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="restoreMotivo" rows="3" required
                            placeholder="Describa el motivo de esta restauración..."></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label text-danger" for="confirmRestore">
                            <strong>Confirmo que deseo realizar esta restauración</strong>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnRestore">
                        <i class="bi bi-arrow-clockwise"></i>
                        Restaurar Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para el módulo de backups */
.badge-tipo {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
}

.badge-estado {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
}

#backupsTable tbody tr:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0 2px;
}

.modal-body .form-check {
    padding-left: 2rem;
}

.spinner-backup {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes progress-pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.progress-bar-animated {
    animation: progress-pulse 1s ease-in-out infinite;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

<!-- Scripts específicos del módulo -->
<script>
// Configuración base para el módulo
const BACKUP_CONFIG = {
    baseUrl: '<?php echo SITE_URL; ?>',
    apiUrl: '<?php echo SITE_URL; ?>/api/backups.php'
};

console.log('Configuración cargada:', BACKUP_CONFIG);
</script>
<script src="<?php echo SITE_URL; ?>/assets/js/modules/backups.js"></script>
<script>
    // Inicializar módulo al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM cargado, inicializando BackupsModule...');
        
        // Verificar que BackupsModule existe
        if (typeof BackupsModule !== 'undefined') {
            console.log('BackupsModule encontrado');
            BackupsModule.init();
        } else {
            console.error('BackupsModule no está definido. Verifica que backups.js se haya cargado correctamente.');
        }
    });
</script>