<?php
// hospitalization/api/delete_hospital_charge.php
session_start();
header('Content-Type: application/json');

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

// Check permissions
if (!isset($_SESSION['tipoUsuario']) || !in_array($_SESSION['tipoUsuario'], ['admin', 'doc'])) {
    echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_cargo = isset($_POST['id_cargo']) ? intval($_POST['id_cargo']) : 0;

    if ($id_cargo <= 0) {
        throw new Exception('ID de cargo inválido');
    }

    // Start transaction
    $conn->beginTransaction();

    // 1. Get charge details before deletion
    $stmt_get_acc = $conn->prepare("SELECT id_cuenta, tipo_cargo FROM cargos_hospitalarios WHERE id_cargo = ?");
    $stmt_get_acc->execute([$id_cargo]);
    $cargo_info = $stmt_get_acc->fetch(PDO::FETCH_ASSOC);

    if (!$cargo_info) {
        throw new Exception('Cargo no encontrado');
    }

    $id_cuenta = $cargo_info['id_cuenta'];
    $tipo_cargo = $cargo_info['tipo_cargo'];

    // 2. Perform deletion or soft-delete
    if ($tipo_cargo === 'Habitación') {
        // Soft-delete for Room charges to prevent auto-reinsertion by detalle_encamamiento.php
        $stmt_soft_delete = $conn->prepare("
            UPDATE cargos_hospitalarios 
            SET tipo_cargo = 'Habitación (Excluido)', cantidad = 0, precio_unitario = 0 
            WHERE id_cargo = ?
        ");
        $stmt_soft_delete->execute([$id_cargo]);
    } else {
        // Standard hard-delete for other types
        $stmt_delete = $conn->prepare("DELETE FROM cargos_hospitalarios WHERE id_cargo = ?");
        $stmt_delete->execute([$id_cargo]);
    }

    // 3. Recalculate account totals
    $stmt_total = $conn->prepare("SELECT COALESCE(SUM(cantidad * precio_unitario), 0) FROM cargos_hospitalarios WHERE id_cuenta = ?");
    $stmt_total->execute([$id_cuenta]);
    $new_total_general = $stmt_total->fetchColumn();

    $stmt_sync = $conn->prepare("
        UPDATE cuenta_hospitalaria 
        SET 
            total_general = ?,
            total_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ?),
            monto_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ?)
        WHERE id_cuenta = ?
    ");
    $stmt_sync->execute([$new_total_general, $id_cuenta, $id_cuenta, $id_cuenta]);

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Cargo eliminado correctamente'
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
