<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
date_default_timezone_set('America/Guatemala');
verify_session();

$database = new Database();
$conn = $database->getConnection();

$page_title = "Verificar Ventas vs Inventario - CM Siloé";
include_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    .stat-value { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.25rem; }
    .stat-label { font-size: 0.875rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
    .table-container {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    .table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.2px;
        border: none;
    }
    .table thead th:first-child { border-top-left-radius: 12px; }
    .table thead th:last-child { border-top-right-radius: 12px; }
    .badge-ok { background: rgba(16,185,129,0.15); color: #059669; }
    .badge-warn { background: rgba(245,158,11,0.15); color: #d97706; }
    .badge-err { background: rgba(239,68,68,0.15); color: #dc2626; }
    .diff-positivo { color: #059669; font-weight: 700; }
    .diff-negativo { color: #dc2626; font-weight: 700; }
    .diff-cero { color: #6b7280; }
</style>

<div class="dashboard-wrapper sidebar-collapsed">
    <div class="dashboard-mobile-overlay"></div>
    <button class="btn btn-white shadow-sm border rounded-circle position-fixed d-none d-md-flex align-items-center justify-content-center" id="desktopSidebarToggle" style="top:20px;left:20px;width:45px;height:45px;z-index:1040;transition:all .3s">
        <i class="bi bi-list text-primary fs-4"></i>
    </button>

    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height:40px;margin-right:15px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
            <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width:32px;height:32px;">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>
            <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../patients/index.php" class="nav-link"><i class="bi bi-people"></i> Pacientes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../appointments/index.php" class="nav-link"><i class="bi bi-calendar"></i> Citas</a></li>
            <li><a href="../minor_procedures/index.php" class="nav-link"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
            <li><a href="../examinations/index.php" class="nav-link"><i class="bi bi-file-earmark-medical"></i> Exámenes</a></li>
            <li><a href="../dispensary/index.php" class="nav-link"><i class="bi bi-cart4"></i> Dispensario</a></li>
            <li><a href="index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
            <li><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
            <li><a href="../sales/index.php" class="nav-link"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="../reports/index.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
        </ul>
        <div class="mt-auto">
            <div class="dropdown p-2">
                <div class="d-flex align-items-center text-dark">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width:32px;height:32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content-glass">
        <div class="container-fluid p-2 p-md-3">

            <?php
            // ----- PROCESAR CORRECCIÓN SI SE SOLICITÓ -----
            if (isset($_POST['corregir']) && $_POST['corregir'] === '1') {
                try {
                    $conn->beginTransaction();

                    $items = json_decode($_POST['items_json'], true);
                    $stmt_upd = $conn->prepare("UPDATE inventario SET cantidad_med = ? WHERE id_inventario = ?");
                    $corregidos = 0;

                    foreach ($items as $item) {
                        $nuevo_stock = max(0, (int)$item['nuevo_stock']);
                        $stmt_upd->execute([$nuevo_stock, (int)$item['id_inventario']]);
                        $corregidos++;
                    }

                    $conn->commit();
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>¡Corrección aplicada!</strong> ' . $corregidos . ' medicamento(s) actualizado(s).
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                } catch (Exception $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            Error: ' . htmlspecialchars($e->getMessage()) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
            }

            // ----- CONSULTA PRINCIPAL -----
            $sql = "
                SELECT 
                    i.id_inventario,
                    i.nom_medicamento,
                    i.presentacion_med,
                    i.casa_farmaceutica,
                    i.cantidad_med AS stock_actual,
                    COALESCE(dv.total_vendido, 0) AS total_vendido,
                    COALESCE(pi.quantity, 0) AS recibido_purchase,
                    (
                        SELECT COALESCE(SUM(c2.cantidad_compra), 0)
                        FROM compras c2
                        WHERE c2.nombre_compra = i.nom_medicamento
                          AND c2.estado_compra IN ('Completo', 'Abonado')
                    ) AS comprado_compras,
                    (COALESCE(pi.quantity, 0) + (
                        SELECT COALESCE(SUM(c3.cantidad_compra), 0)
                        FROM compras c3
                        WHERE c3.nombre_compra = i.nom_medicamento
                          AND c3.estado_compra IN ('Completo', 'Abonado')
                    )) AS total_recibido
                FROM inventario i
                LEFT JOIN (
                    SELECT id_inventario, SUM(cantidad_vendida) AS total_vendido
                    FROM detalle_ventas
                    GROUP BY id_inventario
                ) dv ON i.id_inventario = dv.id_inventario
                LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id AND pi.status = 'Recibido'
                WHERE dv.total_vendido IS NOT NULL
                ORDER BY i.nom_medicamento ASC
            ";

            $stmt = $conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Separar en dos grupos: con y sin purchase data
            $con_data = [];
            $sin_data = [];
            $total_discrepancias = 0;
            $total_stock_actual = 0;
            $total_vendido = 0;

            foreach ($rows as $r) {
                $r['stock_esperado'] = $r['total_recibido'] - $r['total_vendido'];
                $r['discrepancia'] = $r['stock_actual'] - $r['stock_esperado'];
                $r['nuevo_stock_sugerido'] = max(0, $r['stock_actual'] - $r['discrepancia']);

                if ($r['discrepancia'] > 0) {
                    $r['tipo'] = 'sobra'; // stock más alto de lo esperado → ventas no descontaron
                } elseif ($r['discrepancia'] < 0) {
                    $r['tipo'] = 'falta';  // stock más bajo de lo esperado
                } else {
                    $r['tipo'] = 'ok';
                }

                $total_stock_actual += $r['stock_actual'];
                $total_vendido += $r['total_vendido'];
                if ($r['discrepancia'] != 0) $total_discrepancias++;

                if ($r['total_recibido'] > 0) {
                    $con_data[] = $r;
                } else {
                    $sin_data[] = $r;
                }
            }
            ?>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-shield-exclamation me-2 text-warning"></i>
                        Verificación de Ventas vs Inventario
                    </h2>
                    <p class="text-muted mb-0">Detección de ventas que no descontaron medicamento del inventario</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportCSV()">
                        <i class="bi bi-download me-2"></i>Exportar CSV
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Volver a Inventario
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-value" style="color:#6366f1;"><?php echo count($rows); ?></div>
                    <div class="stat-label">Medicamentos con ventas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value" style="color:#ef4444;"><?php echo $total_discrepancias; ?></div>
                    <div class="stat-label">Con discrepancias</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.1);color:#059669;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div class="stat-value" style="color:#059669;"><?php echo number_format($total_vendido); ?></div>
                    <div class="stat-label">Unidades vendidas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.1);color:#d97706;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="stat-value" style="color:#d97706;"><?php echo number_format($total_stock_actual); ?></div>
                    <div class="stat-label">Stock actual total</div>
                </div>
            </div>

            <?php if (count($rows) === 0): ?>
                <div class="alert alert-success text-center py-5">
                    <i class="bi bi-check-circle-fill fs-1 d-block mb-3 text-success"></i>
                    <h4>No se encontraron ventas registradas</h4>
                    <p class="mb-0 text-muted">No hay datos de ventas para verificar.</p>
                </div>
            <?php else: ?>

            <!-- Items CON purchase data (verificación confiable) -->
            <?php if (count($con_data) > 0): ?>
            <div class="mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-check-circle text-primary me-2"></i>
                    Medicamentos con datos de compra (verificación confiable)
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?php echo count($con_data); ?></span>
                </h5>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaConData">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Presentación</th>
                                    <th class="text-center">Stock Actual</th>
                                    <th class="text-center">Total Vendido</th>
                                    <th class="text-center">Total Recibido</th>
                                    <th class="text-center">Stock Esperado</th>
                                    <th class="text-center">Discrepancia</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($con_data as $r): 
                                    $badge = $r['discrepancia'] == 0 ? 'badge-ok' : ($r['discrepancia'] > 0 ? 'badge-err' : 'badge-warn');
                                    $icono = $r['discrepancia'] == 0 ? 'bi-check-circle-fill' : ($r['discrepancia'] > 0 ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill');
                                    $label = $r['discrepancia'] == 0 ? 'OK' : ($r['discrepancia'] > 0 ? 'SOBRA STOCK' : 'FALTA STOCK');
                                    $diff_class = $r['discrepancia'] == 0 ? 'diff-cero' : ($r['discrepancia'] > 0 ? 'diff-positivo' : 'diff-negativo');
                                    $diff_sign = $r['discrepancia'] > 0 ? '+' : '';
                                ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($r['nom_medicamento']); ?></td>
                                    <td><?php echo htmlspecialchars($r['presentacion_med']); ?></td>
                                    <td class="text-center"><?php echo $r['stock_actual']; ?></td>
                                    <td class="text-center"><?php echo $r['total_vendido']; ?></td>
                                    <td class="text-center"><?php echo $r['total_recibido']; ?></td>
                                    <td class="text-center"><?php echo $r['stock_esperado']; ?></td>
                                    <td class="text-center <?php echo $diff_class; ?>">
                                        <?php echo $r['discrepancia'] != 0 ? $diff_sign . $r['discrepancia'] : '0'; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $badge; ?> px-3 py-2">
                                            <i class="bi <?php echo $icono; ?> me-1"></i> <?php echo $label; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Items SIN purchase data (solo informativo) -->
            <?php if (count($sin_data) > 0): ?>
            <div class="mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-info-circle text-secondary me-2"></i>
                    Medicamentos sin datos de compra (solo informativo)
                    <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2"><?php echo count($sin_data); ?></span>
                </h5>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaSinData">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Presentación</th>
                                    <th class="text-center">Stock Actual</th>
                                    <th class="text-center">Total Vendido</th>
                                    <th class="text-center">Stock + Vendido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sin_data as $r): 
                                    $total_historico = $r['stock_actual'] + $r['total_vendido'];
                                ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($r['nom_medicamento']); ?></td>
                                    <td><?php echo htmlspecialchars($r['presentacion_med']); ?></td>
                                    <td class="text-center"><?php echo $r['stock_actual']; ?></td>
                                    <td class="text-center"><?php echo $r['total_vendido']; ?></td>
                                    <td class="text-center"><?php echo $total_historico; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulario de corrección (solo items con discrepancia Y purchase data) -->
            <?php 
            $corregibles = array_filter($con_data, function($r) { return $r['discrepancia'] != 0; });
            if (count($corregibles) > 0): 
            ?>
            <div class="card shadow-sm border-0 rounded-4 mb-4" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(12px);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="bi bi-tools fs-2 text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-2">Corrección Automática</h5>
                            <p class="text-muted mb-3">
                                Se ajustarán <strong><?php echo count($corregibles); ?></strong> medicamento(s) que tienen discrepancia 
                                para que el stock coincida con: <code>Total Recibido - Total Vendido</code>.
                                Los items con "SOBRA STOCK" se reducirán (ventas no descontaron). Los de "FALTA STOCK" se incrementarán.
                            </p>

                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Medicamento</th>
                                            <th class="text-center">Stock Actual</th>
                                            <th class="text-center">Nuevo Stock</th>
                                            <th class="text-center">Ajuste</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($corregibles as $r): 
                                            $ajuste = $r['nuevo_stock_sugerido'] - $r['stock_actual'];
                                            $ajuste_signo = $ajuste > 0 ? '+' : '';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['nom_medicamento']); ?></td>
                                            <td class="text-center"><?php echo $r['stock_actual']; ?></td>
                                            <td class="text-center fw-bold"><?php echo $r['nuevo_stock_sugerido']; ?></td>
                                            <td class="text-center <?php echo $ajuste > 0 ? 'diff-positivo' : 'diff-negativo'; ?>">
                                                <?php echo $ajuste != 0 ? $ajuste_signo . $ajuste : '0'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" id="correctionForm">
                                <input type="hidden" name="corregir" value="1">
                                <input type="hidden" name="items_json" id="itemsJsonInput">
                                <button type="button" class="btn btn-warning" onclick="confirmarCorreccion()">
                                    <i class="bi bi-arrow-repeat me-2"></i>Aplicar Corrección
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
<script src="../../assets/js/dashboard-reengineered.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Desktop sidebar toggle
    const toggleBtn = document.getElementById('desktopSidebarToggle');
    const sidebar = document.querySelector('.sidebar-glass');
    const mainContent = document.querySelector('.main-content-glass');
    const wrapper = document.querySelector('.dashboard-wrapper');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function() {
            wrapper.classList.toggle('sidebar-collapsed');
        });
    }

    const closeBtn = document.getElementById('sidebarCloseBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            wrapper.classList.add('sidebar-collapsed');
        });
    }
});

function exportCSV() {
    let csv = '\uFEFF'; // BOM for Excel UTF-8
    csv += 'Medicamento,Presentación,Stock Actual,Total Vendido,Total Recibido,Stock Esperado,Discrepancia,Estado\n';

    // Con data rows
    document.querySelectorAll('#tablaConData tbody tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        if (tds.length < 8) return;
        const nombre = tds[0].textContent.trim();
        const pres = tds[1].textContent.trim();
        const stock = tds[2].textContent.trim();
        const vendido = tds[3].textContent.trim();
        const recibido = tds[4].textContent.trim();
        const esperado = tds[5].textContent.trim();
        const diff = tds[6].textContent.trim();
        const estado = tds[7].querySelector('.badge') ? tds[7].querySelector('.badge').textContent.trim() : tds[7].textContent.trim();
        csv += `${nombre},${pres},${stock},${vendido},${recibido},${esperado},${diff},${estado}\n`;
    });

    // Sin data rows
    document.querySelectorAll('#tablaSinData tbody tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        if (tds.length < 5) return;
        const nombre = tds[0].textContent.trim();
        const pres = tds[1].textContent.trim();
        const stock = tds[2].textContent.trim();
        const vendido = tds[3].textContent.trim();
        const historico = tds[4].textContent.trim();
        csv += `${nombre},${pres},${stock},${vendido},N/A,N/A,N/A,Sin datos de compra\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'verificacion_ventas_inventario.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function confirmarCorreccion() {
    const form = document.getElementById('correctionForm');
    const jsonInput = document.getElementById('itemsJsonInput');

    <?php 
    $corregibles_json = [];
    foreach ($corregibles as $r) {
        $corregibles_json[] = [
            'id_inventario' => $r['id_inventario'],
            'nom_medicamento' => $r['nom_medicamento'],
            'nuevo_stock' => $r['nuevo_stock_sugerido'],
            'stock_actual' => $r['stock_actual']
        ];
    }
    ?>
    jsonInput.value = JSON.stringify(<?php echo json_encode($corregibles_json); ?>);

    Swal.fire({
        title: '¿Aplicar corrección?',
        html: `Se ajustará el stock de <strong><?php echo count($corregibles); ?></strong> medicamento(s).<br>
               Esta acción no se puede deshacer automáticamente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Sí, aplicar',
        cancelButtonText: 'Cancelar',
        background: 'rgba(255,255,255,0.95)',
        backdrop: 'rgba(0,0,0,0.4)'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Corrigiendo...',
                text: 'Actualizando stock de inventario',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            form.submit();
        }
    });
}
</script>
