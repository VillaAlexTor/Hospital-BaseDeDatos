<?php
/**
 * modules/dashboard/paciente.php
 * Dashboard específico para Pacientes - LIMPIO
 */

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

$page_title = "Mi Portal de Salud";

// Verificar que sea paciente
if ($_SESSION['rol'] !== 'Paciente') {
    $_SESSION['warning_message'] = 'Acceso no autorizado';
    header('Location: index.php');
    exit;
}

// Obtener ID del paciente logueado
$stmt = $pdo->prepare("
    SELECT pac.id_paciente, pac.numero_historia_clinica, pac.grupo_sanguineo, pac.factor_rh
    FROM paciente pac
    INNER JOIN persona per ON pac.id_paciente = per.id_persona
    INNER JOIN usuario u ON per.id_persona = u.id_persona
    WHERE u.id_usuario = ?
");
$stmt->execute([$_SESSION['user_id']]);
$paciente_info = $stmt->fetch();

if (!$paciente_info) {
    die('<div class="alert alert-danger">No se encontró información del paciente</div>');
}

$id_paciente = $paciente_info['id_paciente'];

// ==================== INFORMACIÓN DEL PACIENTE ====================
$stmt = $pdo->prepare("
    SELECT 
        per.nombres,
        per.apellidos,
        per.fecha_nacimiento,
        per.genero,
        per.telefono,
        per.email,
        per.direccion,
        per.ciudad,
        pac.alergias,
        pac.enfermedades_cronicas,
        pac.seguro_medico,
        pac.fecha_primera_consulta
    FROM persona per
    INNER JOIN paciente pac ON per.id_persona = pac.id_paciente
    WHERE pac.id_paciente = ?
");
$stmt->execute([$id_paciente]);
$datos_personales = $stmt->fetch();

// Calcular edad
$fecha_nac = !empty($datos_personales['fecha_nacimiento']) ? decrypt_data($datos_personales['fecha_nacimiento']) : null;
$edad = '';
if ($fecha_nac) {
    $fecha_nac_obj = new DateTime($fecha_nac);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac_obj)->y . ' años';
}

// ==================== ESTADÍSTICAS ====================

// Próxima cita
$stmt = $pdo->prepare("
    SELECT 
        c.id_cita,
        c.fecha_cita,
        c.hora_cita,
        c.estado_cita,
        c.tipo_cita,
        c.motivo_consulta,
        c.consultorio,
        per.nombres as medico_nombres,
        per.apellidos as medico_apellidos,
        e.nombre as especialidad
    FROM cita c
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE c.id_paciente = ?
    AND c.fecha_cita >= CURDATE()
    AND c.estado_cita NOT IN ('Cancelada', 'Atendida', 'No asistió')
    ORDER BY c.fecha_cita, c.hora_cita
    LIMIT 1
");
$stmt->execute([$id_paciente]);
$proxima_cita = $stmt->fetch();

// Total de consultas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_paciente = ?");
$stmt->execute([$id_paciente]);
$total_consultas = $stmt->fetchColumn();

// Citas este año
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cita 
    WHERE id_paciente = ? 
    AND YEAR(fecha_cita) = YEAR(CURDATE())
");
$stmt->execute([$id_paciente]);
$citas_este_anio = $stmt->fetchColumn();

// ==================== ÚLTIMAS CONSULTAS ====================
$stmt = $pdo->prepare("
    SELECT 
        c.fecha_hora_atencion,
        c.diagnostico,
        c.proxima_cita,
        per.nombres as medico_nombres,
        per.apellidos as medico_apellidos,
        e.nombre as especialidad
    FROM consulta c
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE c.id_paciente = ?
    ORDER BY c.fecha_hora_atencion DESC
    LIMIT 5
");
$stmt->execute([$id_paciente]);
$ultimas_consultas = $stmt->fetchAll();

// ==================== CITAS PROGRAMADAS ====================
$stmt = $pdo->prepare("
    SELECT 
        c.id_cita,
        c.fecha_cita,
        c.hora_cita,
        c.estado_cita,
        c.tipo_cita,
        c.consultorio,
        per.nombres as medico_nombres,
        per.apellidos as medico_apellidos,
        e.nombre as especialidad
    FROM cita c
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    INNER JOIN especialidad e ON m.id_especialidad = e.id_especialidad
    WHERE c.id_paciente = ?
    AND c.fecha_cita >= CURDATE()
    AND c.estado_cita NOT IN ('Cancelada', 'Atendida', 'No asistió')
    ORDER BY c.fecha_cita, c.hora_cita
    LIMIT 10
");
$stmt->execute([$id_paciente]);
$citas_programadas = $stmt->fetchAll();

// ==================== DOCUMENTOS RECIENTES ====================
$stmt = $pdo->prepare("
    SELECT 
        d.id_documento,
        d.tipo_documento,
        d.fecha_emision,
        d.estado,
        c.fecha_hora_atencion,
        per.nombres as medico_nombres,
        per.apellidos as medico_apellidos
    FROM documento_medico d
    INNER JOIN consulta c ON d.id_consulta = c.id_consulta
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN personal p ON m.id_medico = p.id_personal
    INNER JOIN persona per ON p.id_personal = per.id_persona
    WHERE c.id_paciente = ?
    ORDER BY d.fecha_emision DESC
    LIMIT 5
");
$stmt->execute([$id_paciente]);
$documentos_recientes = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<?php require_once '../../includes/sidebar.php'; ?>

<main class="container-fluid px-4">
    <!-- Encabezado de Bienvenida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg shadow-lg p-6">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-3xl font-bold mb-2">
                            <i class="fas fa-heart-pulse mr-3"></i>
                            ¡Bienvenido/a, <?php echo htmlspecialchars(decrypt_data($datos_personales['nombres']) ?? 'Usuario'); ?>!
                        </h1>
                        <p class="text-blue-100 text-lg">Tu Portal de Salud Personal</p>
                        <p class="text-blue-200 text-sm mt-2">
                            <i class="fas fa-calendar mr-2"></i>
                            <?php echo format_date_es('%A, %d de %B de %Y'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="bg-white bg-opacity-20 rounded-full w-32 h-32 mx-auto flex items-center justify-center">
                            <i class="fas fa-user-circle text-white text-8xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información Rápida -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <i class="fas fa-id-card text-blue-600 text-3xl mb-2"></i>
                <div class="text-sm text-gray-600">Historia Clínica</div>
                <div class="text-xl font-bold text-gray-800"><?php echo $paciente_info['numero_historia_clinica']; ?></div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <i class="fas fa-tint text-red-600 text-3xl mb-2"></i>
                <div class="text-sm text-gray-600">Grupo Sanguíneo</div>
                <div class="text-xl font-bold text-gray-800">
                    <?php echo $paciente_info['grupo_sanguineo'] ?: 'No registrado'; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <i class="fas fa-birthday-cake text-purple-600 text-3xl mb-2"></i>
                <div class="text-sm text-gray-600">Edad</div>
                <div class="text-xl font-bold text-gray-800"><?php echo $edad ?: 'No registrado'; ?></div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <i class="fas fa-stethoscope text-green-600 text-3xl mb-2"></i>
                <div class="text-sm text-gray-600">Total Consultas</div>
                <div class="text-xl font-bold text-gray-800"><?php echo $total_consultas; ?></div>
            </div>
        </div>
    </div>

    <!-- Próxima Cita Destacada -->
    <?php if ($proxima_cita): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold mb-3">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Tu Próxima Cita
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <div class="text-green-100 text-sm">Fecha y Hora</div>
                                <div class="text-2xl font-bold">
                                    <?php echo format_date_es('%d de %B', strtotime($proxima_cita['fecha_cita'])); ?>
                                </div>
                                <div class="text-xl">
                                    <?php echo substr($proxima_cita['hora_cita'], 0, 5); ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-green-100 text-sm">Médico</div>
                                <div class="text-lg font-semibold">
                                    Dr(a). <?php echo decrypt_data($proxima_cita['medico_apellidos']); ?>
                                </div>
                                <div class="text-sm text-green-100">
                                    <?php echo $proxima_cita['especialidad']; ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-green-100 text-sm">Consultorio</div>
                                <div class="text-lg font-semibold">
                                    <?php echo $proxima_cita['consultorio'] ?: 'Por asignar'; ?>
                                </div>
                                <div class="text-sm">
                                    <span class="px-2 py-1 bg-white bg-opacity-30 rounded">
                                        <?php echo $proxima_cita['tipo_cita']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($proxima_cita['motivo_consulta']): ?>
                        <div class="mt-4 bg-white bg-opacity-20 rounded-lg p-3">
                            <div class="text-green-100 text-sm mb-1">Motivo:</div>
                            <div class="text-white"><?php echo htmlspecialchars($proxima_cita['motivo_consulta']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-calendar-day text-white text-9xl opacity-20"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-yellow-600 text-2xl mr-3"></i>
                    <div>
                        <h4 class="font-bold text-yellow-800">No tienes citas programadas</h4>
                        <p class="text-yellow-700 text-sm mt-1">
                            Si necesitas agendar una consulta, contacta con recepción al teléfono del hospital.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenido Principal -->
    <div class="row">
        <!-- Columna Izquierda -->
        <div class="col-lg-8">
            <!-- Mis Citas Programadas -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                    Mis Citas Programadas
                </h3>
                
                <?php if (empty($citas_programadas)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-6xl mb-4"></i>
                    <p class="text-lg">No tienes citas programadas</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($citas_programadas as $cita): 
                        $color_estado = [
                            'Programada' => 'border-yellow-400 bg-yellow-50',
                            'Confirmada' => 'border-green-400 bg-green-50',
                            'En espera' => 'border-blue-400 bg-blue-50'
                        ];
                        $badge_estado = [
                            'Programada' => 'bg-yellow-100 text-yellow-800',
                            'Confirmada' => 'bg-green-100 text-green-800',
                            'En espera' => 'bg-blue-100 text-blue-800'
                        ];
                        $border = $color_estado[$cita['estado_cita']] ?? 'border-gray-400 bg-gray-50';
                        $badge = $badge_estado[$cita['estado_cita']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <div class="border-l-4 <?php echo $border; ?> p-4 rounded-r-lg hover:shadow-md transition">
                        <div class="flex justify-content-between align-items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="text-xl font-bold text-gray-800">
                                        <?php echo format_date_es('%d/%m/%Y', strtotime($cita['fecha_cita'])); ?> - 
                                        <?php echo substr($cita['hora_cita'], 0, 5); ?>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $badge; ?>">
                                        <?php echo $cita['estado_cita']; ?>
                                    </span>
                                </div>
                                
                                <div class="text-lg font-semibold text-gray-900 mb-2">
                                    Dr(a). <?php echo decrypt_data($cita['medico_nombres']) . ' ' . decrypt_data($cita['medico_apellidos']); ?>
                                </div>
                                
                                <div class="text-sm text-gray-600">
                                    <i class="fas fa-hospital mr-1"></i>
                                    <?php echo $cita['especialidad']; ?>
                                    <?php if ($cita['consultorio']): ?>
                                        | <i class="fas fa-door-open mr-1"></i><?php echo $cita['consultorio']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Historial de Consultas -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-history text-purple-600 mr-2"></i>
                    Mi Historial de Consultas
                </h3>
                
                <?php if (empty($ultimas_consultas)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-file-medical text-6xl mb-4"></i>
                    <p>Aún no tienes consultas registradas</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Médico</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Especialidad</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Próximo Control</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($ultimas_consultas as $consulta): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php echo date('d/m/Y', strtotime($consulta['fecha_hora_atencion'])); ?>
                                </td>
                                <td class="px-4 py-3">
                                    Dr(a). <?php echo decrypt_data($consulta['medico_apellidos']); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo $consulta['especialidad']; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($consulta['proxima_cita']): ?>
                                        <span class="text-green-600 font-semibold">
                                            <?php echo date('d/m/Y', strtotime($consulta['proxima_cita'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Sin programar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="col-lg-4">
            <!-- Mis Datos de Salud -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-notes-medical text-red-600 mr-2"></i>
                    Información de Salud
                </h3>
                
                <div class="space-y-3 text-sm">
                    <?php if ($datos_personales['alergias']): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="font-bold text-red-800 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Alergias
                        </div>
                        <div class="text-red-700">
                            <?php echo htmlspecialchars($datos_personales['alergias']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($datos_personales['enfermedades_cronicas']): ?>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                        <div class="font-bold text-orange-800 mb-1">
                            <i class="fas fa-heartbeat mr-1"></i>Condiciones Crónicas
                        </div>
                        <div class="text-orange-700">
                            <?php echo htmlspecialchars($datos_personales['enfermedades_cronicas']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($datos_personales['seguro_medico']): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <div class="font-bold text-blue-800 mb-1">
                            <i class="fas fa-shield-alt mr-1"></i>Seguro Médico
                        </div>
                        <div class="text-blue-700">
                            <?php echo htmlspecialchars($datos_personales['seguro_medico']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$datos_personales['alergias'] && !$datos_personales['enfermedades_cronicas']): ?>
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-info-circle text-3xl mb-2"></i>
                        <p class="text-sm">No hay información de salud registrada</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mis Documentos -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-file-medical-alt text-green-600 mr-2"></i>
                    Mis Documentos
                </h3>
                
                <?php if (empty($documentos_recientes)): ?>
                <div class="text-center text-gray-500 py-6">
                    <i class="fas fa-folder-open text-4xl mb-2"></i>
                    <p class="text-sm">No hay documentos</p>
                </div>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($documentos_recientes as $doc): 
                        $iconos = [
                            'Receta' => 'fa-prescription',
                            'Orden Examen' => 'fa-flask',
                            'Certificado' => 'fa-certificate',
                            'Informe' => 'fa-file-alt'
                        ];
                        $icon = $iconos[$doc['tipo_documento']] ?? 'fa-file';
                    ?>
                    <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-2">
                                <i class="fas <?php echo $icon; ?> text-blue-600 mt-1"></i>
                                <div>
                                    <div class="font-semibold text-sm text-gray-800">
                                        <?php echo $doc['tipo_documento']; ?>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php echo date('d/m/Y', strtotime($doc['fecha_emision'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Dr(a). <?php echo decrypt_data($doc['medico_apellidos']); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded">
                                <?php echo $doc['estado']; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Contacto de Emergencia -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-bold text-red-800 mb-3">
                    <i class="fas fa-ambulance mr-2"></i>
                    ¿Necesitas Ayuda?
                </h4>
                <p class="text-sm text-red-700 mb-3">
                    Si tienes una emergencia médica, llama inmediatamente:
                </p>
                <div class="text-center">
                    <a href="tel:911" class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition">
                        <i class="fas fa-phone mr-2"></i>
                        911 - Emergencias
                    </a>
                </div>
                <p class="text-xs text-red-600 mt-3 text-center">
                    Para citas o consultas regulares, contacta con recepción
                </p>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>