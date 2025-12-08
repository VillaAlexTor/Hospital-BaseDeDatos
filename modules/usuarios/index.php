<?php
/**
 * modules/usuarios/index.php
 * Módulo de Gestión de Usuarios (Solo Administradores)
 */

// Ruta correcta desde modules/usuarios/
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';

$page_title = "Gestión de Usuarios";

// Verificar que sea administrador
if ($_SESSION['rol'] !== 'Administrador') {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a este módulo';
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-people-fill"></i>
            Gestión de Usuarios
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-success" onclick="abrirModalCrearDoctor()">
                    <i class="bi bi-person-plus-fill"></i> Crear Usuario Médico
                </button>
                <button type="button" class="btn btn-primary" onclick="abrirModalCrearPaciente()">
                    <i class="bi bi-person-plus"></i> Crear Usuario Paciente
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Buscar por username, nombre...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filtrar por Rol</label>
                    <select class="form-select" id="filterRol">
                        <option value="">Todos los roles</option>
                        <option value="Administrador">Administrador</option>
                        <option value="Médico">Médico</option>
                        <option value="Paciente">Paciente</option>
                        <option value="Enfermero">Enfermero</option>
                        <option value="Recepcionista">Recepcionista</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filtrar por Estado</label>
                    <select class="form-select" id="filterEstado">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                        <option value="bloqueado">Bloqueado</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="cargarUsuarios()">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tablaUsuarios">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nombre Completo</th>
                            <th>Rol(es)</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Fecha Creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyUsuarios">
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <nav aria-label="Paginación">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>
</main>

<!-- Modal: Crear Usuario Médico -->
<div class="modal fade" id="modalCrearDoctor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge"></i> Crear Usuario para Médico
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCrearDoctor">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Médico <span class="text-danger">*</span></label>
                        <select class="form-select" id="selectMedico" required>
                            <option value="">Cargando médicos...</option>
                        </select>
                        <small class="form-text text-muted">Solo se muestran médicos activos sin usuario</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="usernameDoctor" required
                               pattern="[a-zA-Z0-9._-]+" minlength="4" maxlength="50"
                               placeholder="Ej: dr.juan.perez">
                        <small class="form-text text-muted">Mínimo 4 caracteres. Solo letras, números, punto, guion</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="passwordDoctor" required
                               minlength="8">
                        <small class="form-text text-muted">Mínimo 8 caracteres. El médico deberá cambiarla en el primer login</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nota:</strong> El usuario tendrá rol de "Médico" y deberá cambiar la contraseña en su primer inicio de sesión.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="crearUsuarioDoctor()">
                    <i class="bi bi-save"></i> Crear Usuario
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear Usuario Paciente -->
<div class="modal fade" id="modalCrearPaciente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person"></i> Crear Usuario para Paciente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCrearPaciente">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Paciente <span class="text-danger">*</span></label>
                        <select class="form-select" id="selectPaciente" required>
                            <option value="">Cargando pacientes...</option>
                        </select>
                        <small class="form-text text-muted">Solo se muestran pacientes activos sin usuario</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="usernamePaciente" required
                               pattern="[a-zA-Z0-9._-]+" minlength="4" maxlength="50"
                               placeholder="Ej: juan.perez">
                        <small class="form-text text-muted">Mínimo 4 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="passwordPaciente" required
                               minlength="8">
                        <small class="form-text text-muted">Mínimo 8 caracteres</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        El paciente podrá acceder a su portal de salud y ver su historial médico.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="crearUsuarioPaciente()">
                    <i class="bi bi-save"></i> Crear Usuario
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="idUsuarioCambiarEstado">
                <div class="mb-3">
                    <label class="form-label">Nuevo Estado</label>
                    <select class="form-select" id="nuevoEstado">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                        <option value="bloqueado">Bloqueado</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="cambiarEstado()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Resetear Contraseña -->
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-key"></i> Resetear Contraseña
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="idUsuarioResetPassword">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    El usuario deberá cambiar esta contraseña en su próximo inicio de sesión.
                </div>
                <div class="mb-3">
                    <label class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="nuevaPassword" required
                           minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirmarPassword" required
                           minlength="8">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="resetearPassword()">
                    <i class="bi bi-key"></i> Resetear Contraseña
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Verificar si CSRF_TOKEN ya existe (puede venir de otro archivo JS)
if (typeof CSRF_TOKEN === 'undefined') {
    const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
}
const csrfToken = '<?php echo generate_csrf_token(); ?>'; // Variable alternativa

let currentPage = 1;
let totalPages = 1;

// ==================== CARGAR USUARIOS ====================
async function cargarUsuarios(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const rol = document.getElementById('filterRol').value;
    const estado = document.getElementById('filterEstado').value;
    
    try {
        const url = `../../api/usuarios.php?action=list&page=${page}&search=${encodeURIComponent(search)}&rol=${encodeURIComponent(rol)}&estado=${encodeURIComponent(estado)}`;
        console.log('Fetching:', url); // Debug
        
        const response = await fetch(url, {
            headers: { 'X-CSRF-Token': csrfToken }
        });
        
        console.log('Response status:', response.status); // Debug
        
        const data = await response.json();
        console.log('Data received:', data); // Debug
        
        if (data.success) {
            mostrarUsuarios(data.data.usuarios);
            totalPages = data.data.total_pages;
            actualizarPaginacion();
        } else {
            mostrarError(data.message || 'Error desconocido');
        }
    } catch (error) {
        console.error('Error completo:', error);
        mostrarError('Error al cargar usuarios: ' + error.message);
        // Mostrar mensaje en tabla
        document.getElementById('tbodyUsuarios').innerHTML = 
            `<tr><td colspan="8" class="text-center text-danger">
                <i class="bi bi-exclamation-triangle"></i> Error de conexión. 
                Verifique la consola del navegador (F12).
            </td></tr>`;
    }
}

function mostrarUsuarios(usuarios) {
    const tbody = document.getElementById('tbodyUsuarios');
    
    if (usuarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No se encontraron usuarios</td></tr>';
        return;
    }
    
    tbody.innerHTML = usuarios.map(user => `
        <tr>
            <td>${user.id_usuario}</td>
            <td><strong>${user.username}</strong></td>
            <td>${user.nombres} ${user.apellidos}</td>
            <td>${getBadgesRoles(user.roles)}</td>
            <td>${getBadgeEstado(user.estado, user.cuenta_bloqueada)}</td>
            <td>${user.ultimo_acceso ? new Date(user.ultimo_acceso).toLocaleString('es-BO') : 'Nunca'}</td>
            <td>${new Date(user.fecha_creacion).toLocaleDateString('es-BO')}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-warning" onclick="abrirModalCambiarEstado(${user.id_usuario}, '${user.estado}')" 
                            title="Cambiar estado">
                        <i class="bi bi-toggle-on"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="abrirModalResetPassword(${user.id_usuario})" 
                            title="Resetear contraseña">
                        <i class="bi bi-key"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getBadgesRoles(roles) {
    if (!roles) return '<span class="badge bg-secondary">Sin rol</span>';
    return roles.split(',').map(rol => {
        const colors = {
            'Administrador': 'danger',
            'Médico': 'success',
            'Paciente': 'primary',
            'Enfermero': 'info'
        };
        return `<span class="badge bg-${colors[rol.trim()] || 'secondary'}">${rol.trim()}</span>`;
    }).join(' ');
}

function getBadgeEstado(estado, bloqueado) {
    if (bloqueado == 1 || estado === 'bloqueado') {
        return '<span class="badge bg-danger">Bloqueado</span>';
    }
    const colors = {
        'activo': 'success',
        'inactivo': 'secondary'
    };
    return `<span class="badge bg-${colors[estado] || 'secondary'}">${estado.toUpperCase()}</span>`;
}

// ==================== CREAR USUARIO MÉDICO ====================
async function abrirModalCrearDoctor() {
    const modal = new bootstrap.Modal(document.getElementById('modalCrearDoctor'));
    
    // Cargar médicos sin usuario
    try {
        const response = await fetch('../../api/usuarios.php?action=doctors_without_user', {
            headers: { 'X-CSRF-Token': csrfToken }
        });
        const data = await response.json();
        
        const select = document.getElementById('selectMedico');
        if (data.success && data.data.length > 0) {
            select.innerHTML = '<option value="">Seleccione un médico...</option>' +
                data.data.map(m => `<option value="${m.id_personal}">${m.apellidos} ${m.nombres} - ${m.especialidad}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No hay médicos sin usuario</option>';
        }
    } catch (error) {
        console.error('Error:', error);
    }
    
    modal.show();
}

async function crearUsuarioDoctor() {
    const id_personal = document.getElementById('selectMedico').value;
    const username = document.getElementById('usernameDoctor').value;
    const password = document.getElementById('passwordDoctor').value;
    
    if (!id_personal || !username || !password) {
        mostrarError('Todos los campos son requeridos');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id_personal', id_personal);
        formData.append('username', username);
        formData.append('password', password);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('../../api/usuarios.php?action=create_doctor', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarExito('Usuario creado exitosamente para el médico');
            bootstrap.Modal.getInstance(document.getElementById('modalCrearDoctor')).hide();
            document.getElementById('formCrearDoctor').reset();
            cargarUsuarios();
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al crear usuario');
    }
}

// ==================== CREAR USUARIO PACIENTE ====================
async function abrirModalCrearPaciente() {
    const modal = new bootstrap.Modal(document.getElementById('modalCrearPaciente'));
    
    // Cargar pacientes sin usuario
    try {
        const response = await fetch('../../api/usuarios.php?action=patients_without_user', {
            headers: { 'X-CSRF-Token': csrfToken }
        });
        const data = await response.json();
        
        const select = document.getElementById('selectPaciente');
        if (data.success && data.data.length > 0) {
            select.innerHTML = '<option value="">Seleccione un paciente...</option>' +
                data.data.map(p => `<option value="${p.id_paciente}">${p.apellidos} ${p.nombres} - HC: ${p.numero_historia_clinica}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No hay pacientes sin usuario</option>';
        }
    } catch (error) {
        console.error('Error:', error);
    }
    
    modal.show();
}

async function crearUsuarioPaciente() {
    const id_paciente = document.getElementById('selectPaciente').value;
    const username = document.getElementById('usernamePaciente').value;
    const password = document.getElementById('passwordPaciente').value;
    
    if (!id_paciente || !username || !password) {
        mostrarError('Todos los campos son requeridos');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id_paciente', id_paciente);
        formData.append('username', username);
        formData.append('password', password);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('../../api/usuarios.php?action=create_patient', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarExito('Usuario creado exitosamente para el paciente');
            bootstrap.Modal.getInstance(document.getElementById('modalCrearPaciente')).hide();
            document.getElementById('formCrearPaciente').reset();
            cargarUsuarios();
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al crear usuario');
    }
}

// ==================== CAMBIAR ESTADO ====================
function abrirModalCambiarEstado(id, estadoActual) {
    document.getElementById('idUsuarioCambiarEstado').value = id;
    document.getElementById('nuevoEstado').value = estadoActual;
    new bootstrap.Modal(document.getElementById('modalCambiarEstado')).show();
}

async function cambiarEstado() {
    const id = document.getElementById('idUsuarioCambiarEstado').value;
    const estado = document.getElementById('nuevoEstado').value;
    
    try {
        const formData = new FormData();
        formData.append('id_usuario', id);
        formData.append('estado', estado);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('../../api/usuarios.php?action=change_status', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarExito('Estado actualizado');
            bootstrap.Modal.getInstance(document.getElementById('modalCambiarEstado')).hide();
            cargarUsuarios();
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al cambiar estado');
    }
}

// ==================== RESETEAR CONTRASEÑA ====================
function abrirModalResetPassword(id) {
    document.getElementById('idUsuarioResetPassword').value = id;
    document.getElementById('nuevaPassword').value = '';
    document.getElementById('confirmarPassword').value = '';
    new bootstrap.Modal(document.getElementById('modalResetPassword')).show();
}

async function resetearPassword() {
    const id = document.getElementById('idUsuarioResetPassword').value;
    const nueva = document.getElementById('nuevaPassword').value;
    const confirmar = document.getElementById('confirmarPassword').value;
    
    if (nueva !== confirmar) {
        mostrarError('Las contraseñas no coinciden');
        return;
    }
    
    if (nueva.length < 8) {
        mostrarError('La contraseña debe tener al menos 8 caracteres');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id_usuario', id);
        formData.append('nueva_password', nueva);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('../../api/usuarios.php?action=reset_password', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarExito('Contraseña reseteada exitosamente');
            bootstrap.Modal.getInstance(document.getElementById('modalResetPassword')).hide();
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al resetear contraseña');
    }
}

// ==================== PAGINACIÓN ====================
function actualizarPaginacion() {
    const pagination = document.getElementById('pagination');
    let html = '';
    
    // Anterior
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarUsuarios(${currentPage - 1}); return false;">Anterior</a>
    </li>`;
    
    // Páginas
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cargarUsuarios(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Siguiente
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarUsuarios(${currentPage + 1}); return false;">Siguiente</a>
    </li>`;
    
    pagination.innerHTML = html;
}

// ==================== UTILIDADES ====================
function mostrarExito(mensaje) {
    alert(mensaje); // Cambiar por toast/notificación
}

function mostrarError(mensaje) {
    alert('Error: ' + mensaje); // Cambiar por toast/notificación
}

// Cargar usuarios al iniciar
document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    
    // Búsqueda en tiempo real
    document.getElementById('searchInput').addEventListener('input', debounce(() => {
        cargarUsuarios(1);
    }, 500));
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>