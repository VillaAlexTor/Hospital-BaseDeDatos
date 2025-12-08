<?php
// test-usuarios-api.php
// Guarda este archivo en la raíz de HOSPITAL/ y accede vía: http://localhost/hospital/test-usuarios-api.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico de API de Usuarios</h2>";

// Test 1: Verificar archivos
echo "<h3>1. Verificación de archivos</h3>";
$archivos = [
    'api/usuarios.php',
    'config/database.php',
    'config/security.php',
    'includes/config.php'
];

foreach ($archivos as $archivo) {
    $existe = file_exists($archivo);
    $color = $existe ? 'green' : 'red';
    $icono = $existe ? '✅' : '❌';
    echo "<div style='color: $color'>$icono $archivo</div>";
}

// Test 2: Simular llamada a la API
echo "<h3>2. Simulación de API</h3>";

try {
    // Iniciar buffer para capturar errores
    ob_start();
    
    // Simular parámetros GET
    $_GET['action'] = 'list';
    $_GET['page'] = '1';
    $_GET['search'] = '';
    $_GET['rol'] = '';
    $_GET['estado'] = '';
    
    // Incluir el archivo de API
    include 'api/usuarios.php';
    
    $output = ob_get_clean();
    
    echo "<h4>Salida de la API:</h4>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Verificar si es JSON válido
    $json = json_decode($output);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div style='color: green'>✅ JSON válido</div>";
    } else {
        echo "<div style='color: red'>❌ JSON inválido: " . json_last_error_msg() . "</div>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
    echo "<strong>❌ Error capturado:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Test 3: Verificar conexión a BD
echo "<h3>3. Verificación de Base de Datos</h3>";

try {
    require_once 'config/database.php';
    
    if (isset($conn) || class_exists('Database')) {
        echo "<div style='color: green'>✅ Archivo de BD cargado</div>";
        
        // Intentar conectar
        if (isset($conn)) {
            $result = $conn->query("SELECT 1");
            if ($result) {
                echo "<div style='color: green'>✅ Conexión a BD exitosa</div>";
            }
        }
        
        // Verificar si existe la tabla usuario
        $tableCheck = $conn->query("SHOW TABLES LIKE 'usuario'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            echo "<div style='color: green'>✅ Tabla 'usuario' existe</div>";
            
            // Contar usuarios
            $countResult = $conn->query("SELECT COUNT(*) as total FROM usuario");
            if ($countResult) {
                $row = $countResult->fetch_assoc();
                echo "<div style='color: blue'>ℹ️ Total de usuarios: {$row['total']}</div>";
            }
        } else {
            echo "<div style='color: red'>❌ Tabla 'usuario' no existe</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red'>❌ Error de BD: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 4: Verificar permisos de PHP
echo "<h3>4. Configuración de PHP</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Log Errors: " . ini_get('log_errors') . "<br>";
echo "Error Log: " . ini_get('error_log') . "<br>";

// Test 5: Verificar error log de Apache/PHP
echo "<h3>5. Últimas líneas del error log</h3>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -10);
    echo "<pre style='background: #ffe6e6; padding: 10px; border: 1px solid red; max-height: 200px; overflow: auto;'>";
    echo htmlspecialchars(implode('', $lastLines));
    echo "</pre>";
} else {
    echo "<div style='color: orange'>⚠️ No se pudo acceder al error log</div>";
    echo "<div>Revisa: " . htmlspecialchars(php_ini_loaded_file()) . "</div>";
}

echo "<hr>";
echo "<p><strong>Siguiente paso:</strong> Revisa la salida de 'Simulación de API' para ver el error exacto.</p>";
?>