<?php
/**
 * Constantes del Sistema
 * Define todas las constantes utilizadas en la aplicación
 */

// ==========================================
// INFORMACIÓN DEL SISTEMA
// ==========================================
define('APP_NAME', 'Sistema Hospitalario');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'Equipo de Desarrollo');
define('APP_YEAR', '2025');
define('APP_DESCRIPTION', 'Sistema de Gestión Hospitalaria Integral');

// ==========================================
// CONFIGURACIÓN DE RUTAS
// ==========================================
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('MODULES_PATH', BASE_PATH . '/modules');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('LOGS_PATH', BASE_PATH . '/logs');

// URLs
define('BASE_URL', 'http://localhost/hospital');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ==========================================
// CONFIGURACIÓN DE SESIÓN
// ==========================================
define('SESSION_LIFETIME', 3600); // 1 hora en segundos
define('SESSION_TIMEOUT_WARNING', 300); // Advertir 5 minutos antes
define('SESSION_REGENERATE_INTERVAL', 1800); // Regenerar ID cada 30 minutos

// ==========================================
// CONFIGURACIÓN DE SEGURIDAD
// ==========================================
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
define('PASSWORD_EXPIRY_DAYS', 90);
define('PASSWORD_HISTORY_COUNT', 5); // No repetir últimas 5 contraseñas

// ==========================================
// CONFIGURACIÓN DE ARCHIVOS
// ==========================================
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB en bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
define('ALLOWED_ALL_TYPES', array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES));

// Tamaños de imagen
define('PROFILE_IMAGE_MAX_WIDTH', 500);
define('PROFILE_IMAGE_MAX_HEIGHT', 500);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 150);

// ==========================================
// CONFIGURACIÓN DE PAGINACIÓN
// ==========================================
define('ITEMS_PER_PAGE', 20);
define('PATIENTS_PER_PAGE', 15);
define('APPOINTMENTS_PER_PAGE', 10);
define('MAX_PAGINATION_LINKS', 5);

// ==========================================
// CONFIGURACIÓN DE EMAIL
// ==========================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'hospital@example.com');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@hospital.com');
define('SMTP_FROM_NAME', APP_NAME);
define('SMTP_ENCRYPTION', 'tls'); // 'tls' o 'ssl'

// ==========================================
// CONFIGURACIÓN DE NOTIFICACIONES
// ==========================================
define('NOTIFICATION_EMAIL_ENABLED', false);
define('NOTIFICATION_SMS_ENABLED', false);
define('NOTIFICATION_APPOINTMENT_REMINDER', true);
define('NOTIFICATION_REMINDER_HOURS', 24); // Recordar citas 24 horas antes

// ==========================================
// ESTADOS DE ENTIDADES
// ==========================================

// Estados de Usuario
define('USER_STATUS_ACTIVE', 'activo');
define('USER_STATUS_INACTIVE', 'inactivo');
define('USER_STATUS_BLOCKED', 'bloqueado');
define('USER_STATUS_SUSPENDED', 'suspendido');

// Estados de Paciente
define('PATIENT_STATUS_ACTIVE', 'activo');
define('PATIENT_STATUS_INACTIVE', 'inactivo');
define('PATIENT_STATUS_DECEASED', 'fallecido');

// Estados de Cita
define('APPOINTMENT_STATUS_SCHEDULED', 'Programada');
define('APPOINTMENT_STATUS_CONFIRMED', 'Confirmada');
define('APPOINTMENT_STATUS_WAITING', 'En espera');
define('APPOINTMENT_STATUS_ATTENDED', 'Atendida');
define('APPOINTMENT_STATUS_CANCELLED', 'Cancelada');
define('APPOINTMENT_STATUS_NO_SHOW', 'No asistió');

// Estados de Internamiento
define('ADMISSION_STATUS_ACTIVE', 'En curso');
define('ADMISSION_STATUS_DISCHARGED', 'Alta médica');
define('ADMISSION_STATUS_VOLUNTARY', 'Alta voluntaria');
define('ADMISSION_STATUS_REFERRED', 'Referido');
define('ADMISSION_STATUS_DECEASED', 'Fallecido');

// Estados de Medicamento
define('MEDICINE_STATUS_ACTIVE', 'Activo');
define('MEDICINE_STATUS_DISCONTINUED', 'Descontinuado');
define('MEDICINE_STATUS_SUSPENDED', 'Suspendido');

// Estados de Alerta
define('ALERT_STATUS_PENDING', 'Pendiente');
define('ALERT_STATUS_REVIEWING', 'En revisión');
define('ALERT_STATUS_RESOLVED', 'Atendida');
define('ALERT_STATUS_IGNORED', 'Ignorada');

// ==========================================
// TIPOS DE DOCUMENTOS
// ==========================================
define('DOC_TYPE_DNI', 'DNI');
define('DOC_TYPE_PASSPORT', 'Pasaporte');
define('DOC_TYPE_CI', 'CI');
define('DOC_TYPE_RUC', 'RUC');
define('DOC_TYPE_OTHER', 'Otro');

// Tipos de Documento Médico
define('MEDICAL_DOC_PRESCRIPTION', 'Receta');
define('MEDICAL_DOC_LAB_ORDER', 'Orden Examen');
define('MEDICAL_DOC_CERTIFICATE', 'Certificado');
define('MEDICAL_DOC_REPORT', 'Informe');
define('MEDICAL_DOC_REFERRAL', 'Referencia');
define('MEDICAL_DOC_PROOF', 'Constancia');

// ==========================================
// GRUPOS SANGUÍNEOS
// ==========================================
define('BLOOD_TYPES', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);

// ==========================================
// GÉNEROS
// ==========================================
define('GENDER_MALE', 'M');
define('GENDER_FEMALE', 'F');
define('GENDER_OTHER', 'Otro');
define('GENDER_PREFER_NOT_SAY', 'Prefiero no decir');

// ==========================================
// ROLES DEL SISTEMA
// ==========================================
define('ROLE_ADMIN', 'Administrador');
define('ROLE_DOCTOR', 'Médico');
define('ROLE_NURSE', 'Enfermero');
define('ROLE_RECEPTIONIST', 'Recepcionista');
define('ROLE_PHARMACIST', 'Farmacéutico');
define('ROLE_LAB', 'Laboratorio');
define('ROLE_PATIENT', 'Paciente');

// ==========================================
// CONFIGURACIÓN DE INVENTARIO
// ==========================================
define('INVENTORY_LOW_STOCK_ALERT', true);
define('INVENTORY_EXPIRY_WARNING_DAYS', 90); // Alertar 90 días antes
define('INVENTORY_CRITICAL_STOCK_PERCENTAGE', 20); // Alertar al 20%

// ==========================================
// CONFIGURACIÓN DE REPORTES
// ==========================================
define('REPORT_DATE_FORMAT', 'd/m/Y');
define('REPORT_DATETIME_FORMAT', 'd/m/Y H:i');
define('REPORT_EXPORT_FORMATS', ['pdf', 'excel', 'csv']);
define('REPORT_MAX_RECORDS', 10000);

// ==========================================
// CONFIGURACIÓN DE LOGS
// ==========================================
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
define('LOG_FILE_PREFIX', 'hospital_');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_RETENTION_DAYS', 30);

// ==========================================
// CONFIGURACIÓN DE BACKUP
// ==========================================
define('BACKUP_ENABLED', true);
define('BACKUP_AUTO_SCHEDULE', 'daily'); // daily, weekly, monthly
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_PATH', BASE_PATH . '/backups');
define('BACKUP_COMPRESS', true);

// ==========================================
// CONFIGURACIÓN DE API
// ==========================================
define('API_RATE_LIMIT', 100); // Peticiones por minuto
define('API_TIMEOUT', 30); // Segundos
define('API_ENABLE_CORS', false);
define('API_VERSION', 'v1');

// ==========================================
// CONFIGURACIÓN DE CACHE
// ==========================================
define('CACHE_ENABLED', false);
define('CACHE_DRIVER', 'file'); // file, redis, memcached
define('CACHE_LIFETIME', 3600); // 1 hora
define('CACHE_PATH', BASE_PATH . '/cache');

// ==========================================
// MENSAJES DEL SISTEMA
// ==========================================
define('MSG_SUCCESS_SAVE', 'Registro guardado exitosamente');
define('MSG_SUCCESS_UPDATE', 'Registro actualizado exitosamente');
define('MSG_SUCCESS_DELETE', 'Registro eliminado exitosamente');
define('MSG_ERROR_SAVE', 'Error al guardar el registro');
define('MSG_ERROR_UPDATE', 'Error al actualizar el registro');
define('MSG_ERROR_DELETE', 'Error al eliminar el registro');
define('MSG_ERROR_NOT_FOUND', 'Registro no encontrado');
define('MSG_ERROR_PERMISSION', 'No tiene permisos para realizar esta acción');
define('MSG_ERROR_SESSION_EXPIRED', 'Su sesión ha expirado');
define('MSG_ERROR_INVALID_DATA', 'Datos inválidos');
define('MSG_ERROR_DUPLICATE', 'Ya existe un registro con estos datos');

// ==========================================
// CONFIGURACIÓN DE INTERNACIONALIZACIÓN
// ==========================================
define('DEFAULT_LANGUAGE', 'es');
define('DEFAULT_TIMEZONE', 'America/La_Paz');
define('DEFAULT_CURRENCY', 'BOB');
define('DEFAULT_COUNTRY', 'Bolivia');
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');
define('TIME_FORMAT', 'H:i');

// ==========================================
// DÍAS DE LA SEMANA EN ESPAÑOL
// ==========================================
define('DAYS_OF_WEEK', [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
]);

// ==========================================
// MESES DEL AÑO EN ESPAÑOL
// ==========================================
define('MONTHS_OF_YEAR', [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
]);

// ==========================================
// CONFIGURACIÓN DE DESARROLLO/PRODUCCIÓN
// ==========================================
define('ENVIRONMENT', 'development'); // development, production
define('DEBUG_MODE', ENVIRONMENT === 'development');
define('DISPLAY_ERRORS', DEBUG_MODE);
define('ERROR_REPORTING_LEVEL', DEBUG_MODE ? E_ALL : E_ERROR);

// Configurar errores según el entorno
if (DEBUG_MODE) {
    error_reporting(ERROR_REPORTING_LEVEL);
    ini_set('display_errors', '1');
} else {
    error_reporting(ERROR_REPORTING_LEVEL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . '/error.log');
}

// ==========================================
// VALIDACIONES REGEX
// ==========================================
define('REGEX_EMAIL', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');
define('REGEX_PHONE', '/^[0-9]{7,15}$/');
define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{4,20}$/');
define('REGEX_PASSWORD', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/');
define('REGEX_ALPHANUMERIC', '/^[a-zA-Z0-9]+$/');

// ==========================================
// CONFIGURACIÓN DE HORARIOS
// ==========================================
define('BUSINESS_HOURS_START', '08:00');
define('BUSINESS_HOURS_END', '18:00');
define('APPOINTMENT_DURATION_DEFAULT', 30); // Minutos
define('APPOINTMENT_BUFFER_TIME', 5); // Minutos entre citas

// ==========================================
// CONFIGURACIÓN DE CONSULTAS
// ==========================================
define('CONSULTATION_TYPES', [
    'Ambulatoria' => 'Consulta Ambulatoria',
    'Emergencia' => 'Emergencia',
    'Control' => 'Control',
    'Domiciliaria' => 'Domiciliaria'
]);

// ==========================================
// ESPECIALIDADES MÉDICAS COMUNES
// ==========================================
define('MEDICAL_SPECIALTIES', [
    'Medicina General',
    'Pediatría',
    'Cardiología',
    'Ginecología',
    'Traumatología',
    'Dermatología',
    'Oftalmología',
    'Otorrinolaringología',
    'Psiquiatría',
    'Neurología',
    'Urología',
    'Oncología',
    'Endocrinología',
    'Gastroenterología',
    'Neumología'
]);

// ==========================================
// CONFIGURACIÓN DE AUDITORÍA
// ==========================================
define('AUDIT_ENABLED', true);
define('AUDIT_LOG_LOGINS', true);
define('AUDIT_LOG_QUERIES', true);
define('AUDIT_LOG_SENSITIVE_DATA', true);
define('AUDIT_RETENTION_DAYS', 365); // 1 año

// ==========================================
// INFORMACIÓN DE CONTACTO
// ==========================================
define('CONTACT_EMAIL', 'contacto@hospital.com');
define('CONTACT_PHONE', '+591 2 1234567');
define('CONTACT_ADDRESS', 'Av. Principal #123, La Paz, Bolivia');
define('SUPPORT_EMAIL', 'soporte@hospital.com');
define('EMERGENCY_PHONE', '911');