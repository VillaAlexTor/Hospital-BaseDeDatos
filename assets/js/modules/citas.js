/**
 * Módulo de Citas Médicas
 * Maneja programación, edición y visualización de citas
 */

const CitasModule = {
    currentDate: new Date(),
    selectedDate: null,
    selectedMedico: null,
    citas: [],
    
    /**
     * Inicializar módulo
     */
    init() {
        this.initCalendar();
        this.initForms();
        this.initEventListeners();
        this.loadCitas();
        console.log('CitasModule inicializado');
    },
    
    /**
     * Inicializar calendario
     */
    initCalendar() {
        const calendarEl = document.getElementById('citasCalendar');
        if (!calendarEl) return;
        
        // Si usas FullCalendar
        if (typeof FullCalendar !== 'undefined') {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'es',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: async (info, successCallback) => {
                    const citas = await this.loadCitasByDateRange(
                        info.startStr,
                        info.endStr
                    );
                    successCallback(citas);
                },
                eventClick: (info) => {
                    this.showCitaDetails(info.event.id);
                },
                dateClick: (info) => {
                    this.selectedDate = info.dateStr;
                    this.showNuevaCitaModal();
                }
            });
            
            calendar.render();
            this.calendar = calendar;
        }
    },
    
    /**
     * Inicializar formularios
     */
    initForms() {
        this.initNuevaCitaForm();
        this.initEditarCitaForm();
        this.initBuscarPacienteForm();
    },
    
    /**
     * Inicializar formulario de nueva cita
     */
    initNuevaCitaForm() {
        const form = document.getElementById('nuevaCitaForm');
        if (!form) return;
        
        const validator = new FormValidator('nuevaCitaForm', {
            id_paciente: [
                (val) => Validation.required(val, 'Seleccione un paciente')
            ],
            id_medico: [
                (val) => Validation.required(val, 'Seleccione un médico')
            ],
            fecha_cita: [
                (val) => Validation.required(val, 'Seleccione una fecha'),
                (val) => Validation.minDate(val, new Date().toISOString().split('T')[0], 
                    'La fecha no puede ser anterior a hoy')
            ],
            hora_cita: [
                (val) => Validation.required(val, 'Seleccione una hora'),
                (val) => HospitalValidation.horarioCita(val)
            ],
            motivo_consulta: [
                (val) => Validation.required(val, 'Ingrese el motivo de la consulta'),
                (val) => Validation.minLength(val, 10, 'Debe tener al menos 10 caracteres')
            ]
        });
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!validator.validateAll()) {
                return;
            }
            
            const formData = validator.getValues();
            await this.crearCita(formData);
        });
        
        // Cargar horarios disponibles al cambiar médico o fecha
        const medicoSelect = form.elements['id_medico'];
        const fechaInput = form.elements['fecha_cita'];
        
        if (medicoSelect && fechaInput) {
            [medicoSelect, fechaInput].forEach(el => {
                el.addEventListener('change', () => {
                    if (medicoSelect.value && fechaInput.value) {
                        this.loadHorariosDisponibles(medicoSelect.value, fechaInput.value);
                    }
                });
            });
        }
    },
    
    /**
     * Inicializar formulario de editar cita
     */
    initEditarCitaForm() {
        const form = document.getElementById('editarCitaForm');
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            await this.actualizarCita(data.id_cita, data);
        });
    },
    
    /**
     * Inicializar búsqueda de pacientes
     */
    initBuscarPacienteForm() {
        const searchInput = document.getElementById('buscarPaciente');
        if (!searchInput) return;
        
        const debouncedSearch = debounce(async (query) => {
            if (query.length < 2) return;
            
            const pacientes = await this.buscarPacientes(query);
            this.mostrarResultadosPacientes(pacientes);
        }, 300);
        
        searchInput.addEventListener('input', (e) => {
            debouncedSearch(e.target.value);
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
            const citaId = target.dataset.citaId;
            
            switch (action) {
                case 'ver-cita':
                    this.showCitaDetails(citaId);
                    break;
                case 'editar-cita':
                    this.showEditarCitaModal(citaId);
                    break;
                case 'cancelar-cita':
                    await this.cancelarCita(citaId);
                    break;
                case 'confirmar-cita':
                    await this.confirmarCita(citaId);
                    break;
                case 'nueva-cita':
                    this.showNuevaCitaModal();
                    break;
            }
        });
        
        // Filtros
        const filtroEstado = document.getElementById('filtroEstado');
        const filtroMedico = document.getElementById('filtroMedico');
        const filtroFecha = document.getElementById('filtroFecha');
        
        [filtroEstado, filtroMedico, filtroFecha].forEach(el => {
            if (el) {
                el.addEventListener('change', () => this.aplicarFiltros());
            }
        });
    },
    
    /**
     * Cargar citas
     */
    async loadCitas() {
        showLoading();
        
        try {
            const hoy = new Date().toISOString().split('T')[0];
            const response = await CitasAPI.list(hoy, hoy);
            
            if (response.success) {
                this.citas = response.data.citas;
                this.renderCitas();
            }
        } catch (error) {
            console.error('Error cargando citas:', error);
            showToast('Error al cargar citas', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Cargar citas por rango de fechas
     */
    async loadCitasByDateRange(fechaInicio, fechaFin) {
        try {
            const response = await CitasAPI.list(fechaInicio, fechaFin);
            
            if (response.success) {
                // Convertir a formato FullCalendar
                return response.data.citas.map(cita => ({
                    id: cita.id_cita,
                    title: `${cita.paciente_nombre} - ${cita.especialidad}`,
                    start: `${cita.fecha_cita}T${cita.hora_cita}`,
                    backgroundColor: this.getColorByEstado(cita.estado_cita),
                    extendedProps: cita
                }));
            }
        } catch (error) {
            console.error('Error cargando citas:', error);
            return [];
        }
    },
    
    /**
     * Renderizar lista de citas
     */
    renderCitas() {
        const container = document.getElementById('listaCitas');
        if (!container) return;
        
        if (this.citas.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No hay citas programadas
                </div>
            `;
            return;
        }
        
        const html = this.citas.map(cita => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-0">${cita.hora_cita}</h6>
                            <small class="text-muted">${formatDate(cita.fecha_cita)}</small>
                        </div>
                        <div class="col-md-4">
                            <strong>${cita.paciente_nombre}</strong><br>
                            <small class="text-muted">HC: ${cita.numero_historia_clinica}</small>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-${this.getBadgeColorByEstado(cita.estado_cita)}">
                                ${cita.estado_cita}
                            </span><br>
                            <small>${cita.especialidad}</small>
                        </div>
                        <div class="col-md-2 text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" 
                                        data-action="ver-cita" 
                                        data-cita-id="${cita.id_cita}">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-warning" 
                                        data-action="editar-cita" 
                                        data-cita-id="${cita.id_cita}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                ${cita.estado_cita === 'Programada' ? `
                                <button class="btn btn-outline-danger" 
                                        data-action="cancelar-cita" 
                                        data-cita-id="${cita.id_cita}">
                                    <i class="bi bi-x"></i>
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    /**
     * Crear nueva cita
     */
    async crearCita(data) {
        showLoading();
        
        try {
            const response = await CitasAPI.create(data);
            
            if (response.success) {
                showToast('Cita creada exitosamente', 'success');
                this.closeModal('nuevaCitaModal');
                this.loadCitas();
                
                if (this.calendar) {
                    this.calendar.refetchEvents();
                }
            } else {
                showAlert(response.message || 'Error al crear cita', 'danger');
            }
        } catch (error) {
            console.error('Error creando cita:', error);
            showAlert('Error al crear cita', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Actualizar cita
     */
    async actualizarCita(id, data) {
        showLoading();
        
        try {
            const response = await CitasAPI.update(id, data);
            
            if (response.success) {
                showToast('Cita actualizada exitosamente', 'success');
                this.closeModal('editarCitaModal');
                this.loadCitas();
            } else {
                showAlert(response.message || 'Error al actualizar cita', 'danger');
            }
        } catch (error) {
            console.error('Error actualizando cita:', error);
            showAlert('Error al actualizar cita', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Cancelar cita
     */
    async cancelarCita(id) {
        const motivo = prompt('Ingrese el motivo de cancelación:');
        if (!motivo) return;
        
        showLoading();
        
        try {
            const response = await CitasAPI.cancel(id, motivo);
            
            if (response.success) {
                showToast('Cita cancelada exitosamente', 'success');
                this.loadCitas();
            } else {
                showAlert(response.message || 'Error al cancelar cita', 'danger');
            }
        } catch (error) {
            console.error('Error cancelando cita:', error);
            showAlert('Error al cancelar cita', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Confirmar cita
     */
    async confirmarCita(id) {
        if (!confirm('¿Confirmar esta cita?')) return;
        
        showLoading();
        
        try {
            const response = await CitasAPI.confirm(id);
            
            if (response.success) {
                showToast('Cita confirmada exitosamente', 'success');
                this.loadCitas();
            } else {
                showAlert(response.message || 'Error al confirmar cita', 'danger');
            }
        } catch (error) {
            console.error('Error confirmando cita:', error);
            showAlert('Error al confirmar cita', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Cargar horarios disponibles
     */
    async loadHorariosDisponibles(idMedico, fecha) {
        const container = document.getElementById('horariosDisponibles');
        if (!container) return;
        
        showLoading();
        
        try {
            const response = await CitasAPI.availableSlots(idMedico, fecha);
            
            if (response.success) {
                const slots = response.data.slots;
                
                const html = slots.map(slot => `
                    <button type="button" 
                            class="btn ${slot.disponible ? 'btn-outline-success' : 'btn-outline-secondary'} btn-sm m-1"
                            ${!slot.disponible ? 'disabled' : ''}
                            onclick="CitasModule.selectHorario('${slot.hora}')">
                        ${slot.hora_formatted}
                    </button>
                `).join('');
                
                container.innerHTML = html || '<p class="text-muted">No hay horarios disponibles</p>';
            }
        } catch (error) {
            console.error('Error cargando horarios:', error);
            container.innerHTML = '<p class="text-danger">Error al cargar horarios</p>';
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Seleccionar horario
     */
    selectHorario(hora) {
        const horaInput = document.getElementById('hora_cita');
        if (horaInput) {
            horaInput.value = hora;
        }
    },
    
    /**
     * Buscar pacientes
     */
    async buscarPacientes(query) {
        try {
            const response = await PacientesAPI.search(query);
            return response.success ? response.data : [];
        } catch (error) {
            console.error('Error buscando pacientes:', error);
            return [];
        }
    },
    
    /**
     * Mostrar resultados de búsqueda de pacientes
     */
    mostrarResultadosPacientes(pacientes) {
        const container = document.getElementById('resultadosPacientes');
        if (!container) return;
        
        if (pacientes.length === 0) {
            container.innerHTML = '<p class="text-muted">No se encontraron pacientes</p>';
            return;
        }
        
        const html = pacientes.map(p => `
            <div class="list-group-item list-group-item-action" 
                 onclick="CitasModule.seleccionarPaciente(${p.id_paciente}, '${p.nombres} ${p.apellidos}')">
                <strong>${p.nombres} ${p.apellidos}</strong><br>
                <small>DNI: ${p.numero_documento} | HC: ${p.numero_historia_clinica}</small>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    /**
     * Seleccionar paciente
     */
    seleccionarPaciente(id, nombre) {
        const idInput = document.getElementById('id_paciente');
        const nombreInput = document.getElementById('paciente_seleccionado');
        
        if (idInput) idInput.value = id;
        if (nombreInput) nombreInput.value = nombre;
        
        const container = document.getElementById('resultadosPacientes');
        if (container) container.innerHTML = '';
    },
    
    /**
     * Mostrar detalles de cita
     */
    async showCitaDetails(id) {
        showLoading();
        
        try {
            const response = await CitasAPI.get(id);
            
            if (response.success) {
                const cita = response.data;
                // Aquí mostrarías los detalles en un modal
                console.log('Detalles de cita:', cita);
            }
        } catch (error) {
            console.error('Error obteniendo detalles:', error);
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Mostrar modal de nueva cita
     */
    showNuevaCitaModal() {
        const modal = new bootstrap.Modal(document.getElementById('nuevaCitaModal'));
        modal.show();
        
        if (this.selectedDate) {
            const fechaInput = document.getElementById('fecha_cita');
            if (fechaInput) fechaInput.value = this.selectedDate;
        }
    },
    
    /**
     * Mostrar modal de editar cita
     */
    async showEditarCitaModal(id) {
        showLoading();
        
        try {
            const response = await CitasAPI.get(id);
            
            if (response.success) {
                const form = document.getElementById('editarCitaForm');
                if (form) {
                    // Llenar formulario con datos de la cita
                    Object.keys(response.data).forEach(key => {
                        const input = form.elements[key];
                        if (input) input.value = response.data[key];
                    });
                    
                    const modal = new bootstrap.Modal(document.getElementById('editarCitaModal'));
                    modal.show();
                }
            }
        } catch (error) {
            console.error('Error cargando cita:', error);
        } finally {
            hideLoading();
        }
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
     * Aplicar filtros
     */
    aplicarFiltros() {
        // Implementar lógica de filtros
        this.loadCitas();
    },
    
    /**
     * Obtener color por estado
     */
    getColorByEstado(estado) {
        const colores = {
            'Programada': '#0d6efd',
            'Confirmada': '#198754',
            'Atendida': '#6c757d',
            'Cancelada': '#dc3545',
            'No asistió': '#ffc107'
        };
        return colores[estado] || '#6c757d';
    },
    
    /**
     * Obtener color de badge por estado
     */
    getBadgeColorByEstado(estado) {
        const colores = {
            'Programada': 'primary',
            'Confirmada': 'success',
            'Atendida': 'secondary',
            'Cancelada': 'danger',
            'No asistió': 'warning'
        };
        return colores[estado] || 'secondary';
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('citasCalendar') || document.getElementById('listaCitas')) {
        CitasModule.init();
    }
});

// Exportar
window.CitasModule = CitasModule;

console.log('citas.js cargado correctamente');