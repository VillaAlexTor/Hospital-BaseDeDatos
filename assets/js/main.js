/**
 * Main JavaScript File
 * Funciones globales y configuración inicial
 */
// Variables globales
const APP = {
    name: 'Sistema Hospitalario',
    version: '1.0.0',
    baseUrl: (typeof SITE_URL !== 'undefined' ? SITE_URL : null) || 
            (typeof window.location !== 'undefined' ? window.location.origin + '/hospital' : 'http://localhost/hospital'),
    csrfToken: (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''),
    debug: true
};
// INICIALIZACIÓN
document.addEventListener('DOMContentLoaded', function() {
    console.log(`${APP.name} v${APP.version} - Inicializado`);
    // Inicializar componentes
    initTooltips();
    initPopovers();
    initConfirmDialogs();
    initFormValidation();
    initAutoSave();
    initSessionTimeout();
    initSidebarToggle();
    initDataTables();
    // Auto-dismiss alerts
    autoHideAlerts();
});
// INICIALIZACIÓN DE COMPONENTES BOOTSTRAP
// Inicializar tooltips de Bootstrap
function initTooltips() {
    const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}
// Inicializar popovers de Bootstrap
function initPopovers() {
    const popoverTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="popover"]')
    );
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}
// ALERTAS Y NOTIFICACIONES
// Auto-ocultar alertas después de 5 segundos
function autoHideAlerts() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
}
// Mostrar alerta temporal
function showAlert(message, type = 'info', duration = 5000) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    const container = document.querySelector('.alert-container') || createAlertContainer();
    container.insertAdjacentHTML('beforeend', alertHtml);
    if (duration > 0) {
        setTimeout(() => {
            const alert = container.lastElementChild;
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, duration);
    }
}
function createAlertContainer() {
    const container = document.createElement('div');
    container.className = 'alert-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    container.style.marginTop = '60px';
    document.body.appendChild(container);
    return container;
}
// Mostrar toast notification
function showToast(message, type = 'info') {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}
function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    container.style.marginTop = '60px';
    document.body.appendChild(container);
    return container;
}
// DIÁLOGOS DE CONFIRMACIÓN
// Inicializar diálogos de confirmación
function initConfirmDialogs() {
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-confirm]');
        if (target) {
            e.preventDefault();
            const message = target.dataset.confirm || '¿Está seguro de realizar esta acción?';
            if (confirm(message)) {
                if (target.tagName === 'A') {
                    window.location.href = target.href;
                } else if (target.tagName === 'BUTTON' && target.form) {
                    target.form.submit();
                }
            }
        }
    });
}
// Mostrar diálogo de confirmación personalizado
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}
// VALIDACIÓN DE FORMULARIOS
// Inicializar validación de formularios
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}
// Prevenir doble submit
function preventDoubleSubmit(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
        
        setTimeout(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }, 3000);
    }
}
// AUTO-GUARDADO
let autoSaveTimeout;
// Inicializar auto-guardado en formularios
function initAutoSave() {
    const autoSaveForms = document.querySelectorAll('[data-autosave]');
    autoSaveForms.forEach(form => {
        form.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveFormData(form);
            }, 2000);
        });
    });
}
// Guardar datos del formulario en localStorage
function saveFormData(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    const formId = form.id || 'form_' + Date.now();
    localStorage.setItem('autosave_' + formId, JSON.stringify(data));
    showToast('Borrador guardado', 'success');
}
// Cargar datos guardados del formulario
function loadFormData(formId) {
    const savedData = localStorage.getItem('autosave_' + formId);
    if (savedData) {
        const data = JSON.parse(savedData);
        const form = document.getElementById(formId);
        if (form) {
            Object.keys(data).forEach(key => {
                const input = form.elements[key];
                if (input) {
                    input.value = data[key];
                }
            });
        }
    }
}
// CONTROL DE SESIÓN
let sessionCheckInterval;
// Inicializar verificación de timeout de sesión
function initSessionTimeout() {
    const timeout = 3600000; // 1 hora
    const warningTime = 300000; // 5 minutos antes
    let lastActivity = Date.now();
    // Actualizar última actividad
    ['mousedown', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, () => {
            lastActivity = Date.now();
        });
    });
    // Verificar cada minuto
    sessionCheckInterval = setInterval(() => {
        const inactive = Date.now() - lastActivity;
        if (inactive >= timeout) {
            clearInterval(sessionCheckInterval);
            showAlert('Su sesión ha expirado por inactividad', 'warning');
            setTimeout(() => {
                window.location.href = APP.baseUrl + '/logout.php';
            }, 2000);
        } else if (inactive >= warningTime) {
            const remaining = Math.ceil((timeout - inactive) / 60000);
            showToast(`Su sesión expirará en ${remaining} minutos`, 'warning');
        }
    }, 60000);
}
// SIDEBAR TOGGLE
// Inicializar toggle del sidebar
function initSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', 
                document.body.classList.contains('sidebar-collapsed'));
        });
        // Restaurar estado
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    }
}
// DATATABLES
// Inicializar DataTables
function initDataTables() {
    const tables = document.querySelectorAll('.datatable');
    if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        tables.forEach(table => {
            $(table).DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 25,
                responsive: true
            });
        });
    }
}
// UTILIDADES GENERALES
// Formatear número
function formatNumber(number, decimals = 2) {
    return new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}
// Formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB'
    }).format(amount);
}
// Formatear fecha
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-BO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(date);
}
// Formatear fecha y hora
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-BO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}
// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
// Copiar texto al portapapeles
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copiado al portapapeles', 'success');
    }).catch(err => {
        console.error('Error al copiar:', err);
        showToast('Error al copiar', 'error');
    });
}
// Loading spinner
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    const spinner = document.getElementById('loadingSpinner');
    if (overlay) overlay.style.display = 'block';
    if (spinner) spinner.style.display = 'block';
}
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    const spinner = document.getElementById('loadingSpinner');
    if (overlay) overlay.style.display = 'none';
    if (spinner) spinner.style.display = 'none';
}
// Scroll to top
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
// Get URL parameters
function getUrlParams() {
    return new URLSearchParams(window.location.search);
}
// Update URL without reload
function updateUrl(params) {
    const url = new URL(window.location);
    Object.keys(params).forEach(key => {
        url.searchParams.set(key, params[key]);
    });
    window.history.pushState({}, '', url);
}
// EXPORTAR FUNCIONES GLOBALES
window.APP = APP;
window.showAlert = showAlert;
window.showToast = showToast;
window.confirmDialog = confirmDialog;
window.formatNumber = formatNumber;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.debounce = debounce;
window.copyToClipboard = copyToClipboard;
window.scrollToTop = scrollToTop;
window.getUrlParams = getUrlParams;
window.updateUrl = updateUrl;
console.log('Main.js cargado correctamente');