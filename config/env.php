<?php
/**
 * config/env.php
 * Cargador de configuración desde variables de entorno o valores por defecto
 */

class EnvLoader {
    private static $values = [];
    
    /**
     * Cargar valor de entorno o usar defecto
     */
    public static function get($key, $default = null) {
        // Primero intenta desde variables de entorno
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Luego desde getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Finalmente usar el valor por defecto
        return $default;
    }
    
    /**
     * Establecer valor
     */
    public static function set($key, $value) {
        self::$values[$key] = $value;
        $_ENV[$key] = $value;
    }
}
