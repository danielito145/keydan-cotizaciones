<?php
require_once 'config/db.php';
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: cotizaciones.php?error=ID de cotización no proporcionado");
    exit;
}

$id_cotizacion = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

try {
    $conn->beginTransaction();

    // Verificar si el id_proforma existe
    $stmt = $conn->prepare("SELECT id_proforma FROM proformas WHERE id_proforma = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new Exception("La cotización con ID $id_cotizacion no existe");
    }

    // Verificar si el id_usuario existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new Exception("El usuario con ID $id_usuario no existe");
    }

    // Actualizar estado de la cotización a "aprobada"
    $stmt = $conn->prepare("UPDATE proformas SET estado = 'aprobada' WHERE id_proforma = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();

    // Registrar en historial
    $stmt = $conn->prepare("INSERT INTO historial_proforma (id_proforma, estado_anterior, estado_nuevo, comentario, id_usuario, fecha_cambio)
                           VALUES (:id_proforma, 'pendiente', 'aprobada', 'Cotización aprobada', :id_usuario, NOW())");
    $stmt->bindParam(':id_proforma', $id_cotizacion);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->execute();

    $conn->commit();
    header("Location: ver_cotizacion.php?id=$id_cotizacion&success=1&message=" . urlencode("Cotización aprobada correctamente"));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ver_cotizacion.php?id=$id_cotizacion&error=" . urlencode("Error al aprobar cotización: " . $e->getMessage()));
    exit;
}
?>