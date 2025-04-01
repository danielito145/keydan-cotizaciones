<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $id_cotizacion = $_POST['id_cotizacion'];

    // Verificar si la cotización tiene proformas asociadas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM proformas WHERE id_cotizacion = :id_cotizacion");
    $stmt->bindParam(':id_cotizacion', $id_cotizacion);
    $stmt->execute();
    $proformas_count = $stmt->fetchColumn();

    if ($proformas_count > 0) {
        $response['mensaje'] = 'No se puede eliminar la cotización porque tiene proformas asociadas.';
    } else {
        // Marcar la cotización como "rechazada" en lugar de eliminarla
        $stmt = $conn->prepare("UPDATE cotizaciones SET estado = 'rechazada', fecha_modificacion = NOW() WHERE id_cotizacion = :id_cotizacion");
        $stmt->bindParam(':id_cotizacion', $id_cotizacion);
        $stmt->execute();

        $response['exito'] = true;
        $response['mensaje'] = 'Cotización marcada como rechazada con éxito';
    }
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al eliminar la cotización: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>