<?php
// ver_proforma.php
require_once 'config/db.php';
require_once 'includes/header.php';

// Verificar si hay un ID proporcionado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: proformas.php?error=ID de proforma no proporcionado");
    exit;
}

$id_proforma = $_GET['id'];

// Obtener datos de la proforma
$stmt = $conn->prepare("
    SELECT p.*, c.razon_social, c.ruc, c.direccion, c.telefono, c.email, c.contacto_nombre,
           u.nombre as usuario_nombre, u.apellido as usuario_apellido
    FROM proformas p
    LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
    LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_proforma = :id
");
$stmt->bindParam(':id', $id_proforma);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: proformas.php?error=Proforma no encontrada");
    exit;
}

$proforma = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener detalles de la proforma
$stmt = $conn->prepare("
    SELECT pd.*, m.nombre as material_nombre, m.tipo as material_tipo, m.color as material_color, m.biodegradable
    FROM proforma_detalles pd
    LEFT JOIN materiales m ON pd.id_material = m.id_material
    WHERE pd.id_proforma = :id_proforma
");
$stmt->bindParam(':id_proforma', $id_proforma);
$stmt->execute();
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si la proforma tiene una cotización de origen, obtener esos datos
$cotizacion = null;
if (!empty($proforma['id_cotizacion'])) {
    $stmt = $conn->prepare("SELECT * FROM cotizaciones WHERE id_cotizacion = :id_cotizacion");
    $stmt->bindParam(':id_cotizacion', $proforma['id_cotizacion']);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Función para mostrar el estado con colores
function mostrarEstado($estado) {
    $clase = '';
    $texto = ucfirst($estado);
    
    switch($estado) {
        case 'emitida':
            $clase = 'badge-warning';
            break;
        case 'aprobada':
            $clase = 'badge-success';
            break;
        case 'rechazada':
            $clase = 'badge-danger';
            break;
        case 'facturada':
            $clase = 'badge-info';
            break;
        case 'vencida':
            $clase = 'badge-secondary';
            break;
    }
    
    return '<span class="badge ' . $clase . '">' . $texto . '</span>';
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-8">
            <h2><i class="fas fa-file-invoice"></i> Detalle de Proforma</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="proformas.php">Proformas</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detalle de Proforma #<?php echo $proforma['codigo']; ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-right">
            <a href="imprimir_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Versión Imprimible
            </a>
        </div>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $_GET['message'] ?? 'Operación realizada con éxito'; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['error']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Información de la Proforma -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información de la Proforma</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Código:</div>
                        <div class="col-md-8"><?php echo $proforma['codigo']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Fecha Emisión:</div>
                        <div class="col-md-8"><?php echo date('d/m/Y', strtotime($proforma['fecha_emision'])); ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Validez:</div>
                        <div class="col-md-8"><?php echo $proforma['validez']; ?> días</div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Estado:</div>
                        <div class="col-md-8"><?php echo mostrarEstado($proforma['estado']); ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Creado por:</div>
                        <div class="col-md-8"><?php echo $proforma['usuario_nombre'] . ' ' . $proforma['usuario_apellido']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Creado el:</div>
                        <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($proforma['fecha_creacion'])); ?></div>
                    </div>
                    
                    <?php if (!empty($proforma['fecha_aprobacion'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Aprobado el:</div>
                        <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($proforma['fecha_aprobacion'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($proforma['fecha_rechazo'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Rechazado el:</div>
                        <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($proforma['fecha_rechazo'])); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Motivo rechazo:</div>
                        <div class="col-md-8"><?php echo $proforma['motivo_rechazo']; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cotizacion)): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Cotización:</div>
                        <div class="col-md-8">
                            <a href="ver_cotizacion.php?id=<?php echo $cotizacion['id_cotizacion']; ?>">
                                <?php echo $cotizacion['codigo']; ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Información del Cliente -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Razón Social:</div>
                        <div class="col-md-8"><?php echo $proforma['razon_social']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">RUC:</div>
                        <div class="col-md-8"><?php echo $proforma['ruc']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Dirección:</div>
                        <div class="col-md-8"><?php echo $proforma['direccion']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Teléfono:</div>
                        <div class="col-md-8"><?php echo $proforma['telefono']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Email:</div>
                        <div class="col-md-8"><?php echo $proforma['email']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Contacto:</div>
                        <div class="col-md-8"><?php echo $proforma['contacto_nombre'] ?? 'No especificado'; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Condiciones de Pago:</div>
                        <div class="col-md-8"><?php echo $proforma['condiciones_pago']; ?></div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 font-weight-bold">Tiempo de Entrega:</div>
                        <div class="col-md-8"><?php echo $proforma['tiempo_entrega']; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detalle de Productos -->
<!-- Detalle de Productos -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Detalle de Productos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Especificaciones</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?php echo $contador++; ?></td>
                            <td>
                                <?php echo $detalle['descripcion']; ?>
                            </td>
                            <td>
                                <?php
                                // No necesitamos conversión ya que los valores están en pulgadas
                                $ancho_pulg = $detalle['ancho'];
                                $largo_pulg = $detalle['largo'];
                                ?>
                                <strong>Dimensiones:</strong> <?php echo $ancho_pulg; ?>" x <?php echo $largo_pulg; ?>" (<?php echo number_format($ancho_pulg * 2.54, 2); ?> x <?php echo number_format($largo_pulg * 2.54, 2); ?> cm)
                                
                                <?php if (!empty($detalle['medida_referencial'])): ?>
                                <br><strong>Medida referencial:</strong> <?php echo $detalle['medida_referencial']; ?>
                                <?php endif; ?>
                                
                                <?php if ($detalle['fuelle'] > 0): ?>
                                <br><strong>Fuelle:</strong> <?php echo $detalle['fuelle']; ?>" (<?php echo number_format($detalle['fuelle'] * 2.54, 2); ?> cm)
                                <?php endif; ?>
                                
                                <br><strong>Espesor:</strong> <?php echo number_format($detalle['micraje'], 2); ?> micras
                                
                                <br><strong>Color:</strong> <?php echo $detalle['material_color']; ?>
                                
                                <br><strong>Material:</strong> <?php echo $detalle['material_nombre']; ?>
                                
                                <?php if ($detalle['biodegradable']): ?>
                                <br><strong>Biodegradable:</strong> Sí
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo number_format($detalle['cantidad']); ?></td>
                            <td class="text-right">S/ <?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                            <td class="text-right">S/ <?php echo number_format($detalle['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"></td>
                        <th class="text-right">Subtotal:</th>
                        <td class="text-right">S/ <?php echo number_format($proforma['subtotal'], 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <th class="text-right">IGV (18%):</th>
                        <td class="text-right">S/ <?php echo number_format($proforma['impuestos'], 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <th class="text-right">TOTAL:</th>
                        <td class="text-right font-weight-bold">S/ <?php echo number_format($proforma['total'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
    
    <!-- Notas Adicionales -->
    <?php if (!empty($proforma['notas'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Notas Adicionales</h5>
        </div>
        <div class="card-body">
            <?php echo nl2br(htmlspecialchars($proforma['notas'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Acciones -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Acciones</h5>
        </div>
        <div class="card-body text-center">
            <a href="proformas.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Proformas
            </a>
            
            <a href="imprimir_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Imprimir Proforma
            </a>
            
            <?php if ($proforma['estado'] == 'emitida'): ?>
                <a href="aprobar_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-success" onclick="return confirm('¿Está seguro de aprobar esta proforma? Esto generará una orden de venta.');">
                    <i class="fas fa-check"></i> Aprobar Proforma
                </a>
                
                <a href="rechazar_proforma.php?id=<?php echo $id_proforma; ?>" class="btn btn-danger">
                    <i class="fas fa-times"></i> Rechazar Proforma
                </a>
            <?php endif; ?>
            
            <?php if ($proforma['estado'] == 'aprobada'): ?>
                <?php
                // Verificar si ya tiene orden de venta
                $stmt = $conn->prepare("SELECT id_orden, codigo FROM ordenes_venta WHERE id_proforma = :id_proforma");
                $stmt->bindParam(':id_proforma', $id_proforma);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <a href="ver_orden.php?id=<?php echo $orden['id_orden']; ?>" class="btn btn-primary">
                        <i class="fas fa-file-invoice"></i> Ver Orden #<?php echo $orden['codigo']; ?>
                    </a>
                <?php
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>