<?php
/**
 * modules/ayuda/terminos.php
 * Términos y Condiciones de Uso del Sistema Hospitalario
 */

require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Términos de Uso";
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
                <h1 class="display-5 mb-3">📋 Términos y Condiciones de Uso</h1>
                <p class="text-muted">
                    <i class="fas fa-calendar-alt"></i> Última actualización: <?php echo date('d/m/Y'); ?> | 
                    Vigencia: <?php echo date('Y'); ?> | Versión 1.0
                </p>
            </div>

            <!-- Contenido -->
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <!-- Introducción -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">1. Aceptación de los Términos</h2>
                        <p>
                            Bienvenido al <strong>Sistema Integral de Gestión Hospitalaria</strong>. Al acceder y 
                            utilizar este sistema, usted acepta cumplir y estar sujeto a los siguientes términos y 
                            condiciones de uso.
                        </p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Importante:</strong> Si no está de acuerdo con alguno de estos términos, 
                            no utilice este sistema. El uso continuo del sistema constituye la aceptación de 
                            estos términos.
                        </div>
                        <p>
                            Estos términos constituyen un acuerdo legal vinculante entre usted (el "Usuario") y 
                            la institución hospitalaria (el "Hospital").
                        </p>
                    </section>

                    <!-- Definiciones -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">2. Definiciones</h2>
                        
                        <dl class="row">
                            <dt class="col-sm-3">Sistema</dt>
                            <dd class="col-sm-9">
                                El Sistema Integral de Gestión Hospitalaria, incluyendo todos sus módulos, 
                                funcionalidades y componentes.
                            </dd>

                            <dt class="col-sm-3">Usuario</dt>
                            <dd class="col-sm-9">
                                Cualquier persona autorizada a acceder al sistema: personal médico, administrativo, 
                                pacientes o visitantes autorizados.
                            </dd>

                            <dt class="col-sm-3">Cuenta</dt>
                            <dd class="col-sm-9">
                                Las credenciales únicas (usuario y contraseña) asignadas a cada usuario para acceder al sistema.
                            </dd>

                            <dt class="col-sm-3">Datos Sensibles</dt>
                            <dd class="col-sm-9">
                                Información personal, médica o administrativa que requiere protección especial según 
                                las leyes de privacidad.
                            </dd>

                            <dt class="col-sm-3">Servicios</dt>
                            <dd class="col-sm-9">
                                Todas las funcionalidades disponibles en el sistema: gestión de citas, historiales clínicos, 
                                inventarios, reportes, etc.
                            </dd>
                        </dl>
                    </section>

                    <!-- Elegibilidad -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">3. Elegibilidad y Registro</h2>
                        
                        <h3 class="h5 mb-3">3.1 Requisitos de Elegibilidad</h3>
                        <p>Para utilizar este sistema, usted debe:</p>
                        <ul>
                            <li>Ser mayor de 18 años o tener el consentimiento de un padre/tutor legal</li>
                            <li>Tener autorización oficial del Hospital para acceder al sistema</li>
                            <li>Estar vinculado al Hospital como: empleado, médico, paciente o proveedor autorizado</li>
                            <li>Aceptar estos términos y la Política de Privacidad</li>
                            <li>Proporcionar información veraz y actualizada</li>
                        </ul>

                        <h3 class="h5 mb-3">3.2 Creación de Cuenta</h3>
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Responsabilidades al crear una cuenta:</h6>
                                <ul class="mb-0">
                                    <li>Proporcionar información precisa, completa y actualizada</li>
                                    <li>Mantener la seguridad de su contraseña</li>
                                    <li>Notificar inmediatamente cualquier uso no autorizado de su cuenta</li>
                                    <li>Aceptar responsabilidad por todas las actividades realizadas con su cuenta</li>
                                    <li>Actualizar su información cuando sea necesario</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="h5 mb-3">3.3 Tipos de Usuario</h3>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo de Usuario</th>
                                    <th>Permisos y Responsabilidades</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Administrador</strong></td>
                                    <td>Acceso completo al sistema, gestión de usuarios, configuración general</td>
                                </tr>
                                <tr>
                                    <td><strong>Médico</strong></td>
                                    <td>Gestión de consultas, historiales, prescripciones, solo sus pacientes</td>
                                </tr>
                                <tr>
                                    <td><strong>Recepcionista</strong></td>
                                    <td>Programación de citas, registro de pacientes, información general</td>
                                </tr>
                                <tr>
                                    <td><strong>Enfermería</strong></td>
                                    <td>Registro de signos vitales, administración de medicamentos, evoluciones</td>
                                </tr>
                                <tr>
                                    <td><strong>Farmacia</strong></td>
                                    <td>Gestión de inventario, dispensación de medicamentos, alertas</td>
                                </tr>
                                <tr>
                                    <td><strong>Paciente</strong></td>
                                    <td>Consulta de citas, historial personal, resultados de exámenes</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- Uso Aceptable -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">4. Uso Aceptable del Sistema</h2>
                        
                        <h3 class="h5 mb-3">4.1 Usos Permitidos</h3>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <strong>Puede utilizar el sistema para:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Realizar las funciones propias de su rol asignado</li>
                                <li>Acceder a información necesaria para su trabajo</li>
                                <li>Gestionar citas, consultas y tratamientos médicos</li>
                                <li>Generar reportes autorizados</li>
                                <li>Actualizar información dentro de sus permisos</li>
                            </ul>
                        </div>

                        <h3 class="h5 mb-3">4.2 Usos Prohibidos</h3>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> <strong>Está estrictamente prohibido:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Acceder a información sin autorización explícita</li>
                                <li>Compartir credenciales de acceso con terceros</li>
                                <li>Intentar vulnerar las medidas de seguridad del sistema</li>
                                <li>Copiar, distribuir o publicar datos de pacientes sin autorización</li>
                                <li>Utilizar el sistema para fines no médicos o comerciales</li>
                                <li>Introducir virus, malware o código malicioso</li>
                                <li>Realizar ingeniería inversa del sistema</li>
                                <li>Modificar, alterar o eliminar datos sin autorización</li>
                                <li>Usar el sistema para actividades ilegales</li>
                                <li>Acceder a datos de pacientes por curiosidad personal</li>
                                <li>Falsificar documentos médicos o registros</li>
                                <li>Realizar búsquedas no justificadas médicamente</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Advertencia:</strong> La violación de estas prohibiciones puede resultar en 
                            la terminación inmediata de su acceso, acciones disciplinarias, y posibles 
                            consecuencias legales.
                        </div>
                    </section>

                    <!-- Seguridad -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">5. Seguridad y Confidencialidad</h2>
                        
                        <h3 class="h5 mb-3">5.1 Protección de Credenciales</h3>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card border-primary h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-key text-primary"></i> Su Contraseña Debe:
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Tener al menos 8 caracteres</li>
                                            <li>Incluir mayúsculas y minúsculas</li>
                                            <li>Contener números y símbolos</li>
                                            <li>Ser única (no reutilizar)</li>
                                            <li>Cambiarse cada 90 días</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-danger h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-ban text-danger"></i> Nunca Debe:
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Compartir su contraseña</li>
                                            <li>Anotarla en lugares visibles</li>
                                            <li>Usar contraseñas obvias</li>
                                            <li>Dejar sesión abierta</li>
                                            <li>Acceder desde equipos públicos</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="h5 mb-3">5.2 Confidencialidad Médica</h3>
                        <p>
                            <strong>Todo usuario se compromete a mantener la confidencialidad de:</strong>
                        </p>
                        <ul>
                            <li>Información médica de pacientes (protegida por secreto médico)</li>
                            <li>Datos personales de usuarios del sistema</li>
                            <li>Información administrativa sensible</li>
                            <li>Credenciales de acceso y tokens de seguridad</li>
                        </ul>

                        <h3 class="h5 mb-3">5.3 Auditoría y Monitoreo</h3>
                        <div class="alert alert-info">
                            <i class="fas fa-clipboard-check"></i> 
                            <strong>Aviso Importante:</strong> Todas las actividades en el sistema son monitoreadas 
                            y registradas, incluyendo:
                            <ul class="mb-0 mt-2">
                                <li>Fecha, hora y duración de sesiones</li>
                                <li>Acciones realizadas (crear, modificar, eliminar, consultar)</li>
                                <li>Datos accedidos y modificados</li>
                                <li>Dirección IP y dispositivo utilizado</li>
                                <li>Intentos de acceso fallidos</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Responsabilidades -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">6. Responsabilidades del Usuario</h2>
                        
                        <h3 class="h5 mb-3">6.1 Personal Médico y Administrativo</h3>
                        <ul>
                            <li><strong>Exactitud de datos:</strong> Ingresar información precisa y completa</li>
                            <li><strong>Actualización oportuna:</strong> Mantener registros actualizados</li>
                            <li><strong>Uso profesional:</strong> Utilizar el sistema solo para fines médicos legítimos</li>
                            <li><strong>Cumplimiento normativo:</strong> Seguir protocolos médicos y administrativos</li>
                            <li><strong>Capacitación:</strong> Mantenerse actualizado en el uso del sistema</li>
                            <li><strong>Reportar incidentes:</strong> Notificar problemas de seguridad o errores</li>
                        </ul>

                        <h3 class="h5 mb-3">6.2 Pacientes</h3>
                        <ul>
                            <li>Proporcionar información médica veraz y completa</li>
                            <li>Actualizar datos de contacto cuando cambien</li>
                            <li>Confirmar o cancelar citas oportunamente</li>
                            <li>No compartir acceso a su cuenta con terceros</li>
                            <li>Consultar al médico antes de tomar decisiones basadas en información del sistema</li>
                        </ul>

                        <h3 class="h5 mb-3">6.3 Todos los Usuarios</h3>
                        <div class="card bg-light">
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Cerrar sesión al terminar de usar el sistema</li>
                                    <li>No dejar equipos desatendidos con sesión activa</li>
                                    <li>Reportar accesos no autorizados inmediatamente</li>
                                    <li>Mantener navegador y sistema operativo actualizados</li>
                                    <li>No intentar acceder a áreas no autorizadas</li>
                                    <li>Respetar los derechos de privacidad de otros usuarios</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Propiedad Intelectual -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">7. Propiedad Intelectual</h2>
                        
                        <p>
                            Todo el contenido del sistema, incluyendo pero no limitado a:
                        </p>
                        <ul>
                            <li>Código fuente y diseño del software</li>
                            <li>Interfaz de usuario y diseño gráfico</li>
                            <li>Logos, marcas y nombres comerciales</li>
                            <li>Documentación y manuales</li>
                            <li>Estructuras de base de datos</li>
                        </ul>
                        <p>
                            <strong>Son propiedad exclusiva del Hospital y están protegidos por leyes de propiedad 
                            intelectual.</strong> Queda prohibida su copia, modificación, distribución o 
                            explotación comercial sin autorización escrita.
                        </p>

                        <div class="alert alert-warning">
                            <i class="fas fa-copyright"></i> 
                            Los datos ingresados por los usuarios (historiales médicos, registros) son propiedad 
                            del Hospital pero están sujetos a derechos de privacidad de los pacientes.
                        </div>
                    </section>

                    <!-- Limitación de Responsabilidad -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">8. Limitación de Responsabilidad</h2>
                        
                        <h3 class="h5 mb-3">8.1 Disponibilidad del Sistema</h3>
                        <p>
                            Nos esforzamos por mantener el sistema disponible 24/7, pero no garantizamos:
                        </p>
                        <ul>
                            <li>Disponibilidad ininterrumpida (puede haber mantenimientos programados)</li>
                            <li>Operación libre de errores</li>
                            <li>Que defectos serán corregidos inmediatamente</li>
                            <li>Protección contra todas las amenazas de seguridad</li>
                        </ul>

                        <h3 class="h5 mb-3">8.2 Exclusión de Garantías</h3>
                        <div class="alert alert-secondary">
                            <p class="mb-0">
                                <strong>EL SISTEMA SE PROPORCIONA "TAL CUAL" SIN GARANTÍAS DE NINGÚN TIPO.</strong> 
                                El Hospital no garantiza que el sistema satisfaga sus necesidades específicas o que 
                                el uso sea ininterrumpido o libre de errores.
                            </p>
                        </div>

                        <h3 class="h5 mb-3">8.3 Limitaciones Específicas</h3>
                        <p>El Hospital no será responsable por:</p>
                        <ul>
                            <li>Pérdida de datos debido a fallas técnicas, errores humanos o eventos fuera de control</li>
                            <li>Decisiones médicas basadas únicamente en información del sistema</li>
                            <li>Accesos no autorizados resultantes de negligencia del usuario</li>
                            <li>Interrupciones causadas por terceros (ataques DDoS, hackers)</li>
                            <li>Problemas de conectividad o hardware del usuario</li>
                            <li>Daños indirectos, consecuentes o punitivos</li>
                        </ul>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Importante:</strong> Este sistema es una herramienta de apoyo. Las decisiones 
                            médicas finales deben tomarse por profesionales calificados basándose en evaluación 
                            clínica completa.
                        </div>
                    </section>

                    <!-- Suspensión y Terminación -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">9. Suspensión y Terminación de Cuenta</h2>
                        
                        <h3 class="h5 mb-3">9.1 Causas de Suspensión o Terminación</h3>
                        <p>El Hospital se reserva el derecho de suspender o terminar su acceso si:</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <strong>Suspensión Temporal</strong>
                                    </div>
                                    <div class="card-body">
                                        <ul class="small mb-0">
                                            <li>Múltiples intentos fallidos de login</li>
                                            <li>Actividad sospechosa detectada</li>
                                            <li>Incumplimiento menor de políticas</li>
                                            <li>Falta de actualización de información</li>
                                            <li>Período de inactividad prolongado</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card border-danger">
                                    <div class="card-header bg-danger text-white">
                                        <strong>Terminación Permanente</strong>
                                    </div>
                                    <div class="card-body">
                                        <ul class="small mb-0">
                                            <li>Violación grave de términos</li>
                                            <li>Acceso no autorizado a datos</li>
                                            <li>Compartir credenciales</li>
                                            <li>Uso fraudulento del sistema</li>
                                            <li>Fin de relación laboral/paciente</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="h5 mb-3">9.2 Proceso de Apelación</h3>
                        <p>
                            Si su cuenta es suspendida o terminada y considera que fue un error, puede apelar 
                            contactando a: <strong>soporte@hospital.com</strong> en un plazo de 15 días hábiles.
                        </p>

                        <h3 class="h5 mb-3">9.3 Efectos de la Terminación</h3>
                        <ul>
                            <li>Pérdida inmediata de acceso al sistema</li>
                            <li>Los datos del Hospital permanecen como propiedad del Hospital</li>
                            <li>Obligación de confidencialidad continúa vigente</li>
                            <li>Posibles acciones legales en caso de violaciones graves</li>
                        </ul>
                    </section>

                    <!-- Modificaciones -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">10. Modificaciones al Sistema y Términos</h2>
                        
                        <h3 class="h5 mb-3">10.1 Actualizaciones del Sistema</h3>
                        <p>Nos reservamos el derecho de:</p>
                        <ul>
                            <li>Modificar, suspender o discontinuar cualquier funcionalidad</li>
                            <li>Realizar mantenimientos programados con notificación previa</li>
                            <li>Actualizar requisitos técnicos del sistema</li>
                            <li>Agregar o remover características</li>
                        </ul>

                        <h3 class="h5 mb-3">10.2 Cambios en los Términos</h3>
                        <p>
                            Estos términos pueden ser modificados en cualquier momento. Los cambios significativos serán notificados mediante:
                        </p>
                        <ul>
                            <li>Notificación en el sistema al iniciar sesión</li>
                            <li>Correo electrónico a la dirección registrada</li>
                            <li>Publicación en página de anuncios</li>
                        </ul>
                        <p>
                            El uso continuado del sistema después de la notificación constituye aceptación de los nuevos términos.
                        </p>
                    </section>

                    <!-- Disposiciones Legales -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">11. Disposiciones Legales</h2>
                        
                        <h3 class="h5 mb-3">11.1 Ley Aplicable</h3>
                        <p>
                            Estos términos se rigen por las leyes de Bolivia. Cualquier disputa se resolverá 
                            en los tribunales competentes de La Paz, Bolivia.
                        </p>

                        <h3 class="h5 mb-3">11.2 Indemnización</h3>
                        <p>
                            Usted acepta indemnizar y mantener indemne al Hospital, sus empleados y agentes de 
                            cualquier reclamación resultante de:
                        </p>
                        <ul>
                            <li>Su violación de estos términos</li>
                            <li>Su violación de derechos de terceros</li>
                            <li>Su uso indebido del sistema</li>
                            <li>Información falsa proporcionada por usted</li>
                        </ul>

                        <h3 class="h5 mb-3">11.3 Divisibilidad</h3>
                        <p>
                            Si alguna disposición de estos términos se considera inválida o inaplicable, 
                            las disposiciones restantes continuarán en pleno vigor.
                        </p>

                        <h3 class="h5 mb-3">11.4 Acuerdo Completo</h3>
                        <p>
                            Estos términos, junto con la Política de Privacidad, constituyen el acuerdo completo 
                            entre usted y el Hospital respecto al uso del sistema.
                        </p>
                    </section>

                    <!-- Contacto -->
                    <section class="mb-5">
                        <h2 class="h4 mb-3">12. Contacto y Soporte</h2>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-headset text-primary"></i> Soporte Técnico</h5>
                                        <p class="small mb-2">Para problemas técnicos del sistema:</p>
                                        <ul class="list-unstyled small mb-0">
                                            <li><i class="fas fa-envelope"></i> soporte@hospital.com</li>
                                            <li><i class="fas fa-phone"></i> +591 (2) 123-4567 ext. 100</li>
                                            <li><i class="fas fa-clock"></i> 24/7</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-balance-scale text-success"></i> Asuntos Legales</h5>
                                        <p class="small mb-2">Para consultas sobre términos y condiciones:</p>
                                        <ul class="list-unstyled small mb-0">
                                            <li><i class="fas fa-envelope"></i> legal@hospital.com</li>
                                            <li><i class="fas fa-phone"></i> +591 (2) 123-4567 ext. 200</li>
                                            <li><i class="fas fa-clock"></i> Lun-Vie 8:00-18:00</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Aceptación Final -->
                    <section>
                        <div class="alert alert-primary">
                            <h5 class="alert-heading">
                                <i class="fas fa-check-square"></i> Aceptación de Términos
                            </h5>
                            <hr>
                            <p class="mb-0">
                                <strong>AL HACER CLIC EN "ACEPTO" O AL UTILIZAR EL SISTEMA, USTED RECONOCE QUE:</strong>
                            </p>
                            <ul class="mt-2 mb-0">
                                <li>Ha leído y entendido estos Términos y Condiciones</li>
                                <li>Acepta estar legalmente vinculado por estos términos</li>
                                <li>Acepta la Política de Privacidad del sistema</li>
                                <li>Se compromete a utilizar el sistema de manera responsable y ética</li>
                                <li>Comprende las consecuencias del incumplimiento de estos términos</li>
                            </ul>
                        </div>

                        <div class="alert alert-danger">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-circle"></i> Advertencia Final
                            </h6>
                            <p class="small mb-0">
                                El uso no autorizado, acceso indebido o violación de estos términos puede resultar 
                                en responsabilidad civil y penal bajo las leyes de Bolivia, incluyendo pero no 
                                limitado a la Ley de Protección de Datos Personales y el Código Penal Boliviano.
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Footer de la página -->
            <div class="text-center mt-4 mb-5">
                <p class="text-muted">
                    <i class="fas fa-gavel"></i> Estos términos están sujetos a las leyes de Bolivia
                </p>
                <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
                <a href="politica-privacidad.php" class="btn btn-outline-secondary">
                    <i class="fas fa-shield-alt"></i> Ver Política de Privacidad
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

dl dt {
    color: #495057;
    font-weight: 600;
}

dl dd {
    margin-bottom: 1rem;
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

.alert-danger {
    border-left-color: #dc3545;
}

.alert-primary {
    border-left-color: #007bff;
}

.table th {
    background-color: #f8f9fa;
}
</style>

<?php require_once '../../includes/footer.php'; ?>