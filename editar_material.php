<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $color = $_POST['color'];
    $descripcion = $_POST['descripcion'] ?: null;

    $stmt = $conn->prepare("INSERT INTO materiales (codigo, nombre, tipo, color, descripcion, estado, fecha_registro) 
                           VALUES (:codigo, :nombre, :tipo, :color, :descripcion, 'activo', NOW())");
    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':color', $color);
    $stmt->bindParam(':descripcion', $descripcion);
    $stmt->execute();

    $response['exito'] = true;
    $response['mensaje'] = 'Material guardado con éxito';
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al guardar el material: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>