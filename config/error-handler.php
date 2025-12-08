<?php
/**
 * config/error-handler.php
 * Manejo centralizado de errores y excepciones
 */

class ErrorHandler {
    private static $debug = false;
    
    /**
     * Inicializar el manejador de errores
     */
    public static function init($debug = false) {
        self::$debug = $debug;
        
        // Registrar funciones de manejo
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Manejar errores PHP
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $isError = in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);
        
        if (!($errno & error_reporting())) {
            return false;
        }
        
        $errorType = self::getErrorType($errno);
        $message = "$errorType: $errstr in $errfile:$errline";
        
        error_log($message);
        
        if (self::$debug && $isError) {
            echo '<pre>' . htmlspecialchars($message) . '</pre>';
        }
        
        return true;
    }
    
    /**
     * Manejar excepciones
     */
    public static function handleException($exception) {
        $message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
        
        error_log($message);
        
        if (self::$debug) {
            echo '<pre>' . htmlspecialchars($exception) . '</pre>';
        } else {
            http_response_code(500);
            echo 'Se ha producido un error. Por favor, contacte al administrador.';
        }
    }
    
    /**
     * Manejar errores fatales al apagar
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
    
    /**
     * Obtener tipo de error
     */
    private static function getErrorType($errno) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_DEPRECATED => 'DEPRECATED'
        ];
        
        return $types[$errno] ?? 'UNKNOWN ERROR';
    }
}
