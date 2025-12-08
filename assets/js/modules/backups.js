/**
 * assets/js/modules/backups.js
 * Módulo de gestión de backups - Lógica Frontend
 */

const BackupsModule = {
    currentPage: 1,
    itemsPerPage: 20,
    totalPages: 0,
    filters: {},
    baseUrl: typeof BACKUP_CONFIG !== 'undefined' ? BACKUP_CONFIG.baseUrl : (window.location.origin + '/hospital'),
    apiUrl: typeof BACKUP_CONFIG !== 'undefined' ? BACKUP_CONFIG.apiUrl : (window.location.origin + '/hospital/api/backups.php'),

    /**
     * Inicializar módulo
     */
    init() {
        console.log('Inicializando módulo de Backups...');
        console.log('Base URL:', this.baseUrl);
        console.log('API URL:', this.apiUrl);
        
        // Cargar estadísticas
        this.loadStatistics();
        
        // Cargar lista de backups
        this.loadBackups();
        
        // Event listeners
        this.setupEventListeners();
        
        // Auto-refresh cada 30 segundos
        setInterval(() => {
            if (document.getElementById('backupsTable')) {
                this.loadBackups(this.currentPage, false);
            }
        }, 30000);
    },

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Formulario crear backup
        const createForm = document.getElementById('createBackupForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createBackup();
            });
        }

        // Formulario restaurar
        const restoreForm = document.getElementById('restoreBackupForm');
        if (restoreForm) {
            restoreForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.restoreBackup();
            });
        }

        // Cambio en tipo de restauración
        const restorationType = document.getElementById('restorationType');
        if (restorationType) {
            restorationType.addEventListener('change', (e) => {
                const tablesContainer = document.getElementById('tablesContainer');
                tablesContainer.style.display = e.target.value === 'Parcial' ? 'block' : 'none';
            });
        }

        // Seleccionar todas las tablas
        const selectAllTables = document.getElementById('selectAllTables');
        if (selectAllTables) {
            selectAllTables.addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('#tablesList input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
            });
        }
    },

    /**
     * Cargar estadísticas
     */
    async loadStatistics() {
        try {
            console.log('Cargando estadísticas...');
            const url = `${this.apiUrl}?action=statistics`;
            console.log('URL estadísticas:', url);
            const response = await fetch(url);
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Estadísticas recibidas:', result);
            
            if (result.success) {
                document.getElementById('stat-total').textContent = result.data.total || 0;
                document.getElementById('stat-completed').textContent = result.data.completados || 0;
                document.getElementById('stat-processing').textContent = result.data.en_proceso || 0;
                document.getElementById('stat-size').textContent = (result.data.tamanio_total || 0) + ' MB';
            } else {
                console.error('Error en respuesta:', result.message);
            }
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
            // Mostrar valores por defecto
            document.getElementById('stat-total').textContent = '0';
            document.getElementById('stat-completed').textContent = '0';
            document.getElementById('stat-processing').textContent = '0';
            document.getElementById('stat-size').textContent = '0 MB';
        }
    },

    /**
     * Cargar lista de backups
     */
    async loadBackups(page = 1, showLoading = true) {
        this.currentPage = page;
        
        if (showLoading) {
            this.showLoadingTable();
        }
        
        try {
            console.log('Cargando backups, página:', page);
            
            // Construir URL con filtros
            const params = new URLSearchParams({
                action: 'list',
                page: page,
                limit: this.itemsPerPage,
                ...this.filters
            });
            
            const url = `${this.apiUrl}?${params}`;
            console.log('URL completa:', url);
            
            const response = await fetch(url);
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Backups recibidos:', result);
            
            if (result.success) {
                this.renderTable(result.data);
                this.renderPagination(result.pages, result.total);
            } else {
                this.showAlert('error', result.message || 'Error al cargar backups');
                this.renderTable([]);
            }
        } catch (error) {
            console.error('Error completo cargando backups:', error);
            this.showAlert('error', 'Error al cargar los backups: ' + error.message);
            
            // Mostrar mensaje de error en la tabla
            const tbody = document.getElementById('backupsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                        <p class="text-danger mt-2">Error al cargar los backups</p>
                        <p class="text-muted small">${error.message}</p>
                        <button class="btn btn-sm btn-primary mt-2" onclick="BackupsModule.loadBackups()">
                            <i class="bi bi-arrow-clockwise"></i> Reintentar
                        </button>
                    </td>
                </tr>
            `;
        }
    },

    /**
     * Renderizar tabla
     */
    renderTable(backups) {
        const tbody = document.getElementById('backupsTableBody');
        
        if (backups.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No se encontraron backups</p>
                        <button class="btn btn-primary mt-2" onclick="BackupsModule.showCreateModal()">
                            <i class="bi bi-plus-circle"></i> Crear primer backup
                        </button>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = backups.map(backup => `
            <tr>
                <td><strong>#${backup.id_backup}</strong></td>
                <td>
                    <span class="badge bg-primary badge-tipo">
                        ${backup.tipo_backup}
                    </span>
                </td>
                <td>
                    ${this.formatDateTime(backup.fecha_inicio)}
                </td>
                <td>
                    ${backup.duracion_minutos ? backup.duracion_minutos + ' min' : '-'}
                </td>
                <td>
                    ${backup.tamanio_mb ? backup.tamanio_mb + ' MB' : '-'}
                </td>
                <td>
                    ${this.renderStatusBadge(backup.estado_backup)}
                </td>
                <td>
                    ${backup.cifrado ? '<i class="bi bi-lock-fill text-success"></i>' : '<i class="bi bi-unlock text-muted"></i>'}
                </td>
                <td>
                    ${backup.comprimido ? '<i class="bi bi-file-zip-fill text-info"></i>' : '<i class="bi bi-file text-muted"></i>'}
                </td>
                <td class="action-buttons">
                    ${this.renderActions(backup)}
                </td>
            </tr>
        `).join('');
    },

    /**
     * Renderizar badge de estado
     */
    renderStatusBadge(estado) {
        const badges = {
            'Completado': 'bg-success',
            'En proceso': 'bg-warning',
            'Fallido': 'bg-danger',
            'Corrupto': 'bg-dark',
            'Restaurado': 'bg-info'
        };
        
        const bgClass = badges[estado] || 'bg-secondary';
        
        return `<span class="badge ${bgClass} badge-estado">${estado}</span>`;
    },

    /**
     * Renderizar botones de acción
     */
    renderActions(backup) {
        let actions = `
            <button class="btn btn-sm btn-info" onclick="BackupsModule.showDetails(${backup.id_backup})" 
                    title="Ver detalles">
                <i class="bi bi-eye"></i>
            </button>
        `;
        
        if (backup.estado_backup === 'Completado') {
            actions += `
                <button class="btn btn-sm btn-success" onclick="BackupsModule.downloadBackup(${backup.id_backup})"
                        title="Descargar">
                    <i class="bi bi-download"></i>
                </button>
                <button class="btn btn-sm btn-warning" onclick="BackupsModule.showRestoreModal(${backup.id_backup})"
                        title="Restaurar">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="BackupsModule.verifyIntegrity(${backup.id_backup})"
                        title="Verificar integridad">
                    <i class="bi bi-shield-check"></i>
                </button>
            `;
        }
        
        actions += `
            <button class="btn btn-sm btn-danger" onclick="BackupsModule.deleteBackup(${backup.id_backup})"
                    title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        `;
        
        return actions;
    },

    /**
     * Renderizar paginación
     */
    renderPagination(totalPages, totalRecords) {
        this.totalPages = totalPages;
        
        const pagination = document.getElementById('pagination');
        const start = ((this.currentPage - 1) * this.itemsPerPage) + 1;
        const end = Math.min(this.currentPage * this.itemsPerPage, totalRecords);
        
        document.getElementById('showing-start').textContent = start;
        document.getElementById('showing-end').textContent = end;
        document.getElementById('total-records').textContent = totalRecords;
        
        let html = '';
        
        // Botón anterior
        html += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="BackupsModule.loadBackups(${this.currentPage - 1}); return false;">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `;
        
        // Páginas
        const maxButtons = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="BackupsModule.loadBackups(${i}); return false;">
                        ${i}
                    </a>
                </li>
            `;
        }
        
        // Botón siguiente
        html += `
            <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="BackupsModule.loadBackups(${this.currentPage + 1}); return false;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `;
        
        pagination.innerHTML = html;
    },

    /**
     * Mostrar loading en tabla
     */
    showLoadingTable() {
        const tbody = document.getElementById('backupsTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando backups...</p>
                </td>
            </tr>
        `;
    },

    /**
     * Mostrar modal crear backup
     */
    showCreateModal() {
        const modal = new bootstrap.Modal(document.getElementById('createBackupModal'));
        document.getElementById('createBackupForm').reset();
        modal.show();
    },

    /**
     * Crear backup
     */
    async createBackup() {
        const btn = document.getElementById('btnCreateBackup');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creando...';
        
        try {
            const data = {
                tipo: document.getElementById('backupType').value,
                cifrado: document.getElementById('cifrado').checked,
                comprimido: document.getElementById('comprimido').checked,
                observaciones: document.getElementById('observaciones').value
            };
            
            const response = await fetch(`${this.apiUrl}?action=create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message);
                
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('createBackupModal'));
                modal.hide();
                
                // Recargar datos
                this.loadStatistics();
                this.loadBackups();
            } else {
                this.showAlert('error', result.message);
            }
        } catch (error) {
            console.error('Error creando backup:', error);
            this.showAlert('error', 'Error al crear el backup');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    },

    /**
     * Mostrar detalles del backup
     */
    async showDetails(id) {
        try {
            const response = await fetch(`${this.apiUrl}?action=details&id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                const backup = result.data;
                
                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> ${backup.id_backup}</p>
                            <p><strong>Tipo:</strong> 
                                <span class="badge bg-primary">${backup.tipo_backup}</span>
                            </p>
                            <p><strong>Estado:</strong> ${this.renderStatusBadge(backup.estado_backup)}</p>
                            <p><strong>Fecha Inicio:</strong> ${this.formatDateTime(backup.fecha_inicio)}</p>
                            <p><strong>Fecha Fin:</strong> ${backup.fecha_fin ? this.formatDateTime(backup.fecha_fin) : '-'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Duración:</strong> ${backup.duracion_minutos || '-'} minutos</p>
                            <p><strong>Tamaño:</strong> ${backup.tamanio_mb || '-'} MB</p>
                            <p><strong>Cifrado:</strong> ${backup.cifrado ? 'Sí' : 'No'}</p>
                            <p><strong>Comprimido:</strong> ${backup.comprimido ? 'Sí' : 'No'}</p>
                            <p><strong>Archivo existe:</strong> ${backup.archivo_existe ? 'Sí' : 'No'}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Ubicación:</strong> <code>${backup.ubicacion_archivo}</code></p>
                            <p><strong>Hash (SHA-256):</strong> <code class="small">${backup.hash_verificacion || '-'}</code></p>
                            <p><strong>Realizado por:</strong> ${backup.realizado_por_nombre || '-'} 
                               (${backup.realizado_por_username || '-'})</p>
                        </div>
                    </div>
                    ${backup.observaciones ? `
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <p><strong>Observaciones:</strong></p>
                                <p class="text-muted">${backup.observaciones}</p>
                            </div>
                        </div>
                    ` : ''}
                `;
                
                document.getElementById('backupDetails').innerHTML = html;
                
                const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                modal.show();
            }
        } catch (error) {
            console.error('Error cargando detalles:', error);
            this.showAlert('error', 'Error al cargar los detalles');
        }
    },

    /**
     * Descargar backup
     */
    downloadBackup(id) {
        if (confirm('¿Desea descargar este backup?')) {
            window.location.href = `${this.apiUrl}?action=download&id=${id}`;
            this.showAlert('info', 'Descargando backup...');
        }
    },

    /**
     * Mostrar modal restaurar
     */
    showRestoreModal(id) {
        document.getElementById('restoreBackupId').value = id;
        document.getElementById('restoreBackupForm').reset();
        document.getElementById('tablesContainer').style.display = 'none';
        
        const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
        modal.show();
    },

    /**
     * Restaurar backup
     */
    async restoreBackup() {
        if (!confirm('¿ESTÁ COMPLETAMENTE SEGURO de restaurar este backup? Esta acción reemplazará todos los datos actuales.')) {
            return;
        }
        
        const btn = document.getElementById('btnRestore');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restaurando...';
        
        try {
            const data = {
                backup_id: document.getElementById('restoreBackupId').value,
                tipo: document.getElementById('restorationType').value,
                motivo: document.getElementById('restoreMotivo').value,
                tablas: []
            };
            
            // Si es parcial, obtener tablas seleccionadas
            if (data.tipo === 'Parcial') {
                const checkboxes = document.querySelectorAll('#tablesList input[type="checkbox"]:checked');
                data.tablas = Array.from(checkboxes).map(cb => cb.value);
            }
            
            const response = await fetch(`${this.apiUrl}?action=restore`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('restoreModal'));
                modal.hide();
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                this.showAlert('error', result.message);
            }
        } catch (error) {
            console.error('Error restaurando backup:', error);
            this.showAlert('error', 'Error al restaurar el backup');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    },

    /**
     * Verificar integridad
     */
    async verifyIntegrity(id) {
        try {
            const response = await fetch(`${this.baseUrl}/api/backups.php?action=verify&id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                if (result.valid) {
                    this.showAlert('success', '✓ Backup íntegro - Hash verificado correctamente');
                } else {
                    this.showAlert('error', '✗ Backup corrupto - El archivo está dañado');
                }
            } else {
                this.showAlert('error', result.message);
            }
        } catch (error) {
            console.error('Error verificando integridad:', error);
            this.showAlert('error', 'Error al verificar la integridad');
        }
    },

    /**
     * Eliminar backup
     */
    async deleteBackup(id) {
        if (!confirm('¿Está seguro de eliminar este backup? Esta acción no se puede deshacer.')) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?action=delete&id=${id}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message);
                this.loadStatistics();
                this.loadBackups();
            } else {
                this.showAlert('error', result.message);
            }
        } catch (error) {
            console.error('Error eliminando backup:', error);
            this.showAlert('error', 'Error al eliminar el backup');
        }
    },

    /**
     * Aplicar filtros
     */
    applyFilters() {
        this.filters = {
            type: document.getElementById('filterType').value,
            status: document.getElementById('filterStatus').value,
            date_from: document.getElementById('filterDateFrom').value,
            date_to: document.getElementById('filterDateTo').value
        };
        
        this.loadBackups(1);
    },

    /**
     * Limpiar filtros
     */
    clearFilters() {
        document.getElementById('filterType').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        
        this.filters = {};
        this.loadBackups(1);
    },

    /**
     * Mostrar alerta
     */
    showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'x-circle' : 
                    type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="bi bi-${icon}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    },

    /**
     * Formatear fecha y hora
     */
    formatDateTime(datetime) {
        if (!datetime) return '-';
        
        const date = new Date(datetime);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }
};

// Exportar para uso global
window.BackupsModule = BackupsModule;