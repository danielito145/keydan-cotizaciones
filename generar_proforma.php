<?php
// generar_proforma.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: cotizaciones.php?error=ID de cotización no proporcionado");
    exit;
}

$id_cotizacion = $_GET['id'];

try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Obtener datos de la cotización
    $stmt = $conn->prepare("SELECT * FROM cotizaciones WHERE id_cotizacion = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Cotización no encontrada");
    }
    
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Usar el mismo código de la cotización pero cambiando el prefijo de COT a PRO
    $codigo_cotizacion = $cotizacion['codigo'];
    $codigo = str_replace('COT-', 'PRO-', $codigo_cotizacion);
    
    // Verificar si ya existe una proforma con este código
    $stmt = $conn->prepare("SELECT id_proforma FROM proformas WHERE codigo = :codigo");
    $stmt->bindParam(':codigo', $codigo);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Si ya existe, generar un código nuevo
        $stmt = $conn->prepare("SELECT codigo FROM proformas WHERE codigo LIKE :codigo_like ORDER BY codigo DESC LIMIT 1");
        $codigo_like = 'PRO-' . date('Y') . '-' . date('m') . '-%';
        $stmt->bindParam(':codigo_like', $codigo_like);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $ultimo_codigo = $stmt->fetch(PDO::FETCH_ASSOC)['codigo'];
            $numero = intval(substr($ultimo_codigo, -3));
            $nuevo_numero = $numero + 1;
            $codigo = 'PRO-' . date('Y') . '-' . date('m') . '-' . str_pad($nuevo_numero, 3, '0', STR_PAD_LEFT);
        } else {
            $codigo = 'PRO-' . date('Y') . '-' . date('m') . '-001';
        }
    }
    
    // Insertar proforma
    $stmt = $conn->prepare("INSERT INTO proformas (codigo, id_cotizacion, id_cliente, fecha_emision, validez, 
                           condiciones_pago, tiempo_entrega, id_usuario, subtotal, impuestos, total, 
                           estado, notas, fecha_creacion) 
                          VALUES (:codigo, :id_cotizacion, :id_cliente, NOW(), :validez, 
                           :condiciones_pago, :tiempo_entrega, :id_usuario, :subtotal, :impuestos, :total, 
                           'emitida', :notas, NOW())");
    
    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':id_cotizacion', $id_cotizacion);
    $stmt->bindParam(':id_cliente', $cotizacion['id_cliente']);
    $stmt->bindParam(':validez', $cotizacion['validez']);
    $stmt->bindParam(':condiciones_pago', $cotizacion['condiciones_pago']);
    $stmt->bindParam(':tiempo_entrega', $cotizacion['tiempo_entrega']);
    $stmt->bindParam(':id_usuario', $cotizacion['id_usuario']);
    $stmt->bindParam(':subtotal', $cotizacion['subtotal']);
    $stmt->bindParam(':impuestos', $cotizacion['impuestos']);
    $stmt->bindParam(':total', $cotizacion['total']);
    $stmt->bindParam(':notas', $cotizacion['notas']);
    
    $stmt->execute();
    
    // Obtener el ID de la proforma insertada
    $id_proforma = $conn->lastInsertId();
    
    // Obtener detalles de la cotización
    $stmt = $conn->prepare("SELECT cd.*, m.nombre as material 
                           FROM cotizacion_detalles cd 
                           JOIN materiales m ON cd.id_material = m.id_material 
                           WHERE cd.id_cotizacion = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Insertar cada detalle en la proforma
    foreach ($detalles as $detalle) {
        $stmt = $conn->prepare("INSERT INTO proforma_detalles (id_proforma, id_material, descripcion, ancho, largo, 
                               micraje, fuelle, colores, color_texto, biodegradable, cantidad, precio_unitario, subtotal, 
                               espesor, medida_referencial) 
                              VALUES (:id_proforma, :id_material, :descripcion, :ancho, :largo, 
                               :micraje, :fuelle, :colores, :color_texto, :biodegradable, :cantidad, :precio_unitario, :subtotal,
                               :espesor, :medida_referencial)");
        
        // Crear descripción
        $descripcion = $detalle['material'];
        if ($detalle['biodegradable']) {
            $descripcion .= " Biodegradable";
        }
        
        // Crear variables temporales para pasar por referencia
        $id_material = $detalle['id_material'];
        $ancho = $detalle['ancho'];
        $largo = $detalle['largo'];
        $micraje = $detalle['micraje'];
        $fuelle = $detalle['fuelle'];
        $colores = $detalle['colores'];
        $color_texto = $detalle['color_texto'];
        $biodegradable = $detalle['biodegradable'];
        $cantidad = $detalle['cantidad'];
        $precio_unitario = $detalle['precio_unitario'];
        $subtotal_item = $detalle['subtotal'];
        $espesor = isset($detalle['espesor']) ? $detalle['espesor'] : '';
        $medida_referencial = isset($detalle['medida_referencial']) ? $detalle['medida_referencial'] : '';
        
        $stmt->bindParam(':id_proforma', $id_proforma);
        $stmt->bindParam(':id_material', $id_material);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':ancho', $ancho);
        $stmt->bindParam(':largo', $largo);
        $stmt->bindParam(':micraje', $micraje);
        $stmt->bindParam(':fuelle', $fuelle);
        $stmt->bindParam(':colores', $colores);
        $stmt->bindParam(':color_texto', $color_texto);
        $stmt->bindParam(':biodegradable', $biodegradable, PDO::PARAM_BOOL);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio_unitario', $precio_unitario);
        $stmt->bindParam(':subtotal', $subtotal_item);
        $stmt->bindParam(':espesor', $espesor);
        $stmt->bindParam(':medida_referencial', $medida_referencial);
        
        $stmt->execute();
    }
    
    // Actualizar estado de la cotización a 'convertida'
    $stmt = $conn->prepare("UPDATE cotizaciones SET estado = 'convertida' WHERE id_cotizacion = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir directamente a la página de impresión de proforma
    echo "<script>
        window.location.href = 'imprimir_proforma.php?id=" . $id_proforma . "';
    </script>";
    exit;
    
} catch(Exception $e) {
    // Deshacer transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Mostrar error detallado en lugar de redirigir
    echo "<h3>Error al generar proforma:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<h4>Detalles del error:</h4>";
    echo "<pre>";
    print_r($e->getTraceAsString());
    echo "</pre>";
    echo "<a href='ver_cotizacion.php?id=" . $id_cotizacion . "' class='btn btn-secondary'>Volver a la cotización</a>";
    exit;
}