/**
 * js/utils/api.js
 * API Utilities
 * Funciones para hacer peticiones a la API
 */

const API = {
    baseUrl: (typeof APP !== 'undefined' && APP.baseUrl) || 
             (typeof SITE_URL !== 'undefined' ? SITE_URL : null) ||
             (window.location.origin + '/hospital'),
    
    /**
     * Obtener token CSRF
     */
    getCsrfToken() {
        return (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '') ||
               (typeof APP !== 'undefined' && APP.csrfToken ? APP.csrfToken : '');
    },
    
    /**
     * Realizar petición GET
     */
    async get(endpoint, params = {}) {
        const url = new URL(`${this.baseUrl}/api/${endpoint}`);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Content-Type': 'application/json'
                }
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },
    
    /**
     * Realizar petición POST
     */
    async post(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/api/${endpoint}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },
    
    /**
     * Realizar petición POST con JSON
     */
    async postJSON(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/api/${endpoint}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },
    
    /**
     * Realizar petición PUT
     */
    async put(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/api/${endpoint}`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },
    
    /**
     * Realizar petición DELETE
     */
    async delete(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/api/${endpoint}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    },
    
    /**
     * Manejar respuesta
     */
    async handleResponse(response) {
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Error en la petición');
        }
        
        return data;
    },
    
    /**
     * Manejar error
     */
    handleError(error) {
        console.error('API Error:', error);
        
        if (typeof showToast === 'function') {
            showToast(error.message || 'Error en la comunicación con el servidor', 'danger');
        }
        
        return {
            success: false,
            message: error.message,
            data: null
        };
    },
    
    /**
     * Upload de archivo
     */
    async uploadFile(endpoint, file, additionalData = {}) {
        try {
            const formData = new FormData();
            formData.append('file', file);
            
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });
            
            const response = await fetch(`${this.baseUrl}/api/${endpoint}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: formData
            });
            
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }
};

// ==========================================
// FUNCIONES ESPECÍFICAS DE LA API
// ==========================================

/**
 * API de Autenticación
 */
const AuthAPI = {
    login: (username, password) => {
        return API.post('auth.php?action=login', { username, password });
    },
    
    logout: () => {
        return API.post('auth.php?action=logout');
    },
    
    checkSession: () => {
        return API.get('auth.php', { action: 'check_session' });
    },
    
    getUserInfo: () => {
        return API.get('auth.php', { action: 'get_user_info' });
    },
    
    changePassword: (currentPassword, newPassword, confirmPassword) => {
        return API.post('auth.php?action=change_password', {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        });
    }
};

/**
 * API de Pacientes
 */
const PacientesAPI = {
    list: (page = 1, limit = 10, search = '', estado = 'activo') => {
        return API.get('pacientes.php', { action: 'list', page, limit, search, estado });
    },
    
    get: (id) => {
        return API.get('pacientes.php', { action: 'get', id });
    },
    
    create: (data) => {
        return API.post('pacientes.php?action=create', data);
    },
    
    update: (id, data) => {
        data.id_paciente = id;
        return API.post('pacientes.php?action=update', data);
    },
    
    delete: (id) => {
        return API.post('pacientes.php?action=delete', { id });
    },
    
    search: (query) => {
        return API.get('pacientes.php', { action: 'search', q: query });
    }
};

/**
 * API de Citas
 */
const CitasAPI = {
    list: (fechaInicio, fechaFin, idMedico = null, idPaciente = null, estado = null) => {
        const params = { action: 'list', fecha_inicio: fechaInicio, fecha_fin: fechaFin };
        if (idMedico) params.id_medico = idMedico;
        if (idPaciente) params.id_paciente = idPaciente;
        if (estado) params.estado = estado;
        return API.get('citas.php', params);
    },
    
    get: (id) => {
        return API.get('citas.php', { action: 'get', id });
    },
    
    create: (data) => {
        return API.post('citas.php?action=create', data);
    },
    
    update: (id, data) => {
        data.id_cita = id;
        return API.post('citas.php?action=update', data);
    },
    
    cancel: (id, motivo) => {
        return API.post('citas.php?action=cancel', { id_cita: id, motivo_cancelacion: motivo });
    },
    
    confirm: (id) => {
        return API.post('citas.php?action=confirm', { id_cita: id });
    },
    
    availableSlots: (idMedico, fecha) => {
        return API.get('citas.php', { action: 'available_slots', id_medico: idMedico, fecha });
    },
    
    stats: (fechaInicio, fechaFin) => {
        return API.get('citas.php', { action: 'stats', fecha_inicio: fechaInicio, fecha_fin: fechaFin });
    }
};

// ==========================================
// HELPERS PARA PETICIONES
// ==========================================

/**
 * Realizar petición con loading
 */
async function fetchWithLoading(apiCall, loadingMessage = 'Cargando...') {
    if (typeof showLoading === 'function') showLoading();
    
    try {
        const result = await apiCall();
        return result;
    } catch (error) {
        console.error('Error:', error);
        throw error;
    } finally {
        if (typeof hideLoading === 'function') hideLoading();
    }
}

/**
 * Realizar petición con confirmación
 */
async function fetchWithConfirm(message, apiCall) {
    if (confirm(message)) {
        return await fetchWithLoading(apiCall);
    }
    return null;
}

// ==========================================
// EXPORTAR
// ==========================================

window.API = API;
window.AuthAPI = AuthAPI;
window.PacientesAPI = PacientesAPI;
window.CitasAPI = CitasAPI;
window.fetchWithLoading = fetchWithLoading;
window.fetchWithConfirm = fetchWithConfirm;

console.log('API.js cargado correctamente');