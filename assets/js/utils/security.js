/**
 * js/utils/security.js
 * Security Utilities
 * Funciones de seguridad del lado del cliente
 */

const Security = {
    
    /**
     * Escapar HTML para prevenir XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    },
    
    /**
     * Sanitizar input
     */
    sanitizeInput(input) {
        if (typeof input !== 'string') return input;
        return this.escapeHtml(input.trim());
    },
    
    /**
     * Validar CSRF Token
     */
    validateCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        return token && token.length > 0;
    },
    
    /**
     * Obtener CSRF Token
     */
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || CSRF_TOKEN || '';
    },
    
    /**
     * Agregar CSRF token a FormData
     */
    addCSRFToFormData(formData) {
        formData.append('csrf_token', this.getCSRFToken());
        return formData;
    },
    
    /**
     * Validar email
     */
    validateEmail(email) {
        const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return re.test(String(email).toLowerCase());
    },
    
    /**
     * Validar teléfono
     */
    validatePhone(phone) {
        const re = /^[0-9]{7,15}$/;
        return re.test(phone.replace(/[\s-]/g, ''));
    },
    
    /**
     * Validar username
     */
    validateUsername(username) {
        const re = /^[a-zA-Z0-9_]{4,20}$/;
        return re.test(username);
    },
    
    /**
     * Validar fortaleza de contraseña
     */
    validatePasswordStrength(password) {
        const errors = [];
        
        if (password.length < 8) {
            errors.push('La contraseña debe tener al menos 8 caracteres');
        }
        
        if (!/[A-Z]/.test(password)) {
            errors.push('Debe contener al menos una letra mayúscula');
        }
        
        if (!/[a-z]/.test(password)) {
            errors.push('Debe contener al menos una letra minúscula');
        }
        
        if (!/[0-9]/.test(password)) {
            errors.push('Debe contener al menos un número');
        }
        
        return {
            valid: errors.length === 0,
            errors: errors,
            strength: this.getPasswordStrength(password)
        };
    },
    
    /**
     * Calcular nivel de fortaleza de contraseña
     */
    getPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        if (strength <= 2) return 'débil';
        if (strength <= 4) return 'media';
        return 'fuerte';
    },
    
    /**
     * Generar password aleatorio
     */
    generatePassword(length = 12) {
        const lowercase = 'abcdefghijklmnopqrstuvwxyz';
        const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const numbers = '0123456789';
        const symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        const allChars = lowercase + uppercase + numbers + symbols;
        let password = '';
        
        // Asegurar al menos un carácter de cada tipo
        password += lowercase[Math.floor(Math.random() * lowercase.length)];
        password += uppercase[Math.floor(Math.random() * uppercase.length)];
        password += numbers[Math.floor(Math.random() * numbers.length)];
        password += symbols[Math.floor(Math.random() * symbols.length)];
        
        // Llenar el resto
        for (let i = password.length; i < length; i++) {
            password += allChars[Math.floor(Math.random() * allChars.length)];
        }
        
        // Mezclar
        return password.split('').sort(() => 0.5 - Math.random()).join('');
    },
    
    /**
     * Encriptar datos (base64 simple - NO USAR PARA DATOS SENSIBLES)
     */
    simpleEncrypt(text) {
        return btoa(unescape(encodeURIComponent(text)));
    },
    
    /**
     * Desencriptar datos (base64 simple)
     */
    simpleDecrypt(encoded) {
        try {
            return decodeURIComponent(escape(atob(encoded)));
        } catch (e) {
            console.error('Error al desencriptar:', e);
            return null;
        }
    },
    
    /**
     * Verificar si la conexión es segura
     */
    isSecureConnection() {
        return window.location.protocol === 'https:';
    },
    
    /**
     * Limpiar datos sensibles del localStorage
     */
    clearSensitiveData() {
        const keysToRemove = [];
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.includes('password') || key.includes('token') || key.includes('secret')) {
                keysToRemove.push(key);
            }
        }
        
        keysToRemove.forEach(key => localStorage.removeItem(key));
    },
    
    /**
     * Detectar posible XSS en input
     */
    detectXSS(input) {
        const xssPatterns = [
            /<script[^>]*>.*?<\/script>/gi,
            /javascript:/gi,
            /on\w+\s*=/gi,
            /<iframe[^>]*>/gi,
            /eval\(/gi,
            /expression\(/gi
        ];
        
        return xssPatterns.some(pattern => pattern.test(input));
    },
    
    /**
     * Limpiar posible XSS
     */
    cleanXSS(input) {
        if (typeof input !== 'string') return input;
        
        // Remover scripts
        let clean = input.replace(/<script[^>]*>.*?<\/script>/gi, '');
        
        // Remover event handlers
        clean = clean.replace(/on\w+\s*=\s*["'][^"']*["']/gi, '');
        
        // Remover javascript: URLs
        clean = clean.replace(/javascript:/gi, '');
        
        // Escapar HTML
        return this.escapeHtml(clean);
    },
    
    /**
     * Validar y limpiar URL
     */
    sanitizeUrl(url) {
        try {
            const urlObj = new URL(url);
            // Solo permitir http y https
            if (!['http:', 'https:'].includes(urlObj.protocol)) {
                return null;
            }
            return urlObj.toString();
        } catch (e) {
            return null;
        }
    },
    
    /**
     * Rate limiting simple
     */
    checkRateLimit(action, limit = 5, timeWindow = 60000) {
        const key = `ratelimit_${action}`;
        const now = Date.now();
        
        let attempts = JSON.parse(localStorage.getItem(key) || '[]');
        
        // Limpiar intentos antiguos
        attempts = attempts.filter(time => now - time < timeWindow);
        
        if (attempts.length >= limit) {
            return false;
        }
        
        attempts.push(now);
        localStorage.setItem(key, JSON.stringify(attempts));
        
        return true;
    },
    
    /**
     * Generar hash simple (NO USAR PARA PASSWORDS)
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash.toString(36);
    },
    
    /**
     * Verificar integridad de datos
     */
    verifyIntegrity(data, hash) {
        return this.simpleHash(JSON.stringify(data)) === hash;
    }
};

// ==========================================
// EVENTOS DE SEGURIDAD
// ==========================================

/**
 * Prevenir click derecho (opcional)
 */
function disableRightClick() {
    document.addEventListener('contextmenu', e => e.preventDefault());
}

/**
 * Prevenir inspección (opcional - no recomendado)
 */
function disableDevTools() {
    document.addEventListener('keydown', e => {
        if (e.key === 'F12' || 
            (e.ctrlKey && e.shiftKey && e.key === 'I') ||
            (e.ctrlKey && e.shiftKey && e.key === 'J') ||
            (e.ctrlKey && e.key === 'U')) {
            e.preventDefault();
        }
    });
}

/**
 * Detectar extensiones maliciosas
 */
function detectMaliciousExtensions() {
    // Detectar modificaciones DOM sospechosas
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeName === 'SCRIPT' && !node.src.includes(window.location.hostname)) {
                    console.warn('Script externo detectado:', node.src);
                }
            });
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

/**
 * Inicializar medidas de seguridad
 */
function initSecurity() {
    // Limpiar datos sensibles al cerrar
    window.addEventListener('beforeunload', () => {
        Security.clearSensitiveData();
    });
    
    // Detectar extensiones
    detectMaliciousExtensions();
    
    // Validar CSRF token
    if (!Security.validateCSRFToken()) {
        console.warn('CSRF token no encontrado');
    }
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', initSecurity);

// ==========================================
// EXPORTAR
// ==========================================

window.Security = Security;
window.disableRightClick = disableRightClick;
window.disableDevTools = disableDevTools;

console.log('Security.js cargado correctamente');