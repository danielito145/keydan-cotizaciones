<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $id_precio = $_POST['id_precio'];
    $id_material = $_POST['id_material'];
    $id_proveedor = $_POST['id_proveedor'];
    $precio = $_POST['precio'];
    $moneda = $_POST['moneda'];
    $fecha_vigencia = $_POST['fecha_vigencia'];

    if ($id_precio) {
        // Editar precio existente
        $stmt = $conn->prepare("UPDATE precios_materiales SET 
                                id_proveedor = :id_proveedor, 
                                precio = :precio, 
                                moneda = :moneda, 
                                fecha_vigencia = :fecha_vigencia 
                                WHERE id_precio = :id_precio");
        $stmt->bindParam(':id_precio', $id_precio);
    } else {
        // Agregar nuevo precio
        $stmt = $conn->prepare("INSERT INTO precios_materiales (id_material, id_proveedor, precio, moneda, fecha_vigencia, estado, fecha_registro) 
                               VALUES (:id_material, :id_proveedor, :precio, :moneda, :fecha_vigencia, 'activo', NOW())");
        $stmt->bindParam(':id_material', $id_material);
    }

    $stmt->bindParam(':id_proveedor', $id_proveedor);
    $stmt->bindParam(':precio', $precio);
    $stmt->bindParam(':moneda', $moneda);
    $stmt->bindParam(':fecha_vigencia', $fecha_vigencia);
    $stmt->execute();

    $response['exito'] = true;
    $response['mensaje'] = 'Precio guardado con éxito';
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al guardar el precio: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>