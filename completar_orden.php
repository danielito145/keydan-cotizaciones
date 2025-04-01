<?php
// completar_orden.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ordenes.php?error=ID de orden no proporcionado");
    exit;
}

$id_orden = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto
$fecha_completado = date('Y-m-d H:i:s');
$observaciones = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';

// Si se envió el formulario con observaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['observaciones'])) {
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
        
        // Verificar que la orden esté en estado 'en_produccion'
        if ($orden['estado'] !== 'en_produccion') {
            throw new Exception("Solo se pueden completar órdenes en estado 'en producción'");
        }
        
        // Actualizar el estado de la orden a 'completada'
        $stmt = $conn->prepare("UPDATE ordenes_venta SET 
                              estado = 'completada', 
                              fecha_completado = :fecha_completado,
                              observaciones = :observaciones
                              WHERE id_orden = :id");
        
        $stmt->bindParam(':fecha_completado', $fecha_completado);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->bindParam(':id', $id_orden);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver orden con mensaje de éxito
        header("Location: ver_orden.php?id=" . $id_orden . "&success=1&message=" . urlencode("La orden ha sido completada correctamente"));
        exit;
        
    } catch(Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Redirigir con mensaje de error
        header("Location: ver_orden.php?id=" . $id_orden . "&error=" . urlencode("Error al completar orden: " . $e->getMessage()));
        exit;
    }
} else {
    // Mostrar formulario para agregar observaciones
    require_once 'includes/header.php';
?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Completar Orden</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="completar_orden.php?id=<?php echo $id_orden; ?>">
                            <div class="form-group">
                                <label for="observaciones">Observaciones:</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="4"></textarea>
                                <small class="form-text text-muted">Puede agregar comentarios sobre la producción, entrega, o cualquier información relevante.</small>
                            </div>
                            <div class="text-right">
                                <a href="ver_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-success">Marcar como Completada</button>
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
?>