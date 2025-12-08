/**
 * js/modules/auth.js
 * Módulo de Autenticación
 * Maneja login, logout y gestión de sesión
 */

const AuthModule = {
    /**
     * Inicializar módulo
     */
    init() {
        this.initLoginForm();
        this.initChangePasswordForm();
        this.initSessionCheck();
        console.log('AuthModule inicializado');
    },
    
    /**
     * Inicializar formulario de login
     */
    initLoginForm() {
        const loginForm = document.getElementById('loginForm');
        if (!loginForm) return;
        
        const validator = new FormValidator('loginForm', {
            username: [
                (val) => Validation.required(val, 'El usuario es requerido'),
                (val) => Validation.minLength(val, 4, 'El usuario debe tener al menos 4 caracteres')
            ],
            password: [
                (val) => Validation.required(val, 'La contraseña es requerida'),
                (val) => Validation.minLength(val, 4, 'La contraseña debe tener al menos 4 caracteres')
            ]
        });
        
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!validator.validateAll()) {
                return;
            }
            
            const formData = validator.getValues();
            await this.login(formData.username, formData.password);
        });
        
        // Mostrar/ocultar contraseña
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', () => {
                const passwordInput = document.getElementById('password');
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                togglePassword.innerHTML = type === 'password' 
                    ? '<i class="bi bi-eye"></i>' 
                    : '<i class="bi bi-eye-slash"></i>';
            });
        }
    },
    
    /**
     * Realizar login
     */
    async login(username, password) {
        showLoading();
        
        try {
            const response = await AuthAPI.login(username, password);
            
            if (response.success) {
                showToast('Inicio de sesión exitoso', 'success');
                
                // Redirigir al dashboard
                setTimeout(() => {
                    window.location.href = APP.baseUrl + '/modules/dashboard/index.php';
                }, 1000);
            } else {
                showAlert(response.message || 'Error al iniciar sesión', 'danger');
            }
        } catch (error) {
            console.error('Error en login:', error);
            showAlert('Error al iniciar sesión. Intente nuevamente.', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Realizar logout
     */
    async logout() {
        if (!confirm('¿Está seguro que desea cerrar sesión?')) {
            return;
        }
        
        showLoading();
        
        try {
            await AuthAPI.logout();
            window.location.href = APP.baseUrl + '/login.php';
        } catch (error) {
            console.error('Error en logout:', error);
            // Forzar logout aunque falle la petición
            window.location.href = APP.baseUrl + '/logout.php';
        }
    },
    
    /**
     * Inicializar formulario de cambio de contraseña
     */
    initChangePasswordForm() {
        const form = document.getElementById('changePasswordForm');
        if (!form) return;
        
        const validator = new FormValidator('changePasswordForm', {
            current_password: [
                (val) => Validation.required(val, 'La contraseña actual es requerida')
            ],
            new_password: [
                (val) => Validation.required(val, 'La nueva contraseña es requerida'),
                (val) => Validation.minLength(val, 8, 'Debe tener al menos 8 caracteres'),
                (val) => {
                    const result = Security.validatePasswordStrength(val);
                    return result.valid 
                        ? { valid: true } 
                        : { valid: false, message: result.errors.join(', ') };
                }
            ],
            confirm_password: [
                (val) => Validation.required(val, 'Confirme la nueva contraseña'),
                (val) => {
                    const newPassword = form.elements['new_password'].value;
                    return Validation.matches(val, newPassword, 'Las contraseñas no coinciden');
                }
            ]
        });
        
        // Indicador de fortaleza
        const newPasswordInput = form.elements['new_password'];
        const strengthIndicator = document.getElementById('passwordStrength');
        
        if (newPasswordInput && strengthIndicator) {
            newPasswordInput.addEventListener('input', () => {
                const strength = Security.getPasswordStrength(newPasswordInput.value);
                strengthIndicator.className = 'password-strength ' + strength;
                strengthIndicator.textContent = 'Fortaleza: ' + strength;
            });
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!validator.validateAll()) {
                return;
            }
            
            const formData = validator.getValues();
            await this.changePassword(
                formData.current_password,
                formData.new_password,
                formData.confirm_password
            );
        });
    },
    
    /**
     * Cambiar contraseña
     */
    async changePassword(currentPassword, newPassword, confirmPassword) {
        showLoading();
        
        try {
            const response = await AuthAPI.changePassword(
                currentPassword,
                newPassword,
                confirmPassword
            );
            
            if (response.success) {
                showAlert('Contraseña actualizada exitosamente', 'success');
                document.getElementById('changePasswordForm').reset();
            } else {
                showAlert(response.message || 'Error al cambiar contraseña', 'danger');
            }
        } catch (error) {
            console.error('Error al cambiar contraseña:', error);
            showAlert('Error al cambiar contraseña', 'danger');
        } finally {
            hideLoading();
        }
    },
    
    /**
     * Verificar sesión periódicamente
     */
    initSessionCheck() {
        // Verificar cada 5 minutos
        setInterval(async () => {
            try {
                const response = await AuthAPI.checkSession();
                
                if (!response.success) {
                    showAlert('Su sesión ha expirado', 'warning');
                    setTimeout(() => {
                        window.location.href = APP.baseUrl + '/logout.php';
                    }, 2000);
                }
            } catch (error) {
                console.error('Error verificando sesión:', error);
            }
        }, 5 * 60 * 1000);
    },
    
    /**
     * Obtener información del usuario actual
     */
    async getUserInfo() {
        try {
            const response = await AuthAPI.getUserInfo();
            return response.success ? response.data : null;
        } catch (error) {
            console.error('Error obteniendo info de usuario:', error);
            return null;
        }
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    AuthModule.init();
});

// Manejar botón de logout
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-action="logout"]')) {
        e.preventDefault();
        AuthModule.logout();
    }
});

// Exportar
window.AuthModule = AuthModule;

console.log('auth.js cargado correctamente');