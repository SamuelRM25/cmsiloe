<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Get JSON data
    $json_data = file_get_contents('php://input');
    if (!$json_data) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['nombre_cliente']) || !isset($data['tipo_pago']) || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Datos incompletos');
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO ventas (id_usuario, nombre_cliente, tipo_pago, total, estado, fecha_venta) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Obtener la fecha y hora actual en la zona horaria de Guatemala
    $fecha_actual = date('Y-m-d H:i:s');
    
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $data['nombre_cliente'],
        $data['tipo_pago'],
        $data['total'],
        $data['estado'],
        $fecha_actual
    ]);
    
    $id_venta = $conn->lastInsertId();
    
    // Capture current stock before deduction (for verification)
    $stmt_check_before = $conn->prepare("SELECT cantidad_med FROM inventario WHERE id_inventario = ?");
    $stmt_insert_detalle = $conn->prepare("INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad_vendida, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_deduct = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ?");
    $stmt_verify = $conn->prepare("SELECT cantidad_med FROM inventario WHERE id_inventario = ?");
    
    $items_verificados = 0;
    
    foreach ($data['items'] as $item) {
        // Validate item data
        if (!isset($item['id_inventario']) || !isset($item['cantidad']) || !isset($item['precio_unitario'])) {
            throw new Exception('Datos de item incompletos');
        }
        
        // 1. Snapshot stock before deduction
        $stmt_check_before->execute([$item['id_inventario']]);
        $before = $stmt_check_before->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            throw new Exception('Medicamento ID ' . $item['id_inventario'] . ' no encontrado en inventario');
        }
        $stock_before = (int)$before['cantidad_med'];
        $cantidad_vender = (int)$item['cantidad'];
        
        // 2. Verify sufficient stock
        if ($stock_before < $cantidad_vender) {
            throw new Exception('Stock insuficiente para el item ID ' . $item['id_inventario'] . '. Disponible: ' . $stock_before . ', solicitado: ' . $cantidad_vender);
        }
        
        // 3. Insert sale detail
        $stmt_insert_detalle->execute([
            $id_venta,
            $item['id_inventario'],
            $cantidad_vender,
            $item['precio_unitario']
        ]);
        
        // 4. Deduct from inventory
        $stmt_deduct->execute([$cantidad_vender, $item['id_inventario']]);
        
        // 5. Verify deduction was correct
        $stmt_verify->execute([$item['id_inventario']]);
        $after = $stmt_verify->fetch(PDO::FETCH_ASSOC);
        $stock_after = (int)$after['cantidad_med'];
        $expected_stock = $stock_before - $cantidad_vender;
        
        if ($stock_after !== $expected_stock) {
            throw new Exception('Discrepancia en inventario al vender ' . $item['id_inventario'] . ': se esperaba stock ' . $expected_stock . ' pero se obtuvo ' . $stock_after);
        }
        
        if ($stock_after < 0) {
            throw new Exception('Stock negativo detectado para el medicamento ID ' . $item['id_inventario'] . ' después de la venta');
        }
        
        $items_verificados++;
    }
    
    // Clear reservations for this session (since cart is now processed)
    $stmt_res = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ?");
    $stmt_res->execute([session_id()]);

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Venta registrada correctamente. Stock verificado.',
        'id_venta' => $id_venta,
        'items_verificados' => $items_verificados
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof PDO) {
        $conn->rollBack();
    }
    
    // Log the error
    error_log('Error in save_venta.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}