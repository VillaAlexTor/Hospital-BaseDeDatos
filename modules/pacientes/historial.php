<?php
/**
 * modules/pacientes/historial.php
 * Mostrar y editar (si aplica) el historial clínico del paciente
 */

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (!isset($_SESSION['user_id'])) {
	header('Location: ../../index.php');
	exit();
}

$page_title = "Historial Clínico";
require_once '../../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
	echo '<div class="alert alert-warning">ID de paciente no proporcionado</div>';
	require_once '../../includes/footer.php';
	exit();
}

$id = (int) $_GET['id'];

// Obtener datos del paciente
try {
	$stmt = $pdo->prepare("
		SELECT 
			per.nombres, per.apellidos, 
			pac.numero_historia_clinica,
			pac.grupo_sanguineo, pac.factor_rh
		FROM paciente pac
		INNER JOIN persona per ON pac.id_paciente = per.id_persona
		WHERE pac.id_paciente = ?
	");
	$stmt->execute([$id]);
	$paciente = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$paciente) {
		throw new Exception('Paciente no encontrado');
	}
	
	// Desencriptar datos
	$paciente['nombres'] = decrypt_data($paciente['nombres']);
	$paciente['apellidos'] = decrypt_data($paciente['apellidos']);
	
} catch (Exception $e) {
	echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
	require_once '../../includes/footer.php';
	exit();
}

// Obtener historial clínico
try {
	$stmt = $pdo->prepare("SELECT h.* FROM historial_clinico h WHERE h.id_paciente = ?");
	$stmt->execute([$id]);
	$historial = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$historial) {
		// Crear un historial vacío si no existe
		$stmt = $pdo->prepare("INSERT INTO historial_clinico (id_paciente) VALUES (?)");
		$stmt->execute([$id]);
		$stmt = $pdo->prepare("SELECT h.* FROM historial_clinico h WHERE h.id_paciente = ?");
		$stmt->execute([$id]);
		$historial = $stmt->fetch(PDO::FETCH_ASSOC);
	}
} catch (Exception $e) {
	echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
	require_once '../../includes/footer.php';
	exit();
}

$error = '';
$success = '';

// Guardar cambios en el historial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_any_role(['Administrador','Médico'])) {
	if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
		$error = 'Token CSRF inválido';
	} else {
		try {
			$antecedentes_personales = sanitize_input($_POST['antecedentes_personales'] ?? '');
			$antecedentes_familiares = sanitize_input($_POST['antecedentes_familiares'] ?? '');
			$cirugias_previas = sanitize_input($_POST['cirugias_previas'] ?? '');
			$medicamentos_actuales = sanitize_input($_POST['medicamentos_actuales'] ?? '');
			$habitos = sanitize_input($_POST['habitos'] ?? '');

			$stmt = $pdo->prepare("UPDATE historial_clinico SET antecedentes_personales = ?, antecedentes_familiares = ?, cirugias_previas = ?, medicamentos_actuales = ?, habitos = ?, fecha_actualizacion = NOW(), actualizado_por = ? WHERE id_paciente = ?");
			$stmt->execute([$antecedentes_personales, $antecedentes_familiares, $cirugias_previas, $medicamentos_actuales, $habitos, $_SESSION['user_id'], $id]);
			$success = 'Historial clínico actualizado correctamente';
			log_action('UPDATE', 'historial_clinico', $id, 'Actualización de historial clínico');
			// Recargar
			header('Location: historial.php?id=' . $id);
			exit();
		} catch (Exception $e) {
			$error = 'Error al guardar historial: ' . $e->getMessage();
		}
	}
}

// Obtener consultas recientes del paciente
try {
	$stmt = $pdo->prepare("
		SELECT 
			c.id_consulta,
			c.fecha_consulta,
			c.motivo_consulta,
			c.diagnostico,
			CONCAT(per.nombres, ' ', per.apellidos) as medico
		FROM consulta c
		LEFT JOIN usuario u ON c.id_medico = u.id_usuario
		LEFT JOIN persona per ON u.id_persona = per.id_persona
		WHERE c.id_paciente = ?
		ORDER BY c.fecha_consulta DESC
		LIMIT 5
	");
	$stmt->execute([$id]);
	$consultas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$consultas_recientes = [];
}
?>

<!-- Contenido Principal -->
<main>
	<div class="container-fluid">
		<!-- Encabezado -->
		<div class="row mb-4">
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h1 class="h2 mb-2">
							<i class="fas fa-file-medical text-warning me-2"></i>
							Historial Clínico
						</h1>
						<p class="text-muted mb-0">
							Paciente: <strong><?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?></strong>
							<span class="mx-2">|</span>
							HC: <strong><?php echo htmlspecialchars($paciente['numero_historia_clinica']); ?></strong>
							<?php if ($paciente['grupo_sanguineo']): ?>
								<span class="mx-2">|</span>
								<span class="badge bg-danger"><?php echo $paciente['grupo_sanguineo'] . $paciente['factor_rh']; ?></span>
							<?php endif; ?>
						</p>
					</div>
					<div>
						<a href="ver.php?id=<?php echo $id; ?>" class="btn btn-secondary">
							<i class="fas fa-arrow-left me-2"></i>Volver
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Alertas -->
		<?php if ($error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<i class="fas fa-exclamation-circle me-2"></i>
			<?php echo $error; ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
		<?php endif; ?>
		
		<?php if ($success): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<i class="fas fa-check-circle me-2"></i>
			<?php echo $success; ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
		<?php endif; ?>

		<!-- Información de última actualización -->
		<?php if (!empty($historial['fecha_actualizacion'])): ?>
		<div class="alert alert-info mb-4">
			<i class="fas fa-info-circle me-2"></i>
			<strong>Última actualización:</strong> 
			<?php echo date('d/m/Y H:i', strtotime($historial['fecha_actualizacion'])); ?>
			<?php if (!empty($historial['actualizado_por'])): ?>
				<?php
				try {
					$stmt = $pdo->prepare("SELECT CONCAT(per.nombres, ' ', per.apellidos) as nombre FROM usuario u INNER JOIN persona per ON u.id_persona = per.id_persona WHERE u.id_usuario = ?");
					$stmt->execute([$historial['actualizado_por']]);
					$actualizador = $stmt->fetch();
					if ($actualizador) {
						echo ' por <strong>' . htmlspecialchars(decrypt_data($actualizador['nombre'])) . '</strong>';
					}
				} catch (Exception $e) {}
				?>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- Formulario de Historial Clínico -->
		<form method="POST" id="form-historial">
			<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

			<div class="row">
				<!-- Columna principal - Formulario -->
				<div class="col-lg-8">
					<!-- Antecedentes Personales -->
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-user-md text-primary me-2"></i>
								Antecedentes Personales
							</h5>
						</div>
						<div class="card-body">
							<textarea name="antecedentes_personales" 
									  class="form-control" 
									  rows="5"
									  placeholder="Incluya enfermedades previas, alergias, hospitalizaciones anteriores, etc."
									  <?php echo !has_any_role(['Administrador','Médico']) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($historial['antecedentes_personales'] ?? ''); ?></textarea>
							<small class="text-muted">
								<i class="fas fa-lightbulb me-1"></i>
								Ejemplo: Asma desde los 5 años, apendicectomía en 2015, alergia a penicilina.
							</small>
						</div>
					</div>

					<!-- Antecedentes Familiares -->
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-users text-info me-2"></i>
								Antecedentes Familiares
							</h5>
						</div>
						<div class="card-body">
							<textarea name="antecedentes_familiares" 
									  class="form-control" 
									  rows="4"
									  placeholder="Enfermedades hereditarias o relevantes en familiares directos..."
									  <?php echo !has_any_role(['Administrador','Médico']) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($historial['antecedentes_familiares'] ?? ''); ?></textarea>
							<small class="text-muted">
								<i class="fas fa-lightbulb me-1"></i>
								Ejemplo: Padre con diabetes tipo 2, madre con hipertensión arterial.
							</small>
						</div>
					</div>

					<!-- Cirugías Previas -->
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-procedures text-danger me-2"></i>
								Cirugías Previas
							</h5>
						</div>
						<div class="card-body">
							<textarea name="cirugias_previas" 
									  class="form-control" 
									  rows="4"
									  placeholder="Liste todas las cirugías realizadas con fechas aproximadas..."
									  <?php echo !has_any_role(['Administrador','Médico']) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($historial['cirugias_previas'] ?? ''); ?></textarea>
							<small class="text-muted">
								<i class="fas fa-lightbulb me-1"></i>
								Ejemplo: Apendicectomía (marzo 2015), cesárea (julio 2018).
							</small>
						</div>
					</div>

					<!-- Medicamentos Actuales -->
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-pills text-success me-2"></i>
								Medicamentos Actuales
							</h5>
						</div>
						<div class="card-body">
							<textarea name="medicamentos_actuales" 
									  class="form-control" 
									  rows="4"
									  placeholder="Medicamentos que el paciente está tomando actualmente con dosis..."
									  <?php echo !has_any_role(['Administrador','Médico']) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($historial['medicamentos_actuales'] ?? ''); ?></textarea>
							<small class="text-muted">
								<i class="fas fa-lightbulb me-1"></i>
								Ejemplo: Enalapril 10mg (1 vez al día), Metformina 850mg (2 veces al día).
							</small>
						</div>
					</div>

					<!-- Hábitos -->
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-smoking text-warning me-2"></i>
								Hábitos y Estilo de Vida
							</h5>
						</div>
						<div class="card-body">
							<textarea name="habitos" 
									  class="form-control" 
									  rows="3"
									  placeholder="Tabaquismo, consumo de alcohol, actividad física, dieta, etc."
									  <?php echo !has_any_role(['Administrador','Médico']) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($historial['habitos'] ?? ''); ?></textarea>
							<small class="text-muted">
								<i class="fas fa-lightbulb me-1"></i>
								Ejemplo: Fumador de 10 cigarrillos/día, sedentario, consume alcohol ocasionalmente.
							</small>
						</div>
					</div>

					<!-- Botones de acción -->
					<?php if (has_any_role(['Administrador','Médico'])): ?>
					<div class="card shadow-sm mb-4">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<p class="text-muted mb-0">
										<i class="fas fa-save me-1"></i>
										Los cambios se guardarán en el historial permanente del paciente
									</p>
								</div>
								<div>
									<a href="ver.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">
										<i class="fas fa-times me-2"></i>Cancelar
									</a>
									<button type="submit" class="btn btn-primary btn-lg">
										<i class="fas fa-save me-2"></i>Guardar Historial
									</button>
								</div>
							</div>
						</div>
					</div>
					<?php else: ?>
					<div class="alert alert-warning">
						<i class="fas fa-lock me-2"></i>
						<strong>Modo solo lectura:</strong> No tienes permisos para editar el historial clínico. 
						Solo Administradores y Médicos pueden realizar cambios.
					</div>
					<?php endif; ?>
				</div>

				<!-- Columna lateral - Consultas recientes -->
				<div class="col-lg-4">
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-white py-3">
							<h5 class="mb-0">
								<i class="fas fa-history text-secondary me-2"></i>
								Consultas Recientes
							</h5>
						</div>
						<div class="card-body">
							<?php if (empty($consultas_recientes)): ?>
							<div class="text-center py-4">
								<i class="fas fa-calendar-times text-muted" style="font-size: 3rem;"></i>
								<p class="text-muted mt-3 mb-0">No hay consultas registradas</p>
							</div>
							<?php else: ?>
							<div class="list-group list-group-flush">
								<?php foreach ($consultas_recientes as $consulta): ?>
								<div class="list-group-item px-0">
									<div class="d-flex w-100 justify-content-between mb-2">
										<h6 class="mb-1">
											<i class="fas fa-calendar-day text-primary me-1"></i>
											<?php echo date('d/m/Y', strtotime($consulta['fecha_consulta'])); ?>
										</h6>
									</div>
									<p class="mb-1 small">
										<strong>Motivo:</strong> 
										<?php echo htmlspecialchars(substr($consulta['motivo_consulta'] ?? 'No especificado', 0, 50)) . (strlen($consulta['motivo_consulta'] ?? '') > 50 ? '...' : ''); ?>
									</p>
									<?php if (!empty($consulta['diagnostico'])): ?>
									<p class="mb-1 small text-muted">
										<strong>Diagnóstico:</strong> 
										<?php echo htmlspecialchars(substr($consulta['diagnostico'], 0, 50)) . (strlen($consulta['diagnostico']) > 50 ? '...' : ''); ?>
									</p>
									<?php endif; ?>
									<?php if (!empty($consulta['medico'])): ?>
									<p class="mb-0 small text-muted">
										<i class="fas fa-user-md me-1"></i>
										<?php 
										$medico_nombre = decrypt_data($consulta['medico']);
										echo htmlspecialchars($medico_nombre); 
										?>
									</p>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
							</div>
							<div class="mt-3 text-center">
								<a href="../consultas/index.php?paciente=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
									<i class="fas fa-list me-1"></i>Ver todas las consultas
								</a>
							</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Información adicional -->
					<div class="card shadow-sm mb-4 bg-light">
						<div class="card-body">
							<h6 class="card-title">
								<i class="fas fa-info-circle text-info me-2"></i>
								Información Importante
							</h6>
							<ul class="small mb-0">
								<li class="mb-2">El historial clínico es confidencial y solo accesible por personal médico autorizado.</li>
								<li class="mb-2">Mantenga esta información actualizada para garantizar una atención médica adecuada.</li>
								<li class="mb-0">Todos los cambios quedan registrados en el sistema de auditoría.</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</main>

<script>
// Confirmación antes de guardar
document.getElementById('form-historial')?.addEventListener('submit', function(e) {
	if (!confirm('¿Está seguro de guardar los cambios en el historial clínico del paciente?')) {
		e.preventDefault();
		return false;
	}
});

// Auto-guardar en localStorage cada 30 segundos (opcional)
<?php if (has_any_role(['Administrador','Médico'])): ?>
let autoSaveTimer;
const formInputs = document.querySelectorAll('#form-historial textarea');

formInputs.forEach(input => {
	input.addEventListener('input', function() {
		clearTimeout(autoSaveTimer);
		autoSaveTimer = setTimeout(() => {
			// Guardar en localStorage como respaldo
			const formData = {};
			formInputs.forEach(field => {
				formData[field.name] = field.value;
			});
			localStorage.setItem('historial_backup_<?php echo $id; ?>', JSON.stringify(formData));
			console.log('Respaldo automático guardado');
		}, 30000); // 30 segundos
	});
});

// Restaurar desde localStorage si existe
window.addEventListener('load', function() {
	const backup = localStorage.getItem('historial_backup_<?php echo $id; ?>');
	if (backup) {
		if (confirm('Se encontró un respaldo no guardado. ¿Desea restaurarlo?')) {
			const formData = JSON.parse(backup);
			Object.keys(formData).forEach(key => {
				const field = document.querySelector(`[name="${key}"]`);
				if (field && !field.value) {
					field.value = formData[key];
				}
			});
		} else {
			localStorage.removeItem('historial_backup_<?php echo $id; ?>');
		}
	}
});
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>