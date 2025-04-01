<?php
// rechazar_proforma.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: proformas.php?error=ID de proforma no proporcionado");
    exit;
}

$id_proforma = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto
$fecha_rechazo = date('Y-m-d H:i:s');
$motivo = isset($_POST['motivo']) ? $_POST['motivo'] : 'Rechazado por el cliente';

// Si se envió el formulario con el motivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['motivo'])) {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Verificar si la proforma existe y obtener sus datos
        $stmt = $conn->prepare("SELECT p.*, cl.razon_social as cliente, cl.email 
                              FROM proformas p 
                              JOIN clientes cl ON p.id_cliente = cl.id_cliente 
                              WHERE p.id_proforma = :id");
        $stmt->bindParam(':id', $id_proforma);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Proforma no encontrada");
        }
        
        $proforma = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar que la proforma esté en estado 'emitida'
        if ($proforma['estado'] !== 'emitida') {
            throw new Exception("Solo se pueden rechazar proformas en estado 'emitida'");
        }
        
        // Actualizar el estado de la proforma a 'rechazada'
        $stmt = $conn->prepare("UPDATE proformas SET 
                              estado = 'rechazada', 
                              fecha_rechazo = :fecha_rechazo,
                              id_usuario_rechazo = :id_usuario,
                              motivo_rechazo = :motivo
                              WHERE id_proforma = :id");
        
        $stmt->bindParam(':fecha_rechazo', $fecha_rechazo);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->bindParam(':id', $id_proforma);
        $stmt->execute();
        
        // Registrar el rechazo en el historial
        $stmt = $conn->prepare("INSERT INTO historial_proformas (
                              id_proforma, accion, id_usuario, fecha, detalles)
                              VALUES (
                              :id_proforma, 'rechazada', :id_usuario, NOW(), 
                              :detalles)");
        
        $detalles_historial = "Proforma rechazada. Motivo: " . $motivo;
        
        $stmt->bindParam(':id_proforma', $id_proforma);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':detalles', $detalles_historial);
        
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver proforma con mensaje de éxito
        header("Location: ver_proforma.php?id=" . $id_proforma . "&success=1&message=Proforma rechazada correctamente");
        exit;
        
    } catch(Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Redirigir con mensaje de error
        header("Location: ver_proforma.php?id=" . $id_proforma . "&error=" . urlencode("Error al rechazar proforma: " . $e->getMessage()));
        exit;
    }
} else {
    // Mostrar formulario para especificar el motivo del rechazo
    require_once 'includes/header.php';
?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Rechazar Proforma</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="rechazar_proforma.php?id=<?php echo $id_proforma; ?>">
                            <div class="form-group">
                                <label for="motivo">Motivo del rechazo:</label>
                                <textarea class="form-control" id="motivo" name="motivo" rows="4" required></textarea>
                                <small class="form-text text-muted">Por favor, especifique el motivo por el cual se rechaza esta proforma.</small>
                            </div>
                            <div class="text-right">
                                <a href="ver_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
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