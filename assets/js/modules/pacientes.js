/**
 * Módulo de Pacientes
 * Maneja CRUD y visualización de pacientes
 */

const PacientesModule = {
    currentPage: 1,
    totalPages: 1,
    pacientes: [],
    
    /**
     * Inicializar módulo
     */
    init() {
        this.initForms();
        this.initEventListeners();
        this.initSearch();
        this.loadPacientes();
        console.log('PacientesModule inicializado');
    },
    
    /**
     * Inicializar formularios
     */
    initForms() {
        this.initRegistrarForm();
        this.initEditarForm();
    },
    
    /**
     * Inicializar formulario de registro
     */
    initRegistrarForm() {
        const form = document.getElementById('registrarPacienteForm');
        if (!form) return;
        
        const validator = new FormValidator('registrarPacienteForm', {
            tipo_documento: [
                (val) => Validation.required(val, 'Seleccione el tipo de documento')
            ],
            numero_documento: [
                (val) => Validation.required(val, 'Ingrese el número de documento'),
                (val) => Validation.ci(val)
            ],
            nombres: [
                (val) => Validation.required(val, 'Ingrese los nombres'),
                (val) => Validation.alpha(val),
                (val) => Validation.minLength(val, 2, 'Mínimo 2 caracteres')
            ],
            apellidos: [
                (val) => Validation.required(val, 'Ingrese los apellidos'),
                (val) => Validation.alpha(val),
                (val) => Validation.minLength(val, 2, 'Mínimo 2 caracteres')
            ],
            fecha_nacimiento: [
                (val) => Validation.required(val, 'Ingrese la fecha de nacimiento'),
                (val) => Validation.maxDate(val, new Date().toISOString().split('T')[0], 
                    'La fecha no puede ser futura'),
                (val) => HospitalValidation.edadMinima(val, 0)
            ],
            genero: [
                (val) => Validation.required(val, 'Seleccione el género')
            ],
            telefono: [
                (val) => val ? Validation.phone(val) : { valid: true }
            ],
            email: [
                (val) => val ? Validation.email(val) : { valid: true }
            ]
        });
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!validator.validateAll()) {
                return;
            }
            
            const formData = validator.getValues();
            await this.registrarPaciente(formData);
        });
        
        // Calcular edad automáticamente
        const fechaNacInput = form.elements['fecha_nacimiento'];
        const edadDisplay = document.getElementById('edad_display');
        
        if (fechaNacInput && edadDisplay) {
            fechaNacInput.addEventListener('change', () => {
                const edad = this.calcularEdad(fechaNacInput.value);
                edadDisplay.textContent = edad ? `${edad} años` : '';
            });
        }
    },
    
    /**
     * Inicializar formulario de edición
     */
    initEditarForm() {
        const form = document.getElementById('editarPacienteForm');
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            await this.actualizarPaciente(data.id_paciente, data);
        });
    },
    
    /**
     * Inicializar event listeners
     */
    initEventListeners() {
        // Botones de acción
        document.addEventListener('click', async (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;
            
            const action = target.dataset.action;
            const pacienteId = target.dataset.pacienteId;
            
            switch (action) {
                case 'ver-paciente':
                    this.showPacienteDetails(pacienteId);
                    break;
                case 'editar-paciente':
                    this.showEditarModal(pacienteId);
                    break;
                case 'eliminar-paciente':
                    await this.eliminarPaciente(pacienteId);
                    break;
                case 'ver-historial':
                    this.verHistorial(pacienteId);
                    break;
                case 'nueva-cita':
                    this.nuevaCita(pacienteId);
                    break;
            }
        });
        
        // Paginación
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-page]');
            if (target) {
                e.preventDefault();
                const page = parseInt(target.dataset.page);
                this.loadPacientes(page);
            }
        });
        
        // Filtros
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', () => {
                this.loadPacientes(1);
            });
        }
    },
    
    /**
     * Inicializar búsqueda
     */
    initSearch() {
        const searchInput = document.getElementById('buscarPaciente');
        if (!searchInput) return;
        
        const debouncedSearch = debounce((query) => {
            this.loadPacientes(1, query);
        }, 500);
        
        searchInput.addEventListener('input', (e) => {
            debouncedSearch(e.target.value);
        });
    },
    
    /**
     * Cargar pacientes
     */
    async loadPacientes(page = 1, search = '') {
        this.currentPage = page;
        const estado = document.getElementById('filtroEstado')?.value || 'activo';
        
        showLoading();
        
        try {
            const response = await PacientesAPI.list(page, 15, search, estado);
            
            if (response.success) {
                this.pacientes = response.data.pacientes;
                this.totalPages = response.data.total_pages;
                this.renderPacientes();
                this.renderPagination();
            }
        } catch (error) {
            console.error('Error cargando pacientes:', error);
            showToast('Error al cargar pacientes', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Renderizar lista de pacientes
     */
    renderPacientes() {
        const container = document.getElementById('listaPacientes');
        if (!container) return;
        
        if (this.pacientes.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No se encontraron pacientes
                </div>
            `;
            return;
        }
        
        const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>HC</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Teléfono</th>
                            <th>Grupo Sang.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.pacientes.map(p => this.renderPacienteRow(p)).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
        container.innerHTML = html;
    },
    
    /**
     * Renderizar fila de paciente
     */
    renderPacienteRow(paciente) {
        return `
            <tr>
                <td><strong>${paciente.numero_historia_clinica}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                             style="width: 40px; height: 40px;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <strong>${paciente.nombres} ${paciente.apellidos}</strong><br>
                            <small class="text-muted">${this.calcularEdad(paciente.fecha_nacimiento)} años - ${paciente.genero}</small>
                        </div>
                    </div>
                </td>
                <td>${paciente.numero_documento}</td>
                <td>${paciente.telefono || '-'}</td>
                <td>
                    ${paciente.grupo_sanguineo ? 
                        `<span class="badge bg-danger">${paciente.grupo_sanguineo}${paciente.factor_rh || ''}</span>` : 
                        '-'
                    }
                </td>
                <td>
                    <span class="badge bg-${this.getBadgeColorByEstado(paciente.estado_paciente)}">
                        ${paciente.estado_paciente}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" 
                                data-action="ver-paciente" 
                                data-paciente-id="${paciente.id_paciente}"
                                title="Ver detalles">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" 
                                data-action="editar-paciente" 
                                data-paciente-id="${paciente.id_paciente}"
                                title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-info" 
                                data-action="ver-historial" 
                                data-paciente-id="${paciente.id_paciente}"
                                title="Ver historial">
                            <i class="bi bi-file-medical"></i>
                        </button>
                        <button class="btn btn-outline-success" 
                                data-action="nueva-cita" 
                                data-paciente-id="${paciente.id_paciente}"
                                title="Nueva cita">
                            <i class="bi bi-calendar-plus"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    },
    
    /**
     * Renderizar paginación
     */
    renderPagination() {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        if (this.totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<nav><ul class="pagination justify-content-center">';
        
        // Botón anterior
        html += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage - 1}">Anterior</a>
            </li>
        `;
        
        // Páginas
        for (let i = 1; i <= this.totalPages; i++) {
            if (i === 1 || i === this.totalPages || 
                (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `
                    <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Botón siguiente
        html += `
            <li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage + 1}">Siguiente</a>
            </li>
        `;
        
        html += '</ul></nav>';
        container.innerHTML = html;
    },
    
    /**
     * Registrar nuevo paciente
     */
    async registrarPaciente(data) {
        showLoading();
        
        try {
            const response = await PacientesAPI.create(data);
            
            if (response.success) {
                showToast('Paciente registrado exitosamente', 'success');
                this.closeModal('registrarPacienteModal');
                document.getElementById('registrarPacienteForm').reset();
                this.loadPacientes();
            } else {
                showAlert(response.message || 'Error al registrar paciente', 'danger');
            }
        } catch (error) {
            console.error('Error registrando paciente:', error);
            showAlert('Error al registrar paciente', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Actualizar paciente
     */
    async actualizarPaciente(id, data) {
        showLoading();
        
        try {
            const response = await PacientesAPI.update(id, data);
            
            if (response.success) {
                showToast('Paciente actualizado exitosamente', 'success');
                this.closeModal('editarPacienteModal');
                this.loadPacientes(this.currentPage);
            } else {
                showAlert(response.message || 'Error al actualizar paciente', 'danger');
            }
        } catch (error) {
            console.error('Error actualizando paciente:', error);
            showAlert('Error al actualizar paciente', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Eliminar paciente
     */
    async eliminarPaciente(id) {
        if (!confirm('¿Está seguro de inactivar este paciente?')) {
            return;
        }
        
        showLoading();
        
        try {
            const response = await PacientesAPI.delete(id);
            
            if (response.success) {
                showToast('Paciente inactivado exitosamente', 'success');
                this.loadPacientes(this.currentPage);
            } else {
                showAlert(response.message || 'Error al inactivar paciente', 'danger');
            }
        } catch (error) {
            console.error('Error eliminando paciente:', error);
            showAlert('Error al inactivar paciente', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Mostrar detalles del paciente
     */
    async showPacienteDetails(id) {
        showLoading();
        
        try {
            const response = await PacientesAPI.get(id);
            
            if (response.success) {
                const paciente = response.data;
                this.renderPacienteDetails(paciente);
                
                const modal = new bootstrap.Modal(document.getElementById('detallesPacienteModal'));
                modal.show();
            }
        } catch (error) {
            console.error('Error obteniendo detalles:', error);
            showToast('Error al cargar detalles', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Renderizar detalles del paciente
     */
    renderPacienteDetails(paciente) {
        const container = document.getElementById('detallesPacienteContent');
        if (!container) return;
        
        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Información Personal</h6>
                    <table class="table table-sm">
                        <tr><th>Nombres:</th><td>${paciente.nombres}</td></tr>
                        <tr><th>Apellidos:</th><td>${paciente.apellidos}</td></tr>
                        <tr><th>Documento:</th><td>${paciente.numero_documento}</td></tr>
                        <tr><th>Fecha Nac.:</th><td>${formatDate(paciente.fecha_nacimiento)}</td></tr>
                        <tr><th>Edad:</th><td>${this.calcularEdad(paciente.fecha_nacimiento)} años</td></tr>
                        <tr><th>Género:</th><td>${paciente.genero}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Información Médica</h6>
                    <table class="table table-sm">
                        <tr><th>HC:</th><td>${paciente.numero_historia_clinica}</td></tr>
                        <tr><th>Grupo Sang.:</th><td>${paciente.grupo_sanguineo || '-'}${paciente.factor_rh || ''}</td></tr>
                        <tr><th>Seguro:</th><td>${paciente.seguro_medico || '-'}</td></tr>
                        <tr><th>Alergias:</th><td>${paciente.alergias || 'Ninguna'}</td></tr>
                    </table>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    },
    
    /**
     * Mostrar modal de edición
     */
    async showEditarModal(id) {
        showLoading();
        
        try {
            const response = await PacientesAPI.get(id);
            
            if (response.success) {
                const form = document.getElementById('editarPacienteForm');
                if (form) {
                    // Llenar formulario
                    Object.keys(response.data).forEach(key => {
                        const input = form.elements[key];
                        if (input) input.value = response.data[key] || '';
                    });
                    
                    const modal = new bootstrap.Modal(document.getElementById('editarPacienteModal'));
                    modal.show();
                }
            }
        } catch (error) {
            console.error('Error cargando paciente:', error);
            showToast('Error al cargar datos', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Ver historial clínico
     */
    verHistorial(id) {
        window.location.href = `${APP.baseUrl}/modules/historial-clinico/ver.php?id=${id}`;
    },
    
    /**
     * Nueva cita para paciente
     */
    nuevaCita(id) {
        window.location.href = `${APP.baseUrl}/modules/citas/programar.php?paciente=${id}`;
    },
    
    /**
     * Calcular edad
     */
    calcularEdad(fechaNacimiento) {
        if (!fechaNacimiento) return null;
        
        const hoy = new Date();
        const nacimiento = new Date(fechaNacimiento);
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const mes = hoy.getMonth() - nacimiento.getMonth();
        
        if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
            edad--;
        }
        
        return edad;
    },
    
    /**
     * Cerrar modal
     */
    closeModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    },
    
    /**
     * Obtener color de badge por estado
     */
    getBadgeColorByEstado(estado) {
        const colores = {
            'activo': 'success',
            'inactivo': 'secondary',
            'fallecido': 'dark'
        };
        return colores[estado] || 'secondary';
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('listaPacientes') || 
        document.querySelector('.pacientes-page')) {
        PacientesModule.init();
    }
});

// Exportar
window.PacientesModule = PacientesModule;

console.log('pacientes.js cargado correctamente');