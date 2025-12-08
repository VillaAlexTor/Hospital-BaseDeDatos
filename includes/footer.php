<?php
/**
 * Footer - Pie de página común para todas las páginas
 */
?>

    <!-- Footer -->
    <footer class="footer py-3 bg-light border-top" style="margin-left: 250px; width: calc(100% - 250px); box-sizing: border-box;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.
                    </span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">
                        Versión 1.0.0 | 
                        <a href="<?php echo SITE_URL; ?>/modules/ayuda/politica-privacidad.php" class="text-decoration-none">Política de Privacidad</a> | 
                        <a href="<?php echo SITE_URL; ?>/modules/ayuda/terminos.php" class="text-decoration-none">Términos de Uso</a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Main JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Scripts de utilidades -->
    <script src="<?php echo SITE_URL; ?>/assets/js/utils/api.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/utils/validation.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/utils/security.js"></script>

    <script>
        // Configuración global
        const SITE_URL = '<?php echo SITE_URL; ?>';
        const CSRF_TOKEN = '<?php echo obtener_csrf_token(); ?>';
        
        // Auto-dismiss alerts después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Confirmar antes de eliminar
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-delete, [data-action="delete"]');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('¿Está seguro que desea eliminar este registro? Esta acción no se puede deshacer.')) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });

        // Mostrar/ocultar loading spinner
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'block';
            document.getElementById('loadingSpinner').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        // Función para hacer peticiones AJAX con CSRF token
        async function fetchWithCSRF(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN,
                    'Content-Type': 'application/json'
                }
            };
            
            const mergedOptions = {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...options.headers
                }
            };
            
            try {
                const response = await fetch(url, mergedOptions);
                return response;
            } catch (error) {
                console.error('Error en la petición:', error);
                throw error;
            }
        }

        // Función para mostrar notificaciones toast
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container') || createToastContainer();
            
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Eliminar el toast del DOM después de ocultarse
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            container.style.marginTop = '60px';
            document.body.appendChild(container);
            return container;
        }

        // Formato de números
        function formatNumber(number, decimals = 2) {
            return new Intl.NumberFormat('es-BO', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        }

        // Formato de moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-BO', {
                style: 'currency',
                currency: 'BOB'
            }).format(amount);
        }

        // Formato de fecha
        function formatDate(dateString) {
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('es-BO', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(date);
        }

        // Formato de fecha y hora
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

        // Validar formularios antes de enviar
        (function() {
            'use strict';
            
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
        })();

        // Prevenir doble submit en formularios
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                        
                        // Reactivar después de 3 segundos por si hay error
                        setTimeout(function() {
                            submitButton.disabled = false;
                            submitButton.innerHTML = submitButton.dataset.originalText || 'Enviar';
                        }, 3000);
                    }
                });
            });
        });

        // Detectar inactividad del usuario
        let inactivityTime = function () {
            let time;
            const timeout = <?php echo SESSION_TIMEOUT * 1000; ?>; // Convertir a milisegundos
            
            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            document.onclick = resetTimer;
            document.onscroll = resetTimer;
            
            function logout() {
                alert('Su sesión ha expirado por inactividad.');
                window.location.href = '<?php echo SITE_URL; ?>/logout.php';
            }
            
            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, timeout);
            }
        };
        
        // Iniciar detector de inactividad
        inactivityTime();

        console.log('<?php echo SITE_NAME; ?> - Sistema cargado correctamente');
    </script>

    <style>
        /* Estilos responsive para el footer */
        @media (max-width: 768px) {
            footer {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>

</body>
</html>