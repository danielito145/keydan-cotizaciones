<?php
// aprobar_proforma.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: proformas.php?error=ID de proforma no proporcionado");
    exit;
}

$id_proforma = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto
$fecha_aprobacion = date('Y-m-d H:i:s');

try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Verificar si la proforma existe y obtener sus datos
    $stmt = $conn->prepare("SELECT p.*, cl.razon_social as cliente, cl.email 
                          FROM proformas p 
                          JOIN clientes cl ON p.id_cliente = cl.id_cliente 
                          WHERE p.id_proforma = :id");
    $stmt->bindParam(':id', $id_proforma);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Proforma no encontrada");
    }
    
    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar que la proforma esté en estado 'emitida'
    if ($proforma['estado'] !== 'emitida') {
        throw new Exception("Solo se pueden aprobar proformas en estado 'emitida'");
    }
    
    // Actualizar el estado de la proforma a 'aprobada'
    $stmt = $conn->prepare("UPDATE proformas SET 
                          estado = 'aprobada', 
                          fecha_aprobacion = :fecha_aprobacion,
                          id_usuario_aprobacion = :id_usuario
                          WHERE id_proforma = :id");
    
    $stmt->bindParam(':fecha_aprobacion', $fecha_aprobacion);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->bindParam(':id', $id_proforma);
    $stmt->execute();
    
    // Generar orden de venta
    $codigo_orden = 'OV-' . date('Y') . '-' . date('m') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("INSERT INTO ordenes_venta (
                          codigo, id_proforma, id_cliente, fecha_emision, 
                          condiciones_pago, tiempo_entrega, estado, 
                          subtotal, impuestos, total, id_usuario, fecha_creacion)
                          VALUES (
                          :codigo, :id_proforma, :id_cliente, NOW(),
                          :condiciones_pago, :tiempo_entrega, 'pendiente',
                          :subtotal, :impuestos, :total, :id_usuario, NOW())");
    
    $stmt->bindParam(':codigo', $codigo_orden);
    $stmt->bindParam(':id_proforma', $id_proforma);
    $stmt->bindParam(':id_cliente', $proforma['id_cliente']);
    $stmt->bindParam(':condiciones_pago', $proforma['condiciones_pago']);
    $stmt->bindParam(':tiempo_entrega', $proforma['tiempo_entrega']);
    $stmt->bindParam(':subtotal', $proforma['subtotal']);
    $stmt->bindParam(':impuestos', $proforma['impuestos']);
    $stmt->bindParam(':total', $proforma['total']);
    $stmt->bindParam(':id_usuario', $id_usuario);
    
    $stmt->execute();
    
    // Obtener el ID de la orden generada
    $id_orden = $conn->lastInsertId();
    
    // Obtener los detalles de la proforma
    $stmt = $conn->prepare("SELECT * FROM proforma_detalles WHERE id_proforma = :id_proforma");
    $stmt->bindParam(':id_proforma', $id_proforma);
    $stmt->execute();
    
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Copiar los detalles a la orden de venta
    foreach ($detalles as $detalle) {
        $stmt = $conn->prepare("INSERT INTO orden_detalles (
                              id_orden, id_material, descripcion, ancho, largo, 
                              micraje, fuelle, colores, biodegradable, cantidad, 
                              precio_unitario, subtotal, espesor, medida_referencial)
                              VALUES (
                              :id_orden, :id_material, :descripcion, :ancho, :largo,
                              :micraje, :fuelle, :colores, :biodegradable, :cantidad,
                              :precio_unitario, :subtotal, :espesor, :medida_referencial)");
        
        // Crear variables para bindParam
        $id_material = $detalle['id_material'];
        $descripcion = $detalle['descripcion'];
        $ancho = $detalle['ancho'];
        $largo = $detalle['largo'];
        $micraje = $detalle['micraje'];
        $fuelle = $detalle['fuelle'];
        $colores = $detalle['colores'];
        $biodegradable = $detalle['biodegradable'];
        $cantidad = $detalle['cantidad'];
        $precio_unitario = $detalle['precio_unitario'];
        $subtotal = $detalle['subtotal'];
        $espesor = isset($detalle['espesor']) ? $detalle['espesor'] : null;
        $medida_referencial = isset($detalle['medida_referencial']) ? $detalle['medida_referencial'] : null;
        
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->bindParam(':id_material', $id_material);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':ancho', $ancho);
        $stmt->bindParam(':largo', $largo);
        $stmt->bindParam(':micraje', $micraje);
        $stmt->bindParam(':fuelle', $fuelle);
        $stmt->bindParam(':colores', $colores);
        $stmt->bindParam(':biodegradable', $biodegradable, PDO::PARAM_BOOL);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio_unitario', $precio_unitario);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':espesor', $espesor);
        $stmt->bindParam(':medida_referencial', $medida_referencial);
        
        $stmt->execute();
    }
    
    // Registrar la aprobación en el historial
    $stmt = $conn->prepare("INSERT INTO historial_proformas (
                          id_proforma, accion, id_usuario, fecha, detalles)
                          VALUES (
                          :id_proforma, 'aprobada', :id_usuario, NOW(), 
                          :detalles)");
    
    $detalles_historial = "Proforma aprobada y convertida a Orden de Venta #" . $codigo_orden;
    
    $stmt->bindParam(':id_proforma', $id_proforma);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->bindParam(':detalles', $detalles_historial);
    
    $stmt->execute();
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir a la página de ver orden con mensaje de éxito
    header("Location: ver_orden.php?id=" . $id_orden . "&success=1");
    exit;
    
} catch(Exception $e) {
    // Deshacer transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Redirigir con mensaje de error
    header("Location: ver_proforma.php?id=" . $id_proforma . "&error=" . urlencode("Error al aprobar proforma: " . $e->getMessage()));
    exit;
}