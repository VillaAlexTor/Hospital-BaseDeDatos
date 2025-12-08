<?php
/**
 * =====================================================
 * ARCHIVO: modules/pacientes/registrar.php
 * Formulario de registro de nuevo paciente
 * =====================================================
 */

$page_title = "Registrar Nuevo Paciente";
require_once '../../includes/header.php';

// Verificar permisos
if (!has_any_role(['Administrador', 'Recepcionista'])) {
    die('<div class="alert alert-danger">No tienes permisos para registrar pacientes</div>');
}

$error = '';
$success = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            // Iniciar transacción
            $pdo->beginTransaction();
            
            // ========== DATOS PERSONALES ==========
            $tipo_documento = sanitize_input($_POST['tipo_documento']);
            $numero_documento = sanitize_input($_POST['numero_documento']);
            $nombres = sanitize_input($_POST['nombres']);
            $apellidos = sanitize_input($_POST['apellidos']);
            $fecha_nacimiento = $_POST['fecha_nacimiento'];
            $genero = $_POST['genero'];
            $telefono = sanitize_input($_POST['telefono']);
            $email = sanitize_input($_POST['email']);
            $direccion = sanitize_input($_POST['direccion']);
            $ciudad = sanitize_input($_POST['ciudad']);
            $pais = sanitize_input($_POST['pais'] ?? 'Bolivia');
            
            // Validar que no exista el documento
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM persona WHERE tipo_documento = ? AND numero_documento = ?");
            $stmt->execute([$tipo_documento, encrypt_data($numero_documento)]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ya existe una persona registrada con este documento");
            }
            
            // Insertar PERSONA (con datos encriptados)
            $stmt = $pdo->prepare("
                INSERT INTO persona (
                    tipo_documento, numero_documento, nombres, apellidos, 
                    fecha_nacimiento, genero, telefono, email, direccion, 
                    ciudad, pais, estado, usuario_crea
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?)
            ");
            
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
                $_SESSION['user_id']
            ]);
            
            $id_persona = $pdo->lastInsertId();
            
            // ========== DATOS MÉDICOS DEL PACIENTE ==========
            // Combinar grupo sanguíneo y factor RH
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
            
            // Contacto de emergencia
            $contacto_emergencia_nombre = sanitize_input($_POST['contacto_emergencia_nombre'] ?? '');
            $contacto_emergencia_telefono = sanitize_input($_POST['contacto_emergencia_telefono'] ?? '');
            $contacto_emergencia_relacion = sanitize_input($_POST['contacto_emergencia_relacion'] ?? '');
            
            // Seguro médico
            $seguro_medico = sanitize_input($_POST['seguro_medico'] ?? '');
            $numero_poliza = sanitize_input($_POST['numero_poliza'] ?? '');
            
            // Generar número de historia clínica automático
            $stmt = $pdo->query("SELECT COUNT(*) + 1 FROM paciente");
            $contador = $stmt->fetchColumn();
            $numero_historia = 'HC-' . date('Y') . '-' . str_pad($contador, 4, '0', STR_PAD_LEFT);
            
            // Insertar PACIENTE
            $stmt = $pdo->prepare("
                INSERT INTO paciente (
                    id_paciente, grupo_sanguineo, factor_rh, alergias, 
                    enfermedades_cronicas, contacto_emergencia_nombre, 
                    contacto_emergencia_telefono, contacto_emergencia_relacion,
                    seguro_medico, numero_poliza, fecha_primera_consulta,
                    numero_historia_clinica, estado_paciente
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'activo')
            ");
            
            $stmt->execute([
                $id_persona,
                $grupo_sanguineo,
                $factor_rh,
                $alergias,
                $enfermedades_cronicas,
                encrypt_data($contacto_emergencia_nombre),
                encrypt_data($contacto_emergencia_telefono),
                $contacto_emergencia_relacion,
                $seguro_medico,
                encrypt_data($numero_poliza),
                $numero_historia
            ]);
            
            // Crear historial clínico vacío
            $stmt = $pdo->prepare("
                INSERT INTO historial_clinico (id_paciente)
                VALUES (?)
            ");
            $stmt->execute([$id_persona]);
            
            // Registrar en auditoría
            log_action('INSERT', 'paciente', $id_persona, "Nuevo paciente registrado: $nombres $apellidos - HC: $numero_historia");
            
            // Commit de la transacción
            $pdo->commit();
            
            $success = "Paciente registrado exitosamente con Historia Clínica: <strong>$numero_historia</strong>";
            
            // Limpiar formulario después del éxito
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar paciente: " . $e->getMessage();
            error_log("Error registrando paciente: " . $e->getMessage());
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
                            <i class="fas fa-user-plus text-primary me-2"></i>
                            Registrar Nuevo Paciente
                        </h1>
                        <p class="text-muted mb-0">Complete todos los campos requeridos</p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
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
            <div class="mt-3">
                <a href="index.php" class="text-success text-decoration-underline fw-bold">Ver listado de pacientes</a>
                <span class="mx-2">|</span>
                <a href="registrar.php" class="text-success text-decoration-underline fw-bold">Registrar otro paciente</a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="" id="form-paciente" class="needs-validation" novalidate>
            <!-- TOKEN CSRF -->   
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
                                <option value="CI" selected>Cédula de Identidad</option>
                                <option value="Pasaporte">Pasaporte</option>
                                <option value="RUC">RUC</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        
                        <!-- Número de Documento -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="numero_documento">
                                Número de Documento <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="numero_documento" id="numero_documento" required
                                    maxlength="20" class="form-control" placeholder="Ej: 12345678">
                        </div>
                        
                        <!-- Nombres -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="nombres">
                                Nombres <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="nombres" id="nombres" required
                                    maxlength="100" class="form-control" placeholder="Ej: Juan Carlos">
                        </div>
                        
                        <!-- Apellidos -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="apellidos">
                                Apellidos <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="apellidos" id="apellidos" required
                                    maxlength="100" class="form-control" placeholder="Ej: Pérez López">
                        </div>
                        
                        <!-- Fecha de Nacimiento -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="fecha_nacimiento">
                                Fecha de Nacimiento <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required
                                    max="<?php echo date('Y-m-d'); ?>" class="form-control">
                            <small class="text-muted">Edad: <span id="edad-display">-</span></small>
                        </div>
                        
                        <!-- Género -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="genero">
                                Género <span class="text-danger">*</span>
                            </label>
                            <select name="genero" id="genero" required class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                                <option value="Prefiero no decir">Prefiero no decir</option>
                            </select>
                        </div>
                        
                        <!-- Teléfono -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="telefono">
                                Teléfono <span class="text-danger">*</span>
                            </label>
                            <input type="tel" name="telefono" id="telefono" required
                                    maxlength="20" pattern="[0-9+\-\s()]+" 
                                    class="form-control" placeholder="Ej: 71234567">
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="email">
                                Correo Electrónico
                            </label>
                            <input type="email" name="email" id="email"
                                    maxlength="100" class="form-control" 
                                    placeholder="Ej: correo@ejemplo.com">
                        </div>
                        
                        <!-- Dirección -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="direccion">
                                Dirección
                            </label>
                            <input type="text" name="direccion" id="direccion"
                                    maxlength="255" class="form-control" 
                                    placeholder="Ej: Av. 6 de Agosto #123">
                        </div>
                        
                        <!-- Ciudad -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="ciudad">
                                Ciudad <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="ciudad" id="ciudad" required
                                    maxlength="100" value="La Paz" 
                                    class="form-control" placeholder="Ej: La Paz">
                        </div>
                        
                        <!-- País -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="pais">
                                País
                            </label>
                            <input type="text" name="pais" id="pais"
                                    maxlength="100" value="Bolivia" class="form-control">
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
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="AB">AB</option>
                                <option value="O">O</option>
                            </select>
                        </div>
                        
                        <!-- Factor RH -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="factor_rh">
                                Factor RH
                            </label>
                            <select name="factor_rh" id="factor_rh" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="+">Positivo (+)</option>
                                <option value="-">Negativo (-)</option>
                            </select>
                        </div>
                        
                        <!-- Alergias -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="alergias">
                                Alergias Conocidas
                            </label>
                            <textarea name="alergias" id="alergias" rows="3"
                                      class="form-control"
                                      placeholder="Ej: Penicilina, mariscos, polen..."></textarea>
                        </div>
                        
                        <!-- Enfermedades Crónicas -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold" for="enfermedades_cronicas">
                                Enfermedades Crónicas
                            </label>
                            <textarea name="enfermedades_cronicas" id="enfermedades_cronicas" rows="3"
                                      class="form-control"
                                      placeholder="Ej: Diabetes tipo 2, hipertensión arterial..."></textarea>
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
                                   maxlength="200" class="form-control" placeholder="Ej: María Pérez">
                        </div>
                        
                        <!-- Teléfono del Contacto -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="contacto_emergencia_telefono">
                                Teléfono
                            </label>
                            <input type="tel" name="contacto_emergencia_telefono" id="contacto_emergencia_telefono"
                                   maxlength="20" pattern="[0-9+\-\s()]+" 
                                   class="form-control" placeholder="Ej: 72345678">
                        </div>
                        
                        <!-- Relación -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="contacto_emergencia_relacion">
                                Relación
                            </label>
                            <select name="contacto_emergencia_relacion" id="contacto_emergencia_relacion"
                                    class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="Padre/Madre">Padre/Madre</option>
                                <option value="Hijo/Hija">Hijo/Hija</option>
                                <option value="Esposo/Esposa">Esposo/Esposa</option>
                                <option value="Hermano/Hermana">Hermano/Hermana</option>
                                <option value="Otro familiar">Otro familiar</option>
                                <option value="Amigo/Conocido">Amigo/Conocido</option>
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
                                   placeholder="Ej: Seguro Universal de Salud">
                        </div>
                        
                        <!-- Número de Póliza -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="numero_poliza">
                                Número de Póliza
                            </label>
                            <input type="text" name="numero_poliza" id="numero_poliza"
                                   maxlength="50" class="form-control" 
                                   placeholder="Ej: POL-123456">
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
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Registrar Paciente
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
document.getElementById('fecha_nacimiento').addEventListener('change', function() {
    const fechaNac = new Date(this.value);
    const hoy = new Date();
    let edad = hoy.getFullYear() - fechaNac.getFullYear();
    const mes = hoy.getMonth() - fechaNac.getMonth();
    
    if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
        edad--;
    }
    
    document.getElementById('edad-display').textContent = edad + ' años';
});

// Validación del formulario
document.getElementById('form-paciente').addEventListener('submit', function(e) {
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
    if (!confirm('¿Está seguro de registrar este paciente con los datos ingresados?')) {
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