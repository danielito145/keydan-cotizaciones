<?php
// cancelar_orden.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ordenes.php?error=ID de orden no proporcionado");
    exit;
}

$id_orden = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto

// Si se envió el formulario con motivo de cancelación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['motivo_cancelacion'])) {
    $motivo_cancelacion = $_POST['motivo_cancelacion'];
    $fecha_cancelacion = date('Y-m-d H:i:s');
    
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Verificar si la orden existe y obtener sus datos
        $stmt = $conn->prepare("SELECT * FROM ordenes_venta WHERE id_orden = :id");
        $stmt->bindParam(':id', $id_orden);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Orden no encontrada");
        }
        
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar que la orden esté en estado 'pendiente'
        if ($orden['estado'] !== 'pendiente') {
            throw new Exception("Solo se pueden cancelar órdenes en estado 'pendiente'");
        }
        
        // Actualizar el estado de la orden a 'cancelada'
        $stmt = $conn->prepare("UPDATE ordenes_venta SET 
                              estado = 'cancelada', 
                              fecha_cancelacion = :fecha_cancelacion,
                              motivo_cancelacion = :motivo_cancelacion,
                              id_usuario_cancelacion = :id_usuario
                              WHERE id_orden = :id");
        
        $stmt->bindParam(':fecha_cancelacion', $fecha_cancelacion);
        $stmt->bindParam(':motivo_cancelacion', $motivo_cancelacion);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':id', $id_orden);
        $stmt->execute();
        
        // Registrar en historial de cambios
        $stmt = $conn->prepare("INSERT INTO historial_orden 
                                (id_orden, id_usuario, fecha_cambio, estado_anterior, estado_nuevo, comentario) 
                                VALUES (:id_orden, :id_usuario, :fecha_cambio, :estado_anterior, :estado_nuevo, :comentario)");
        
        $estado_anterior = $orden['estado'];
        $estado_nuevo = 'cancelada';
        $comentario = "Orden cancelada: " . $motivo_cancelacion;
        
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':fecha_cambio', $fecha_cancelacion);
        $stmt->bindParam(':estado_anterior', $estado_anterior);
        $stmt->bindParam(':estado_nuevo', $estado_nuevo);
        $stmt->bindParam(':comentario', $comentario);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver orden con mensaje de éxito
        header("Location: ver_orden.php?id=" . $id_orden . "&success=1&message=" . urlencode("La orden ha sido cancelada correctamente"));
        exit;
        
    } catch(Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Redirigir con mensaje de error
        header("Location: ver_orden.php?id=" . $id_orden . "&error=" . urlencode("Error al cancelar orden: " . $e->getMessage()));
        exit;
    }
} else {
    // Mostrar formulario para agregar motivo de cancelación
    require_once 'includes/header.php';
    
    // Obtener datos de la orden
    $stmt = $conn->prepare("SELECT o.*, cl.razon_social as cliente 
                           FROM ordenes_venta o 
                           JOIN clientes cl ON o.id_cliente = cl.id_cliente 
                           WHERE o.id_orden = :id");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar que exista la orden y esté en estado pendiente
    if (!$orden || $orden['estado'] !== 'pendiente') {
        header("Location: ordenes.php?error=" . urlencode("La orden no existe o no se puede cancelar"));
        exit;
    }
?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-times-circle mr-2"></i> Cancelar Orden</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>¡Atención!</strong> Está a punto de cancelar la orden <strong><?php echo $orden['codigo']; ?></strong> 
                            para el cliente <strong><?php echo $orden['cliente']; ?></strong>. 
                            Esta acción no se puede deshacer.
                        </div>
                        
                        <form method="post" action="cancelar_orden.php?id=<?php echo $id_orden; ?>">
                            <div class="form-group">
                                <label for="motivo_cancelacion">Motivo de Cancelación:</label>
                                <textarea class="form-control" id="motivo_cancelacion" name="motivo_cancelacion" rows="4" required></textarea>
                                <small class="form-text text-muted">
                                    Por favor, proporcione un motivo detallado para la cancelación de esta orden.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>Detalles de la Orden:</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <tr>
                                            <th width="30%">Código:</th>
                                            <td><?php echo $orden['codigo']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Cliente:</th>
                                            <td><?php echo $orden['cliente']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Fecha de Emisión:</th>
                                            <td><?php echo date('d/m/Y', strtotime($orden['fecha_emision'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Total:</th>
                                            <td>S/ <?php echo number_format($orden['total'], 2); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <a href="ver_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i> Volver
                                </a>
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro de cancelar esta orden? Esta acción no se puede deshacer.');">
                                    <i class="fas fa-times-circle mr-1"></i> Confirmar Cancelación
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    require_once 'includes/footer.php';
    exit;
}