/**
 * assets/js/modules/auditoria.js
 * Módulo JavaScript para Auditoría
 */
const AuditoriaModule = {
    // Configuración
    config: {
        refreshInterval: 30000, // 30 segundos
        maxRetries: 3
    },
    // Estado
    state: {
        autoRefresh: false,
        currentFilters: {}
    },
    /**
     * Inicializar módulo
     */
    init() {
        console.log('Módulo de Auditoría inicializado');
        
        this.bindEvents();
        this.loadFiltersFromURL();
        this.setupAutoRefresh();
    },
    /**
     * Vincular eventos
     */
    bindEvents() {
        // Auto-submit de formulario con delay
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(() => {
                this.submitFilters();
            }, 500));
        }
        // Detectar cambios en filtros
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.submitFilters();
            });
        });
        // Botón de refresh
        const refreshBtn = document.getElementById('btn-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refresh();
            });
        }
        // Toggle auto-refresh
        const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                this.toggleAutoRefresh(e.target.checked);
            });
        }
        // Exportar botones
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const format = e.currentTarget.dataset.export;
                this.export(format);
            });
        });
    },
    // Cargar filtros desde URL
    loadFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);
        this.state.currentFilters = Object.fromEntries(params);
    },
    // Enviar filtros
    submitFilters() {
        const form = document.querySelector('form[method="GET"]');
        if (form) {
            form.submit();
        }
    },
    // Refrescar datos
    refresh() {
        showLoading();
        window.location.reload();
    },
    // Toggle auto-refresh
    toggleAutoRefresh(enable) {
        this.state.autoRefresh = enable;
        if (enable) {
            this.autoRefreshInterval = setInterval(() => {
                this.refresh();
            }, this.config.refreshInterval);
            showToast('Auto-actualización activada', 'info');
        } else {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }
            showToast('Auto-actualización desactivada', 'info');
        }
    },
    // Setup auto-refresh
    setupAutoRefresh() {
        // Restaurar estado desde localStorage
        const autoRefreshEnabled = localStorage.getItem('auditoria_autorefresh') === 'true';
        const toggle = document.getElementById('auto-refresh-toggle');
        if (toggle) {
            toggle.checked = autoRefreshEnabled;
            this.toggleAutoRefresh(autoRefreshEnabled);
        }
    },
    // Exportar datos
    export(format) {
        const params = new URLSearchParams(this.state.currentFilters);
        params.set('export', format);
        showLoading();
        const url = `exportar.php?${params.toString()}`;
        if (format === 'pdf') {
            // Abrir en nueva ventana
            window.open(url, '_blank');
            hideLoading();
        } else {
            // Descargar directamente
            window.location.href = url;
            setTimeout(hideLoading, 1000);
        }
    },
    // Obtener estadísticas
    async getStats(periodo = 'hoy') {
        try {
            const response = await fetch(`../../api/auditoria.php?action=stats&periodo=${periodo}`, {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            if (!response.ok) {
                throw new Error('Error al obtener estadísticas');
            }
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error:', error);
            showToast('Error al obtener estadísticas', 'error');
            return null;
        }
    },
    // Ver detalles de un log
    viewDetails(logId) {
        window.location.href = `ver.php?id=${logId}`;
    },
    // Filtrar por usuario
    filterByUser(userId) {
        const params = new URLSearchParams(this.state.currentFilters);
        params.set('usuario', userId);
        window.location.href = `?${params.toString()}`;
    },
    // Filtrar por IP
    filterByIP(ip) {
        const params = new URLSearchParams(this.state.currentFilters);
        params.set('search', ip);
        window.location.href = `?${params.toString()}`;
    },
    // Limpiar filtros
    clearFilters() {
        window.location.href = 'index.php';
    },
    // Comparar valores (diff)
    compareValues(oldVal, newVal) {
        // Implementar diff visual si es necesario
        return {
            added: [],
            removed: [],
            modified: []
        };
    },
    // Formatear fecha
    formatDate(dateStr) {
        const date = new Date(dateStr);
        return new Intl.DateTimeFormat('es-BO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        }).format(date);
    },
    // Obtener color por criticidad
    getCriticalityColor(criticidad) {
        const colors = {
            'Baja': 'secondary',
            'Media': 'info',
            'Alta': 'warning',
            'Crítica': 'danger'
        };
        return colors[criticidad] || 'secondary';
    },
    // Obtener icono por acción
    getActionIcon(accion) {
        const icons = {
            'INSERT': 'fa-plus-circle',
            'UPDATE': 'fa-edit',
            'DELETE': 'fa-trash',
            'SELECT': 'fa-eye',
            'LOGIN': 'fa-sign-in-alt',
            'LOGOUT': 'fa-sign-out-alt',
            'LOGIN_FAILED': 'fa-times-circle',
            'EXECUTE': 'fa-cog',
            'EXPORT': 'fa-download'
        };
        return icons[accion] || 'fa-circle';
    }
};
// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('/auditoria/')) {
        AuditoriaModule.init();
    }
});
// Exportar para uso global
window.AuditoriaModule = AuditoriaModule;
console.log('auditoria.js cargado correctamente');