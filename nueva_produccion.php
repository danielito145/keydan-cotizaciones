<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Activar depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener datos del formulario
        $id_orden = $_POST['id_orden'];
        $id_responsable = $_POST['id_responsable'];
        $fecha_estimada_fin = $_POST['fecha_estimada_fin'];
        $observaciones = $_POST['observaciones'];
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $fecha_inicio = date('Y-m-d H:i:s');
        
        // Validar orden de venta
        $stmt = $conn->prepare("SELECT o.*, COUNT(op.id_orden_produccion) AS tiene_produccion 
                              FROM ordenes_venta o 
                              LEFT JOIN ordenes_produccion op ON o.id_orden = op.id_orden 
                              WHERE o.id_orden = :id_orden
                              GROUP BY o.id_orden");
        $stmt->bindParam(':id_orden', $id_orden);
        if (!$stmt->execute()) {
            throw new Exception("Error al validar orden de venta: " . implode(", ", $stmt->errorInfo()));
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("La orden de venta seleccionada no existe");
        }
        
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar que la orden esté en estado correcto y no tenga ya una producción
        if ($orden['estado'] !== 'pendiente' && $orden['estado'] !== 'en_produccion') {
            throw new Exception("La orden debe estar en estado 'pendiente' o 'en producción' para iniciar una producción");
        }
        
        if ($orden['tiene_produccion'] > 0) {
            throw new Exception("Esta orden ya tiene una producción asociada");
        }
        
        // Generar código único para la orden de producción
        $anio_mes = date('Y-m');
        $stmt = $conn->prepare("SELECT MAX(SUBSTRING_INDEX(codigo, '-', -1)) as ultimo 
                               FROM ordenes_produccion 
                               WHERE codigo LIKE :codigo_pattern");
        $codigo_pattern = 'PROD-' . $anio_mes . '-%';
        $stmt->bindParam(':codigo_pattern', $codigo_pattern);
        if (!$stmt->execute()) {
            throw new Exception("Error al generar código: " . implode(", ", $stmt->errorInfo()));
        }
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo'];
        
        $numero = ($ultimo) ? intval($ultimo) + 1 : 1;
        $codigo = 'PROD-' . $anio_mes . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
        
        // Insertar la orden de producción
        $stmt = $conn->prepare("INSERT INTO ordenes_produccion 
                              (id_orden, codigo, fecha_inicio, fecha_estimada_fin, 
                              estado, id_responsable, observaciones, 
                              fecha_creacion, id_usuario_creacion) 
                              VALUES 
                              (:id_orden, :codigo, :fecha_inicio, :fecha_estimada_fin, 
                              'programada', :id_responsable, :observaciones, 
                              NOW(), :id_usuario)");
        
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_estimada_fin', $fecha_estimada_fin);
        $stmt->bindParam(':id_responsable', $id_responsable);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->bindParam(':id_usuario', $id_usuario);
        if (!$stmt->execute()) {
            throw new Exception("Error al insertar orden de producción: " . implode(", ", $stmt->errorInfo()));
        }
        
        // Obtener el ID de la orden de producción insertada
        $id_orden_produccion = $conn->lastInsertId();
        
        // Insertar detalles de producción
        $stmt = $conn->prepare("SELECT * FROM orden_detalles WHERE id_orden = :id_orden");
        $stmt->bindParam(':id_orden', $id_orden);
        if (!$stmt->execute()) {
            throw new Exception("Error al obtener detalles de la orden: " . implode(", ", $stmt->errorInfo()));
        }
        $detalles_orden = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detalles_orden as $detalle) {
            $stmt = $conn->prepare("INSERT INTO produccion_detalles 
                                  (id_orden_produccion, id_orden_detalle, 
                                  estado, cantidad_programada, cantidad_producida, 
                                  peso_estimado) 
                                  VALUES 
                                  (:id_orden_produccion, :id_orden_detalle, 
                                  'pendiente', :cantidad_programada, 0, 
                                  :peso_estimado)");
            
            $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
            $stmt->bindParam(':id_orden_detalle', $detalle['id_detalle']);
            $stmt->bindParam(':cantidad_programada', $detalle['cantidad']);
            
            $peso_estimado = ($detalle['ancho'] * $detalle['largo'] * ($detalle['micraje'] ?: $detalle['espesor']) * $detalle['cantidad']) / 1000000;
            $stmt->bindParam(':peso_estimado', $peso_estimado);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar detalle de producción: " . implode(", ", $stmt->errorInfo()));
            }
            
            // Crear etapas de producción
            $etapas = ['corte', 'sellado', 'control_calidad', 'empaque'];
            $id_produccion_detalle = $conn->lastInsertId();
            
            foreach ($etapas as $tipo_etapa) {
                $stmt = $conn->prepare("INSERT INTO produccion_etapas 
                                      (id_produccion_detalle, tipo_etapa, estado) 
                                      VALUES 
                                      (:id_produccion_detalle, :tipo_etapa, 'pendiente')");
                
                $stmt->bindParam(':id_produccion_detalle', $id_produccion_detalle);
                $stmt->bindParam(':tipo_etapa', $tipo_etapa);
                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar etapa de producción: " . implode(", ", $stmt->errorInfo()));
                }
            }
        }
        
        // Actualizar estado de la orden de venta
        if ($orden['estado'] == 'pendiente') {
            $stmt = $conn->prepare("UPDATE ordenes_venta SET estado = 'en_produccion' WHERE id_orden = :id_orden");
            $stmt->bindParam(':id_orden', $id_orden);
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar estado de orden de venta: " . implode(", ", $stmt->errorInfo()));
            }
            
            // Registrar en historial de cambios
            $stmt = $conn->prepare("INSERT INTO historial_orden 
                                  (id_orden, id_usuario, fecha_cambio, estado_anterior, estado_nuevo, comentario) 
                                  VALUES (:id_orden, :id_usuario, NOW(), 'pendiente', 'en_produccion', :comentario)");
            
            $comentario = "Orden puesta en producción: " . $codigo;
            $stmt->bindParam(':id_orden', $id_orden);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':comentario', $comentario);
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar historial de orden: " . implode(", ", $stmt->errorInfo()));
            }
        }
        
        // Registrar en historial de producción
        $stmt = $conn->prepare("INSERT INTO historial_produccion 
                              (id_orden_produccion, fecha, tipo_evento, descripcion, id_usuario) 
                              VALUES (:id_orden_produccion, NOW(), 'inicio_produccion', :descripcion, :id_usuario)");
        
        $descripcion = "Producción creada y programada";
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':id_usuario', $id_usuario);
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar historial de producción: " . implode(", ", $stmt->errorInfo()));
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir con éxito
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&success=1&message=" . urlencode("La orden de producción ha sido creada correctamente"));
        exit;
        
    } catch(Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $error_msg = "Error al crear la orden de producción: " . $e->getMessage();
        error_log($error_msg); // Registrar el error en el log del servidor
    }
}

// Obtener lista de órdenes pendientes
$sql = "SELECT o.id_orden, o.codigo, o.fecha_emision, cl.razon_social as cliente, o.total
        FROM ordenes_venta o
        JOIN clientes cl ON o.id_cliente = cl.id_cliente
        LEFT JOIN ordenes_produccion op ON o.id_orden = op.id_orden
        WHERE (o.estado = 'pendiente' OR o.estado = 'en_produccion')
        AND op.id_orden_produccion IS NULL
        ORDER BY o.fecha_emision DESC";

$stmt = $conn->prepare($sql);
if (!$stmt->execute()) {
    $error_msg = "Error al obtener órdenes pendientes: " . implode(", ", $stmt->errorInfo());
}
$ordenes_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de responsables
$stmt = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE activo = 1 ORDER BY nombre");
$responsables = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-plus-circle"></i> Nueva Orden de Producción</h2>
    </div>
    <div class="col-md-4 text-right">
        <a href="produccion.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Producción
        </a>
    </div>
</div>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Crear Nueva Orden de Producción</h5>
    </div>
    <div class="card-body">
        <?php if (count($ordenes_pendientes) > 0): ?>
            <form method="post" action="nueva_produccion.php" id="produccionForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_orden">Orden de Venta: <span class="text-danger">*</span></label>
                            <select class="form-control" id="id_orden" name="id_orden" required>
                                <option value="">-- Seleccione una orden --</option>
                                <?php foreach ($ordenes_pendientes as $orden): ?>
                                    <option value="<?php echo $orden['id_orden']; ?>" data-total="<?php echo $orden['total']; ?>">
                                        <?php echo $orden['codigo'] . ' - ' . $orden['cliente'] . ' - S/ ' . number_format($orden['total'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_responsable">Responsable de Producción: <span class="text-danger">*</span></label>
                            <select class="form-control" id="id_responsable" name="id_responsable" required>
                                <option value="">-- Seleccione un responsable --</option>
                                <?php foreach ($responsables as $resp): ?>
                                    <option value="<?php echo $resp['id_usuario']; ?>">
                                        <?php echo $resp['nombre'] . ' ' . $resp['apellido']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fecha_estimada_fin">Fecha Estimada de Finalización: <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha_estimada_fin" name="fecha_estimada_fin" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="observaciones">Observaciones:</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>
                <div class="text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Orden de Producción
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                No hay órdenes de venta pendientes disponibles para producción.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>