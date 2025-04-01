<?php
require_once 'config/db.php';
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ordenes.php?error=ID de orden no proporcionado");
    exit;
}

$id_orden = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// Usar el mismo id_usuario como id_responsable (ajusta según tu lógica de negocio)
$id_responsable = $id_usuario;

try {
    $conn->beginTransaction();

    // Verificar si la orden ya tiene una orden de producción
    $stmt = $conn->prepare("SELECT id_orden_produccion FROM ordenes_produccion WHERE id_orden = :id_orden");
    $stmt->bindParam(':id_orden', $id_orden);
    $stmt->execute();
    $orden_produccion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden_produccion) {
        // Generar un código único para la orden de producción (ejemplo: OP-YYYY-MM-XXX)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_produccion WHERE YEAR(fecha_inicio) = YEAR(NOW())");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $numero = $result['total'] + 1;
        $codigo = "OP-" . date('Y-m') . "-" . str_pad($numero, 3, '0', STR_PAD_LEFT);

        // Crear orden de producción incluyendo id_responsable
        $stmt = $conn->prepare("INSERT INTO ordenes_produccion (id_orden, codigo, estado, fecha_inicio, id_responsable) 
                               VALUES (:id_orden, :codigo, 'en_proceso', NOW(), :id_responsable)");
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id_responsable', $id_responsable);
        $stmt->execute();
        $id_orden_produccion = $conn->lastInsertId();

        // Obtener detalles de la orden de venta
        $stmt = $conn->prepare("SELECT * FROM orden_detalles WHERE id_orden = :id_orden");
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->execute();
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Insertar detalles en produccion_detalles
        foreach ($detalles as $detalle) {
            $stmt = $conn->prepare("INSERT INTO produccion_detalles (id_orden_produccion, id_orden_detalle, cantidad_programada, estado) 
                                   VALUES (:id_orden_produccion, :id_orden_detalle, :cantidad, 'pendiente')");
            $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
            $stmt->bindParam(':id_orden_detalle', $detalle['id_detalle']);
            $stmt->bindParam(':cantidad', $detalle['cantidad']);
            $stmt->execute();

            $id_produccion_detalle = $conn->lastInsertId();

            // Crear etapas de producción
            $etapas = ['corte', 'sellado', 'control_calidad', 'empaque'];
            foreach ($etapas as $etapa) {
                $stmt = $conn->prepare("INSERT INTO produccion_etapas (id_produccion_detalle, tipo_etapa, estado) 
                                       VALUES (:id_produccion_detalle, :tipo_etapa, 'pendiente')");
                $stmt->bindParam(':id_produccion_detalle', $id_produccion_detalle);
                $stmt->bindParam(':tipo_etapa', $etapa);
                $stmt->execute();
            }
        }
    } else {
        $id_orden_produccion = $orden_produccion['id_orden_produccion'];
    }

    // Actualizar estado de la orden de venta
    $stmt = $conn->prepare("UPDATE ordenes_venta SET estado = 'en_produccion' WHERE id_orden = :id");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();

    // Registrar en historial
    $stmt = $conn->prepare("INSERT INTO historial_orden (id_orden, estado_anterior, estado_nuevo, comentario, id_usuario, fecha_cambio)
                           VALUES (:id_orden, 'pendiente', 'en_produccion', 'Producción iniciada', :id_usuario, NOW())");
    $stmt->bindParam(':id_orden', $id_orden);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->execute();

    $conn->commit();
    header("Location: ver_orden.php?id=$id_orden&success=1&message=" . urlencode("Producción iniciada correctamente"));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ver_orden.php?id=$id_orden&error=" . urlencode("Error al iniciar producción: " . $e->getMessage()));
    exit;
}
?>