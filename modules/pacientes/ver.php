<?php
/**
 * modules/pacientes/ver.php
 * Vista detallada de paciente
 */

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (!isset($_SESSION['user_id'])) {
	header('Location: ../../index.php');
	exit();
}

$page_title = "Ver Paciente";
require_once '../../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
	echo '<div class="alert alert-warning">ID de paciente no proporcionado</div>';
	require_once '../../includes/footer.php';
	exit();
}

$id = (int) $_GET['id'];

// Obtener datos del paciente y persona
try {
	$stmt = $pdo->prepare("SELECT pac.*, per.* FROM paciente pac INNER JOIN persona per ON pac.id_paciente = per.id_persona WHERE pac.id_paciente = ?");
	$stmt->execute([$id]);
	$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$paciente) {
		throw new Exception('Paciente no encontrado');
	}

	// Desencriptar campos
	$nombres = decrypt_data($paciente['nombres']);
	$apellidos = decrypt_data($paciente['apellidos']);
	$numero_documento = decrypt_data($paciente['numero_documento']);
	$telefono = decrypt_data($paciente['telefono']);
	$email = decrypt_data($paciente['email']);
	$direccion = decrypt_data($paciente['direccion']);
	$fecha_nacimiento = decrypt_data($paciente['fecha_nacimiento']);

	$edad = '';
	if (!empty($fecha_nacimiento)) {
		try {
			$fech = new DateTime($fecha_nacimiento);
			$hoy = new DateTime();
			$edad = $hoy->diff($fech)->y . ' años';
		} catch (Exception $e) {
			$edad = '';
		}
	}

} catch (Exception $e) {
	echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
	require_once '../../includes/footer.php';
	exit();
}

// Consultas y últimos eventos (consultas e internamientos)
$ultimas_consultas = [];
$ultimos_internamientos = [];
$total_consultas = 0;
$total_internamientos = 0;

try {
	// Contar totales
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_paciente = ?");
	$stmt->execute([$id]);
	$total_consultas = $stmt->fetchColumn();

	$stmt = $pdo->prepare("SELECT COUNT(*) FROM internamiento WHERE id_paciente = ?");
	$stmt->execute([$id]);
	$total_internamientos = $stmt->fetchColumn();

	// Obtener últimas consultas
	$stmt = $pdo->prepare("SELECT c.*, m.id_medico, m.nombre_completo as medico_nombre FROM consulta c LEFT JOIN medico m ON c.id_medico = m.id_medico WHERE c.id_paciente = ? ORDER BY c.fecha_consulta DESC LIMIT 5");
	$stmt->execute([$id]);
	$ultimas_consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Obtener últimos internamientos
	$stmt = $pdo->prepare("SELECT i.*, c.numero_cama, h.numero_habitacion, s.nombre as sala FROM internamiento i LEFT JOIN cama c ON i.id_cama = c.id_cama LEFT JOIN habitacion h ON c.id_habitacion = h.id_habitacion LEFT JOIN sala s ON h.id_sala = s.id_sala WHERE i.id_paciente = ? ORDER BY i.fecha_ingreso DESC LIMIT 5");
	$stmt->execute([$id]);
	$ultimos_internamientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	// no fatal
}

// Obtener próximas citas
$proximas_citas = [];
try {
	$stmt = $pdo->prepare("
		SELECT c.*, 
			CONCAT(per.nombres, ' ', per.apellidos) as medico_nombre
		FROM cita c
		LEFT JOIN usuario u ON c.id_medico = u.id_usuario
		LEFT JOIN persona per ON u.id_persona = per.id_persona
		WHERE c.id_paciente = ? 
		AND c.fecha_cita >= CURDATE()
		AND c.estado_cita NOT IN ('Cancelada', 'Completada')
		ORDER BY c.fecha_cita ASC, c.hora_cita ASC
		LIMIT 5
	");
	$stmt->execute([$id]);
	$proximas_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	// no fatal
}

?>

<!-- Contenido Principal -->
<main>
	<div class="container-fluid">
		<!-- Encabezado -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center flex-wrap">
					<div class="mb-3 mb-md-0">
						<h1 class="h2 mb-2">
							<i class="fas fa-user-circle text-primary me-2"></i>
							<?php echo htmlspecialchars($nombres . ' ' . $apellidos); ?>
						</h1>
						<div class="d-flex align-items-center gap-3 flex-wrap">
							<span class="text-muted">
								<i class="fas fa-id-card me-1"></i>
								HC: <strong><?php echo htmlspecialchars($paciente['numero_historia_clinica'] ?? 'Sin asignar'); ?></strong>
							</span>
							<?php if ($paciente['grupo_sanguineo']): ?>
							<span class="badge bg-danger">
								<i class="fas fa-tint me-1"></i>
								<?php echo $paciente['grupo_sanguineo'] . $paciente['factor_rh']; ?>
							</span>
							<?php endif; ?>
							<span class="badge <?php echo $paciente['estado_paciente'] === 'activo' ? 'bg-success' : 'bg-secondary'; ?>">
								<?php echo ucfirst($paciente['estado_paciente']); ?>
							</span>
						</div>
					</div>
					<div class="d-flex gap-2 flex-wrap">
						<a href="index.php" class="btn btn-secondary">
							<i class="fas fa-arrow-left me-2"></i>Volver
						</a>
						<?php if (has_any_role(['Administrador','Recepcionista'])): ?>
						<a href="editar.php?id=<?php echo $id; ?>" class="btn btn-success">
							<i class="fas fa-edit me-2"></i>Editar
						</a>
						<?php endif; ?>
						<a href="historial.php?id=<?php echo $id; ?>" class="btn btn-primary">
							<i class="fas fa-file-medical me-2"></i>Historial Clínico
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Estadísticas rápidas -->
		<div class="row g-3 mb-4">
			<div class="col-md-3">
				<div class="card shadow-sm border-start border-primary border-4">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<p class="text-muted small mb-1">Total Consultas</p>
								<h3 class="mb-0 fw-bold"><?php echo number_format($total_consultas); ?></h3>
							</div>
							<i class="fas fa-stethoscope text-primary fa-2x"></i>
						</div>
					</div>
				</div>
			</div>
			
			<div class="col-md-3">
				<div class="card shadow-sm border-start border-warning border-4">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<p class="text-muted small mb-1">Internamientos</p>
								<h3 class="mb-0 fw-bold"><?php echo number_format($total_internamientos); ?></h3>
							</div>
							<i class="fas fa-hospital-user text-warning fa-2x"></i>
						</div>
					</div>
				</div>
			</div>
			
			<div class="col-md-3">
				<div class="card shadow-sm border-start border-info border-4">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<p class="text-muted small mb-1">Edad</p>
								<h3 class="mb-0 fw-bold"><?php echo $edad ?: 'N/A'; ?></h3>
							</div>
							<i class="fas fa-birthday-cake text-info fa-2x"></i>
						</div>
					</div>
				</div>
			</div>
			
			<div class="col-md-3">
				<div class="card shadow-sm border-start border-success border-4">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<p class="text-muted small mb-1">Próximas Citas</p>
								<h3 class="mb-0 fw-bold"><?php echo count($proximas_citas); ?></h3>
							</div>
							<i class="fas fa-calendar-check text-success fa-2x"></i>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="row">
			<!-- Columna principal -->
			<div class="col-lg-8">
				<!-- Datos Personales -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0">
							<i class="fas fa-user text-primary me-2"></i>
							Datos Personales
						</h5>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-id-card text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Documento</small>
										<strong><?php echo htmlspecialchars($paciente['tipo_documento'] . ': ' . $numero_documento); ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-calendar text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Fecha de Nacimiento</small>
										<strong><?php echo $fecha_nacimiento ? htmlspecialchars($fecha_nacimiento) . ' (' . $edad . ')' : 'No registrado'; ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-venus-mars text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Género</small>
										<strong><?php echo $paciente['genero'] === 'M' ? 'Masculino' : ($paciente['genero'] === 'F' ? 'Femenino' : $paciente['genero']); ?></strong>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-phone text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Teléfono</small>
										<strong><?php echo htmlspecialchars($telefono ?: 'No registrado'); ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-envelope text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Email</small>
										<strong><?php echo htmlspecialchars($email ?: 'No registrado'); ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-map-marker-alt text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Dirección</small>
										<strong><?php echo htmlspecialchars($direccion ?: 'No registrado'); ?></strong>
									</div>
								</div>
							</div>
							<div class="col-md-12">
								<div class="d-flex align-items-start">
									<i class="fas fa-city text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Ubicación</small>
										<strong><?php echo htmlspecialchars($paciente['ciudad'] . ', ' . $paciente['pais']); ?></strong>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Información Médica -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0">
							<i class="fas fa-heartbeat text-danger me-2"></i>
							Información Médica
						</h5>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-tint text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Grupo Sanguíneo</small>
										<strong><?php echo $paciente['grupo_sanguineo'] ? htmlspecialchars($paciente['grupo_sanguineo'] . $paciente['factor_rh']) : 'No registrado'; ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-allergies text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Alergias</small>
										<strong><?php echo htmlspecialchars($paciente['alergias'] ?: 'Ninguna registrada'); ?></strong>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-procedures text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Enfermedades Crónicas</small>
										<strong><?php echo htmlspecialchars($paciente['enfermedades_cronicas'] ?: 'Ninguna registrada'); ?></strong>
									</div>
								</div>
								<div class="d-flex align-items-start mb-3">
									<i class="fas fa-shield-alt text-muted me-3 mt-1"></i>
									<div>
										<small class="text-muted d-block">Seguro Médico</small>
										<strong><?php echo htmlspecialchars($paciente['seguro_medico'] ?: 'No tiene'); ?></strong>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Próximas Citas -->
				<?php if (!empty($proximas_citas)): ?>
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0">
							<i class="fas fa-calendar-alt text-success me-2"></i>
							Próximas Citas
						</h5>
					</div>
					<div class="card-body">
						<div class="list-group list-group-flush">
							<?php foreach ($proximas_citas as $cita): ?>
							<div class="list-group-item px-0">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<div class="fw-bold">
											<i class="fas fa-calendar-day text-primary me-2"></i>
											<?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?> 
											a las <?php echo date('H:i', strtotime($cita['hora_cita'])); ?>
										</div>
										<div class="small text-muted mt-1">
											<i class="fas fa-user-md me-1"></i>
											<?php 
											if (!empty($cita['medico_nombre'])) {
												echo htmlspecialchars(decrypt_data($cita['medico_nombre']));
											} else {
												echo 'Médico no asignado';
											}
											?>
										</div>
										<span class="badge bg-warning mt-1"><?php echo $cita['estado_cita']; ?></span>
									</div>
									<a href="../citas/ver.php?id=<?php echo $cita['id_cita']; ?>" class="btn btn-sm btn-outline-primary">
										<i class="fas fa-eye"></i> Ver
									</a>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Últimas Consultas -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
						<h5 class="mb-0">
							<i class="fas fa-notes-medical text-info me-2"></i>
							Últimas Consultas
						</h5>
						<?php if ($total_consultas > 5): ?>
						<a href="../consultas/index.php?paciente=<?php echo $id; ?>" class="btn btn-sm btn-outline-info">
							Ver todas (<?php echo $total_consultas; ?>)
						</a>
						<?php endif; ?>
					</div>
					<div class="card-body">
						<?php if (empty($ultimas_consultas)): ?>
						<div class="text-center py-4">
							<i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
							<p class="text-muted mt-3 mb-0">No hay consultas registradas</p>
						</div>
						<?php else: ?>
						<div class="list-group list-group-flush">
							<?php foreach ($ultimas_consultas as $c): ?>
							<div class="list-group-item px-0">
								<div class="d-flex justify-content-between align-items-start">
									<div class="flex-grow-1">
										<div class="fw-bold"><?php echo htmlspecialchars($c['motivo_consulta'] ?? 'Consulta general'); ?></div>
										<div class="small text-muted mt-1">
											<i class="fas fa-calendar me-1"></i>
											<?php echo date('d/m/Y', strtotime($c['fecha_consulta'])); ?>
											<?php if (!empty($c['medico_nombre'])): ?>
											<span class="mx-2">|</span>
											<i class="fas fa-user-md me-1"></i>
											<?php echo htmlspecialchars($c['medico_nombre']); ?>
											<?php endif; ?>
										</div>
										<?php if (!empty($c['diagnostico'])): ?>
										<div class="small text-muted mt-1">
											<i class="fas fa-diagnoses me-1"></i>
											<?php echo htmlspecialchars(substr($c['diagnostico'], 0, 80)) . (strlen($c['diagnostico']) > 80 ? '...' : ''); ?>
										</div>
										<?php endif; ?>
									</div>
									<a href="../consultas/ver.php?id=<?php echo $c['id_consulta']; ?>" class="btn btn-sm btn-outline-primary ms-3">
										<i class="fas fa-eye"></i>
									</a>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Internamientos Recientes -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
						<h5 class="mb-0">
							<i class="fas fa-hospital-user text-warning me-2"></i>
							Internamientos Recientes
						</h5>
						<?php if ($total_internamientos > 5): ?>
						<a href="../internamiento/index.php?paciente=<?php echo $id; ?>" class="btn btn-sm btn-outline-warning">
							Ver todos (<?php echo $total_internamientos; ?>)
						</a>
						<?php endif; ?>
					</div>
					<div class="card-body">
						<?php if (empty($ultimos_internamientos)): ?>
						<div class="text-center py-4">
							<i class="fas fa-bed text-muted" style="font-size: 3rem;"></i>
							<p class="text-muted mt-3 mb-0">No hay internamientos registrados</p>
						</div>
						<?php else: ?>
						<div class="list-group list-group-flush">
							<?php foreach ($ultimos_internamientos as $i): ?>
							<div class="list-group-item px-0">
								<div class="d-flex justify-content-between align-items-start">
									<div class="flex-grow-1">
										<div class="fw-bold"><?php echo htmlspecialchars($i['diagnostico_ingreso'] ?? 'Internamiento'); ?></div>
										<div class="small text-muted mt-1">
											<i class="fas fa-calendar-plus me-1"></i>
											Ingreso: <?php echo date('d/m/Y', strtotime($i['fecha_ingreso'])); ?>
											<?php if (!empty($i['fecha_alta'])): ?>
											<span class="mx-2">|</span>
											<i class="fas fa-calendar-check me-1"></i>
											Alta: <?php echo date('d/m/Y', strtotime($i['fecha_alta'])); ?>
											<?php endif; ?>
										</div>
										<?php if (!empty($i['numero_cama'])): ?>
										<div class="small text-muted mt-1">
											<i class="fas fa-bed me-1"></i>
											<?php 
											echo 'Cama: ' . htmlspecialchars($i['numero_cama']);
											if (!empty($i['numero_habitacion'])) {
												echo ' - Habitación: ' . htmlspecialchars($i['numero_habitacion']);
											}
											if (!empty($i['sala'])) {
												echo ' (' . htmlspecialchars($i['sala']) . ')';
											}
											?>
										</div>
										<?php endif; ?>
										<span class="badge <?php echo empty($i['fecha_alta']) ? 'bg-success' : 'bg-secondary'; ?> mt-1">
											<?php echo empty($i['fecha_alta']) ? 'Activo' : 'Finalizado'; ?>
										</span>
									</div>
									<a href="../internamiento/evolucion.php?id_internamiento=<?php echo $i['id_internamiento']; ?>" class="btn btn-sm btn-outline-warning ms-3">
										<i class="fas fa-eye"></i>
									</a>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Columna lateral -->
			<div class="col-lg-4">
				<!-- Contacto de Emergencia -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0">
							<i class="fas fa-phone-square-alt text-danger me-2"></i>
							Contacto de Emergencia
						</h5>
					</div>
					<div class="card-body">
						<?php if (!empty($paciente['contacto_emergencia_nombre'])): ?>
						<div class="d-flex align-items-start mb-3">
							<i class="fas fa-user text-muted me-3 mt-1"></i>
							<div>
								<small class="text-muted d-block">Nombre</small>
								<strong><?php echo htmlspecialchars(decrypt_data($paciente['contacto_emergencia_nombre'])); ?></strong>
							</div>
						</div>
						<div class="d-flex align-items-start mb-3">
							<i class="fas fa-phone text-muted me-3 mt-1"></i>
							<div>
								<small class="text-muted d-block">Teléfono</small>
								<strong><?php echo htmlspecialchars(decrypt_data($paciente['contacto_emergencia_telefono'])); ?></strong>
							</div>
						</div>
						<div class="d-flex align-items-start">
							<i class="fas fa-link text-muted me-3 mt-1"></i>
							<div>
								<small class="text-muted d-block">Relación</small>
								<strong><?php echo htmlspecialchars($paciente['contacto_emergencia_relacion'] ?? 'No especificada'); ?></strong>
							</div>
						</div>
						<?php else: ?>
						<p class="text-muted mb-0 text-center py-3">
							<i class="fas fa-exclamation-circle"></i><br>
							No hay contacto de emergencia registrado
						</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Acciones Rápidas -->
				<div class="card shadow-sm mb-4">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0">
							<i class="fas fa-bolt text-primary me-2"></i>
							Acciones Rápidas
						</h5>
					</div>
					<div class="card-body">
						<div class="d-grid gap-2">
							<a href="../citas/programar.php?paciente=<?php echo $id; ?>" class="btn btn-primary">
								<i class="fas fa-calendar-plus me-2"></i>Programar Cita
							</a>
							<a href="../internamiento/registrar.php?paciente=<?php echo $id; ?>" class="btn btn-warning">
								<i class="fas fa-hospital-user me-2"></i>Registrar Internamiento
							</a>
							<a href="../consultas/nueva.php?paciente=<?php echo $id; ?>" class="btn btn-info">
								<i class="fas fa-stethoscope me-2"></i>Nueva Consulta
							</a>
							<a href="historial.php?id=<?php echo $id; ?>" class="btn btn-secondary">
								<i class="fas fa-file-medical me-2"></i>Ver Historial Completo
							</a>
						</div>
					</div>
				</div>

				<!-- Información del Registro -->
				<div class="card shadow-sm mb-4 bg-light">
					<div class="card-body">
						<h6 class="card-title">
							<i class="fas fa-info-circle text-info me-2"></i>
							Información del Registro
						</h6>
						<div class="small">
							<div class="mb-2">
								<i class="fas fa-calendar-check text-muted me-2"></i>
								<strong>Primera consulta:</strong><br>
								<?php echo !empty($paciente['fecha_primera_consulta']) ? date('d/m/Y', strtotime($paciente['fecha_primera_consulta'])) : 'No registrada'; ?>
							</div>
							<div class="mb-2">
								<i class="fas fa-user-plus text-muted me-2"></i>
								<strong>Registrado por:</strong><br>
								<?php 
								if (!empty($paciente['usuario_crea'])) {
									try {
										$stmt = $pdo->prepare("SELECT CONCAT(per.nombres, ' ', per.apellidos) as nombre FROM usuario u INNER JOIN persona per ON u.id_persona = per.id_persona WHERE u.id_usuario = ?");
										$stmt->execute([$paciente['usuario_crea']]);
										$creador = $stmt->fetch();
										if ($creador) {
											echo htmlspecialchars(decrypt_data($creador['nombre']));
										} else {
											echo 'No disponible';
										}
									} catch (Exception $e) {
										echo 'No disponible';
									}
								} else {
									echo 'No disponible';
								}
								?>
							</div>
							<?php if (!empty($paciente['fecha_modifica'])): ?>
							<div>
								<i class="fas fa-edit text-muted me-2"></i>
								<strong>Última modificación:</strong><br>
								<?php echo date('d/m/Y H:i', strtotime($paciente['fecha_modifica'])); ?>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<?php require_once '../../includes/footer.php'; ?>