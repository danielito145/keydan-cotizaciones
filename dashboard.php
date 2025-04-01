<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Obtener datos para el dashboard
try {
    // Total de cotizaciones
    $stmt = $conn->query("SELECT COUNT(*) as total FROM cotizaciones");
    $total_cotizaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Cotizaciones pendientes
    $stmt = $conn->query("SELECT COUNT(*) as pendientes FROM cotizaciones WHERE estado = 'pendiente'");
    $cotizaciones_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];
    
    // Total de clientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 'activo'");
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Cotizaciones recientes
    $stmt = $conn->query("SELECT c.id_cotizacion, c.codigo, cl.razon_social, c.fecha_cotizacion, c.total, c.estado
                        FROM cotizaciones c
                        JOIN clientes cl ON c.id_cliente = cl.id_cliente
                        ORDER BY c.fecha_cotizacion DESC
                        LIMIT 5");
    $cotizaciones_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="jumbotron">
    <h1 class="display-4">Bienvenido al Sistema de Cotizaciones</h1>
    <p class="lead">Panel de control para la gestión de cotizaciones de KEYDAN SAC.</p>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Total Cotizaciones</h6>
                        <h1 class="display-4"><?php echo $total_cotizaciones; ?></h1>
                    </div>
                    <i class="fas fa-file-invoice-dollar fa-3x"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="cotizaciones.php" class="text-white">Ver detalles</a>
                <i class="fas fa-arrow-circle-right"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Cotizaciones Pendientes</h6>
                        <h1 class="display-4"><?php echo $cotizaciones_pendientes; ?></h1>
                    </div>
                    <i class="fas fa-clock fa-3x"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="cotizaciones.php?estado=pendiente" class="text-white">Ver pendientes</a>
                <i class="fas fa-arrow-circle-right"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Clientes Activos</h6>
                        <h1 class="display-4"><?php echo $total_clientes; ?></h1>
                    </div>
                    <i class="fas fa-users fa-3x"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="clientes.php" class="text-white">Ver clientes</a>
                <i class="fas fa-arrow-circle-right"></i>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-list"></i> Cotizaciones Recientes</h5>
    </div>
    <div class="card-body">
        <?php if(count($cotizaciones_recientes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cotizaciones_recientes as $cotizacion): ?>
                            <tr>
                                <td><?php echo $cotizacion['codigo']; ?></td>
                                <td><?php echo $cotizacion['razon_social']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cotizacion['fecha_cotizacion'])); ?></td>
                                <td>S/ <?php echo number_format($cotizacion['total'], 2); ?></td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    switch($cotizacion['estado']) {
                                        case 'pendiente':
                                            $badge_class = 'badge-warning';
                                            break;
                                        case 'aprobada':
                                            $badge_class = 'badge-success';
                                            break;
                                        case 'rechazada':
                                            $badge_class = 'badge-danger';
                                            break;
                                        case 'convertida':
                                            $badge_class = 'badge-info';
                                            break;
                                        default:
                                            $badge_class = 'badge-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($cotizacion['estado']); ?></span>
                                </td>
                                <td>
                                    <a href="ver_cotizacion.php?id=<?php echo $cotizacion['id_cotizacion']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar_cotizacion.php?id=<?php echo $cotizacion['id_cotizacion']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No hay cotizaciones recientes.</div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-center">
        <a href="cotizaciones.php" class="btn btn-primary">Ver todas las cotizaciones</a>
        <a href="nueva_cotizacion.php" class="btn btn-success">Crear nueva cotización</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>