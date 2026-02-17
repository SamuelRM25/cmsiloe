<?php
// hospitalization/ingresar_paciente.php - Formulario de Ingreso de Paciente
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];

// Fetch available beds
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get available beds grouped by room
    $stmt_beds = $conn->query("
        SELECT 
            c.id_cama,
            c.numero_cama,
            c.estado,
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            h.descripcion
        FROM camas c
        INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
        WHERE c.estado = 'Disponible' AND h.estado != 'Mantenimiento'
        ORDER BY h.piso, h.numero_habitacion, c.numero_cama
    ");
    $available_beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);

    // Get doctors
    $stmt_docs = $conn->query("
        SELECT idUsuario, nombre, apellido, especialidad 
        FROM usuarios 
        WHERE tipoUsuario IN ('admin', 'doc')
        ORDER BY nombre
    ");
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    // Get patients for search
    $stmt_patients = $conn->query("
        SELECT id_paciente, nombre, apellido, fecha_nacimiento, genero
        FROM pacientes
        ORDER BY nombre, apellido
    ");
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar Paciente - Hospitalización</title>

    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .bed-card-selection {
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            color: var(--dash-dark);
        }
        .btn-check:checked + .bed-card-selection {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        .bed-card-selection:hover {
            border-color: #3b82f6;
            background-color: rgba(59, 130, 246, 0.05);
        }
    </style>
</head>

<body>
    <div class="dashboard-wrapper">
        <!-- Mobile Overlay -->
        <div class="dashboard-mobile-overlay"></div>





        <!-- Main Content Glass -->
        <!-- Main Content Glass -->
        <div class="container py-4">
            <div class="container-fluid">
                <!-- Header Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card-glass p-4" style="background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-1">
                                        <i class="bi bi-person-plus-fill me-2 text-primary"></i>Ingreso de Paciente
                                    </h2>
                                    <p class="text-muted mb-0">
                                        Complete el formulario para iniciar la estancia hospitalaria interna.
                                    </p>
                                </div>
                                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                                    <i class="bi bi-arrow-left me-2"></i>Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="ingresoForm" action="api/create_ingreso.php" method="POST">
                    <div class="row g-4">
                        <!-- Left Column: Patient & Details -->
                        <div class="col-lg-7">
                            <!-- Datos del Paciente -->
                            <div class="card-glass mb-4">
                                <div class="card-header-glass">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-person-vcard me-2"></i>Datos del Paciente</h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-12" id="search_paciente_div">
                                            <label class="form-label fw-bold">Buscar Paciente Existente</label>
                                            <select class="form-select" id="paciente_select" name="id_paciente">
                                                <option value="">Seleccionar paciente...</option>
                                                <?php foreach ($patients as $pac): ?>
                                                    <option value="<?php echo $pac['id_paciente']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?>"
                                                            data-nacimiento="<?php echo $pac['fecha_nacimiento']; ?>"
                                                            data-genero="<?php echo $pac['genero']; ?>"
                                                            <?php echo (isset($_GET['id_paciente']) && $_GET['id_paciente'] == $pac['id_paciente']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?> - 
                                                        <?php echo date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y; ?> años
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6" id="referido_nombre_div" style="display: none;">
                                            <label class="form-label fw-bold">Nombres (Referido)</label>
                                            <input type="text" class="form-control" name="referido_nombre" id="referido_nombre">
                                        </div>
                                        <div class="col-md-6" id="referido_apellido_div" style="display: none;">
                                            <label class="form-label fw-bold">Apellidos (Referido)</label>
                                            <input type="text" class="form-control" name="referido_apellido" id="referido_apellido">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalles del Ingreso -->
                            <div class="card-glass">
                                <div class="card-header-glass">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-clipboard-pulse me-2"></i>Detalles del Ingreso</h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                                                Fecha y Hora
                                                <button type="button" id="btn_retrasado" class="btn btn-xs btn-outline-warning py-0" style="font-size: 10px;">
                                                    <i class="bi bi-clock-history"></i> Retrasado
                                                </button>
                                            </label>
                                            <input type="datetime-local" class="form-control" name="fecha_ingreso" id="fecha_ingreso" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                            <input type="hidden" name="is_retrasado" id="is_retrasado" value="0">
                                        </div>

                                        <div class="col-md-6" id="div_fecha_alta" style="display: none;">
                                            <label class="form-label fw-bold">Fecha y Hora de Alta</label>
                                            <input type="datetime-local" class="form-control" name="fecha_alta" id="fecha_alta">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tipo de Ingreso</label>
                                            <select class="form-select" name="tipo_ingreso" required>
                                                <option value="Programado">Programado</option>
                                                <option value="Emergencia" selected>Emergencia</option>
                                                <option value="Referido">Referido</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6" id="doctor_select_div">
                                            <label class="form-label fw-bold">Médico Responsable</label>
                                            <select class="form-select" id="id_doctor" name="id_doctor">
                                                <option value="">Seleccionar médico...</option>
                                                <?php foreach ($doctors as $doc): ?>
                                                    <option value="<?php echo $doc['idUsuario']; ?>">Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6" id="referido_doctor_div" style="display: none;">
                                            <label class="form-label fw-bold">Médico Referente</label>
                                            <input type="text" class="form-control" name="referido_doctor" id="referido_doctor" placeholder="Dr. Nombre Apellido">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold">Motivo de Ingreso</label>
                                            <textarea class="form-control" name="motivo_ingreso" rows="2" required placeholder="Motivo principal..."></textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold">Diagnóstico Presuntivo</label>
                                            <input type="text" class="form-control" name="diagnostico_ingreso" placeholder="Ej: Apendicitis aguda" required>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold">Notas Adicionales</label>
                                            <textarea class="form-control" name="notas_ingreso" rows="2" placeholder="Observaciones..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Bed Selection -->
                        <div class="col-lg-5">
                            <div class="card-glass h-100">
                                <div class="card-header-glass">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-hospital me-2"></i>Asignación de Cama</h5>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (count($available_beds) > 0): ?>
                                        <div class="bed-selection-container" style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                            <div class="row g-3">
                                                <?php foreach ($available_beds as $bed): ?>
                                                    <div class="col-12">
                                                        <input type="radio" class="btn-check" name="id_cama" id="bed_<?php echo $bed['id_cama']; ?>" value="<?php echo $bed['id_cama']; ?>" required>
                                                        <label class="bed-card-selection text-start w-100 p-3 rounded-4 shadow-sm" for="bed_<?php echo $bed['id_cama']; ?>">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <div class="fw-bold fs-5">Hab. <?php echo htmlspecialchars($bed['numero_habitacion']); ?> - Cama <?php echo htmlspecialchars($bed['numero_cama']); ?></div>
                                                                    <div class="text-xs text-muted">
                                                                        <?php echo htmlspecialchars($bed['descripcion']); ?> - <?php echo htmlspecialchars($bed['tipo_habitacion']); ?> (Piso <?php echo htmlspecialchars($bed['piso']); ?>)
                                                                    </div>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="text-primary fw-bold">Q<?php echo number_format($bed['tarifa_por_noche'], 2); ?></div>
                                                                    <small class="text-muted text-xs">por noche</small>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center p-5">
                                            <i class="bi bi-exclamation-triangle fs-1 text-warning opacity-50 mb-3"></i>
                                            <p class="text-muted">No hay camas disponibles actualmente.</p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-4 pt-4 border-top">
                                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill shadow-sm" <?php echo (count($available_beds) == 0 ? 'disabled' : ''); ?>>
                                            <i class="bi bi-check-circle-fill me-2"></i>Confirmar Ingreso
                                        </button>
                                        <button type="button" class="btn btn-link w-100 mt-2 text-muted text-decoration-none" onclick="window.location.href='index.php'">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../assets/js/dashboard-reengineered.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#paciente_select').select2({
                placeholder: 'Buscar paciente...',
                width: '100%'
            });

            const tipoIngresoSelect = $('select[name="tipo_ingreso"]');
            const searchPacienteDiv = $('#search_paciente_div');
            const referidoNombreDiv = $('#referido_nombre_div');
            const referidoApellidoDiv = $('#referido_apellido_div');
            const doctorSelectDiv = $('#doctor_select_div');
            const referidoDoctorDiv = $('#referido_doctor_div');

            function togglePatientInput() {
                if (tipoIngresoSelect.val() === 'Referido') {
                    searchPacienteDiv.hide();
                    $('#paciente_select').prop('required', false);
                    referidoNombreDiv.show();
                    $('#referido_nombre').prop('required', true);
                    referidoApellidoDiv.show();
                    $('#referido_apellido').prop('required', true);
                    doctorSelectDiv.hide();
                    $('#id_doctor').prop('required', false);
                    referidoDoctorDiv.show();
                    $('#referido_doctor').prop('required', true);
                } else {
                    searchPacienteDiv.show();
                    $('#paciente_select').prop('required', true);
                    referidoNombreDiv.hide();
                    $('#referido_nombre').prop('required', false);
                    referidoApellidoDiv.hide();
                    $('#referido_apellido').prop('required', false);
                    doctorSelectDiv.show();
                    $('#id_doctor').prop('required', true);
                    referidoDoctorDiv.hide();
                    $('#referido_doctor').prop('required', false);
                }
            }

            tipoIngresoSelect.on('change', togglePatientInput);
            togglePatientInput();

            // Retrasado logic
            $('#btn_retrasado').on('click', function() {
                const isRetrasado = $('#is_retrasado');
                const divFechaAlta = $('#div_fecha_alta');
                const inputFechaAlta = $('#fecha_alta');
                
                if (isRetrasado.val() === '0') {
                    isRetrasado.val('1');
                    $(this).removeClass('btn-outline-warning').addClass('btn-warning');
                    divFechaAlta.fadeIn();
                    inputFechaAlta.prop('required', true);
                } else {
                    isRetrasado.val('0');
                    $(this).removeClass('btn-warning').addClass('btn-outline-warning');
                    divFechaAlta.fadeOut();
                    inputFechaAlta.prop('required', false);
                }
            });

            // Form submission
            $('#ingresoForm').on('submit', function(e) {
                e.preventDefault();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalContent = submitBtn.html();

                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Procesando...');

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: 'Paciente ingresado correctamente',
                                    confirmButtonColor: '#3a56b7'
                                }).then(() => {
                                    window.location.href = 'detalle_encamamiento.php?id=' + data.id_encamamiento;
                                });
                            } else {
                                throw new Error(data.message || 'Error al procesar el ingreso');
                            }
                        } catch (err) {
                            Swal.fire('Error', err.message, 'error');
                            submitBtn.prop('disabled', false).html(originalContent);
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                        submitBtn.prop('disabled', false).html(originalContent);
                    }
                });
            });
        });
    </script>
</body>

</html>