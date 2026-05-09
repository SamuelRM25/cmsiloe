<?php
// includes/sidebar.php
$rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Base path helper
function get_base_path($target_dir) {
    // This is a simplified helper to handle the relative paths from different levels
    // Since we are usually in php/[module]/index.php, we need to go up two levels
    return "../$target_dir/index.php";
}
?>

<div class="sidebar-glass p-3 d-flex flex-column">
    <div class="brand-section d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center text-decoration-none">
            <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"/>
        </div>
        <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width: 32px; height: 32px;">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
        <li class="nav-item">
            <a href="../dashboard/index.php" class="nav-link <?php echo ($current_dir === 'dashboard') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="../patients/index.php" class="nav-link <?php echo ($current_dir === 'patients') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Pacientes
            </a>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'user'): ?>
        <li>
            <a href="../appointments/index.php" class="nav-link <?php echo ($current_dir === 'appointments') ? 'active' : ''; ?>">
                <i class="bi bi-calendar"></i> Citas
            </a>
        </li>
        <li>
            <a href="../minor_procedures/index.php" class="nav-link <?php echo ($current_dir === 'minor_procedures') ? 'active' : ''; ?>">
                <i class="bi bi-bandaid"></i> Proc. Menores
            </a>
        </li>
        <li>
            <a href="../examinations/index.php" class="nav-link <?php echo ($current_dir === 'examinations') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-medical"></i> Exámenes
            </a>
        </li>
        <li>
            <a href="../dispensary/index.php" class="nav-link <?php echo ($current_dir === 'dispensary') ? 'active' : ''; ?>">
                <i class="bi bi-cart4"></i> Dispensario
            </a>
        </li>
        <li>
            <a href="../inventory/index.php" class="nav-link <?php echo ($current_dir === 'inventory') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Inventario
            </a>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin'): ?>
        <li>
            <a href="../purchases/index.php" class="nav-link <?php echo ($current_dir === 'purchases') ? 'active' : ''; ?>">
                <i class="bi bi-cart-plus"></i> Compras
            </a>
        </li>
        <li>
            <a href="../sales/index.php" class="nav-link <?php echo ($current_dir === 'sales') ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i> Ventas
            </a>
        </li>
        <li>
            <a href="../reports/index.php" class="nav-link <?php echo ($current_dir === 'reports') ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i> Reportes
            </a>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'user'): ?>
        <li>
            <a href="../billing/index.php" class="nav-link <?php echo ($current_dir === 'billing') ? 'active' : ''; ?>">
                <i class="bi bi-cash-coin"></i> Cobros
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="mt-auto">
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                    <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                </div>
                <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
</div>
