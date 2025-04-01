<?php
require_once 'config/db.php';

$response = [];

try {
    $id_material = $_POST['id_material'];

    $stmt = $conn->prepare("SELECT pm.id_precio, pm.id_material, pm.id_proveedor, pm.precio, pm.moneda, pm.fecha_vigencia, pm.estado, p.razon_social 
                            FROM precios_materiales pm 
                            JOIN proveedores p ON pm.id_proveedor = p.id_proveedor 
                            WHERE pm.id_material = :id_material");
    $stmt->bindParam(':id_material', $id_material);
    $stmt->execute();
    $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $response = ['error' => 'Error al obtener los precios: ' . $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>