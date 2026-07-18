<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');

verify_session();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        echo json_encode(['status' => 'error', 'message' => 'No se recibieron items para actualizar']);
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = ? WHERE id_inventario = ?");
    $actualizados = 0;

    foreach ($input['items'] as $item) {
        $id = $item['id_inventario'] ?? null;
        $cantidad = $item['cantidad_fisica'] ?? null;

        if (!$id || $cantidad === null || $cantidad < 0) {
            continue;
        }

        $stmt->execute([(int)$cantidad, (int)$id]);
        $actualizados++;
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Corte realizado correctamente. $actualizados medicamento(s) actualizado(s).",
        'actualizados' => $actualizados
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error en save_corte.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar el corte: ' . $e->getMessage()]);
}
