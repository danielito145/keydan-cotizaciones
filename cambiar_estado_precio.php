<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $id_precio = $_POST['id_precio'];
    $estado = $_POST['estado'];

    $stmt = $conn->prepare("UPDATE precios_materiales SET estado = :estado WHERE id_precio = :id_precio");
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':id_precio', $id_precio);
    $stmt->execute();

    $response['exito'] = true;
    $response['mensaje'] = 'Estado actualizado con éxito';
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al actualizar el estado: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>