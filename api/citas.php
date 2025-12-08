<?php
/**
 * api/citas.php
 * API de Citas Médicas
 * Maneja operaciones CRUD de citas
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
// Función para validar fecha
function validar_fecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}
// Función para validar hora
function validar_hora($hora) {
    $h = DateTime::createFromFormat('H:i:s', $hora);
    if (!$h) {
        $h = DateTime::createFromFormat('H:i', $hora);
    }
    return $h !== false;
}
// Función para verificar token CSRF
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
    // LISTAR CITAS
    case 'list':
        try {
            $fecha_inicio = sanitize_input($_GET['fecha_inicio'] ?? date('Y-m-d'));
            $fecha_fin = sanitize_input($_GET['fecha_fin'] ?? date('Y-m-d'));
            $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
            $id_paciente = isset($_GET['id_paciente']) ? (int)$_GET['id_paciente'] : null;
            $estado = sanitize_input($_GET['estado'] ?? '');
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            // Validar fechas
            if (!validar_fecha($fecha_inicio) || !validar_fecha($fecha_fin)) {
                sendResponse(false, 'Fechas inválidas', null, 400);
            }
            $where = "WHERE c.fecha_cita BETWEEN ? AND ?";
            $params = [$fecha_inicio, $fecha_fin];
            if ($id_medico) {
                $where .= " AND c.id_medico = ?";
                $params[] = $id_medico;
            }
            if ($id_paciente) {
                $where .= " AND c.id_paciente = ?";
                $params[] = $id_paciente;
            }
            if ($estado) {
                $where .= " AND c.estado_cita = ?";
                $params[] = $estado;
            }
            // Contar total
            $count_query = "SELECT COUNT(*) FROM cita c $where";
            $stmt = $pdo->prepare($count_query);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            // Obtener citas con paginación
            $query = "
                SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.motivo_consulta,
                        c.tipo_cita, c.estado_cita, c.consultorio, c.costo_cita,
                        c.observaciones,
                        CONCAT(pp.nombres, ' ', pp.apellidos) as paciente_nombre,
                        pp.telefono as paciente_telefono,
                        pac.numero_historia_clinica,
                        CONCAT(pm.nombres, ' ', pm.apellidos) as medico_nombre,
                        e.nombre as especialidad,
                        c.fecha_registro
                FROM cita c
                JOIN paciente pac ON c.id_paciente = pac.id_paciente
                JOIN persona pp ON pac.id_paciente = pp.id_persona
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona pm ON per.id_personal = pm.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                $where
                ORDER BY c.fecha_cita DESC, c.hora_cita DESC
                LIMIT ? OFFSET ?
            ";
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $citas = $stmt->fetchAll();
            sendResponse(true, 'Citas obtenidas exitosamente', [
                'citas' => $citas,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            error_log("Error listando citas: " . $e->getMessage());
            sendResponse(false, 'Error al obtener citas', null, 500);
        }
        break;
    // OBTENER UNA CITA
    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de cita requerido', null, 400);
        }
        try {
            $stmt = $pdo->prepare("
                SELECT c.*,
                        CONCAT(pp.nombres, ' ', pp.apellidos) as paciente_nombre,
                        pac.numero_historia_clinica, pac.grupo_sanguineo,
                        pac.alergias, pac.enfermedades_cronicas,
                        pp.telefono as paciente_telefono, 
                        pp.email as paciente_email,
                        pp.fecha_nacimiento as paciente_fecha_nacimiento,
                        CONCAT(pm.nombres, ' ', pm.apellidos) as medico_nombre,
                        e.nombre as especialidad,
                        m.numero_colegiatura,
                        per.telefono as medico_telefono,
                        CONCAT(preg.nombres, ' ', preg.apellidos) as registrado_por_nombre
                FROM cita c
                JOIN paciente pac ON c.id_paciente = pac.id_paciente
                JOIN persona pp ON pac.id_paciente = pp.id_persona
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona pm ON per.id_personal = pm.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                LEFT JOIN personal preg_per ON c.registrado_por = preg_per.id_personal
                LEFT JOIN persona preg ON preg_per.id_personal = preg.id_persona
                WHERE c.id_cita = ?
            ");
            $stmt->execute([$id]);
            $cita = $stmt->fetch();
            if ($cita) {
                sendResponse(true, 'Cita encontrada', $cita);
            } else {
                sendResponse(false, 'Cita no encontrada', null, 404);
            }
        } catch (PDOException $e) {
            error_log("Error obteniendo cita: " . $e->getMessage());
            sendResponse(false, 'Error al obtener cita', null, 500);
        }
        break;
    // CREAR CITA
    case 'create':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        // Validar datos requeridos
        $required = ['id_paciente', 'id_medico', 'fecha_cita', 'hora_cita'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                sendResponse(false, "El campo $field es requerido", null, 400);
            }
        }
        // Validar tipos
        $id_paciente = (int)$_POST['id_paciente'];
        $id_medico = (int)$_POST['id_medico'];
        $fecha_cita = sanitize_input($_POST['fecha_cita']);
        $hora_cita = sanitize_input($_POST['hora_cita']);
        if (!validar_fecha($fecha_cita)) {
            sendResponse(false, 'Fecha de cita inválida', null, 400);
        }
        if (!validar_hora($hora_cita)) {
            sendResponse(false, 'Hora de cita inválida', null, 400);
        }
        // Validar que la fecha no sea en el pasado
        if (strtotime($fecha_cita) < strtotime(date('Y-m-d'))) {
            sendResponse(false, 'No se pueden agendar citas en fechas pasadas', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que el paciente exista y esté activo
            $stmt = $pdo->prepare("
                SELECT p.id_paciente, p.estado_paciente,
                        CONCAT(per.nombres, ' ', per.apellidos) as nombre
                FROM paciente p
                JOIN persona per ON p.id_paciente = per.id_persona
                WHERE p.id_paciente = ?
            ");
            $stmt->execute([$id_paciente]);
            $paciente = $stmt->fetch();
            if (!$paciente) {
                $pdo->rollBack();
                sendResponse(false, 'Paciente no encontrado', null, 404);
            }
            if ($paciente['estado_paciente'] !== 'activo') {
                $pdo->rollBack();
                sendResponse(false, 'El paciente no está activo', null, 400);
            }
            // Verificar que el médico exista y esté disponible
            $stmt = $pdo->prepare("
                SELECT m.id_medico, m.disponible_consulta, m.costo_consulta,
                        e.nombre as especialidad,
                        CONCAT(per.nombres, ' ', per.apellidos) as nombre
                FROM medico m
                JOIN personal p ON m.id_medico = p.id_personal
                JOIN persona per ON p.id_personal = per.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                WHERE m.id_medico = ? AND p.estado_laboral = 'activo'
            ");
            $stmt->execute([$id_medico]);
            $medico = $stmt->fetch();
            if (!$medico) {
                $pdo->rollBack();
                sendResponse(false, 'Médico no encontrado o no disponible', null, 404);
            }
            if (!$medico['disponible_consulta']) {
                $pdo->rollBack();
                sendResponse(false, 'El médico no está disponible para consultas', null, 400);
            }
            // Verificar disponibilidad del horario
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as ocupado
                FROM cita 
                WHERE id_medico = ? 
                AND fecha_cita = ? 
                AND hora_cita = ?
                AND estado_cita NOT IN ('Cancelada', 'No asistió')
            ");
            $stmt->execute([$id_medico, $fecha_cita, $hora_cita]);
            $resultado = $stmt->fetch();
            if ($resultado['ocupado'] > 0) {
                $pdo->rollBack();
                sendResponse(false, 'El horario seleccionado no está disponible', null, 409);
            }
            // Verificar que el paciente no tenga otra cita a la misma hora
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as tiene_cita
                FROM cita 
                WHERE id_paciente = ? 
                AND fecha_cita = ? 
                AND hora_cita = ?
                AND estado_cita NOT IN ('Cancelada', 'No asistió')
            ");
            $stmt->execute([$id_paciente, $fecha_cita, $hora_cita]);
            $resultado = $stmt->fetch();
            if ($resultado['tiene_cita'] > 0) {
                $pdo->rollBack();
                sendResponse(false, 'El paciente ya tiene una cita en este horario', null, 409);
            }
            // Determinar costo
            $costo_cita = isset($_POST['costo_cita']) ? 
                        (float)$_POST['costo_cita'] : 
                        (float)$medico['costo_consulta'];
            // Insertar cita
            $stmt = $pdo->prepare("
                INSERT INTO cita (
                    id_paciente, id_medico, fecha_cita, hora_cita,
                    motivo_consulta, tipo_cita, estado_cita, consultorio,
                    costo_cita, observaciones, registrado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_paciente,
                $id_medico,
                $fecha_cita,
                $hora_cita,
                sanitize_input($_POST['motivo_consulta'] ?? ''),
                sanitize_input($_POST['tipo_cita'] ?? 'Primera vez'),
                sanitize_input($_POST['estado_cita'] ?? 'Programada'),
                sanitize_input($_POST['consultorio'] ?? ''),
                $costo_cita,
                sanitize_input($_POST['observaciones'] ?? ''),
                $_SESSION['user_id']
            ]);
            $id_cita = $pdo->lastInsertId();
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (
                    id_usuario, accion, tabla_afectada, registro_id,
                    descripcion, ip_address, resultado, criticidad
                ) VALUES (?, 'INSERT', 'cita', ?, ?, ?, 'Éxito', 'Baja')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id_cita,
                "Cita creada: Paciente '{$paciente['nombre']}' con Dr(a). '{$medico['nombre']}' - {$medico['especialidad']} el $fecha_cita a las $hora_cita",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Cita creada exitosamente', [
                'id_cita' => $id_cita,
                'fecha_cita' => $fecha_cita,
                'hora_cita' => $hora_cita,
                'paciente' => $paciente['nombre'],
                'medico' => $medico['nombre'],
                'especialidad' => $medico['especialidad']
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creando cita: " . $e->getMessage());
            sendResponse(false, 'Error al crear cita. Intente nuevamente.', null, 500);
        }
        break;
    // ACTUALIZAR CITA
    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id_cita']) ? (int)$_POST['id_cita'] : (int)($_GET['id'] ?? 0);
        if (empty($id)) {
            sendResponse(false, 'ID de cita requerido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que la cita exista
            $stmt = $pdo->prepare("SELECT * FROM cita WHERE id_cita = ?");
            $stmt->execute([$id]);
            $cita_actual = $stmt->fetch();
            if (!$cita_actual) {
                $pdo->rollBack();
                sendResponse(false, 'Cita no encontrada', null, 404);
            }
            // No permitir actualizar citas ya atendidas
            if ($cita_actual['estado_cita'] === 'Atendida') {
                $pdo->rollBack();
                sendResponse(false, 'No se puede modificar una cita ya atendida', null, 400);
            }
            $fecha_cita = sanitize_input($_POST['fecha_cita'] ?? $cita_actual['fecha_cita']);
            $hora_cita = sanitize_input($_POST['hora_cita'] ?? $cita_actual['hora_cita']);
            if (!validar_fecha($fecha_cita) || !validar_hora($hora_cita)) {
                $pdo->rollBack();
                sendResponse(false, 'Fecha u hora inválidas', null, 400);
            }
            // Si cambia fecha u hora, verificar disponibilidad
            if ($fecha_cita != $cita_actual['fecha_cita'] || $hora_cita != $cita_actual['hora_cita']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as ocupado
                    FROM cita 
                    WHERE id_medico = ? 
                    AND fecha_cita = ? 
                    AND hora_cita = ?
                    AND estado_cita NOT IN ('Cancelada', 'No asistió')
                    AND id_cita != ?
                ");
                $stmt->execute([
                    $cita_actual['id_medico'],
                    $fecha_cita,
                    $hora_cita,
                    $id
                ]);
                $resultado = $stmt->fetch();
                if ($resultado['ocupado'] > 0) {
                    $pdo->rollBack();
                    sendResponse(false, 'El nuevo horario no está disponible', null, 409);
                }
            }
            // Actualizar cita
            $stmt = $pdo->prepare("
                UPDATE cita 
                SET fecha_cita = ?, 
                    hora_cita = ?, 
                    motivo_consulta = ?,
                    tipo_cita = ?, 
                    estado_cita = ?, 
                    consultorio = ?,
                    observaciones = ?
                WHERE id_cita = ?
            ");
            $stmt->execute([
                $fecha_cita,
                $hora_cita,
                sanitize_input($_POST['motivo_consulta'] ?? $cita_actual['motivo_consulta']),
                sanitize_input($_POST['tipo_cita'] ?? $cita_actual['tipo_cita']),
                sanitize_input($_POST['estado_cita'] ?? $cita_actual['estado_cita']),
                sanitize_input($_POST['consultorio'] ?? $cita_actual['consultorio']),
                sanitize_input($_POST['observaciones'] ?? $cita_actual['observaciones']),
                $id
            ]);
            // Registrar cambios en auditoría
            $cambios = [];
            if ($fecha_cita != $cita_actual['fecha_cita']) {
                $cambios[] = "Fecha: {$cita_actual['fecha_cita']} → $fecha_cita";
            }
            if ($hora_cita != $cita_actual['hora_cita']) {
                $cambios[] = "Hora: {$cita_actual['hora_cita']} → $hora_cita";
            }
            $descripcion_cambios = empty($cambios) ? 
                                    'Cita actualizada' : 
                                    'Cita actualizada: ' . implode(', ', $cambios);
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (
                    id_usuario, accion, tabla_afectada, registro_id,
                    descripcion, ip_address, resultado
                ) VALUES (?, 'UPDATE', 'cita', ?, ?, ?, 'Éxito')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id,
                $descripcion_cambios,
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Cita actualizada exitosamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error actualizando cita: " . $e->getMessage());
            sendResponse(false, 'Error al actualizar cita', null, 500);
        }
        break;
    // CANCELAR CITA
    case 'cancel':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id_cita']) ? (int)$_POST['id_cita'] : 0;
        $motivo = sanitize_input($_POST['motivo_cancelacion'] ?? 'No especificado');
        if (empty($id)) {
            sendResponse(false, 'ID de cita requerido', null, 400);
        }
        if (empty(trim($motivo)) || $motivo === 'No especificado') {
            sendResponse(false, 'Debe especificar el motivo de cancelación', null, 400);
        }
        try {
            $pdo->beginTransaction();
            // Verificar que la cita exista y no esté ya cancelada
            $stmt = $pdo->prepare("SELECT estado_cita FROM cita WHERE id_cita = ?");
            $stmt->execute([$id]);
            $cita = $stmt->fetch();
            if (!$cita) {
                $pdo->rollBack();
                sendResponse(false, 'Cita no encontrada', null, 404);
            }
            if ($cita['estado_cita'] === 'Cancelada') {
                $pdo->rollBack();
                sendResponse(false, 'La cita ya está cancelada', null, 400);
            }
            if ($cita['estado_cita'] === 'Atendida') {
                $pdo->rollBack();
                sendResponse(false, 'No se puede cancelar una cita ya atendida', null, 400);
            }
            // Cancelar cita
            $stmt = $pdo->prepare("
                UPDATE cita 
                SET estado_cita = 'Cancelada',
                    fecha_cancelacion = NOW(),
                    motivo_cancelacion = ?
                WHERE id_cita = ?
            ");
            $stmt->execute([$motivo, $id]);
            // Registrar en auditoría
            $stmt = $pdo->prepare("
                INSERT INTO log_auditoria (
                    id_usuario, accion, tabla_afectada, registro_id,
                    descripcion, ip_address, resultado, criticidad
                ) VALUES (?, 'UPDATE', 'cita', ?, ?, ?, 'Éxito', 'Media')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $id,
                "Cita cancelada. Motivo: $motivo",
                $_SERVER['REMOTE_ADDR']
            ]);
            $pdo->commit();
            sendResponse(true, 'Cita cancelada exitosamente');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error cancelando cita: " . $e->getMessage());
            sendResponse(false, 'Error al cancelar cita', null, 500);
        }
        break;
    // CONFIRMAR CITA
    case 'confirm':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id_cita']) ? (int)$_POST['id_cita'] : 0;
        if (empty($id)) {
            sendResponse(false, 'ID de cita requerido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE cita 
                SET estado_cita = 'Confirmada'
                WHERE id_cita = ?
                AND estado_cita = 'Programada'
            ");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                // Registrar confirmación
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (
                        id_usuario, accion, tabla_afectada, registro_id,
                        descripcion, ip_address, resultado
                    ) VALUES (?, 'UPDATE', 'cita', ?, 'Cita confirmada', ?, 'Éxito')
                ");
                $stmt->execute([$_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR']]);
                $pdo->commit();
                sendResponse(true, 'Cita confirmada exitosamente');
            } else {
                $pdo->rollBack();
                sendResponse(false, 'No se pudo confirmar la cita. Verifique el estado.', null, 400);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error confirmando cita: " . $e->getMessage());
            sendResponse(false, 'Error al confirmar cita', null, 500);
        }
        break;
    // OBTENER HORARIOS DISPONIBLES
    case 'available_slots':
        $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : 0;
        $fecha = sanitize_input($_GET['fecha'] ?? date('Y-m-d'));
        if (empty($id_medico)) {
            sendResponse(false, 'ID de médico requerido', null, 400);
        }
        if (!validar_fecha($fecha)) {
            sendResponse(false, 'Fecha inválida', null, 400);
        }
        try {
            // Obtener día de la semana en español
            $dias_map = [
                1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 
                4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
            ];
            $dia_numero = (int)date('N', strtotime($fecha));
            $dia = $dias_map[$dia_numero];
            // Obtener horario del médico
            $stmt = $pdo->prepare("
                SELECT hora_inicio, hora_fin, duracion_cita, consultorio, cupo_maximo
                FROM horario_medico
                WHERE id_medico = ? AND dia_semana = ? AND activo = 1
            ");
            $stmt->execute([$id_medico, $dia]);
            $horario = $stmt->fetch();
            if (!$horario) {
                sendResponse(true, 'No hay horarios disponibles para este día', [
                    'slots' => [],
                    'mensaje' => 'El médico no atiende este día'
                ]);
            }
            // Obtener todas las citas ocupadas de una vez (optimización)
            $stmt = $pdo->prepare("
                SELECT hora_cita 
                FROM cita
                WHERE id_medico = ? AND fecha_cita = ?
                AND estado_cita NOT IN ('Cancelada', 'No asistió')
            ");
            $stmt->execute([$id_medico, $fecha]);
            $ocupados = array_column($stmt->fetchAll(), 'hora_cita');
            // Generar slots de tiempo
            $inicio = strtotime($horario['hora_inicio']);
            $fin = strtotime($horario['hora_fin']);
            $duracion = ($horario['duracion_cita'] ?? 30) * 60; // Convertir a segundos
            $slots = [];
            $current = $inicio;
            while ($current < $fin) {
                $hora = date('H:i:s', $current);
                $hora_formatted = date('H:i', $current);
                $ocupado = in_array($hora, $ocupados) || in_array($hora_formatted . ':00', $ocupados);
                $slots[] = [
                    'hora' => $hora,
                    'hora_formatted' => date('h:i A', $current),
                    'disponible' => !$ocupado
                ];
                $current += $duracion;
            }
            sendResponse(true, 'Horarios obtenidos exitosamente', [
                'slots' => $slots,
                'consultorio' => $horario['consultorio'],
                'fecha' => $fecha,
                'dia' => $dia,
                'cupo_maximo' => $horario['cupo_maximo']
            ]);
        } catch (PDOException $e) {
            error_log("Error obteniendo horarios: " . $e->getMessage());
            sendResponse(false, 'Error al obtener horarios', null, 500);
        }
        break;
    // ESTADÍSTICAS DE CITAS
    case 'stats':
        try {
            $fecha_inicio = sanitize_input($_GET['fecha_inicio'] ?? date('Y-m-01'));
            $fecha_fin = sanitize_input($_GET['fecha_fin'] ?? date('Y-m-t'));
            $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
            if (!validar_fecha($fecha_inicio) || !validar_fecha($fecha_fin)) {
                sendResponse(false, 'Fechas inválidas', null, 400);
            }
            $where = "WHERE fecha_cita BETWEEN ? AND ?";
            $params = [$fecha_inicio, $fecha_fin];
            if ($id_medico) {
                $where .= " AND id_medico = ?";
                $params[] = $id_medico;
            }
            // Estadísticas generales
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado_cita = 'Programada' THEN 1 ELSE 0 END) as programadas,
                    SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) as confirmadas,
                    SUM(CASE WHEN estado_cita = 'En espera' THEN 1 ELSE 0 END) as en_espera,
                    SUM(CASE WHEN estado_cita = 'Atendida' THEN 1 ELSE 0 END) as atendidas,
                    SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN estado_cita = 'No asistió' THEN 1 ELSE 0 END) as no_asistio,
                    ROUND(AVG(costo_cita), 2) as costo_promedio,
                    SUM(costo_cita) as ingreso_total
                FROM cita
                $where
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch();
            // Citas por tipo
            $stmt = $pdo->prepare("
                SELECT tipo_cita, COUNT(*) as cantidad
                FROM cita
                $where
                GROUP BY tipo_cita
            ");
            $stmt->execute($params);
            $por_tipo = $stmt->fetchAll();
            // Top 5 médicos con más citas
            $stmt = $pdo->prepare("
                SELECT m.id_medico,
                        CONCAT(p.nombres, ' ', p.apellidos) as medico,
                        e.nombre as especialidad,
                        COUNT(*) as total_citas
                FROM cita c
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona p ON per.id_personal = p.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                $where
                GROUP BY m.id_medico
                ORDER BY total_citas DESC
                LIMIT 5
            ");
            $stmt->execute($params);
            $top_medicos = $stmt->fetchAll();
            sendResponse(true, 'Estadísticas obtenidas', [
                'general' => $stats,
                'por_tipo' => $por_tipo,
                'top_medicos' => $top_medicos,
                'periodo' => [
                    'inicio' => $fecha_inicio,
                    'fin' => $fecha_fin
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            sendResponse(false, 'Error al obtener estadísticas', null, 500);
        }
        break;
    // CITAS DEL DÍA
    case 'today':
        try {
            $fecha = date('Y-m-d');
            $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
            $where = "WHERE c.fecha_cita = ?";
            $params = [$fecha];
            if ($id_medico) {
                $where .= " AND c.id_medico = ?";
                $params[] = $id_medico;
            }
            $query = "
                SELECT c.id_cita, c.hora_cita, c.estado_cita,
                        c.tipo_cita, c.consultorio,
                        CONCAT(pp.nombres, ' ', pp.apellidos) as paciente,
                        pac.numero_historia_clinica,
                        CONCAT(pm.nombres, ' ', pm.apellidos) as medico,
                        e.nombre as especialidad
                FROM cita c
                JOIN paciente pac ON c.id_paciente = pac.id_paciente
                JOIN persona pp ON pac.id_paciente = pp.id_persona
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona pm ON per.id_personal = pm.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                $where
                AND c.estado_cita NOT IN ('Cancelada')
                ORDER BY c.hora_cita ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $citas = $stmt->fetchAll();
            sendResponse(true, 'Citas del día obtenidas', [
                'fecha' => $fecha,
                'total' => count($citas),
                'citas' => $citas
            ]);
        } catch (PDOException $e) {
            error_log("Error obteniendo citas del día: " . $e->getMessage());
            sendResponse(false, 'Error al obtener citas', null, 500);
        }
        break;
    // MARCAR ASISTENCIA
    case 'mark_attendance':
        if ($method !== 'POST') {
            sendResponse(false, 'Método no permitido', null, 405);
        }
        $id = isset($_POST['id_cita']) ? (int)$_POST['id_cita'] : 0;
        $asistio = isset($_POST['asistio']) ? (bool)$_POST['asistio'] : true;
        if (empty($id)) {
            sendResponse(false, 'ID de cita requerido', null, 400);
        }
        try {
            $pdo->beginTransaction();
            $nuevo_estado = $asistio ? 'En espera' : 'No asistió';
            $stmt = $pdo->prepare("
                UPDATE cita 
                SET estado_cita = ?
                WHERE id_cita = ?
                AND estado_cita IN ('Programada', 'Confirmada')
            ");
            $stmt->execute([$nuevo_estado, $id]);
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_auditoria (
                        id_usuario, accion, tabla_afectada, registro_id,
                        descripcion, ip_address, resultado
                    ) VALUES (?, 'UPDATE', 'cita', ?, ?, ?, 'Éxito')
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    "Asistencia marcada: " . ($asistio ? 'Paciente presente' : 'Paciente no asistió'),
                    $_SERVER['REMOTE_ADDR']
                ]);
                $pdo->commit();
                sendResponse(true, 'Asistencia registrada exitosamente');
            } else {
                $pdo->rollBack();
                sendResponse(false, 'No se pudo registrar la asistencia', null, 400);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error marcando asistencia: " . $e->getMessage());
            sendResponse(false, 'Error al registrar asistencia', null, 500);
        }
        break;
    // BUSCAR CITAS
    case 'search':
        $query = sanitize_input($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            sendResponse(false, 'Ingrese al menos 2 caracteres', null, 400);
        }
        try {
            $searchParam = "%$query%";
            $stmt = $pdo->prepare("
                SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita,
                        CONCAT(pp.nombres, ' ', pp.apellidos) as paciente,
                        pac.numero_historia_clinica,
                        CONCAT(pm.nombres, ' ', pm.apellidos) as medico,
                        e.nombre as especialidad
                FROM cita c
                JOIN paciente pac ON c.id_paciente = pac.id_paciente
                JOIN persona pp ON pac.id_paciente = pp.id_persona
                JOIN medico m ON c.id_medico = m.id_medico
                JOIN personal per ON m.id_medico = per.id_personal
                JOIN persona pm ON per.id_personal = pm.id_persona
                JOIN especialidad e ON m.id_especialidad = e.id_especialidad
                WHERE pp.nombres LIKE ? 
                OR pp.apellidos LIKE ?
                OR pac.numero_historia_clinica LIKE ?
                OR pm.nombres LIKE ?
                OR pm.apellidos LIKE ?
                ORDER BY c.fecha_cita DESC, c.hora_cita DESC
                LIMIT 20
            ");
            $stmt->execute([
                $searchParam, $searchParam, $searchParam,
                $searchParam, $searchParam
            ]);
            $resultados = $stmt->fetchAll();
            sendResponse(true, 'Búsqueda completada', [
                'total' => count($resultados),
                'resultados' => $resultados
            ]);
        } catch (PDOException $e) {
            error_log("Error buscando citas: " . $e->getMessage());
            sendResponse(false, 'Error en la búsqueda', null, 500);
        }
        break;
    // ACCIÓN NO VÁLIDA
    default:
        sendResponse(false, 'Acción no válida', null, 400);
}