<?php
/**
 * modules/ayuda/politica-privacidad.php
 * Política de Privacidad del Sistema Hospitalario
 */

require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Política de Privacidad";
require_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <!-- Header -->
            <div class="mb-4">
                <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php" class="btn btn-outline-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
                <h1 class="display-5 mb-3">🔒 Política de Privacidad</h1>
                <p class="text-muted">
                    <i class="fas fa-calendar-alt"></i> Última actualización: <?php echo date('d/m/Y'); ?> | 
                    Versión 1.0
                </p>
            </div>

            <!-- Contenido -->
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <!-- Introducción -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">1. Introducción</h2>
                        <p>
                            El <strong>Sistema Hospitalario</strong> se compromete a proteger la privacidad y seguridad 
                            de la información personal y médica de todos nuestros usuarios, pacientes y personal.
                        </p>
                        <p>
                            Esta Política de Privacidad describe cómo recopilamos, usamos, almacenamos y protegemos 
                            su información de acuerdo con las leyes de protección de datos vigentes en Bolivia y 
                            estándares internacionales de privacidad médica.
                        </p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Importante:</strong> Al utilizar este sistema, usted acepta los términos 
                            descritos en esta política de privacidad.
                        </div>
                    </section>

                    <!-- Información que recopilamos -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">2. Información que Recopilamos</h2>
                        
                        <h3 class="h5 mb-3">2.1 Información Personal</h3>
                        <ul class="mb-4">
                            <li><strong>Datos de identificación:</strong> Nombre completo, número de documento de identidad, fecha de nacimiento, género</li>
                            <li><strong>Datos de contacto:</strong> Dirección física, teléfono, correo electrónico</li>
                            <li><strong>Datos demográficos:</strong> Ciudad, país de residencia</li>
                            <li><strong>Fotografía:</strong> Imagen de perfil (opcional)</li>
                        </ul>

                        <h3 class="h5 mb-3">2.2 Información Médica Sensible</h3>
                        <ul class="mb-4">
                            <li><strong>Historia clínica:</strong> Diagnósticos, tratamientos, cirugías previas, hospitalizaciones</li>
                            <li><strong>Datos clínicos:</strong> Grupo sanguíneo, alergias, enfermedades crónicas, medicamentos actuales</li>
                            <li><strong>Resultados de exámenes:</strong> Análisis de laboratorio, estudios de imagen, biopsias</li>
                            <li><strong>Consultas médicas:</strong> Motivos de consulta, síntomas, evolución, notas médicas</li>
                            <li><strong>Recetas médicas:</strong> Medicamentos prescritos, dosis, frecuencia</li>
                            <li><strong>Datos de internamiento:</strong> Fechas de ingreso/alta, habitación, evolución médica</li>
                        </ul>

                        <h3 class="h5 mb-3">2.3 Información de Uso del Sistema</h3>
                        <ul>
                            <li>Direcciones IP de acceso</li>
                            <li>Fecha y hora de las sesiones</li>
                            <li>Navegador y sistema operativo utilizado</li>
                            <li>Acciones realizadas en el sistema (registradas en auditoría)</li>
                            <li>Documentos descargados o impresos</li>
                        </ul>
                    </section>

                    <!-- Cómo usamos la información -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">3. Cómo Usamos la Información</h2>
                        
                        <p>Utilizamos la información recopilada para los siguientes propósitos legítimos:</p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card border-primary h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-hospital text-primary"></i> Atención Médica
                                        </h5>
                                        <ul class="small mb-0">
                                            <li>Proporcionar atención médica adecuada</li>
                                            <li>Gestionar citas y consultas</li>
                                            <li>Mantener historiales clínicos</li>
                                            <li>Coordinar tratamientos</li>
                                            <li>Prescribir medicamentos</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-clipboard-list text-success"></i> Gestión Administrativa
                                        </h5>
                                        <ul class="small mb-0">
                                            <li>Programar y confirmar citas</li>
                                            <li>Gestionar internamientos</li>
                                            <li>Facturación y cobros</li>
                                            <li>Control de inventarios</li>
                                            <li>Reportes estadísticos</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-warning h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-shield-alt text-warning"></i> Seguridad
                                        </h5>
                                        <ul class="small mb-0">
                                            <li>Autenticar usuarios</li>
                                            <li>Prevenir accesos no autorizados</li>
                                            <li>Detectar actividades sospechosas</li>
                                            <li>Auditar cambios en registros</li>
                                            <li>Cumplir obligaciones legales</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-info h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-chart-line text-info"></i> Mejora Continua
                                        </h5>
                                        <ul class="small mb-0">
                                            <li>Analizar uso del sistema</li>
                                            <li>Mejorar servicios médicos</li>
                                            <li>Optimizar procesos</li>
                                            <li>Capacitación del personal</li>
                                            <li>Investigación médica (anonimizada)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Protección de datos -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">4. Cómo Protegemos su Información</h2>
                        
                        <p>Implementamos múltiples capas de seguridad para proteger su información:</p>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medida de Seguridad</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><i class="fas fa-lock text-primary"></i> <strong>Cifrado AES-256</strong></td>
                                        <td>Todos los datos sensibles se almacenan cifrados en la base de datos</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-key text-success"></i> <strong>Hashing de Contraseñas</strong></td>
                                        <td>Las contraseñas se almacenan con algoritmos de hash seguros (SHA-256 + Salt)</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-user-shield text-info"></i> <strong>Control de Acceso</strong></td>
                                        <td>Sistema de roles y permisos granulares por módulo y acción</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-clipboard-check text-warning"></i> <strong>Auditoría Completa</strong></td>
                                        <td>Registro de todas las acciones con fecha, hora, usuario e IP</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-database text-danger"></i> <strong>Backups Cifrados</strong></td>
                                        <td>Respaldos automáticos diarios con cifrado y almacenamiento seguro</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-network-wired text-secondary"></i> <strong>Seguridad de Red</strong></td>
                                        <td>Firewalls, detección de intrusos, protección DDoS</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-clock text-primary"></i> <strong>Sesiones Seguras</strong></td>
                                        <td>Timeout automático, regeneración de tokens, detección de hijacking</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            <strong>Certificación:</strong> Nuestro sistema cumple con estándares internacionales 
                            de seguridad de información médica (HIPAA compatible).
                        </div>
                    </section>

                    <!-- Compartir información -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">5. Compartir Información</h2>
                        
                        <p><strong>NO vendemos ni compartimos su información con terceros para fines comerciales.</strong></p>
                        
                        <p>Solo compartimos información en los siguientes casos específicos:</p>
                        
                        <ul>
                            <li><strong>Personal médico autorizado:</strong> Médicos, enfermeros y personal administrativo con permisos específicos</li>
                            <li><strong>Emergencias médicas:</strong> Cuando es necesario para salvar vidas o prevenir daños graves</li>
                            <li><strong>Referencias médicas:</strong> Con su consentimiento explícito al derivar a otro especialista</li>
                            <li><strong>Seguros médicos:</strong> Cuando usted lo autoriza para gestionar coberturas</li>
                            <li><strong>Obligaciones legales:</strong> Cuando la ley lo requiere (orden judicial, autoridades sanitarias)</li>
                            <li><strong>Investigación médica:</strong> Solo datos anonimizados y con aprobación de comité de ética</li>
                        </ul>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Importante:</strong> Todo acceso a datos sensibles queda registrado en el sistema de auditoría.
                        </div>
                    </section>

                    <!-- Derechos del usuario -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">6. Sus Derechos</h2>
                        
                        <p>Como titular de sus datos, usted tiene los siguientes derechos:</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-eye text-primary"></i> Derecho de Acceso</h5>
                                        <p class="small mb-0">Solicitar copia de su información personal y médica almacenada</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-edit text-success"></i> Derecho de Rectificación</h5>
                                        <p class="small mb-0">Corregir datos inexactos o incompletos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-ban text-danger"></i> Derecho de Oposición</h5>
                                        <p class="small mb-0">Oponerse al tratamiento de sus datos en casos específicos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-lock text-warning"></i> Derecho de Limitación</h5>
                                        <p class="small mb-0">Solicitar limitación del procesamiento de sus datos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-download text-info"></i> Derecho de Portabilidad</h5>
                                        <p class="small mb-0">Recibir sus datos en formato estructurado y transferible</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h5><i class="fas fa-trash text-secondary"></i> Derecho al Olvido</h5>
                                        <p class="small mb-0">Solicitar eliminación de datos (con limitaciones legales médicas)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> 
                            Para ejercer estos derechos, contacte a nuestro Oficial de Protección de Datos en: 
                            <strong>privacidad@hospital.com</strong>
                        </div>
                    </section>

                    <!-- Retención de datos -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">7. Retención de Datos</h2>
                        
                        <p>Conservamos su información de acuerdo con las siguientes políticas:</p>

                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tipo de Dato</th>
                                    <th>Período de Retención</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Historia clínica completa</td>
                                    <td><strong>Permanente</strong> (obligación legal médica)</td>
                                </tr>
                                <tr>
                                    <td>Datos de consultas y tratamientos</td>
                                    <td><strong>20 años</strong> desde última atención</td>
                                </tr>
                                <tr>
                                    <td>Resultados de exámenes</td>
                                    <td><strong>10 años</strong></td>
                                </tr>
                                <tr>
                                    <td>Datos de facturación</td>
                                    <td><strong>7 años</strong> (obligación fiscal)</td>
                                </tr>
                                <tr>
                                    <td>Logs de auditoría</td>
                                    <td><strong>5 años</strong></td>
                                </tr>
                                <tr>
                                    <td>Datos de contacto</td>
                                    <td>Mientras sea paciente activo + 2 años</td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="small text-muted">
                            <i class="fas fa-info-circle"></i> Los períodos de retención cumplen con la legislación 
                            boliviana y estándares internacionales de registros médicos.
                        </p>
                    </section>

                    <!-- Cookies -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">8. Cookies y Tecnologías Similares</h2>
                        
                        <p>Utilizamos cookies y tecnologías similares para:</p>
                        <ul>
                            <li>Mantener su sesión activa de forma segura</li>
                            <li>Recordar sus preferencias del sistema</li>
                            <li>Analizar el uso del sistema (de forma anónima)</li>
                            <li>Mejorar la experiencia de usuario</li>
                        </ul>

                        <p>Tipos de cookies que utilizamos:</p>
                        <ul>
                            <li><strong>Cookies esenciales:</strong> Necesarias para el funcionamiento del sistema (sesión, autenticación)</li>
                            <li><strong>Cookies funcionales:</strong> Guardan preferencias de idioma, tema, configuración</li>
                            <li><strong>Cookies de rendimiento:</strong> Ayudan a mejorar el rendimiento del sistema</li>
                        </ul>

                        <p>
                            Puede configurar su navegador para rechazar cookies, pero esto puede afectar la funcionalidad del sistema.
                        </p>
                    </section>

                    <!-- Menores de edad -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">9. Privacidad de Menores de Edad</h2>
                        
                        <p>
                            Tomamos precauciones especiales con la información de pacientes menores de 18 años:
                        </p>
                        <ul>
                            <li>Requerimos consentimiento de padres o tutores legales</li>
                            <li>Acceso limitado solo a personal médico autorizado</li>
                            <li>Cifrado adicional de datos sensibles de menores</li>
                            <li>Auditoría reforzada de accesos a registros de menores</li>
                            <li>Protocolos especiales para casos de abuso o negligencia</li>
                        </ul>
                    </section>

                    <!-- Cambios a la política -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">10. Cambios a Esta Política</h2>
                        
                        <p>
                            Nos reservamos el derecho de actualizar esta Política de Privacidad periódicamente. 
                            Los cambios significativos serán notificados mediante:
                        </p>
                        <ul>
                            <li>Aviso destacado en el sistema al iniciar sesión</li>
                            <li>Correo electrónico a usuarios registrados</li>
                            <li>Actualización de la fecha de "Última actualización" en esta página</li>
                        </ul>
                        <p>
                            Le recomendamos revisar esta política periódicamente.
                        </p>
                    </section>

                    <!-- Contacto -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">11. Contacto</h2>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5>Oficial de Protección de Datos</h5>
                                <p class="mb-2">
                                    Si tiene preguntas, inquietudes o desea ejercer sus derechos relacionados con la privacidad:
                                </p>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-envelope text-primary"></i> <strong>Email:</strong> privacidad@hospital.com</li>
                                    <li><i class="fas fa-phone text-success"></i> <strong>Teléfono:</strong> +591 (2) 123-4567</li>
                                    <li><i class="fas fa-map-marker-alt text-danger"></i> <strong>Dirección:</strong> Av. Principal #123, La Paz, Bolivia</li>
                                    <li><i class="fas fa-clock text-info"></i> <strong>Horario:</strong> Lunes a Viernes, 8:00 - 18:00</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Aceptación -->
                    <section>
                        <div class="alert alert-primary">
                            <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Aceptación de esta Política</h5>
                            <p class="mb-0">
                                Al utilizar el Sistema Hospitalario, usted reconoce que ha leído, entendido y acepta 
                                estar sujeto a esta Política de Privacidad. Si no está de acuerdo con algún término, 
                                por favor absténgase de utilizar el sistema y contacte con administración.
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Footer de la página -->
            <div class="text-center mt-4 mb-5">
                <p class="text-muted">
                    <i class="fas fa-shield-alt"></i> Sus datos están protegidos con los más altos estándares de seguridad
                </p>
                <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
                <a href="terminos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-file-contract"></i> Ver Términos de Uso
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 10px;
}

.card-body h2, .card-body h3 {
    color: #2c3e50;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.card-body h3 {
    border-bottom: 1px solid #e9ecef;
}

section {
    scroll-margin-top: 20px;
}

.table th {
    background-color: #f8f9fa;
}

.alert {
    border-left: 4px solid;
}

.alert-info {
    border-left-color: #17a2b8;
}

.alert-success {
    border-left-color: #28a745;
}

.alert-warning {
    border-left-color: #ffc107;
}

.alert-primary {
    border-left-color: #007bff;
}
</style>

<?php require_once '../../includes/footer.php'; ?>