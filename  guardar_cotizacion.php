<?php
require_once 'config/db.php';
session_start();

// Para depuración - comentar o eliminar en producción
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener datos de la cotización
        $codigo = $_POST['codigo'];
        $id_cliente = $_POST['id_cliente'];
        $fecha_cotizacion = $_POST['fecha_cotizacion'];
        $validez = $_POST['validez'];
        $condiciones_pago = $_POST['condiciones_pago'];
        $tiempo_entrega = $_POST['tiempo_entrega'];
        $subtotal = $_POST['subtotal'];
        $impuestos = $_POST['impuestos'];
        $total = $_POST['total'];
        $notas = $_POST['notas'];
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto
        
        // Insertar cotización
        $stmt = $conn->prepare("INSERT INTO cotizaciones (codigo, id_cliente, fecha_cotizacion, validez, 
                                condiciones_pago, tiempo_entrega, id_usuario, subtotal, impuestos, total, 
                                estado, notas, fecha_creacion) 
                               VALUES (:codigo, :id_cliente, :fecha_cotizacion, :validez, 
                                :condiciones_pago, :tiempo_entrega, :id_usuario, :subtotal, :impuestos, :total, 
                                'pendiente', :notas, NOW())");
        
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->bindParam(':fecha_cotizacion', $fecha_cotizacion);
        $stmt->bindParam(':validez', $validez);
        $stmt->bindParam(':condiciones_pago', $condiciones_pago);
        $stmt->bindParam(':tiempo_entrega', $tiempo_entrega);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':impuestos', $impuestos);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':notas', $notas);
        
        $stmt->execute();
        
        // Obtener el ID de la cotización insertada
        $id_cotizacion = $conn->lastInsertId();
        
        // Obtener los ítems de la cotización
        $items_json = $_POST['items_json'];
        $items = json_decode($items_json, true);
        
        // Verificar si hay ítems
        if (!empty($items)) {
            // Insertar cada ítem
            foreach ($items as $item) {
                $stmt = $conn->prepare("INSERT INTO cotizacion_detalles (id_cotizacion, id_material, ancho, largo, 
                                       micraje, fuelle, colores, color_texto, biodegradable, cantidad, costo_unitario, 
                                       precio_unitario, subtotal, espesor, medida_referencial) 
                                      VALUES (:id_cotizacion, :id_material, :ancho, :largo, 
                                       :micraje, :fuelle, :colores, :color_texto, :biodegradable, :cantidad, :costo_unitario, 
                                       :precio_unitario, :subtotal, :espesor, :medida_referencial)");
                
                // Procesar la información de color
                $colores = 0;
                $color_texto = null;
                
                if (isset($item['color']) && !empty($item['color'])) {
                    if ($item['color'] === 'Colores') {
                        $colores = 1;
                        // Si seleccionó 'Colores' y proporcionó un color específico
                        if (isset($item['color_especifico']) && !empty($item['color_especifico'])) {
                            $color_texto = 'Colores (' . $item['color_especifico'] . ')';
                        } else {
                            $color_texto = 'Colores';
                        }
                    } else {
                        // Si seleccionó 'Negro' o 'Transparente'
                        $color_texto = $item['color'];
                        $colores = 0;
                    }
                }
                
                $stmt->bindParam(':id_cotizacion', $id_cotizacion);
                $stmt->bindParam(':id_material', $item['id_material']);
                $stmt->bindParam(':ancho', $item['ancho']);
                $stmt->bindParam(':largo', $item['largo']);
                $stmt->bindParam(':micraje', $item['micraje']);
                $stmt->bindParam(':fuelle', $item['fuelle']);
                $stmt->bindParam(':colores', $colores);
                $stmt->bindParam(':color_texto', $color_texto);
                $stmt->bindParam(':biodegradable', $item['biodegradable'], PDO::PARAM_BOOL);
                $stmt->bindParam(':cantidad', $item['cantidad']);
                $stmt->bindParam(':costo_unitario', $item['costo_unitario'] ?? 0);
                $stmt->bindParam(':precio_unitario', $item['precio_unitario']);
                $stmt->bindParam(':subtotal', $item['subtotal']);
                $stmt->bindParam(':espesor', $item['espesor'] ?? '');
                $stmt->bindParam(':medida_referencial', $item['medida_referencial'] ?? '');
                
                $stmt->execute();
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver cotización con mensaje de éxito
        header("Location: ver_cotizacion.php?id=" . $id_cotizacion . "&success=1");
        exit;
        
    } catch(PDOException $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Para depuración
        echo "Error: " . $e->getMessage();
        
        // Redirigir con mensaje de error
        // header("Location: cotizaciones.php?error=" . urlencode("Error al guardar la cotización: " . $e->getMessage()));
        exit;
    }
} else {
    // Si no es una solicitud POST, redirigir
    header("Location: cotizaciones.php");
    exit;
}