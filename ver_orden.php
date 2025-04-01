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
    // Preparar la consulta
    $stmt = $conn->prepare("SELECT o.*, cl.razon_social as cliente, cl.ruc, cl.direccion, cl.telefono, cl.email,
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido,
                           p.codigo as codigo_proforma, p.id_proforma,
                           DATEDIFF(CURRENT_DATE(), o.fecha_emision) as dias_transcurridos,
                           CASE
                                WHEN o.tiempo_entrega = 'Inmediato' THEN 0
                                WHEN o.tiempo_entrega LIKE '%día%' THEN CAST(SUBSTRING_INDEX(o.tiempo_entrega, ' ', 1) AS SIGNED)
                                WHEN o.tiempo_entrega LIKE '%semana%' THEN CAST(SUBSTRING_INDEX(o.tiempo_entrega, ' ', 1) AS SIGNED) * 7
                                ELSE 30
                            END as dias_entrega
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
    
    // Obtener detalles de la orden
    $stmt = $conn->prepare("SELECT od.*, m.nombre as material 
                           FROM orden_detalles od 
                           JOIN materiales m ON od.id_material = m.id_material 
                           WHERE od.id_orden = :id");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    
    // Obtener todos los detalles
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener historial de cambios (últimos 3)
    $stmt = $conn->prepare("SELECT h.*, 
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                           FROM historial_orden h 
                           JOIN usuarios u ON h.id_usuario = u.id_usuario 
                           WHERE h.id_orden = :id 
                           ORDER BY h.fecha_cambio DESC
                           LIMIT 3");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    header("Location: ordenes.php?error=" . urlencode("Error al obtener la orden: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-file-invoice"></i> Detalle de Orden de Venta</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="ordenes.php">Órdenes de Venta</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detalle de Orden #<?php echo $orden['codigo']; ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="imprimir_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-print"></i> Imprimir
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 
        <?php 
        if (isset($_GET['message'])) {
            echo $_GET['message'];
        } else {
            echo "Orden generada correctamente.";
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['error']; ?>
    </div>
<?php endif; ?>

<!-- Estado y resumen rápido -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo $orden['codigo']; ?></h4>
                    <span class="badge 
                        <?php 
                        switch($orden['estado']) {
                            case 'pendiente': echo 'badge-warning'; break;
                            case 'en_produccion': echo 'badge-info'; break;
                            case 'completada': echo 'badge-success'; break;
                            case 'cancelada': echo 'badge-danger'; break;
                            default: echo 'badge-secondary';
                        }
                        ?> p-2">
                        <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                    </span>
                </div>
                <p class="text-muted mt-2 mb-0">Cliente: <strong><?php echo $orden['cliente']; ?></strong></p>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-3 text-center border-right">
                        <p class="text-muted mb-0">Fecha</p>
                        <p class="font-weight-bold"><?php echo date('d/m/Y', strtotime($orden['fecha_emision'])); ?></p>
                    </div>
                    <div class="col-md-3 text-center border-right">
                        <p class="text-muted mb-0">Total</p>
                        <p class="font-weight-bold">S/ <?php echo number_format($orden['total'], 2); ?></p>
                    </div>
                    <div class="col-md-3 text-center border-right">
                        <p class="text-muted mb-0">Tiempo de Entrega</p>
                        <p class="font-weight-bold"><?php echo $orden['tiempo_entrega']; ?></p>
                    </div>
                    <div class="col-md-3 text-center">
                        <p class="text-muted mb-0">Condiciones de Pago</p>
                        <p class="font-weight-bold"><?php echo $orden['condiciones_pago']; ?></p>
                    </div>
                </div>
                
                <?php if ($orden['estado'] != 'cancelada' && $orden['estado'] != 'completada'): ?>
                    <?php if ($orden['dias_transcurridos'] > $orden['dias_entrega']): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle mr-2"></i> 
                            La orden está retrasada por <strong><?php echo ($orden['dias_transcurridos'] - $orden['dias_entrega']); ?> días</strong>.
                        </div>
                    <?php elseif ($orden['dias_transcurridos'] >= ($orden['dias_entrega'] * 0.8)): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-clock mr-2"></i> 
                            Quedan <strong><?php echo ($orden['dias_entrega'] - $orden['dias_transcurridos']); ?> días</strong> para la fecha límite de entrega.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Seguimiento</h5>
            </div>
            <div class="card-body p-0">
                <!-- Timeline resumido -->
                <div class="timeline-mini">
                    <?php if (count($historial) > 0): ?>
                        <?php foreach($historial as $index => $h): ?>
                            <div class="timeline-item <?php echo $index === 0 ? 'first' : ''; ?>">
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
                                </div>
                                <div class="timeline-content">
                                    <p class="mb-1 font-weight-bold">
                                        <?php if ($h['estado_anterior'] != $h['estado_nuevo']): ?>
                                            Cambio a estado: 
                                            <span class="badge 
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
                                            <?php echo substr($h['comentario'], 0, 30) . (strlen($h['comentario']) > 30 ? '...' : ''); ?>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($h['fecha_cambio'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="p-3 text-center">
                            <a href="seguimiento_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-history mr-1"></i> Ver historial completo
                            </a>
                        </div>
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

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Información de la Orden</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Código:</strong> <?php echo $orden['codigo']; ?></p>
                <p><strong>Cliente:</strong> <?php echo $orden['cliente']; ?></p>
                <p><strong>RUC:</strong> <?php echo $orden['ruc']; ?></p>
                <p><strong>Dirección:</strong> <?php echo $orden['direccion']; ?></p>
                <p><strong>Teléfono:</strong> <?php echo $orden['telefono']; ?></p>
                <p><strong>Email:</strong> <?php echo $orden['email']; ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Fecha de Emisión:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_emision'])); ?></p>
                <p><strong>Condiciones de Pago:</strong> <?php echo $orden['condiciones_pago']; ?></p>
                <p><strong>Tiempo de Entrega:</strong> <?php echo $orden['tiempo_entrega']; ?></p>
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
                <p><strong>Creado por:</strong> <?php echo $orden['usuario_nombre'] . ' ' . $orden['usuario_apellido']; ?></p>
                
                <?php if ($orden['id_proforma']): ?>
                    <p><strong>Proforma Origen:</strong> 
                        <a href="ver_proforma.php?id=<?php echo $orden['id_proforma']; ?>">
                            <?php echo $orden['codigo_proforma']; ?>
                        </a>
                    </p>
                <?php endif; ?>
                
                <?php if ($orden['estado'] == 'completada' && $orden['fecha_completado']): ?>
                    <p><strong>Fecha de Completado:</strong> <?php echo date('d/m/Y H:i', strtotime($orden['fecha_completado'])); ?></p>
                <?php endif; ?>
                
                <?php if ($orden['estado'] == 'cancelada' && $orden['fecha_cancelacion']): ?>
                    <p><strong>Fecha de Cancelación:</strong> <?php echo date('d/m/Y H:i', strtotime($orden['fecha_cancelacion'])); ?></p>
                    <p><strong>Motivo de Cancelación:</strong> <?php echo $orden['motivo_cancelacion']; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Detalle de Productos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Descripción</th>
                        <th>Medidas</th>
                        <th>Detalles</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detalles as $detalle): ?>
                        <tr>
                            <td><?php echo $detalle['descripcion']; ?></td>
                            <td>
                                <?php 
                                // Si hay espesor, usarlo en lugar del micraje
                                if (!empty($detalle['espesor'])) {
                                    echo $detalle['ancho'] . ' x ' . $detalle['largo'] . ' x ' . $detalle['espesor'];
                                } else {
                                    echo $detalle['ancho'] . ' x ' . $detalle['largo'] . ' x ' . $detalle['micraje'] . ' mic';
                                }
                                
                                if ($detalle['fuelle'] > 0) {
                                    echo ' (Fuelle: ' . $detalle['fuelle'] . ')';
                                }
                                
                                // Si hay medida referencial, mostrarla
                                if (!empty($detalle['medida_referencial'])) {
                                    echo '<br><small>' . $detalle['medida_referencial'] . '</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($detalle['colores'] > 0) {
                                    echo 'Colores: ' . $detalle['colores'];
                                } else {
                                    echo 'Sin color';
                                }
                                if ($detalle['biodegradable']) {
                                    echo '<br><span class="badge badge-success">Biodegradable</span>';
                                }
                                ?>
                                <br>
                                <small class="text-muted"><?php echo $detalle['material']; ?></small>
                            </td>
                            <td><?php echo number_format($detalle['cantidad']); ?></td>
                            <td>S/ <?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                            <td>S/ <?php echo number_format($detalle['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">Subtotal:</th>
                        <th>S/ <?php echo number_format($orden['subtotal'], 2); ?></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">IGV (18%):</th>
                        <th>S/ <?php echo number_format($orden['impuestos'], 2); ?></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">Total:</th>
                        <th>S/ <?php echo number_format($orden['total'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($orden['observaciones'])): ?>
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Observaciones</h5>
    </div>
    <div class="card-body">
        <p><?php echo nl2br(htmlspecialchars($orden['observaciones'])); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12 text-right">
        <a href="ordenes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        
        <?php if ($orden['estado'] == 'pendiente'): ?>
            <a href="iniciar_produccion.php?id=<?php echo $id_orden; ?>" class="btn btn-primary"
               onclick="return confirm('¿Está seguro que desea iniciar la producción de esta orden?');">
                <i class="fas fa-industry"></i> Iniciar Producción
            </a>
            <a href="editar_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="cancelar_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-danger">
                <i class="fas fa-times-circle"></i> Cancelar
            </a>
        <?php endif; ?>
        
        <?php if ($orden['estado'] == 'en_produccion'): ?>
            <a href="completar_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-success"
               onclick="return confirm('¿Está seguro que desea marcar esta orden como completada?');">
                <i class="fas fa-check-circle"></i> Completar Orden
            </a>
                        <?php if ($orden['estado'] == 'en_produccion'): ?>
                <a href="ver_produccion.php?id_orden=<?php echo $orden['id_orden']; ?>" class="btn btn-info">
                    <i class="fas fa-industry"></i> Ver Producción
                </a>
            <?php endif; ?>
            <a href="seguimiento_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-info">
                <i class="fas fa-tasks"></i> Seguimiento
            </a>
        <?php endif; ?>
        
        <a href="imprimir_orden.php?id=<?php echo $id_orden; ?>" class="btn btn-dark" target="_blank">
            <i class="fas fa-print"></i> Imprimir
        </a>
        
        <?php if ($orden['id_proforma']): ?>
            <a href="ver_proforma.php?id=<?php echo $orden['id_proforma']; ?>" class="btn btn-primary">
                <i class="fas fa-file-contract"></i> Ver Proforma
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
/* Estilos para el mini timeline */
.timeline-mini {
    position: relative;
    padding: 0;
}

.timeline-mini .timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 15px;
    border-left: 2px solid #dee2e6;
}

.timeline-mini .timeline-item.first {
    border-left: 2px solid #28a745;
}

.timeline-mini .timeline-marker {
    position: absolute;
    left: -6px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-mini .timeline-content {
    padding: 0 0 0 15px;
}

.badge {
    font-size: 85%;
    padding: 0.4em 0.6em;
}
</style>

<?php require_once 'includes/footer.php'; ?>