/**
 * Validation Utilities
 * Funciones de validación de formularios y datos
 */

const Validation = {
    
    /**
     * Validar si el campo está vacío
     */
    required(value, message = 'Este campo es requerido') {
        if (value === null || value === undefined || value === '') {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar longitud mínima
     */
    minLength(value, min, message = `Debe tener al menos ${min} caracteres`) {
        if (value.length < min) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar longitud máxima
     */
    maxLength(value, max, message = `No debe exceder ${max} caracteres`) {
        if (value.length > max) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar email
     */
    email(value, message = 'Email inválido') {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(value)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar número de teléfono
     */
    phone(value, message = 'Teléfono inválido') {
        const re = /^[0-9]{7,15}$/;
        const cleaned = value.replace(/[\s\-()]/g, '');
        if (!re.test(cleaned)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar número
     */
    number(value, message = 'Debe ser un número válido') {
        if (isNaN(value)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar número entero
     */
    integer(value, message = 'Debe ser un número entero') {
        if (!Number.isInteger(Number(value))) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar rango numérico
     */
    range(value, min, max, message = `Debe estar entre ${min} y ${max}`) {
        const num = Number(value);
        if (num < min || num > max) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar fecha
     */
    date(value, message = 'Fecha inválida') {
        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar fecha mínima
     */
    minDate(value, minDate, message = 'Fecha debe ser posterior a la fecha mínima') {
        const date = new Date(value);
        const min = new Date(minDate);
        if (date < min) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar fecha máxima
     */
    maxDate(value, maxDate, message = 'Fecha debe ser anterior a la fecha máxima') {
        const date = new Date(value);
        const max = new Date(maxDate);
        if (date > max) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar URL
     */
    url(value, message = 'URL inválida') {
        try {
            new URL(value);
            return { valid: true };
        } catch (e) {
            return { valid: false, message };
        }
    },
    
    /**
     * Validar patrón regex
     */
    pattern(value, regex, message = 'Formato inválido') {
        if (!regex.test(value)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar igualdad (para confirmar contraseñas)
     */
    matches(value, compareValue, message = 'Los valores no coinciden') {
        if (value !== compareValue) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar DNI/CI boliviano
     */
    ci(value, message = 'CI/DNI inválido') {
        const re = /^[0-9]{7,10}$/;
        const cleaned = value.replace(/[\s\-]/g, '');
        if (!re.test(cleaned)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar solo letras
     */
    alpha(value, message = 'Solo se permiten letras') {
        const re = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
        if (!re.test(value)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar alfanumérico
     */
    alphanumeric(value, message = 'Solo se permiten letras y números') {
        const re = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/;
        if (!re.test(value)) {
            return { valid: false, message };
        }
        return { valid: true };
    },
    
    /**
     * Validar archivo
     */
    file(file, options = {}) {
        const {
            maxSize = 5 * 1024 * 1024, // 5MB
            allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'],
            message = 'Archivo inválido'
        } = options;
        
        if (!file) {
            return { valid: false, message: 'No se ha seleccionado ningún archivo' };
        }
        
        // Validar tamaño
        if (file.size > maxSize) {
            return { 
                valid: false, 
                message: `El archivo excede el tamaño máximo (${maxSize / 1024 / 1024}MB)` 
            };
        }
        
        // Validar tipo
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(extension)) {
            return { 
                valid: false, 
                message: `Tipo de archivo no permitido. Permitidos: ${allowedTypes.join(', ')}` 
            };
        }
        
        return { valid: true };
    },
    
    /**
     * Validar múltiples reglas
     */
    validate(value, rules) {
        for (const rule of rules) {
            const result = rule(value);
            if (!result.valid) {
                return result;
            }
        }
        return { valid: true };
    }
};

// ==========================================
// VALIDADOR DE FORMULARIOS
// ==========================================

class FormValidator {
    constructor(formId, rules = {}) {
        this.form = document.getElementById(formId);
        this.rules = rules;
        this.errors = {};
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateAll()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.form.classList.add('was-validated');
        });
        
        // Validación en tiempo real
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.elements[fieldName];
            if (field) {
                field.addEventListener('blur', () => {
                    this.validateField(fieldName);
                });
                
                field.addEventListener('input', () => {
                    if (this.errors[fieldName]) {
                        this.validateField(fieldName);
                    }
                });
            }
        });
    }
    
    validateField(fieldName) {
        const field = this.form.elements[fieldName];
        const rules = this.rules[fieldName];
        
        if (!field || !rules) return true;
        
        const value = field.value;
        
        for (const rule of rules) {
            const result = typeof rule === 'function' 
                ? rule(value) 
                : Validation[rule.type](value, ...rule.args || []);
            
            if (!result.valid) {
                this.setError(fieldName, result.message);
                return false;
            }
        }
        
        this.clearError(fieldName);
        return true;
    }
    
    validateAll() {
        let isValid = true;
        this.errors = {};
        
        Object.keys(this.rules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    setError(fieldName, message) {
        this.errors[fieldName] = message;
        const field = this.form.elements[fieldName];
        
        if (field) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            
            // Buscar o crear div de error
            let errorDiv = field.parentElement.querySelector('.invalid-feedback');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                field.parentElement.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }
    }
    
    clearError(fieldName) {
        delete this.errors[fieldName];
        const field = this.form.elements[fieldName];
        
        if (field) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            
            const errorDiv = field.parentElement.querySelector('.invalid-feedback');
            if (errorDiv) {
                errorDiv.textContent = '';
            }
        }
    }
    
    clearAllErrors() {
        Object.keys(this.errors).forEach(fieldName => {
            this.clearError(fieldName);
        });
    }
    
    reset() {
        this.form.reset();
        this.clearAllErrors();
        this.form.classList.remove('was-validated');
    }
    
    getValues() {
        const formData = new FormData(this.form);
        return Object.fromEntries(formData);
    }
    
    setValues(data) {
        Object.keys(data).forEach(key => {
            const field = this.form.elements[key];
            if (field) {
                field.value = data[key];
            }
        });
    }
}

// ==========================================
// VALIDACIONES PERSONALIZADAS PARA EL HOSPITAL
// ==========================================

const HospitalValidation = {
    /**
     * Validar número de historia clínica
     */
    historiaClinica(value) {
        const re = /^HC-\d{4}-\d{6}$/;
        if (!re.test(value)) {
            return { 
                valid: false, 
                message: 'Formato inválido. Debe ser HC-YYYY-NNNNNN' 
            };
        }
        return { valid: true };
    },
    
    /**
     * Validar grupo sanguíneo
     */
    grupoSanguineo(value) {
        const valid = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!valid.includes(value)) {
            return { 
                valid: false, 
                message: 'Grupo sanguíneo inválido' 
            };
        }
        return { valid: true };
    },
    
    /**
     * Validar edad mínima
     */
    edadMinima(fechaNacimiento, edadMinima = 0) {
        const hoy = new Date();
        const nacimiento = new Date(fechaNacimiento);
        const edad = Math.floor((hoy - nacimiento) / (365.25 * 24 * 60 * 60 * 1000));
        
        if (edad < edadMinima) {
            return { 
                valid: false, 
                message: `La edad mínima es ${edadMinima} años` 
            };
        }
        return { valid: true };
    },
    
    /**
     * Validar horario de cita
     */
    horarioCita(hora) {
        const [h, m] = hora.split(':').map(Number);
        const minutos = h * 60 + m;
        const inicio = 8 * 60; // 08:00
        const fin = 18 * 60;   // 18:00
        
        if (minutos < inicio || minutos >= fin) {
            return { 
                valid: false, 
                message: 'El horario debe estar entre 08:00 y 18:00' 
            };
        }
        return { valid: true };
    }
};

// ==========================================
// EXPORTAR
// ==========================================

window.Validation = Validation;
window.FormValidator = FormValidator;
window.HospitalValidation = HospitalValidation;

console.log('Validation.js cargado correctamente');