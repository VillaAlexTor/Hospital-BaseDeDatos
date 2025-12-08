/**
 * Módulo Dashboard
 * Maneja estadísticas y visualización del dashboard
 */

const DashboardModule = {
    charts: {},
    refreshInterval: null,
    
    /**
     * Inicializar módulo
     */
    init() {
        this.loadEstadisticas();
        this.initCharts();
        this.initRefreshButton();
        this.startAutoRefresh();
        console.log('DashboardModule inicializado');
    },
    
    /**
     * Cargar estadísticas
     */
    async loadEstadisticas() {
        try {
            // Cargar estadísticas de diferentes módulos
            await Promise.all([
                this.loadEstadisticasGenerales(),
                this.loadCitasHoy(),
                this.loadActividadReciente()
            ]);
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    },
    
    /**
     * Cargar estadísticas generales
     */
    async loadEstadisticasGenerales() {
        const statsContainer = document.getElementById('estadisticasGenerales');
        if (!statsContainer) return;
        
        // Aquí harías una petición real a tu API
        // Por ahora, datos de ejemplo
        const stats = {
            pacientes: 150,
            citasHoy: 25,
            internamientos: 8,
            alertas: 3
        };
        
        this.updateStatsCards(stats);
    },
    
    /**
     * Actualizar tarjetas de estadísticas
     */
    updateStatsCards(stats) {
        // Actualizar contador de pacientes
        const pacientesCard = document.querySelector('[data-stat="pacientes"]');
        if (pacientesCard) {
            this.animateCounter(pacientesCard, stats.pacientes);
        }
        
        // Actualizar contador de citas
        const citasCard = document.querySelector('[data-stat="citas"]');
        if (citasCard) {
            this.animateCounter(citasCard, stats.citasHoy);
        }
        
        // Actualizar contador de internamientos
        const internamientosCard = document.querySelector('[data-stat="internamientos"]');
        if (internamientosCard) {
            this.animateCounter(internamientosCard, stats.internamientos);
        }
        
        // Actualizar contador de alertas
        const alertasCard = document.querySelector('[data-stat="alertas"]');
        if (alertasCard) {
            this.animateCounter(alertasCard, stats.alertas);
        }
    },
    
    /**
     * Animar contador
     */
    animateCounter(element, targetValue, duration = 1000) {
        const startValue = parseInt(element.textContent) || 0;
        const increment = (targetValue - startValue) / (duration / 16);
        let currentValue = startValue;
        
        const timer = setInterval(() => {
            currentValue += increment;
            
            if ((increment > 0 && currentValue >= targetValue) || 
                (increment < 0 && currentValue <= targetValue)) {
                currentValue = targetValue;
                clearInterval(timer);
            }
            
            element.textContent = Math.round(currentValue);
        }, 16);
    },
    
    /**
     * Cargar citas del día
     */
    async loadCitasHoy() {
        const container = document.getElementById('citasHoy');
        if (!container) return;
        
        try {
            const hoy = new Date().toISOString().split('T')[0];
            const response = await CitasAPI.list(hoy, hoy);
            
            if (response.success) {
                const citas = response.data.citas.slice(0, 5); // Primeras 5
                this.renderCitasHoy(citas, container);
            }
        } catch (error) {
            console.error('Error cargando citas:', error);
            container.innerHTML = '<p class="text-danger">Error al cargar citas</p>';
        }
    },
    
    /**
     * Renderizar citas del día
     */
    renderCitasHoy(citas, container) {
        if (citas.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i>
                    No hay citas programadas para hoy
                </div>
            `;
            return;
        }
        
        const html = citas.map(cita => `
            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                <div class="flex-shrink-0">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                        <i class="bi bi-person"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0">${cita.paciente_nombre}</h6>
                    <small class="text-muted">
                        ${cita.hora_cita} - ${cita.especialidad}
                    </small>
                </div>
                <div class="flex-shrink-0">
                    <span class="badge bg-${this.getBadgeColorByEstado(cita.estado_cita)}">
                        ${cita.estado_cita}
                    </span>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    /**
     * Cargar actividad reciente
     */
    async loadActividadReciente() {
        const container = document.getElementById('actividadReciente');
        if (!container) return;
        
        // Simulación de actividad reciente
        const actividades = [
            {
                icono: 'person-plus',
                texto: 'Nuevo paciente registrado',
                tiempo: 'Hace 2 horas',
                tipo: 'success'
            },
            {
                icono: 'calendar-check',
                texto: 'Cita agendada',
                tiempo: 'Hace 3 horas',
                tipo: 'primary'
            },
            {
                icono: 'file-medical',
                texto: 'Consulta completada',
                tiempo: 'Hace 4 horas',
                tipo: 'info'
            }
        ];
        
        this.renderActividadReciente(actividades, container);
    },
    
    /**
     * Renderizar actividad reciente
     */
    renderActividadReciente(actividades, container) {
        const html = actividades.map(act => `
            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                <div class="flex-shrink-0">
                    <div class="bg-${act.tipo} bg-opacity-10 text-${act.tipo} rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                        <i class="bi bi-${act.icono}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0">${act.texto}</p>
                    <small class="text-muted">${act.tiempo}</small>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    /**
     * Inicializar gráficos
     */
    initCharts() {
        this.initCitasChart();
        this.initPacientesChart();
        this.initIngresosChart();
    },
    
    /**
     * Inicializar gráfico de citas
     */
    initCitasChart() {
        const canvas = document.getElementById('citasChart');
        if (!canvas || typeof Chart === 'undefined') return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.citas = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Citas',
                    data: [12, 19, 15, 25, 22, 18, 10],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    },
    
    /**
     * Inicializar gráfico de pacientes
     */
    initPacientesChart() {
        const canvas = document.getElementById('pacientesChart');
        if (!canvas || typeof Chart === 'undefined') return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.pacientes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Inactivos', 'Nuevos'],
                datasets: [{
                    data: [120, 15, 25],
                    backgroundColor: [
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(75, 192, 192)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
    },
    
    /**
     * Inicializar gráfico de ingresos
     */
    initIngresosChart() {
        const canvas = document.getElementById('ingresosChart');
        if (!canvas || typeof Chart === 'undefined') return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.ingresos = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                datasets: [{
                    label: 'Ingresos',
                    data: [15000, 18000, 20000, 22000, 19000, 25000],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Bs ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    },
    
    /**
     * Inicializar botón de actualizar
     */
    initRefreshButton() {
        const refreshBtn = document.getElementById('refreshDashboard');
        if (!refreshBtn) return;
        
        refreshBtn.addEventListener('click', () => {
            this.refresh();
        });
    },
    
    /**
     * Actualizar dashboard
     */
    async refresh() {
        showLoading();
        
        try {
            await this.loadEstadisticas();
            showToast('Dashboard actualizado', 'success');
        } catch (error) {
            console.error('Error actualizando dashboard:', error);
            showToast('Error al actualizar', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Iniciar auto-refresh
     */
    startAutoRefresh() {
        // Actualizar cada 5 minutos
        this.refreshInterval = setInterval(() => {
            this.loadEstadisticas();
        }, 5 * 60 * 1000);
    },
    
    /**
     * Detener auto-refresh
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
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
    },
    
    /**
     * Destruir gráficos al salir
     */
    destroy() {
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.stopAutoRefresh();
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('dashboardContainer') || 
        document.querySelector('.dashboard-page')) {
        DashboardModule.init();
    }
});

// Limpiar al salir
window.addEventListener('beforeunload', () => {
    DashboardModule.destroy();
});

// Exportar
window.DashboardModule = DashboardModule;

console.log('dashboard.js cargado correctamente');