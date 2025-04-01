<?php
require_once 'config/db.php';

$response = ['exito' => false, 'mensaje' => ''];

try {
    $razon_social = $_POST['razon_social'];
    $ruc = $_POST['ruc'] ?: null;
    $direccion = $_POST['direccion'] ?: null;
    $telefono = $_POST['telefono'] ?: null;
    $email = $_POST['email'] ?: null;
    $contacto_nombre = $_POST['contacto_nombre'] ?: null;
    $contacto_cargo = $_POST['contacto_cargo'] ?: null;
    $contacto_telefono = $_POST['contacto_telefono'] ?: null;

    $stmt = $conn->prepare("INSERT INTO clientes (razon_social, ruc, direccion, telefono, email, contacto_nombre, contacto_cargo, contacto_telefono, fecha_registro, estado) 
                           VALUES (:razon_social, :ruc, :direccion, :telefono, :email, :contacto_nombre, :contacto_cargo, :contacto_telefono, NOW(), 'activo')");
    $stmt->bindParam(':razon_social', $razon_social);
    $stmt->bindParam(':ruc', $ruc);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':contacto_nombre', $contacto_nombre);
    $stmt->bindParam(':contacto_cargo', $contacto_cargo);
    $stmt->bindParam(':contacto_telefono', $contacto_telefono);
    $stmt->execute();

    $response['exito'] = true;
    $response['id_cliente'] = $conn->lastInsertId();
    $response['mensaje'] = 'Cliente guardado con éxito';
} catch(PDOException $e) {
    $response['mensaje'] = 'Error al guardar el cliente: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>