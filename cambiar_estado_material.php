<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $id_material = $_POST['id_material'];
    $estado = $_POST['estado'];

    // Verificar si el material tiene cotizaciones asociadas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cotizacion_detalles WHERE id_material = :id_material");
    $stmt->bindParam(':id_material', $id_material);
    $stmt->execute();
    $cotizaciones_count = $stmt->fetchColumn();

    if ($cotizaciones_count > 0 && $estado == 'inactivo') {
        $response['mensaje'] = 'No se puede desactivar el material porque está asociado a cotizaciones.';
    } else {
        $stmt = $conn->prepare("UPDATE materiales SET estado = :estado WHERE id_material = :id_material");
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id_material', $id_material);
        $stmt->execute();

        $response['exito'] = true;
        $response['mensaje'] = 'Estado actualizado con éxito';
    }
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al actualizar el estado: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>