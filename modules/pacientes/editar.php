<?php
/**
 * modules/pacientes/editar.php
 * Editar datos de paciente
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (!isset($_SESSION['user_id'])) {
	header('Location: ../../index.php');
	exit();
}

$page_title = "Editar Paciente";
require_once '../../includes/header.php';

if (!has_any_role(['Administrador', 'Recepcionista'])) {
	echo '<div class="alert alert-danger">No tienes permisos para editar pacientes</div>';
	require_once '../../includes/footer.php';
	exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
	echo '<div class="alert alert-warning">ID de paciente no proporcionado</div>';
	require_once '../../includes/footer.php';
	exit();
}

$id = (int) $_GET['id'];
$error = '';
$success = '';

// Cargar datos existentes
try {
	$stmt = $pdo->prepare("SELECT pac.*, per.* FROM paciente pac INNER JOIN persona per ON pac.id_paciente = per.id_persona WHERE pac.id_paciente = ?");
	$stmt->execute([$id]);
	$paciente = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$paciente) throw new Exception('Paciente no encontrado');

	// Desencriptar para mostrar
	$paciente['nombres'] = decrypt_data($paciente['nombres']);
	$paciente['apellidos'] = decrypt_data($paciente['apellidos']);
	$paciente['numero_documento'] = decrypt_data($paciente['numero_documento']);
	$paciente['telefono'] = decrypt_data($paciente['telefono']);
	$paciente['email'] = decrypt_data($paciente['email']);
	$paciente['direccion'] = decrypt_data($paciente['direccion']);
	$paciente['fecha_nacimiento'] = decrypt_data($paciente['fecha_nacimiento']);
	$paciente['contacto_emergencia_nombre'] = decrypt_data($paciente['contacto_emergencia_nombre'] ?? '');
	$paciente['contacto_emergencia_telefono'] = decrypt_data($paciente['contacto_emergencia_telefono'] ?? '');
	$paciente['numero_poliza'] = decrypt_data($paciente['numero_poliza'] ?? '');

} catch (Exception $e) {
	echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
	require_once '../../includes/footer.php';
	exit();
}

// Procesar POST de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
		$error = 'Token CSRF inválido';
	} else {
		try {
			$pdo->beginTransaction();

			// Datos personales
			$tipo_documento = sanitize_input($_POST['tipo_documento']);
			$numero_documento = sanitize_input($_POST['numero_documento']);
			$nombres = sanitize_input($_POST['nombres']);
			$apellidos = sanitize_input($_POST['apellidos']);
			$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
			$genero = $_POST['genero'];
			$telefono = sanitize_input($_POST['telefono']);
			$email = sanitize_input($_POST['email']);
			$direccion = sanitize_input($_POST['direccion']);
			$ciudad = sanitize_input($_POST['ciudad']);
			$pais = sanitize_input($_POST['pais']);

			// Actualizar PERSONA (sin fecha_modifica, usa fecha_modificacion automática)
			$stmt = $pdo->prepare("UPDATE persona 
				SET tipo_documento = ?, 
					numero_documento = ?, 
					nombres = ?, 
					apellidos = ?, 
					fecha_nacimiento = ?, 
					genero = ?, 
					telefono = ?, 
					email = ?, 
					direccion = ?, 
					ciudad = ?, 
					pais = ?, 
					usuario_modifica = ?
				WHERE id_persona = ?");
			
			$stmt->execute([
				$tipo_documento,
				encrypt_data($numero_documento),
				encrypt_data($nombres),
				encrypt_data($apellidos),
				encrypt_data($fecha_nacimiento),
				$genero,
				encrypt_data($telefono),
				encrypt_data($email),
				encrypt_data($direccion),
				$ciudad,
				$pais,
				$_SESSION['user_id'],
				$id
			]);

			// Datos de paciente
			$grupo_base = $_POST['grupo_sanguineo'] ?? null;
			$factor_rh_seleccionado = $_POST['factor_rh'] ?? null;

			// Crear el grupo sanguíneo completo (ej: A+, O-, etc)
			$grupo_sanguineo = null;
			$factor_rh = null;
			if (!empty($grupo_base) && !empty($factor_rh_seleccionado)) {
				$grupo_sanguineo = $grupo_base . $factor_rh_seleccionado;
				$factor_rh = $factor_rh_seleccionado;
			}

			$alergias = sanitize_input($_POST['alergias'] ?? '');
			$enfermedades_cronicas = sanitize_input($_POST['enfermedades_cronicas'] ?? '');
			$seguro_medico = sanitize_input($_POST['seguro_medico'] ?? '');
			$numero_poliza = sanitize_input($_POST['numero_poliza'] ?? '');

			$stmt = $pdo->prepare("UPDATE paciente 
				SET grupo_sanguineo = ?, 
					factor_rh = ?, 
					alergias = ?, 
					enfermedades_cronicas = ?, 
					contacto_emergencia_nombre = ?, 
					contacto_emergencia_telefono = ?, 
					contacto_emergencia_relacion = ?, 
					seguro_medico = ?, 
					numero_poliza = ?, 
					estado_paciente = ? 
				WHERE id_paciente = ?");
			
			$stmt->execute([
				$grupo_sanguineo,
				$factor_rh,
				$alergias,
				$enfermedades_cronicas,
				encrypt_data($_POST['contacto_emergencia_nombre'] ?? ''),
				encrypt_data($_POST['contacto_emergencia_telefono'] ?? ''),
				$_POST['contacto_emergencia_relacion'] ?? '',
				$seguro_medico,
				encrypt_data($numero_poliza),
				$_POST['estado_paciente'] ?? 'activo',
				$id
			]);

			log_action('UPDATE', 'paciente', $id, 'Edición de paciente por usuario ' . $_SESSION['user_id']);
			$pdo->commit();

			// Redirigir al listado con mensaje de éxito
			$_SESSION['success_message'] = 'Paciente actualizado correctamente';

			// Usar redirección JavaScript como respaldo
			echo '<script>window.location.href = "index.php";</script>';
			exit();

		} catch (Exception $e) {
			$pdo->rollBack();
			$error = 'Error al actualizar paciente: ' . $e->getMessage();
		}
	}
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
							<i class="fas fa-edit text-success me-2"></i>
							Editar Paciente
						</h1>
						<p class="text-muted mb-0">
							Modifica los datos del paciente: <strong><?php echo htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']); ?></strong>
						</p>
					</div>
					<div>
						<a href="ver.php?id=<?php echo $id; ?>" class="btn btn-secondary">
							<i class="fas fa-times me-2"></i>Cancelar
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

		<!-- Formulario -->
		<form method="POST" action="" id="form-editar-paciente">
			<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

			<!-- SECCIÓN 1: DATOS PERSONALES -->
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white py-3">
					<h5 class="mb-0">
						<i class="fas fa-user text-primary me-2"></i>
						Datos Personales
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<!-- Tipo de Documento -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="tipo_documento">
								Tipo de Documento <span class="text-danger">*</span>
							</label>
							<select name="tipo_documento" id="tipo_documento" required class="form-select">
								<option value="">Seleccione...</option>
								<option value="CI" <?php echo $paciente['tipo_documento'] === 'CI' ? 'selected' : ''; ?>>Cédula de Identidad</option>
								<option value="Pasaporte" <?php echo $paciente['tipo_documento'] === 'Pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
								<option value="RUC" <?php echo $paciente['tipo_documento'] === 'RUC' ? 'selected' : ''; ?>>RUC</option>
								<option value="Otro" <?php echo $paciente['tipo_documento'] === 'Otro' ? 'selected' : ''; ?>>Otro</option>
							</select>
						</div>
						
						<!-- Número de Documento -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="numero_documento">
								Número de Documento <span class="text-danger">*</span>
							</label>
							<input type="text" name="numero_documento" id="numero_documento" required
								   maxlength="20" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['numero_documento']); ?>">
						</div>
						
						<!-- Nombres -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="nombres">
								Nombres <span class="text-danger">*</span>
							</label>
							<input type="text" name="nombres" id="nombres" required
								   maxlength="100" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['nombres']); ?>">
						</div>
						
						<!-- Apellidos -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="apellidos">
								Apellidos <span class="text-danger">*</span>
							</label>
							<input type="text" name="apellidos" id="apellidos" required
								   maxlength="100" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['apellidos']); ?>">
						</div>
						
						<!-- Fecha de Nacimiento -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="fecha_nacimiento">
								Fecha de Nacimiento <span class="text-danger">*</span>
							</label>
							<input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required
								   max="<?php echo date('Y-m-d'); ?>" class="form-control"
								   value="<?php echo htmlspecialchars($paciente['fecha_nacimiento']); ?>">
							<small class="text-muted">Edad: <span id="edad-display">-</span></small>
						</div>
						
						<!-- Género -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="genero">
								Género <span class="text-danger">*</span>
							</label>
							<select name="genero" id="genero" required class="form-select">
								<option value="">Seleccione...</option>
								<option value="M" <?php echo $paciente['genero'] === 'M' ? 'selected' : ''; ?>>Masculino</option>
								<option value="F" <?php echo $paciente['genero'] === 'F' ? 'selected' : ''; ?>>Femenino</option>
								<option value="Otro" <?php echo $paciente['genero'] === 'Otro' ? 'selected' : ''; ?>>Otro</option>
								<option value="Prefiero no decir" <?php echo $paciente['genero'] === 'Prefiero no decir' ? 'selected' : ''; ?>>Prefiero no decir</option>
							</select>
						</div>
						
						<!-- Teléfono -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="telefono">
								Teléfono <span class="text-danger">*</span>
							</label>
							<input type="tel" name="telefono" id="telefono" required
								   maxlength="20" pattern="[0-9+\-\s()]+" 
								   class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['telefono']); ?>">
						</div>
						
						<!-- Email -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="email">
								Correo Electrónico
							</label>
							<input type="email" name="email" id="email"
								   maxlength="100" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['email']); ?>">
						</div>
						
						<!-- Dirección -->
						<div class="col-md-6">
							<label class="form-label fw-bold" for="direccion">
								Dirección
							</label>
							<input type="text" name="direccion" id="direccion"
								   maxlength="255" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['direccion']); ?>">
						</div>
						
						<!-- Ciudad -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="ciudad">
								Ciudad <span class="text-danger">*</span>
							</label>
							<input type="text" name="ciudad" id="ciudad" required
								   maxlength="100" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['ciudad'] ?? 'La Paz'); ?>">
						</div>
						
						<!-- País -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="pais">
								País
							</label>
							<input type="text" name="pais" id="pais"
								   maxlength="100" class="form-control"
								   value="<?php echo htmlspecialchars($paciente['pais'] ?? 'Bolivia'); ?>">
						</div>
					</div>
				</div>
			</div>

			<!-- SECCIÓN 2: DATOS MÉDICOS -->
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white py-3">
					<h5 class="mb-0">
						<i class="fas fa-heartbeat text-danger me-2"></i>
						Información Médica
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<!-- Grupo Sanguíneo -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="grupo_sanguineo">
								Grupo Sanguíneo
							</label>
							<select name="grupo_sanguineo" id="grupo_sanguineo" class="form-select">
								<option value="">Seleccione...</option>
								<option value="A" <?php echo (isset($paciente['grupo_sanguineo']) && strpos($paciente['grupo_sanguineo'], 'A') === 0) ? 'selected' : ''; ?>>A</option>
								<option value="B" <?php echo (isset($paciente['grupo_sanguineo']) && strpos($paciente['grupo_sanguineo'], 'B') === 0) ? 'selected' : ''; ?>>B</option>
								<option value="AB" <?php echo (isset($paciente['grupo_sanguineo']) && strpos($paciente['grupo_sanguineo'], 'AB') === 0) ? 'selected' : ''; ?>>AB</option>
								<option value="O" <?php echo (isset($paciente['grupo_sanguineo']) && strpos($paciente['grupo_sanguineo'], 'O') === 0) ? 'selected' : ''; ?>>O</option>
							</select>
						</div>
						
						<!-- Factor RH -->
						<div class="col-md-3">
							<label class="form-label fw-bold" for="factor_rh">
								Factor RH
							</label>
							<select name="factor_rh" id="factor_rh" class="form-select">
								<option value="">Seleccione...</option>
								<option value="+" <?php echo ($paciente['factor_rh'] ?? '') === '+' ? 'selected' : ''; ?>>Positivo (+)</option>
								<option value="-" <?php echo ($paciente['factor_rh'] ?? '') === '-' ? 'selected' : ''; ?>>Negativo (-)</option>
							</select>
						</div>
						
						<!-- Alergias -->
						<div class="col-md-6">
							<label class="form-label fw-bold" for="alergias">
								Alergias Conocidas
							</label>
							<textarea name="alergias" id="alergias" rows="3"
									  class="form-control"
									  placeholder="Ej: Penicilina, mariscos, polen..."><?php echo htmlspecialchars($paciente['alergias'] ?? ''); ?></textarea>
						</div>
						
						<!-- Enfermedades Crónicas -->
						<div class="col-md-12">
							<label class="form-label fw-bold" for="enfermedades_cronicas">
								Enfermedades Crónicas
							</label>
							<textarea name="enfermedades_cronicas" id="enfermedades_cronicas" rows="3"
									  class="form-control"
									  placeholder="Ej: Diabetes tipo 2, hipertensión arterial..."><?php echo htmlspecialchars($paciente['enfermedades_cronicas'] ?? ''); ?></textarea>
						</div>
					</div>
				</div>
			</div>

			<!-- SECCIÓN 3: CONTACTO DE EMERGENCIA -->
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white py-3">
					<h5 class="mb-0">
						<i class="fas fa-phone-square-alt text-warning me-2"></i>
						Contacto de Emergencia
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<!-- Nombre del Contacto -->
						<div class="col-md-4">
							<label class="form-label fw-bold" for="contacto_emergencia_nombre">
								Nombre Completo
							</label>
							<input type="text" name="contacto_emergencia_nombre" id="contacto_emergencia_nombre"
								   maxlength="200" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['contacto_emergencia_nombre'] ?? ''); ?>">
						</div>
						
						<!-- Teléfono del Contacto -->
						<div class="col-md-4">
							<label class="form-label fw-bold" for="contacto_emergencia_telefono">
								Teléfono
							</label>
							<input type="tel" name="contacto_emergencia_telefono" id="contacto_emergencia_telefono"
								   maxlength="20" pattern="[0-9+\-\s()]+" 
								   class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['contacto_emergencia_telefono'] ?? ''); ?>">
						</div>
						
						<!-- Relación -->
						<div class="col-md-4">
							<label class="form-label fw-bold" for="contacto_emergencia_relacion">
								Relación
							</label>
							<select name="contacto_emergencia_relacion" id="contacto_emergencia_relacion"
									class="form-select">
								<option value="">Seleccione...</option>
								<option value="Padre/Madre" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Padre/Madre' ? 'selected' : ''; ?>>Padre/Madre</option>
								<option value="Hijo/Hija" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Hijo/Hija' ? 'selected' : ''; ?>>Hijo/Hija</option>
								<option value="Esposo/Esposa" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Esposo/Esposa' ? 'selected' : ''; ?>>Esposo/Esposa</option>
								<option value="Hermano/Hermana" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Hermano/Hermana' ? 'selected' : ''; ?>>Hermano/Hermana</option>
								<option value="Otro familiar" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Otro familiar' ? 'selected' : ''; ?>>Otro familiar</option>
								<option value="Amigo/Conocido" <?php echo ($paciente['contacto_emergencia_relacion'] ?? '') === 'Amigo/Conocido' ? 'selected' : ''; ?>>Amigo/Conocido</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- SECCIÓN 4: SEGURO MÉDICO -->
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white py-3">
					<h5 class="mb-0">
						<i class="fas fa-shield-alt text-success me-2"></i>
						Seguro Médico
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<!-- Nombre del Seguro -->
						<div class="col-md-6">
							<label class="form-label fw-bold" for="seguro_medico">
								Nombre del Seguro
							</label>
							<input type="text" name="seguro_medico" id="seguro_medico"
								   maxlength="100" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['seguro_medico'] ?? ''); ?>">
						</div>
						
						<!-- Número de Póliza -->
						<div class="col-md-6">
							<label class="form-label fw-bold" for="numero_poliza">
								Número de Póliza
							</label>
							<input type="text" name="numero_poliza" id="numero_poliza"
								   maxlength="50" class="form-control" 
								   value="<?php echo htmlspecialchars($paciente['numero_poliza'] ?? ''); ?>">
						</div>
					</div>
				</div>
			</div>

			<!-- SECCIÓN 5: ESTADO DEL PACIENTE -->
			<div class="card shadow-sm mb-4">
				<div class="card-header bg-white py-3">
					<h5 class="mb-0">
						<i class="fas fa-toggle-on text-info me-2"></i>
						Estado del Paciente
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label fw-bold" for="estado_paciente">
								Estado <span class="text-danger">*</span>
							</label>
							<select name="estado_paciente" id="estado_paciente" required class="form-select">
								<option value="activo" <?php echo ($paciente['estado_paciente'] ?? 'activo') === 'activo' ? 'selected' : ''; ?>>Activo</option>
								<option value="inactivo" <?php echo ($paciente['estado_paciente'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
								<option value="fallecido" <?php echo ($paciente['estado_paciente'] ?? '') === 'fallecido' ? 'selected' : ''; ?>>Fallecido</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- Botones de Acción -->
			<div class="card shadow-sm mb-4">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center">
						<div>
							<p class="text-muted mb-0">
								<i class="fas fa-info-circle me-1"></i>
								Los campos marcados con <span class="text-danger">*</span> son obligatorios
							</p>
						</div>
						<div>
							<a href="ver.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">
								<i class="fas fa-times me-2"></i>Cancelar
							</a>
							<button type="submit" class="btn btn-success btn-lg">
								<i class="fas fa-save me-2"></i>Guardar Cambios
							</button>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</main>

<script>
// Calcular edad automáticamente
function calcularEdad() {
	const fechaNac = new Date(document.getElementById('fecha_nacimiento').value);
	const hoy = new Date();
	let edad = hoy.getFullYear() - fechaNac.getFullYear();
	const mes = hoy.getMonth() - fechaNac.getMonth();
	
	if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
		edad--;
	}
	
	document.getElementById('edad-display').textContent = edad + ' años';
}

// Calcular edad al cargar la página
if (document.getElementById('fecha_nacimiento').value) {
	calcularEdad();
}

// Calcular edad cuando cambie la fecha
document.getElementById('fecha_nacimiento').addEventListener('change', calcularEdad);

// Validación del formulario
document.getElementById('form-editar-paciente').addEventListener('submit', function(e) {
	const nombres = document.getElementById('nombres').value.trim();
	const apellidos = document.getElementById('apellidos').value.trim();
	const numero_documento = document.getElementById('numero_documento').value.trim();
	const telefono = document.getElementById('telefono').value.trim();
	
	if (nombres.length < 2) {
		e.preventDefault();
		alert('El nombre debe tener al menos 2 caracteres');
		document.getElementById('nombres').focus();
		return false;
	}
	
	if (apellidos.length < 2) {
		e.preventDefault();
		alert('El apellido debe tener al menos 2 caracteres');
		document.getElementById('apellidos').focus();
		return false;
	}
	
	if (numero_documento.length < 5) {
		e.preventDefault();
		alert('El número de documento debe tener al menos 5 caracteres');
		document.getElementById('numero_documento').focus();
		return false;
	}
	
	if (telefono.length < 7) {
		e.preventDefault();
		alert('El teléfono debe tener al menos 7 dígitos');
		document.getElementById('telefono').focus();
		return false;
	}
	
	// Confirmar antes de enviar
	if (!confirm('¿Está seguro de guardar los cambios realizados?')) {
		e.preventDefault();
		return false;
	}
});

// Convertir a mayúsculas automáticamente
document.getElementById('nombres').addEventListener('input', function() {
	this.value = this.value.toUpperCase();
});

document.getElementById('apellidos').addEventListener('input', function() {
	this.value = this.value.toUpperCase();
});
</script>

<?php require_once '../../includes/footer.php'; ?>