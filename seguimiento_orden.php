<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ordenes.php?error=ID de orden no proporcionado");
    exit;
}

$id_orden = $_GET['id'];

// Obtener datos de la orden
try {
    // Preparar la consulta para obtener datos de la orden
    $stmt = $conn->prepare("SELECT o.*, cl.razon_social as cliente, cl.ruc,
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido,
                           p.codigo as codigo_proforma, p.id_proforma
                           FROM ordenes_venta o 
                           JOIN clientes cl ON o.id_cliente = cl.id_cliente 
                           JOIN usuarios u ON o.id_usuario = u.id_usuario
                           LEFT JOIN proformas p ON o.id_proforma = p.id_proforma
                           WHERE o.id_orden = :id");
    // Vincular el parámetro
    $stmt->bindParam(':id', $id_orden);
    // Ejecutar la consulta
    $stmt->execute();
    
    // Verificar si se encontró la orden
    if ($stmt->rowCount() === 0) {
        header("Location: ordenes.php?error=Orden no encontrada");
        exit;
    }
    
    // Obtener los datos de la orden
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener historial de cambios
    $stmt = $conn->prepare("SELECT h.*, 
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                           FROM historial_orden h 
                           JOIN usuarios u ON h.id_usuario = u.id_usuario 
                           WHERE h.id_orden = :id 
                           ORDER BY h.fecha_cambio DESC");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar nuevo comentario de seguimiento si se envió el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario']) && !empty($_POST['comentario'])) {
        $comentario = $_POST['comentario'];
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $fecha_actual = date('Y-m-d H:i:s');
        
        try {
            // Insertar comentario en historial
            $stmt = $conn->prepare("INSERT INTO historial_orden 
                                  (id_orden, id_usuario, fecha_cambio, estado_anterior, 
                                   estado_nuevo, comentario) 
                                  VALUES (:id_orden, :id_usuario, :fecha_cambio, :estado_anterior, 
                                          :estado_nuevo, :comentario)");
            
            $stmt->bindParam(':id_orden', $id_orden);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':fecha_cambio', $fecha_actual);
            $stmt->bindParam(':estado_anterior', $orden['estado']);
            $stmt->bindParam(':estado_nuevo', $orden['estado']);
            $stmt->bindParam(':comentario', $comentario);
            $stmt->execute();
            
            // Redirigir para evitar reenvío del formulario
            header("Location: seguimiento_orden.php?id=$id_orden&success=1");
            exit;
        } catch(PDOException $e) {
            $error_msg = "Error al agregar comentario: " . $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    header("Location: ordenes.php?error=" . urlencode("Error al obtener la orden: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-tasks"></i> Seguimiento de Orden</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="ordenes.php">Órdenes de Venta</a></li>
                <li class="breadcrumb-item"><a href="ver_orden.php?id=<?php echo $id_orden; ?>">Detalle de Orden #<?php echo $orden['codigo']; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Seguimiento</li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="ver_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Detalles
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Comentario agregado correctamente.
    </div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <!-- Información de la orden -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Orden</h5>
            </div>
            <div class="card-body">
                <p><strong>Código:</strong> <?php echo $orden['codigo']; ?></p>
                <p><strong>Cliente:</strong> <?php echo $orden['cliente']; ?></p>
                <p><strong>Fecha de Emisión:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_emision'])); ?></p>
                <p><strong>Estado:</strong> 
                    <span class="badge 
                        <?php 
                        switch($orden['estado']) {
                            case 'pendiente': echo 'badge-warning'; break;
                            case 'en_produccion': echo 'badge-info'; break;
                            case 'completada': echo 'badge-success'; break;
                            case 'cancelada': echo 'badge-danger'; break;
                            default: echo 'badge-secondary';
                        }
                        ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                    </span>
                </p>
                <p><strong>Total:</strong> S/ <?php echo number_format($orden['total'], 2); ?></p>
                
                <?php if ($orden['id_proforma']): ?>
                    <p><strong>Proforma:</strong> 
                        <a href="ver_proforma.php?id=<?php echo $orden['id_proforma']; ?>">
                            <?php echo $orden['codigo_proforma']; ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Progreso de la orden -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Progreso de la Orden</h5>
            </div>
            <div class="card-body">
                <div class="progress-tracker">
                    <div class="progress-step <?php echo $orden['estado'] != 'cancelada' ? 'completed' : 'cancelled'; ?>">
                        <div class="progress-marker">1</div>
                        <div class="progress-text">
                            <h6 class="progress-title">Orden Generada</h6>
                            <p class="progress-date">
                                <?php echo date('d/m/Y', strtotime($orden['fecha_emision'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="progress-step <?php echo ($orden['estado'] == 'en_produccion' || $orden['estado'] == 'completada') ? 'completed' : (($orden['estado'] == 'cancelada') ? 'cancelled' : ''); ?>">
                        <div class="progress-marker">2</div>
                        <div class="progress-text">
                            <h6 class="progress-title">En Producción</h6>
                            <?php if ($orden['estado'] == 'en_produccion' || $orden['estado'] == 'completada'): ?>
                                <p class="progress-date">
                                    <?php 
                                    // Buscar en historial cuándo cambió a estado en_produccion
                                    $fecha_inicio = '';
                                    foreach ($historial as $h) {
                                        if ($h['estado_nuevo'] == 'en_produccion') {
                                            $fecha_inicio = date('d/m/Y', strtotime($h['fecha_cambio']));
                                            break;
                                        }
                                    }
                                    echo $fecha_inicio ? $fecha_inicio : 'No registrado';
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="progress-step <?php echo $orden['estado'] == 'completada' ? 'completed' : (($orden['estado'] == 'cancelada') ? 'cancelled' : ''); ?>">
                        <div class="progress-marker">3</div>
                        <div class="progress-text">
                            <h6 class="progress-title">Completada</h6>
                            <?php if ($orden['estado'] == 'completada'): ?>
                                <p class="progress-date">
                                    <?php echo $orden['fecha_completado'] ? date('d/m/Y', strtotime($orden['fecha_completado'])) : 'No registrado'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($orden['estado'] == 'cancelada'): ?>
                    <div class="progress-step cancelled">
                        <div class="progress-marker"><i class="fas fa-times"></i></div>
                        <div class="progress-text">
                            <h6 class="progress-title">Cancelada</h6>
                            <p class="progress-date">
                                <?php echo $orden['fecha_cancelacion'] ? date('d/m/Y', strtotime($orden['fecha_cancelacion'])) : 'No registrado'; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Agregar comentario -->
        <?php if ($orden['estado'] != 'cancelada' && $orden['estado'] != 'completada'): ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Agregar Comentario</h5>
            </div>
            <div class="card-body">
                <form method="post" action="seguimiento_orden.php?id=<?php echo $id_orden; ?>">
                    <div class="form-group">
                        <label for="comentario">Comentario:</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required></textarea>
                        <small class="form-text text-muted">
                            Agregue comentarios sobre el progreso, problemas o cualquier información relevante sobre la orden.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Agregar Comentario
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Historial de cambios -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Historial de Cambios</h5>
            </div>
            <div class="card-body p-0">
                <div class="timeline">
                    <?php if (count($historial) > 0): ?>
                        <?php foreach($historial as $h): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker 
                                    <?php 
                                    if ($h['estado_nuevo'] == 'completada') {
                                        echo 'bg-success';
                                    } elseif ($h['estado_nuevo'] == 'cancelada') {
                                        echo 'bg-danger';
                                    } elseif ($h['estado_nuevo'] == 'en_produccion') {
                                        echo 'bg-info';
                                    } else {
                                        echo 'bg-primary';
                                    }
                                    ?>">
                                    <?php 
                                    if ($h['estado_anterior'] != $h['estado_nuevo']) {
                                        if ($h['estado_nuevo'] == 'completada') {
                                            echo '<i class="fas fa-check"></i>';
                                        } elseif ($h['estado_nuevo'] == 'cancelada') {
                                            echo '<i class="fas fa-times"></i>';
                                        } elseif ($h['estado_nuevo'] == 'en_produccion') {
                                            echo '<i class="fas fa-industry"></i>';
                                        } else {
                                            echo '<i class="fas fa-circle"></i>';
                                        }
                                    } else {
                                        echo '<i class="fas fa-comment"></i>';
                                    }
                                    ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-heading">
                                        <h6 class="mb-0">
                                            <?php if ($h['estado_anterior'] != $h['estado_nuevo']): ?>
                                                Cambio de estado: 
                                                <span class="badge badge-pill badge-secondary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $h['estado_anterior'])); ?>
                                                </span>
                                                <i class="fas fa-arrow-right mx-1"></i>
                                                <span class="badge badge-pill 
                                                    <?php 
                                                    switch($h['estado_nuevo']) {
                                                        case 'pendiente': echo 'badge-warning'; break;
                                                        case 'en_produccion': echo 'badge-info'; break;
                                                        case 'completada': echo 'badge-success'; break;
                                                        case 'cancelada': echo 'badge-danger'; break;
                                                        default: echo 'badge-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $h['estado_nuevo'])); ?>
                                                </span>
                                            <?php else: ?>
                                                Comentario agregado
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($h['fecha_cambio'])); ?> -
                                            Por: <?php echo $h['usuario_nombre'] . ' ' . $h['usuario_apellido']; ?>
                                        </small>
                                    </div>
                                    <div class="timeline-body">
                                        <p><?php echo nl2br(htmlspecialchars($h['comentario'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p>No hay registros en el historial para esta orden.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para el timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    display: flex;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #007bff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    margin-right: 15px;
    flex-shrink: 0;
}

.timeline-content {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    flex-grow: 1;
}

.timeline-heading {
    margin-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.timeline-body p:last-child {
    margin-bottom: 0;
}

/* Estilos para el progreso */
.progress-tracker {
    display: flex;
    flex-direction: column;
}

.progress-step {
    position: relative;
    padding-left: 45px;
    margin-bottom: 20px;
}

.progress-step:before {
    content: '';
    position: absolute;
    left: 16px;
    top: 24px;
    bottom: -20px;
    width: 2px;
    background-color: #dee2e6;
}

.progress-step:last-child:before {
    display: none;
}

.progress-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #dee2e6;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    position: absolute;
    left: 0;
    top: 0;
}

.progress-text {
    padding-bottom: 10px;
}

.progress-step.completed .progress-marker {
    background-color: #28a745;
    color: white;
}

.progress-step.cancelled .progress-marker {
    background-color: #dc3545;
    color: white;
}

.progress-date {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 3px;
}