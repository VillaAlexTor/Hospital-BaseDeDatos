<?php
/**
 * api/debug-usuarios.php
 * Debug directo de la API
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<h1>Debug API - Simulando llamada real</h1><hr>";
// Simular headers
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'list';
$_GET['page'] = 1;
$_GET['search'] = '';
$_GET['rol'] = '';
$_GET['estado'] = '';
echo "<h2>Paso 1: Cargar config</h2>";
try {
    require_once __DIR__ . '/../includes/config.php';
    echo "✅ Config cargado<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    exit;
}
echo "<h2>Paso 2: Cargar auth-check</h2>";
try {
    require_once __DIR__ . '/../includes/auth-check.php';
    echo "✅ Auth-check cargado<br>";
    echo "Rol actual: " . ($_SESSION['rol'] ?? 'NO DEFINIDO') . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    exit;
}
echo "<h2>Paso 3: Verificar permisos</h2>";
if ($_SESSION['rol'] !== 'Administrador') {
    echo "❌ No eres administrador<br>";
    exit;
}
echo "✅ Eres administrador<br>";
echo "<h2>Paso 4: Verificar función decrypt_data</h2>";
if (!function_exists('decrypt_data')) {
    echo "⚠️ decrypt_data NO existe. Creando función temporal...<br>";
    function decrypt_data($data) {
        return $data; // Retornar sin descifrar por ahora
    }
} else {
    echo "✅ decrypt_data existe<br>";
}
echo "<h2>Paso 5: Ejecutar query</h2>";
try {
    $page = 1;
    $limit = 20;
    $offset = 0;
    $query = "
        SELECT 
            u.id_usuario,
            u.username,
            u.email_verificado,
            u.cuenta_bloqueada,
            u.estado,
            u.ultimo_acceso,
            u.fecha_creacion,
            per.nombres,
            per.apellidos,
            per.email,
            per.telefono,
            GROUP_CONCAT(DISTINCT r.nombre) as roles,
            GROUP_CONCAT(DISTINCT r.id_rol) as rol_ids
        FROM usuario u
        JOIN persona per ON u.id_persona = per.id_persona
        LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario AND ur.estado = 'activo'
        LEFT JOIN rol r ON ur.id_rol = r.id_rol
        WHERE 1=1
        GROUP BY u.id_usuario
        ORDER BY u.fecha_creacion DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Query ejecutado exitosamente<br>";
    echo "Usuarios encontrados: " . count($usuarios) . "<br><br>";
    echo "<h3>Procesando datos...</h3>";
    foreach ($usuarios as &$usuario) {
        echo "Usuario: {$usuario['username']}<br>";
        $usuario['nombres'] = decrypt_data($usuario['nombres']);
        $usuario['apellidos'] = decrypt_data($usuario['apellidos']);
        $usuario['email'] = decrypt_data($usuario['email']);
        $usuario['telefono'] = decrypt_data($usuario['telefono']);
    }
    echo "<h3>✅ Datos procesados correctamente</h3>";
    echo "<h2>Paso 6: Generar JSON</h2>";
    $response = [
        'success' => true,
        'message' => 'Usuarios obtenidos',
        'data' => [
            'usuarios' => $usuarios,
            'total' => count($usuarios),
            'page' => $page,
            'limit' => $limit,
            'total_pages' => 1
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Error al generar JSON: " . json_last_error_msg() . "<br>";
    } else {
        echo "✅ JSON generado correctamente<br>";
        echo "<pre>" . htmlspecialchars($json) . "</pre>";
    }
} catch (PDOException $e) {
    echo "❌ Error en query: " . $e->getMessage() . "<br>";
    echo "SQL: " . $query . "<br>";
}
echo "<hr>";
echo "<h2>✅ Debug completado</h2>";
echo "<p><a href='usuarios.php?action=list'>Probar API real</a></p>";
?>