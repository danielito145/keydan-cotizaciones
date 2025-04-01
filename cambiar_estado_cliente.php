<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $id_cliente = $_POST['id_cliente'];
    $estado = $_POST['estado'];

    // Verificar si el cliente tiene cotizaciones asociadas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cotizaciones WHERE id_cliente = :id_cliente");
    $stmt->bindParam(':id_cliente', $id_cliente);
    $stmt->execute();
    $cotizaciones_count = $stmt->fetchColumn();

    if ($cotizaciones_count > 0 && $estado == 'inactivo') {
        $response['mensaje'] = 'No se puede desactivar el cliente porque tiene cotizaciones asociadas.';
    } else {
        $stmt = $conn->prepare("UPDATE clientes SET estado = :estado WHERE id_cliente = :id_cliente");
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id_cliente', $id_cliente);
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