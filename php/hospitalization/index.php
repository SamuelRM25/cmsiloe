<?php
// hospitalization/index.php - Dashboard Principal de Encamamiento - Centro Médico Herrera Saenz
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();

// Set timezone
date_default_timezone_set('America/Guatemala');

// Verificar permisos
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Personal';

// Check permissions based on role
$allowed_roles = ['admin', 'doc', 'user'];
if (!in_array($user_type, $allowed_roles)) {
    header("Location: ../dashboard/index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ====================================
    // FETCH DASHBOARD DATA
    // ====================================

    // Total de camas
    $stmt_total_beds = $conn->query("SELECT COUNT(*) as total FROM camas");
    $total_beds = $stmt_total_beds->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas ocupadas
    $stmt_occupied = $conn->query("SELECT COUNT(*) as total FROM camas WHERE estado = 'Ocupada'");
    $camas_ocupadas = $stmt_occupied->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas disponibles
    $camas_disponibles = $total_beds - $camas_ocupadas;

    // Porcentaje de ocupación
    $porcentaje_ocupacion = $total_beds > 0 ? round(($camas_ocupadas / $total_beds) * 100, 1) : 0;

    // Total pacientes activos (hospitalizados)
    $stmt_active = $conn->query("SELECT COUNT(*) as total FROM encamamientos WHERE estado = 'Activo'");
    $pacientes_activos = $stmt_active->fetch(PDO::FETCH_ASSOC)['total'];

    // Ingresos hoy
    $stmt_today = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_ingreso) = CURDATE()");
    $stmt_today->execute();
    $ingresos_hoy = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'];

    // Altas hoy
    $stmt_altas = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_alta) = CURDATE() AND estado IN ('Alta_Medica', 'Alta_Administrativa')");
    $stmt_altas->execute();
    $altas_hoy = $stmt_altas->fetch(PDO::FETCH_ASSOC)['total'];

    // Estancia promedio (últimos 30 días)
    $stmt_estancia = $conn->query("
        SELECT AVG(DATEDIFF(COALESCE(fecha_alta, NOW()), fecha_ingreso)) as promedio
        FROM encamamientos
        WHERE fecha_ingreso >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $estancia_promedio = round($stmt_estancia->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);

    // Lista de habitaciones con estado
    $stmt_rooms = $conn->query("
        SELECT 
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            h.capacidad_maxima,
            COUNT(c.id_cama) as total_camas,
            SUM(CASE WHEN c.estado = 'Ocupada' THEN 1 ELSE 0 END) as camas_ocupadas,
            h.estado as estado_habitacion
        FROM habitaciones h
        LEFT JOIN camas c ON h.id_habitacion = c.id_habitacion
        GROUP BY h.id_habitacion
        ORDER BY h.piso, h.numero_habitacion
    ");
    $habitaciones = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

    // Pacientes actualmente hospitalizados
    $stmt_patients = $conn->query("
        SELECT 
            e.id_encamamiento,
            e.id_paciente,
            e.fecha_ingreso,
            e.diagnostico_ingreso,
            e.tipo_ingreso,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            pac.fecha_nacimiento,
            pac.genero,
            hab.numero_habitacion,
            hab.tipo_habitacion,
            c.numero_cama,
            u.nombre as nombre_doctor,
            u.apellido as apellido_doctor,
            DATEDIFF(CURDATE(), DATE(e.fecha_ingreso)) as dias_hospitalizado,
            (SELECT COUNT(*) FROM signos_vitales WHERE id_encamamiento = e.id_encamamiento AND DATE(fecha_registro) = CURDATE()) as signos_hoy
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        INNER JOIN camas c ON e.id_cama = c.id_cama
        INNER JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        WHERE e.estado = 'Activo'
        ORDER BY e.fecha_ingreso DESC
    ");
    $pacientes_hospitalizados = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Gestión de Hospitalización - Centro Médico Herrera Saenz";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- External Resources (Fonts & Icons only) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #2563eb;
            /* Royal Blue */
            --secondary-color: #64748b;
            /* Slate */
            --success-color: #10b981;
            /* Emerald */
            --warning-color: #f59e0b;
            /* Amber */
            --danger-color: #ef4444;
            /* Red */
            --info-color: #06b6d4;
            /* Cyan */
            --bg-color: #f3f4f6;
            /* Light Gray Background */
            --card-bg: #ffffff;
            /* White */
            --text-main: #1f2937;
            /* Dark Gray */
            --text-muted: #6b7280;
            /* Medium Gray */
            --sidebar-width: 260px;
            --header-height: 70px;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* --- Sidebar Styles --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .brand img {
            height: 40px;
            margin-right: 12px;
        }

        .brand span {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-color);
        }

        .nav-menu {
            padding: 1.5rem 1rem;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--secondary-color);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background-color: #f3f4f6;
            color: var(--primary-color);
        }

        .nav-item.active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .nav-item i {
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .user-profile {
            padding: 1rem;
            border-top: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* --- Main Content --- */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }

        .page-subtitle {
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        /* --- Tables --- */
        .content-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .custom-table th {
            background-color: #f9fafb;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .custom-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            color: #374151;
            font-size: 0.9rem;
        }

        .custom-table tr:hover {
            background-color: #f9fafb;
        }

        /* --- Badges --- */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }

        .bg-success-light {
            background-color: #d1fae5;
            color: #065f46;
        }

        .bg-warning-light {
            background-color: #fef3c7;
            color: #92400e;
        }

        .bg-danger-light {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .bg-info-light {
            background-color: #cffafe;
            color: #155e75;
        }

        /* --- Bed Grid --- */
        .bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .room-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.2s;
        }

        .room-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }

        .bed-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
            margin-top: 0.75rem;
            border: 1px solid transparent;
        }

        .bed-item.occupied {
            background: #fef2f2;
            border-color: #fee2e2;
        }

        .bed-item.free {
            background: #ecfdf5;
            border-color: #d1fae5;
        }

        /* --- Mobile Toggle --- */
        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: none;
            z-index: 1100;
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-toggle {
                display: flex;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .overlay.active {
                display: block;
            }
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            background: white;
            color: var(--secondary-color);
            transition: all 0.2s;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: #eff6ff;
        }
    </style>
</head>

<body>
    <!-- Mobile Overlay -->
    <div class="overlay" id="mobileOverlay"></div>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="brand">
            <img src="../../assets/img/siloe.png" alt="Logo">
        </div>

        <div class="nav-menu">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>

            <?php if (in_array($rol, ['admin', 'doc', 'user'])): ?>
                <a href="../dashboard/index.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <?php if ($rol !== 'doc'): // Docs don't usually see Patients list as main nav option? Keeping existing logic safe ?>
                    <a href="../patients/index.php" class="nav-item">
                        <i class="bi bi-people"></i> Pacientes
                    </a>
                <?php else: ?>
                    <a href="../patients/index.php" class="nav-item">
                        <i class="bi bi-people"></i> Pacientes
                    </a>
                <?php endif; ?>
                <a href="../hospitalization/index.php" class="nav-item active">
                    <i class="bi bi-hospital"></i> Hospitalización
                </a>
            <?php endif; ?>

            <?php if (in_array($rol, ['admin', 'user'])): ?>
                <a href="../appointments/index.php" class="nav-item">
                    <i class="bi bi-calendar"></i> Citas
                </a>
                <a href="../minor_procedures/index.php" class="nav-item">
                    <i class="bi bi-bandaid"></i> Proc. Menores
                </a>
                <a href="../examinations/index.php" class="nav-item">
                    <i class="bi bi-file-earmark-medical"></i> Exámenes
                </a>
                <a href="../dispensary/index.php" class="nav-item">
                    <i class="bi bi-cart4"></i> Dispensario
                </a>
                <a href="../inventory/index.php" class="nav-item">
                    <i class="bi bi-box-seam"></i> Inventario
                </a>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
                <a href="../purchases/index.php" class="nav-item">
                    <i class="bi bi-cart-plus"></i> Compras
                </a>
                <a href="../sales/index.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Ventas
                </a>
                <a href="../reports/index.php" class="nav-item">
                    <i class="bi bi-bar-chart-line"></i> Reportes
                </a>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
                <a href="../billing/index.php" class="nav-item">
                    <i class="bi bi-cash-coin"></i> Cobros
                </a>
            <?php endif; ?>
        </div>

        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
            </div>
            <div style="flex: 1; overflow: hidden;">
                <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    <?php echo htmlspecialchars($_SESSION['especialidad'] ?? 'Usuario'); ?>
                </div>
            </div>
            <a href="../auth/logout.php" class="text-danger" title="Cerrar Sesión">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Hospitalización</h1>
                <div class="page-subtitle">Gestión de camas, ingresos y altas médicas</div>
            </div>
            <div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" style="border-radius: 8px;" onclick="openDischargesModal()">
                        <i class="bi bi-file-earmark-medical me-2"></i>Reporte Altas
                    </button>
                    <a href="ingresar_paciente.php" class="btn btn-primary" style="border-radius: 8px;">
                        <i class="bi bi-person-plus me-2"></i>Nuevo Ingreso
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Active Patients -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon bg-info-light text-info">
                        <i class="bi bi-hospital"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $pacientes_activos; ?></div>
                <div class="stat-label">Pacientes Ingresados</div>
            </div>

            <!-- Admissions Today -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon bg-success-light text-success">
                        <i class="bi bi-person-plus"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $ingresos_hoy; ?></div>
                <div class="stat-label">Ingresos Hoy</div>
            </div>

            <!-- Discharges Today -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon bg-warning-light text-warning">
                        <i class="bi bi-door-open"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $altas_hoy; ?></div>
                <div class="stat-label">Altas Hoy</div>
            </div>

            <!-- Bed Occupancy -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="bi bi-pie-chart"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $porcentaje_ocupacion; ?>%</div>
                <div class="stat-label">Ocupación (<?php echo $camas_disponibles; ?> libres)</div>
            </div>
        </div>

        <!-- Patients Table -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="bi bi-person-lines-fill text-primary"></i>
                    Pacientes Hospitalizados
                </h2>
                <div style="width: 300px;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar paciente...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="patientsTable">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Ubicación</th>
                            <th>Diagnóstico</th>
                            <th>Médico</th>
                            <th>Ingreso</th>
                            <th>Días</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pacientes_hospitalizados) > 0): ?>
                            <?php foreach ($pacientes_hospitalizados as $pac): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar"
                                                style="width: 32px; height: 32px; font-size: 0.8rem; margin-right: 12px; background: #e0e7ff; color: var(--primary-color);">
                                                <?php echo strtoupper(substr($pac['nombre_paciente'], 0, 1) . substr($pac['apellido_paciente'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($pac['nombre_paciente'] . ' ' . $pac['apellido_paciente']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($pac['genero']); ?>,
                                                    <?php echo date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y; ?>
                                                    años</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-badge bg-info-light">
                                            Hab. <?php echo htmlspecialchars($pac['numero_habitacion']); ?> - Cama
                                            <?php echo htmlspecialchars($pac['numero_cama']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($pac['diagnostico_ingreso']); ?></td>
                                    <td>Dr(a). <?php echo htmlspecialchars($pac['nombre_doctor']); ?></td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($pac['fecha_ingreso'])); ?></div>
                                        <small
                                            class="text-muted"><?php echo date('H:i', strtotime($pac['fecha_ingreso'])); ?></small>
                                    </td>
                                    <td class="fw-bold"><?php echo $pac['dias_hospitalizado']; ?></td>
                                    <td class="text-end">
                                        <button class="action-btn"
                                            onclick="viewPatientDetails(<?php echo $pac['id_encamamiento']; ?>)"
                                            title="Ver Detalles">
                                            <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    No hay pacientes hospitalizados actualmente
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bed Availability Grid -->
        <h3 class="fw-bold mb-4" style="color: #374151;">Disponibilidad de Camas</h3>
        <div class="bed-grid">
            <?php foreach ($habitaciones as $hab):
                $status_color = $hab['camas_ocupadas'] >= $hab['total_camas'] ? 'border-danger' :
                    ($hab['camas_ocupadas'] > 0 ? 'border-warning' : 'border-success');
                ?>
                <div class="room-card"
                    style="border-left: 4px solid <?php echo ($hab['camas_ocupadas'] >= $hab['total_camas']) ? '#ef4444' : '#10b981'; ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Habitación <?php echo htmlspecialchars($hab['numero_habitacion']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($hab['tipo_habitacion']); ?></small>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <?php
                        $stmt_beds = $conn->prepare("SELECT numero_cama, estado FROM camas WHERE id_habitacion = ? ORDER BY numero_cama");
                        $stmt_beds->execute([$hab['id_habitacion']]);
                        $beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($beds as $bed):
                            $is_occupied = strtolower($bed['estado']) === 'ocupada';
                            $bed_class = $is_occupied ? 'occupied' : 'free';
                            $icon_class = $is_occupied ? 'bi-person-fill text-danger' : 'bi-check-circle-fill text-success';
                            ?>
                            <div class="bed-item <?php echo $bed_class; ?>">
                                <i class="<?php echo $icon_class; ?> me-2"></i>
                                <span class="fw-medium">Cama <?php echo htmlspecialchars($bed['numero_cama']); ?></span>
                                <span
                                    class="ms-auto text-xs badge <?php echo $is_occupied ? 'bg-danger-light' : 'bg-success-light'; ?>">
                                    <?php echo $is_occupied ? 'Ocupada' : 'Libre'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Logic
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('mobileOverlay');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Search Functionality
        const searchInput = document.getElementById('searchInput');
        const tableRows = document.querySelectorAll('#patientsTable tbody tr');

        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const term = e.target.value.toLowerCase();
                tableRows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

        function viewPatientDetails(id) {
            window.location.href = `detalle_encamamiento.php?id=${id}`;
        }

        function openDischargesModal() {
            // Implementation specific to discharge modal if needed, 
            // otherwise redirect to reports or show simple alert
            Swal.fire({
                title: 'Reporte de Altas',
                text: 'Funcionalidad en desarrollo para reporte detallado.',
                icon: 'info'
            });
        }
    </script>

    <!-- Modal Reporte de Altas (Reintegrated for completeness) -->
    <div class="modal fade" id="dischargesModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Reporte de Altas y
                        Facturación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-xs fw-bold text-muted uppercase">Fecha Inicio</label>
                            <input type="date" class="form-control" id="report_start_date"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-xs fw-bold text-muted uppercase">Fecha Fin</label>
                            <input type="date" class="form-control" id="report_end_date"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100 py-2 rounded-pill"
                                onclick="generateDischargesReport()">
                                <i class="bi bi-search me-2"></i>Generar Reporte
                            </button>
                        </div>
                    </div>

                    <div id="report_results_container" style="display: none;">
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-xs uppercase">Fecha Alta</th>
                                        <th class="text-xs uppercase">Paciente</th>
                                        <th class="text-xs uppercase">Tipo Ingreso</th>
                                        <th class="text-xs uppercase">Médico</th>
                                        <th class="text-xs uppercase">Días</th>
                                        <th class="text-xs uppercase text-end">Total Generado</th>
                                        <th class="text-xs uppercase text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="report_table_body"></tbody>
                                <tfoot class="bg-light fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end">TOTAL GENERAL:</td>
                                        <td class="text-end text-primary" id="report_total_amount">Q0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Modal logic for discharges (re-adding specific logic)
        const dischargeModal = new bootstrap.Modal(document.getElementById('dischargesModal'));
        function openDischargesModal() {
            dischargeModal.show();
        }

        async function generateDischargesReport() {
            const start = document.getElementById('report_start_date').value;
            const end = document.getElementById('report_end_date').value;
            const container = document.getElementById('report_results_container');
            const tbody = document.getElementById('report_table_body');
            const totalElem = document.getElementById('report_total_amount');

            if (!start || !end) {
                Swal.fire('Error', 'Debe seleccionar ambas fechas', 'error');
                return;
            }

            try {
                const response = await fetch(`api/get_discharges_report.php?start=${start}&end=${end}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al obtener el reporte');
                }

                tbody.innerHTML = '';
                let totalGeneral = 0;

                if (data.report.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron altas en este periodo</td></tr>';
                } else {
                    data.report.forEach(row => {
                        const total = parseFloat(row.total_general) || 0;
                        totalGeneral += total;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                        <td class="align-middle">${new Date(row.fecha_alta).toLocaleDateString()}</td>
                        <td class="align-middle"><strong>${row.nombre_paciente} ${row.apellido_paciente}</strong></td>
                        <td class="align-middle"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${row.tipo_ingreso}</span></td>
                        <td class="align-middle">Dr(a). ${row.nombre_doctor}</td>
                        <td class="align-middle text-center">${row.dias_hospitalizado}</td>
                        <td class="align-middle text-end fw-bold">Q${total.toLocaleString('es-GT', { minimumFractionDigits: 2 })}</td>
                        <td class="text-center">
                            <button class="action-btn" style="width:32px; height:32px;" onclick="viewPatientDetails(${row.id_encamamiento})">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </td>
                    `;
                        tbody.appendChild(tr);
                    });
                }

                totalElem.innerText = `Q${totalGeneral.toLocaleString('es-GT', { minimumFractionDigits: 2 })}`;
                container.style.display = 'block';

            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        }
    </script>
</body>

</html>