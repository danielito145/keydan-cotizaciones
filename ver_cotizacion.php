<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: cotizaciones.php?error=ID de cotización no proporcionado");
    exit;
}

$id_cotizacion = $_GET['id'];
$id_proforma = null;

// Obtener datos de la cotización
try {
    $stmt = $conn->prepare("SELECT c.*, cl.razon_social as cliente, u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                           FROM cotizaciones c 
                           JOIN clientes cl ON c.id_cliente = cl.id_cliente 
                           JOIN usuarios u ON c.id_usuario = u.id_usuario 
                           WHERE c.id_cotizacion = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header("Location: cotizaciones.php?error=Cotización no encontrada");
        exit;
    }
    
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de la cotización
    $stmt = $conn->prepare("SELECT cd.*, m.nombre as material 
                           FROM cotizacion_detalles cd 
                           JOIN materiales m ON cd.id_material = m.id_material 
                           WHERE cd.id_cotizacion = :id");
    $stmt->bindParam(':id', $id_cotizacion);
    $stmt->execute();
    
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si la cotización está convertida, obtener el ID de la proforma
    if ($cotizacion['estado'] == 'convertida') {
        $stmt = $conn->prepare("SELECT id_proforma FROM proformas WHERE id_cotizacion = :id_cotizacion ORDER BY id_proforma DESC LIMIT 1");
        $stmt->bindParam(':id_cotizacion', $id_cotizacion);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $id_proforma = $stmt->fetch(PDO::FETCH_ASSOC)['id_proforma'];
        }
    }
    
} catch(PDOException $e) {
    header("Location: cotizaciones.php?error=" . urlencode("Error al obtener la cotización: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-file-invoice-dollar"></i> Detalle de Cotización</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="cotizaciones.php">Cotizaciones</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detalle de Cotización #<?php echo $cotizacion['codigo']; ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <?php if ($cotizacion['estado'] == 'pendiente' || $cotizacion['estado'] == 'aprobada'): ?>
            <a href="generar_proforma.php?id=<?php echo $id_cotizacion; ?>" class="btn btn-success">
                <i class="fas fa-file-contract"></i> Generar Proforma
            </a>
        <?php elseif ($cotizacion['estado'] == 'convertida' && $id_proforma): ?>
            <a href="imprimir_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Imprimir Proforma
            </a>
        <?php endif; ?>
        <a href="editar_cotizacion.php?id=<?php echo $id_cotizacion; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Cotización guardada correctamente.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['error']; ?>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Información de la Cotización</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Código:</strong> <?php echo $cotizacion['codigo']; ?></p>
                <p><strong>Cliente:</strong> <?php echo $cotizacion['cliente']; ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cotizacion['fecha_cotizacion'])); ?></p>
                <p><strong>Validez:</strong> <?php echo $cotizacion['validez']; ?> días</p>
            </div>
            <div class="col-md-6">
                <p><strong>Condiciones de Pago:</strong> <?php echo $cotizacion['condiciones_pago']; ?></p>
                <p><strong>Tiempo de Entrega:</strong> <?php echo $cotizacion['tiempo_entrega']; ?></p>
                <p><strong>Estado:</strong> 
                    <span class="badge 
                        <?php 
                        switch($cotizacion['estado']) {
                            case 'pendiente': echo 'badge-warning'; break;
                            case 'aprobada': echo 'badge-success'; break;
                            case 'rechazada': echo 'badge-danger'; break;
                            case 'convertida': echo 'badge-info'; break;
                            case 'vencida': echo 'badge-secondary'; break;
                            default: echo 'badge-secondary';
                        }
                        ?>">
                        <?php echo ucfirst($cotizacion['estado']); ?>
                    </span>
                </p>
                <p><strong>Creado por:</strong> <?php echo $cotizacion['usuario_nombre'] . ' ' . $cotizacion['usuario_apellido']; ?></p>
            </div>
        </div>
        
        <?php if (!empty($cotizacion['notas'])): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <p><strong>Notas:</strong></p>
                    <p><?php echo nl2br($cotizacion['notas']); ?></p>
                </div>
            </div>
        <?php endif; ?>
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
                        <th>Material</th>
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
                            <td><?php echo $detalle['material']; ?></td>
                            <td>
                                <?php 
                                echo $detalle['ancho'] . ' x ' . $detalle['largo'] . ' x ' . $detalle['micraje']; 
                                if ($detalle['fuelle'] > 0) {
                                    echo ' (Fuelle: ' . $detalle['fuelle'] . ')';
                                }
                                if (!empty($detalle['espesor'])) {
                                    echo '<br><small>Espesor: ' . $detalle['espesor'] . '</small>';
                                }
                                if (!empty($detalle['medida_referencial'])) {
                                    echo '<br><small>Ref: ' . $detalle['medida_referencial'] . '</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($detalle['colores'] > 0) {
                                    echo 'Colores';
                                } else {
                                    echo 'Negro';
                                }
                                if ($detalle['biodegradable']) {
                                    echo ' (Biodegradable)';
                                }
                                ?>
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
                        <th>S/ <?php echo number_format($cotizacion['subtotal'], 2); ?></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">IGV (18%):</th>
                        <th>S/ <?php echo number_format($cotizacion['impuestos'], 2); ?></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">Total:</th>
                        <th>S/ <?php echo number_format($cotizacion['total'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 text-right">
        <a href="cotizaciones.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <?php if ($cotizacion['estado'] == 'pendiente'): ?>
            <a href="aprobar_cotizacion.php?id=<?php echo $id_cotizacion; ?>" class="btn btn-success">
                <i class="fas fa-check"></i> Aprobar
            </a>
            <a href="rechazar_cotizacion.php?id=<?php echo $id_cotizacion; ?>" class="btn btn-danger">
                <i class="fas fa-times"></i> Rechazar
            </a>
        <?php endif; ?>
        
        <?php if ($cotizacion['estado'] == 'pendiente' || $cotizacion['estado'] == 'aprobada'): ?>
            <a href="generar_proforma.php?id=<?php echo $id_cotizacion; ?>" class="btn btn-primary">
                <i class="fas fa-file-contract"></i> Generar Proforma
            </a>
        <?php elseif ($cotizacion['estado'] == 'convertida' && $id_proforma): ?>
            <a href="imprimir_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Imprimir Proforma
            </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>