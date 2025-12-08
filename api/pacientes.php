<?php
/**
 * api/pacientes.php
 * API de Pacientes 
 * Maneja operaciones CRUD de pacientes
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
// Función para enviar respuesta JSON
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
// Función para sanitizar entrada
function sanitize_input($data) {
    if (is_null($data)) return null;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    return $data;
}
// Función para verificar token CSRF
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
// Función para validar email
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
// Función para validar fecha
function validar_fecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}
// Función para calcular edad
function calcular_edad($fecha_nacimiento) {
    $nacimiento = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    return $hoy->diff($nacimiento)->y;
}
// Obtener método y acción
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// Verificar CSRF token para métodos de modificación
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        sendResponse(false, 'Token CSRF inválido', null, 403);
    }
}
// Ejecutar acción
switch ($action) {
    // LISTAR PACIENTES
    case 'list':
        try {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            $search = sanitize_input($_GET['search'] ?? '');
            $estado = sanitize_input($_GET['estado'] ?? 'activo');
            $grupo_sanguineo = sanitize_input($_GET['grupo_sanguineo'] ?? '');
            $order_by = sanitize_input($_GET['order_by'] ?? 'apellidos');
            $order_dir = strtoupper($_GET['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            // Validar columnas de ordenamiento permitidas
            $columnas_permitidas = ['apellidos', 'nombres', 'fecha_nacimiento', 'fecha_primera_consulta'];
            if (!in_array($order_by, $columnas_permitidas)) {
                $order_by = 'apellidos';
            }
            // Construir query
            $where = "WHERE p.estado_paciente = ?";
            $params = [$estado];
            if (!empty($search)) {
                $where .= " AND (per.nombres LIKE ? OR per.apellidos LIKE ? OR p.numero_historia_clinica LIKE ? OR per.numero_documento LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            if (!empty($grupo_sanguineo)) {
                $where .= " AND p.grupo_sanguineo = ?";
                $params[] = $grupo_sanguineo;
            }
            // Contar total
            $countQuery = "SELECT COUNT(*) FROM paciente p 
                            JOIN persona per ON p.id_paciente = per.id_persona 
                            $where";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            // Obtener pacientes
            $query = "
                SELECT p.id_paciente, p.grupo_sanguineo, p.factor_rh,
                        p.numero_historia_clinica, p.estado_paciente,
                        p.seguro_medico, p.fecha_primera_consulta,
                        per.nombres, per.apellidos, per.fecha_nacimiento,
                        per.genero, per.telefono, per.email, per.ciudad,
                        per.tipo_documento, per.numero_documento,
                        TIMESTAMPDIFF(YEAR, per.fecha_nacimiento, CURDATE()) as edad,
                        (SELECT COUNT(*) FROM cita c WHERE c.id_paciente = p.id_paciente) as total_citas
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                $where
                ORDER BY per.$order_by $order_dir
                LIMIT ? OFFSET ?
            ";
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $pacientes = $stmt->fetchAll();
            // Desencriptar campos sensibles
            foreach ($pacientes as &$paciente) {
                $paciente['nombres'] = decrypt_data($paciente['nombres']);
                $paciente['apellidos'] = decrypt_data($paciente['apellidos']);
                $paciente['telefono'] = decrypt_data($paciente['telefono']);
                $paciente['email'] = decrypt_data($paciente['email']);
                $paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
                $paciente['fecha_nacimiento'] = decrypt_data($paciente['fecha_nacimiento']);
            }
            // Registrar acceso a datos
            if (!empty($pacientes)) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, 
                                                descripcion, ip_address, resultado)
                    VALUES (?, 'SELECT', 'paciente', ?, ?, 'Éxito')
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    "Listado de pacientes: $total registros consultados",
                    $_SERVER['REMOTE_ADDR']
                ]);
            }
            sendResponse(true, 'Pacientes obtenidos exitosamente', [
                'pacientes' => $pacientes,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            error_log("Error listando pacientes: " . $e->getMessage());
            sendResponse(false, 'Error al obtener pacientes', null, 500);
        }
        break;
    // OBTENER UN PACIENTE
    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, per.*,
                        p.id_paciente as id,
                        TIMESTAMPDIFF(YEAR, per.fecha_nacimiento, CURDATE()) as edad,
                        (SELECT COUNT(*) FROM cita WHERE id_paciente = p.id_paciente) as total_citas,
                        (SELECT COUNT(*) FROM consulta WHERE id_paciente = p.id_paciente) as total_consultas,
                        (SELECT MAX(fecha_cita) FROM cita WHERE id_paciente = p.id_paciente) as ultima_cita
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                WHERE p.id_paciente = ?
            ");
            $stmt->execute([$id]);
            $paciente = $stmt->fetch();
            if ($paciente) {
                // Desencriptar campos sensibles
                $paciente['nombres'] = decrypt_data($paciente['nombres']);
                $paciente['apellidos'] = decrypt_data($paciente['apellidos']);
                $paciente['telefono'] = decrypt_data($paciente['telefono']);
                $paciente['email'] = decrypt_data($paciente['email']);
                $paciente['direccion'] = decrypt_data($paciente['direccion']);
                $paciente['ciudad'] = decrypt_data($paciente['ciudad']);
                $paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
                $paciente['fecha_nacimiento'] = decrypt_data($paciente['fecha_nacimiento']);
                $paciente['contacto_emergencia_nombre'] = decrypt_data($paciente['contacto_emergencia_nombre']);
                $paciente['contacto_emergencia_telefono'] = decrypt_data($paciente['contacto_emergencia_telefono']);
                $paciente['numero_poliza'] = decrypt_data($paciente['numero_poliza']);
                // Obtener historial clínico básico
                $stmt = $pdo->prepare("
                    SELECT id_historial, fecha_creacion, ultima_actualizacion
                    FROM historial_clinico
                    WHERE id_paciente = ?
                ");
                $stmt->execute([$id]);
                $paciente['historial_clinico'] = $stmt->fetch();
                // Registrar acceso a datos sensibles
                $stmt = $pdo->prepare("
                    INSERT INTO log_acceso_datos_sensibles (id_usuario, tabla_accedida, 
                                                            registro_id, tipo_acceso, ip_address)
                    VALUES (?, 'paciente', ?, 'Lectura', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR']]);
                sendResponse(true, 'Paciente encontrado', $paciente);
            } else {
                sendResponse(false, 'Paciente no encontrado', null, 404);
            }
        } catch (PDOException $e) {
            error_log("Error obteniendo paciente: " . $e->getMessage());
            sendResponse(false, 'Error al obtener paciente', null, 500);
        }
        break;
    // CREAR PACIENTE
    case 'create':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        // Validar datos requeridos
        $required = ['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 
                    'fecha_nacimiento', 'genero'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                sendResponse(false, "El campo $field es requerido", null, 400);
            }
        }
        // Validaciones adicionales
        if (!validar_fecha($_POST['fecha_nacimiento'])) {
            sendResponse(false, 'Fecha de nacimiento inválida', null, 400);
        }
        $edad = calcular_edad($_POST['fecha_nacimiento']);
        if ($edad > 120 || $edad < 0) {
            sendResponse(false, 'Fecha de nacimiento fuera de rango válido', null, 400);
        }
        if (!empty($_POST['email']) && !validar_email($_POST['email'])) {
            sendResponse(false, 'Email inválido', null, 400);
        }
        if (!in_array($_POST['genero'], ['M', 'F', 'Otro', 'Prefiero no decir'])) {
            sendResponse(false, 'Género inválido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar si el documento ya existe
            $stmt = $pdo->prepare("
                SELECT id_persona FROM persona 
                WHERE tipo_documento = ? AND numero_documento = ?
            ");
            $stmt->execute([
                $_POST['tipo_documento'], 
                encrypt_data($_POST['numero_documento'])
            ]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                sendResponse(false, 'Ya existe una persona con este documento', null, 400);
            }
            // Insertar en PERSONA
            $stmt = $pdo->prepare("
                INSERT INTO persona (tipo_documento, numero_documento, nombres, apellidos,
                                    fecha_nacimiento, genero, telefono, email, direccion,
                                    ciudad, pais, usuario_crea)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['tipo_documento'],
                encrypt_data($_POST['numero_documento']),
                encrypt_data($_POST['nombres']),
                encrypt_data($_POST['apellidos']),
                encrypt_data($_POST['fecha_nacimiento']),
                $_POST['genero'],
                encrypt_data($_POST['telefono'] ?? ''),
                encrypt_data($_POST['email'] ?? ''),
                encrypt_data($_POST['direccion'] ?? ''),
                encrypt_data($_POST['ciudad'] ?? ''),
                encrypt_data($_POST['pais'] ?? 'Bolivia'),
                $_SESSION['user_id']
            ]);
            $id_persona = $pdo->lastInsertId();
            // Generar número de historia clínica único
            $numero_historia = 'HC-' . date('Y') . '-' . str_pad($id_persona, 6, '0', STR_PAD_LEFT);
            // Insertar en PACIENTE
            $stmt = $pdo->prepare("
                INSERT INTO paciente (id_paciente, grupo_sanguineo, factor_rh,
                                    alergias, enfermedades_cronicas,
                                    contacto_emergencia_nombre, contacto_emergencia_telefono,
                                    contacto_emergencia_relacion, seguro_medico, numero_poliza,
                                    numero_historia_clinica, fecha_primera_consulta)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([
                $id_persona,
                $_POST['grupo_sanguineo'] ?? null,
                $_POST['factor_rh'] ?? null,
                encrypt_data($_POST['alergias'] ?? ''),
                encrypt_data($_POST['enfermedades_cronicas'] ?? ''),
                encrypt_data($_POST['contacto_emergencia_nombre'] ?? ''),
                encrypt_data($_POST['contacto_emergencia_telefono'] ?? ''),
                sanitize_input($_POST['contacto_emergencia_relacion'] ?? ''),
                sanitize_input($_POST['seguro_medico'] ?? ''),
                encrypt_data($_POST['numero_poliza'] ?? ''),
                $numero_historia
            ]);
            // Crear historial clínico inicial
            $stmt = $pdo->prepare("
                INSERT INTO historial_clinico (id_paciente, actualizado_por)
                VALUES (?, ?)
            ");
            $stmt->execute([$id_persona, $_SESSION['user_id']]);
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id, 
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'INSERT', 'paciente', ?, ?, ?, 'Éxito', 'Media')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_persona,
                "Paciente creado: {$_POST['nombres']} {$_POST['apellidos']}, HC: $numero_historia",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Paciente creado exitosamente', [
                'id_paciente' => $id_persona,
                'numero_historia_clinica' => $numero_historia,
                'nombres' => $_POST['nombres'],
                'apellidos' => $_POST['apellidos']
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creando paciente: " . $e->getMessage());
            sendResponse(false, 'Error al crear paciente. Intente nuevamente.', null, 500);
        }
        break;
    // ACTUALIZAR PACIENTE
    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id_paciente']) ? (int)$_POST['id_paciente'] : (int)($_GET['id'] ?? 0);
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        // Validaciones
        if (isset($_POST['email']) && !empty($_POST['email']) && !validar_email($_POST['email'])) {
            sendResponse(false, 'Email inválido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que el paciente existe
            $stmt = $pdo->prepare("SELECT id_paciente FROM paciente WHERE id_paciente = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                sendResponse(false, 'Paciente no encontrado', null, 404);
            }
            // Obtener valores anteriores para auditoría
            $stmt = $pdo->prepare("
                SELECT p.*, per.nombres, per.apellidos, per.telefono, per.email
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                WHERE p.id_paciente = ?
            ");
            $stmt->execute([$id]);
            $valores_anteriores = $stmt->fetch();
            // Actualizar PERSONA
            $stmt = $pdo->prepare("
                UPDATE persona 
                SET nombres = ?, apellidos = ?, telefono = ?, email = ?,
                    direccion = ?, ciudad = ?, pais = ?, 
                    fecha_modificacion = CURRENT_TIMESTAMP,
                    usuario_modifica = ?
                WHERE id_persona = ?
            ");
            $stmt->execute([
                encrypt_data($_POST['nombres'] ?? decrypt_data($valores_anteriores['nombres'])),
                encrypt_data($_POST['apellidos'] ?? decrypt_data($valores_anteriores['apellidos'])),
                encrypt_data($_POST['telefono'] ?? ''),
                encrypt_data($_POST['email'] ?? ''),
                encrypt_data($_POST['direccion'] ?? ''),
                encrypt_data($_POST['ciudad'] ?? ''),
                encrypt_data($_POST['pais'] ?? 'Bolivia'),
                $_SESSION['user_id'],
                $id
            ]);
            // Actualizar PACIENTE
            $stmt = $pdo->prepare("
                UPDATE paciente 
                SET grupo_sanguineo = ?, factor_rh = ?,
                    alergias = ?, enfermedades_cronicas = ?,
                    contacto_emergencia_nombre = ?, contacto_emergencia_telefono = ?,
                    contacto_emergencia_relacion = ?, seguro_medico = ?, numero_poliza = ?
                WHERE id_paciente = ?
            ");
            $stmt->execute([
                $_POST['grupo_sanguineo'] ?? $valores_anteriores['grupo_sanguineo'],
                $_POST['factor_rh'] ?? $valores_anteriores['factor_rh'],
                encrypt_data($_POST['alergias'] ?? ''),
                encrypt_data($_POST['enfermedades_cronicas'] ?? ''),
                encrypt_data($_POST['contacto_emergencia_nombre'] ?? ''),
                encrypt_data($_POST['contacto_emergencia_telefono'] ?? ''),
                sanitize_input($_POST['contacto_emergencia_relacion'] ?? ''),
                sanitize_input($_POST['seguro_medico'] ?? ''),
                encrypt_data($_POST['numero_poliza'] ?? ''),
                $id
            ]);
            // Registrar cambios en auditoría
            $cambios = [];
            if (isset($_POST['nombres']) && $_POST['nombres'] != decrypt_data($valores_anteriores['nombres'])) {
                $cambios[] = 'Nombres';
            }
            if (isset($_POST['apellidos']) && $_POST['apellidos'] != decrypt_data($valores_anteriores['apellidos'])) {
                $cambios[] = 'Apellidos';
            }
            if (isset($_POST['telefono'])) $cambios[] = 'Teléfono';
            if (isset($_POST['email'])) $cambios[] = 'Email';
            $descripcion_cambios = empty($cambios) ? 
                                    'Paciente actualizado' : 
                                    'Campos actualizados: ' . implode(', ', $cambios);
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id,
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'UPDATE', 'paciente', ?, ?, ?, 'Éxito', 'Media')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id,
                $descripcion_cambios,
                $_SERVER['REMOTE_ADDR']
            ]);
            // Registrar acceso a datos sensibles
            $stmt = $pdo->prepare("
                INSERT INTO log_acceso_datos_sensibles (id_usuario, tabla_accedida, 
                                                        registro_id, tipo_acceso, ip_address)
                VALUES (?, 'paciente', ?, 'Escritura', ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR']]);
            $pdo->commit();
            sendResponse(true, 'Paciente actualizado exitosamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error actualizando paciente: " . $e->getMessage());
            sendResponse(false, 'Error al actualizar paciente', null, 500);
        }
        break;
    // ELIMINAR/INACTIVAR PACIENTE
    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);
        $motivo = sanitize_input($_POST['motivo'] ?? 'No especificado');
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que no tenga citas activas
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as citas_activas
                FROM cita
                WHERE id_paciente = ?
                AND estado_cita IN ('Programada', 'Confirmada', 'En espera')
                AND fecha_cita >= CURDATE()
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ($result['citas_activas'] > 0) {
                $pdo->rollBack();
                sendResponse(false, 'No se puede inactivar: el paciente tiene citas activas', null, 400);
            }
            // Verificar que no esté internado
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as internado
                FROM internamiento
                WHERE id_paciente = ?
                AND estado_internamiento = 'En curso'
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ($result['internado'] > 0) {
                $pdo->rollBack();
                sendResponse(false, 'No se puede inactivar: el paciente está internado', null, 400);
            }
            // Inactivar paciente (no eliminación física)
            $stmt = $pdo->prepare("
                UPDATE paciente 
                SET estado_paciente = 'inactivo'
                WHERE id_paciente = ?
            ");
            $stmt->execute([$id]);
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id,
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'DELETE', 'paciente', ?, ?, ?, 'Éxito', 'Alta')
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $id, 
                "Paciente inactivado. Motivo: $motivo",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Paciente inactivado exitosamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error inactivando paciente: " . $e->getMessage());
            sendResponse(false, 'Error al inactivar paciente', null, 500);
        }
        break;
    // REACTIVAR PACIENTE
    case 'reactivate':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE paciente 
                SET estado_paciente = 'activo'
                WHERE id_paciente = ? AND estado_paciente = 'inactivo'
            ");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                // Registrar en auditoría
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, registro_id,
                                                descripcion, ip_address, resultado)
                    VALUES (?, 'UPDATE', 'paciente', ?, 'Paciente reactivado', ?, 'Éxito')
                ");
                $stmt->execute([$_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR']]);
                sendResponse(true, 'Paciente reactivado exitosamente');
            } else {
                sendResponse(false, 'Paciente no encontrado o ya está activo', null, 404);
            }
        } catch (PDOException $e) {
            error_log("Error reactivando paciente: " . $e->getMessage());
            sendResponse(false, 'Error al reactivar paciente', null, 500);
        }
        break;
    // BUSCAR PACIENTES 
    case 'search':
        $query = sanitize_input($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            sendResponse(false, 'Ingrese al menos 2 caracteres', null, 400);
        }
        try {
            $searchParam = "%$query%";
            $stmt = $pdo->prepare("
                SELECT p.id_paciente, p.numero_historia_clinica,
                        per.nombres, per.apellidos, per.numero_documento,
                        per.fecha_nacimiento, per.genero,
                        p.grupo_sanguineo, per.telefono,
                        TIMESTAMPDIFF(YEAR, per.fecha_nacimiento, CURDATE()) as edad
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                WHERE p.estado_paciente = 'activo'
                AND (per.nombres LIKE ? OR per.apellidos LIKE ? 
                    OR per.numero_documento LIKE ? OR p.numero_historia_clinica LIKE ?)
                ORDER BY per.apellidos, per.nombres
                LIMIT 15
            ");
            $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
            $resultados = $stmt->fetchAll();
            // Desencriptar campos
            foreach ($resultados as &$resultado) {
                $resultado['nombres'] = decrypt_data($resultado['nombres']);
                $resultado['apellidos'] = decrypt_data($resultado['apellidos']);
                $resultado['telefono'] = decrypt_data($resultado['telefono']);
                $resultado['numero_documento'] = decrypt_data($resultado['numero_documento']);
                $resultado['fecha_nacimiento'] = decrypt_data($resultado['fecha_nacimiento']);
            }
            sendResponse(true, 'Búsqueda completada', [
                'total' => count($resultados),
                'resultados' => $resultados
            ]);
        } catch (PDOException $e) {
            error_log("Error buscando pacientes: " . $e->getMessage());
            sendResponse(false, 'Error en la búsqueda', null, 500);
        }
        break;
    // HISTORIAL MÉDICO DEL PACIENTE
    case 'historial':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        try {
            // Verificar que el paciente existe
            $stmt = $pdo->prepare("SELECT id_paciente FROM paciente WHERE id_paciente = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendResponse(false, 'Paciente no encontrado', null, 404);
            }
            // Obtener historial clínico
            $stmt = $pdo->prepare("
                SELECT h.*,
                        CONCAT(p.nombres, ' ', p.apellidos) as actualizado_por_nombre
                FROM historial_clinico h
                LEFT JOIN medico m ON h.actualizado_por = m.id_medico
                LEFT JOIN personal per ON m.id_medico = per.id_personal
                LEFT JOIN persona p ON per.id_personal = p.id_persona
                WHERE h.id_paciente = ?
            ");
            $stmt->execute([$id]);
            $historial = $stmt->fetch();
            // Obtener últimas consultas
            $stmt = $pdo->prepare("
                SELECT c.id_consulta, c.fecha_hora_atencion, c.tipo_consulta,
                        c.diagnostico, c.estado_consulta,
                        CONCAT(p.nombres, ' ', p.apellidos) as medico_nombre,
                        e.nombre as especialidad
                FROM consulta c
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona p ON per.id_personal = p.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                WHERE c.id_paciente = ?
                ORDER BY c.fecha_hora_atencion DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $consultas = $stmt->fetchAll();
            // Desencriptar diagnósticos
            foreach ($consultas as &$consulta) {
                $consulta['diagnostico'] = decrypt_data($consulta['diagnostico']);
                $consulta['medico_nombre'] = decrypt_data($consulta['medico_nombre']);
            }
            // Obtener alergias y enfermedades crónicas
            $stmt = $pdo->prepare("
                SELECT alergias, enfermedades_cronicas 
                FROM paciente 
                WHERE id_paciente = ?
            ");
            $stmt->execute([$id]);
            $condiciones = $stmt->fetch();
            if ($condiciones) {
                $condiciones['alergias'] = decrypt_data($condiciones['alergias']);
                $condiciones['enfermedades_cronicas'] = decrypt_data($condiciones['enfermedades_cronicas']);
            }
            // Registrar acceso
            $stmt = $pdo->prepare("
                INSERT INTO log_acceso_datos_sensibles (id_usuario, tabla_accedida, 
                                                        registro_id, tipo_acceso, ip_address)
                VALUES (?, 'historial_clinico', ?, 'Lectura', ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR']]);
            sendResponse(true, 'Historial médico obtenido', [
                'historial_clinico' => $historial,
                'consultas_recientes' => $consultas,
                'condiciones_medicas' => $condiciones
            ]);
        } catch (PDOException $e) {
            error_log("Error obteniendo historial: " . $e->getMessage());
            sendResponse(false, 'Error al obtener historial médico', null, 500);
        }
        break;
    // ESTADÍSTICAS DEL PACIENTE
    case 'stats':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de paciente requerido', null, 400);
        }
        try {
            // Estadísticas de citas
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_citas,
                    SUM(CASE WHEN estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas,
                    SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN estado_cita = 'No asistió' THEN 1 ELSE 0 END) as no_asistio,
                    MAX(fecha_cita) as ultima_cita,
                    MIN(fecha_cita) as primera_cita
                FROM cita
                WHERE id_paciente = ?
            ");
            $stmt->execute([$id]);
            $stats_citas = $stmt->fetch();
            // Estadísticas de consultas
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_consultas,
                    COUNT(DISTINCT id_medico) as medicos_diferentes,
                    MAX(fecha_hora_atencion) as ultima_consulta
                FROM consulta
                WHERE id_paciente = ?
            ");
            $stmt->execute([$id]);
            $stats_consultas = $stmt->fetch();
            // Internamientos
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_internamientos,
                    SUM(dias_hospitalizacion) as dias_totales_internado,
                    MAX(fecha_ingreso) as ultimo_internamiento
                FROM internamiento
                WHERE id_paciente = ?
            ");
            $stmt->execute([$id]);
            $stats_internamientos = $stmt->fetch();
            // Especialidades más visitadas
            $stmt = $pdo->prepare("
                SELECT e.nombre as especialidad, COUNT(*) as visitas
                FROM consulta c
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                WHERE c.id_paciente = ?
                GROUP BY e.id_especialidad
                ORDER BY visitas DESC
                LIMIT 5
            ");
            $stmt->execute([$id]);
            $especialidades = $stmt->fetchAll();
            sendResponse(true, 'Estadísticas obtenidas', [
                'citas' => $stats_citas,
                'consultas' => $stats_consultas,
                'internamientos' => $stats_internamientos,
                'especialidades_frecuentes' => $especialidades
            ]);
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            sendResponse(false, 'Error al obtener estadísticas', null, 500);
        }
        break;
    // ==========================================
    // VERIFICAR DOCUMENTO DUPLICADO
    // ==========================================
    case 'check_document':
        $tipo = sanitize_input($_GET['tipo'] ?? '');
        $numero = sanitize_input($_GET['numero'] ?? '');
        $excluir_id = isset($_GET['excluir_id']) ? (int)$_GET['excluir_id'] : 0;
        if (empty($tipo) || empty($numero)) {
            sendResponse(false, 'Tipo y número de documento requeridos', null, 400);
        }
        try {
            $query = "
                SELECT COUNT(*) as existe, id_persona
                FROM persona
                WHERE tipo_documento = ? AND numero_documento = ?
            ";
            $params = [$tipo, encrypt_data($numero)];
            if ($excluir_id > 0) {
                $query .= " AND id_persona != ?";
                $params[] = $excluir_id;
            }
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            sendResponse(true, 'Verificación completada', [
                'existe' => $result['existe'] > 0,
                'id_persona' => $result['id_persona'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error verificando documento: " . $e->getMessage());
            sendResponse(false, 'Error en la verificación', null, 500);
        }
        break;
    // EXPORTAR LISTA DE PACIENTES
    case 'export':
        try {
            $formato = sanitize_input($_GET['formato'] ?? 'csv');
            $estado = sanitize_input($_GET['estado'] ?? 'activo');
            if (!in_array($formato, ['csv', 'json'])) {
                sendResponse(false, 'Formato no soportado', null, 400);
            }
            // Obtener todos los pacientes
            $stmt = $pdo->prepare("
                SELECT p.numero_historia_clinica,
                        per.nombres, per.apellidos, per.numero_documento,
                        per.fecha_nacimiento, per.genero, per.telefono, per.email,
                        p.grupo_sanguineo, p.estado_paciente, p.seguro_medico
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                WHERE p.estado_paciente = ?
                ORDER BY per.apellidos, per.nombres
            ");
            $stmt->execute([$estado]);
            $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Desencriptar datos
            foreach ($pacientes as &$paciente) {
                $paciente['nombres'] = decrypt_data($paciente['nombres']);
                $paciente['apellidos'] = decrypt_data($paciente['apellidos']);
                $paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
                $paciente['telefono'] = decrypt_data($paciente['telefono']);
                $paciente['email'] = decrypt_data($paciente['email']);
                $paciente['fecha_nacimiento'] = decrypt_data($paciente['fecha_nacimiento']);
            }
            // Registrar exportación
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (id_usuario, accion, tabla_afectada, 
                                            descripcion, ip_address, resultado, criticidad)
                VALUES (?, 'EXPORT', 'paciente', ?, ?, 'Éxito', 'Alta')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Exportación de " . count($pacientes) . " pacientes en formato $formato",
                $_SERVER['REMOTE_ADDR']
            ]);
            if ($formato === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="pacientes_' . date('Y-m-d') . '.csv"');
                $output = fopen('php://output', 'w');
                // Encabezados
                fputcsv($output, array_keys($pacientes[0]));
                // Datos
                foreach ($pacientes as $paciente) {
                    fputcsv($output, $paciente);
                }
                fclose($output);
                exit();
            } else {
                sendResponse(true, 'Datos exportados', $pacientes);
            }
        } catch (PDOException $e) {
            error_log("Error exportando pacientes: " . $e->getMessage());
            sendResponse(false, 'Error al exportar datos', null, 500);
        }
        break;
    // OBTENER GRUPOS SANGUÍNEOS DISPONIBLES
    case 'blood_types':
        sendResponse(true, 'Grupos sanguíneos', [
            'tipos' => [
                ['value' => 'A+', 'label' => 'A+'],
                ['value' => 'A-', 'label' => 'A-'],
                ['value' => 'B+', 'label' => 'B+'],
                ['value' => 'B-', 'label' => 'B-'],
                ['value' => 'AB+', 'label' => 'AB+'],
                ['value' => 'AB-', 'label' => 'AB-'],
                ['value' => 'O+', 'label' => 'O+'],
                ['value' => 'O-', 'label' => 'O-']
            ]
        ]);
        break;
    // ACCIÓN NO VÁLIDA
    default:
        sendResponse(false, 'Acción no válida', null, 400);
}
?>