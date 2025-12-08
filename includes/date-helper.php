<?php
/**
 * includes/date-helper.php
 * Funciones auxiliares para manejo de fechas
 */

/**
 * Formatea una fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Formatea fecha y hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i:s') {
    if (empty($datetime)) return '';
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Formatea solo la hora
 */
function formatTime($time, $format = 'H:i') {
    if (empty($time)) return '';
    
    try {
        $dt = new DateTime($time);
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Calcula la diferencia entre dos fechas en días
 */
function dateDiffInDays($date1, $date2) {
    try {
        $dt1 = new DateTime($date1);
        $dt2 = new DateTime($date2);
        $diff = $dt1->diff($dt2);
        return $diff->days;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Obtiene la fecha actual formateada
 */
function getTodayDate($format = 'd/m/Y') {
    return date($format);
}

/**
 * Obtiene la fecha y hora actual formateadas
 */
function getNowDateTime($format = 'd/m/Y H:i:s') {
    return date($format);
}

/**
 * Verifica si una fecha es válida
 */
function isValidDate($date, $format = 'Y-m-d') {
    try {
        $dt = DateTime::createFromFormat($format, $date);
        return $dt !== false && $dt->format($format) === $date;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Añade días a una fecha
 */
function addDaysToDate($date, $days) {
    try {
        $dt = new DateTime($date);
        $dt->modify("+$days days");
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Sustrae días de una fecha
 */
function subtractDaysFromDate($date, $days) {
    try {
        $dt = new DateTime($date);
        $dt->modify("-$days days");
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Obtiene el nombre del día en español
 */
function getDayNameInSpanish($date) {
    $daysInSpanish = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    
    try {
        $dt = new DateTime($date);
        $dayName = $dt->format('l');
        return $daysInSpanish[$dayName] ?? $dayName;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Obtiene el nombre del mes en español
 */
function getMonthNameInSpanish($date) {
    $monthsInSpanish = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    try {
        $dt = new DateTime($date);
        $month = (int) $dt->format('m');
        return $monthsInSpanish[$month] ?? '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Verifica si una fecha es en el pasado
 */
function isInThePast($date) {
    try {
        $dt = new DateTime($date);
        return $dt < new DateTime();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verifica si una fecha es en el futuro
 */
function isInTheFuture($date) {
    try {
        $dt = new DateTime($date);
        return $dt > new DateTime();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Formatea fecha en español usando strftime
 * Ejemplo: format_date_es('%A, %d de %B de %Y', time())
 * Retorna: "Lunes, 01 de Diciembre de 2025"
 */
function format_date_es($format, $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    // Setear locale a español
    $oldLocale = setlocale(LC_TIME, 0);
    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_ES.utf8', 'Spanish_Spain.1252', 'es_MX.UTF-8');
    
    // Formatear la fecha
    $formatted = strftime($format, $timestamp);
    
    // Capitalizar primer carácter
    $formatted = ucfirst($formatted);
    
    // Restaurar locale anterior
    setlocale(LC_TIME, $oldLocale);
    
    return $formatted;
}

/**
 * Alias compatibilidad: formato español simple
 */
function format_date_spanish($date) {
    return formatDate($date, 'd/m/Y');
}

/**
 * Alias compatibilidad: formato español con hora
 */
function format_datetime_spanish($datetime) {
    return formatDateTime($datetime, 'd/m/Y H:i:s');
}
