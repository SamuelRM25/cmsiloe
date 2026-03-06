<?php
// hospitalization/print_alta.php - Resumen de Alta Médica
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();

$id_encamamiento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_encamamiento == 0) {
    die("ID de encamamiento no válido");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch details
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            pac.fecha_nacimiento,
            pac.genero,
            hab.numero_habitacion,
            c.numero_cama,
            u.nombre as doctor_nombre,
            u.apellido as doctor_apellido,
            ch.total_general
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        INNER JOIN camas c ON e.id_cama = c.id_cama
        INNER JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        LEFT JOIN cuenta_hospitalaria ch ON e.id_encamamiento = ch.id_encamamiento
        WHERE e.id_encamamiento = ?
    ");
    $stmt->execute([$id_encamamiento]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Registro no encontrado");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resumen de Alta -
        <?php echo htmlspecialchars($data['nombre_paciente'] . ' ' . $data['apellido_paciente']); ?>
    </title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 40px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            color: #2563eb;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9rem;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 10px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .col {
            flex: 1;
            min-width: 200px;
        }

        .label {
            font-weight: 600;
            width: 150px;
            display: inline-block;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @media print {
            .no-print {
                display: none;
            }
        }

        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button class="btn-print" onclick="window.print()">Imprimir Reporte</button>
    </div>

    <div class="header">
        <h1>Centro Médico Siloé</h1>
        <p>Resumen de Egreso Hospitalario</p>
    </div>

    <div class="section">
        <div class="section-title">Información del Paciente</div>
        <div class="row">
            <div class="col"><span class="label">Paciente:</span>
                <?php echo htmlspecialchars($data['nombre_paciente'] . ' ' . $data['apellido_paciente']); ?>
            </div>
            <div class="col"><span class="label">Género:</span>
                <?php echo htmlspecialchars($data['genero']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col"><span class="label">Habitación:</span>
                <?php echo htmlspecialchars($data['numero_habitacion']); ?>
            </div>
            <div class="col"><span class="label">Cama:</span>
                <?php echo htmlspecialchars($data['numero_cama']); ?>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Detalles del Internamiento</div>
        <div class="row">
            <div class="col"><span class="label">Fecha Ingreso:</span>
                <?php echo date('d/m/Y H:i', strtotime($data['fecha_ingreso'])); ?>
            </div>
            <div class="col"><span class="label">Fecha Egreso:</span>
                <?php echo date('d/m/Y H:i', strtotime($data['fecha_alta'])); ?>
            </div>
        </div>
        <div class="row">
            <div class="col"><span class="label">Médico Responsable:</span> Dr(a).
                <?php echo htmlspecialchars($data['doctor_nombre'] . ' ' . $data['doctor_apellido']); ?>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Diagnóstico y Notas de Egreso</div>
        <div class="row">
            <div class="col"><span class="label">Diagnóstico Final:</span><br>
                <?php echo nl2br(htmlspecialchars($data['diagnostico_egreso'])); ?>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col"><span class="label">Notas de Alta:</span><br>
                <?php echo nl2br(htmlspecialchars($data['notas_alta'] ?? 'Sin notas adicionales.')); ?>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Resumen Administrativo</div>
        <div class="row">
            <div class="col"><span class="label">Total General:</span> <strong
                    style="font-size: 1.2rem; color: #2563eb;">Q
                    <?php echo number_format($data['total_general'], 2); ?>
                </strong></div>
        </div>
    </div>

    <div class="footer">
        <p>Este documento es un resumen clínico y administrativo. Para facturación detallada, solicite su estado de
            cuenta.</p>
        <p>Generado el
            <?php echo date('d/m/Y H:i'); ?> por
            <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>
        </p>
    </div>
</body>

</html>