<?php
/**
 * Security Headers - Configuración de cabeceras HTTP de seguridad
 * Protege contra varios tipos de ataques web
 */

// Prevenir que el navegador interprete archivos como un tipo MIME diferente
header("X-Content-Type-Options: nosniff");

// Protección contra clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Habilitar el filtro XSS del navegador
header("X-XSS-Protection: 1; mode=block");

// Política de referrer - no enviar información del referrer a sitios externos
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy (CSP)
// Ajustar según las necesidades específicas del sitio
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
    "img-src 'self' data: https:",
    "connect-src 'self'",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'"
];
header("Content-Security-Policy: " . implode("; ", $csp));

// Permissions Policy (antes Feature Policy)
$permissions = [
    "geolocation=()",
    "microphone=()",
    "camera=()",
    "payment=()",
    "usb=()",
    "magnetometer=()",
    "gyroscope=()",
    "accelerometer=()"
];
header("Permissions-Policy: " . implode(", ", $permissions));

// HSTS (HTTP Strict Transport Security)
// NOTA: Solo usar si tienes HTTPS configurado
// Para XAMPP con HTTP, comentar esta línea
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Prevenir el cacheo de páginas sensibles
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * Función para configurar headers específicos para páginas de login
 */
function security_headers_login() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Función para configurar headers para descarga de archivos
 */
function security_headers_download() {
    header("X-Content-Type-Options: nosniff");
    header("Content-Disposition: attachment");
}

/**
 * Función para configurar headers para APIs/AJAX
 */
function security_headers_api() {
    header("Content-Type: application/json; charset=UTF-8");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
}

/**
 * Limpia el buffer de salida para asegurar que no se envíe contenido antes de los headers
 */
if (ob_get_level()) {
    ob_clean();
}