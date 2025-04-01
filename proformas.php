<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Procesar filtros si existen
$where = "";
$params = array();

if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $where .= " AND p.estado = :estado";
    $params[':estado'] = $_GET['estado'];
}

if (isset($_GET['cliente']) && !empty($_GET['cliente'])) {
    $where .= " AND (cl.razon_social LIKE :cliente OR cl.ruc LIKE :cliente)";
    $params[':cliente'] = '%' . $_GET['cliente'] . '%';
}

if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
    $where .= " AND p.fecha_emision >= :fecha_desde";
    $params[':fecha_desde'] = $_GET['fecha_desde'] . ' 00:00:00';
}

if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
    $where .= " AND p.fecha_emision <= :fecha_hasta";
    $params[':fecha_hasta'] = $_GET['fecha_hasta'] . ' 23:59:59';
}

// Obtener total de registros para paginación
$sql_count = "SELECT COUNT(*) AS total FROM proformas p 
              JOIN clientes cl ON p.id_cliente = cl.id_cliente
              WHERE 1=1" . $where;

$stmt_count = $conn->prepare($sql_count);
foreach ($params as $param => $value) {
    $stmt_count->bindValue($param, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener lista de proformas
$sql = "SELECT p.*, cl.razon_social as cliente, u.nombre as usuario_nombre, u.apellido as usuario_apellido
        FROM proformas p
        JOIN clientes cl ON p.id_cliente = cl.id_cliente
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE 1=1" . $where . "
        ORDER BY p.fecha_emision DESC
        LIMIT :offset, :limit";

$stmt = $conn->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();

$proformas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el filtro
$stmt_clientes = $conn->query("SELECT id_cliente, razon_social, ruc FROM clientes WHERE estado = 'activo' ORDER BY razon_social");
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-file-contract"></i> Proformas</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Proformas</li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="cotizaciones.php" class="btn btn-primary">
            <i class="fas fa-file-invoice-dollar"></i> Cotizaciones
        </a>
        <a href="ordenes.php" class="btn btn-success">
            <i class="fas fa-file-invoice"></i> Órdenes
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
            echo "Operación completada correctamente.";
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['error']; ?>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" action="proformas.php" class="form-inline">
            <div class="form-group mb-2 mr-2">
                <label for="estado" class="mr-2">Estado:</label>
                <select name="estado" id="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="emitida" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'emitida') ? 'selected' : ''; ?>>Emitida</option>
                    <option value="aprobada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'aprobada') ? 'selected' : ''; ?>>Aprobada</option>
                    <option value="rechazada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                    <option value="vencida" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'vencida') ? 'selected' : ''; ?>>Vencida</option>
                </select>
            </div>
            <div class="form-group mb-2 mr-2">
                <label for="cliente" class="mr-2">Cliente:</label>
                <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo isset($_GET['cliente']) ? $_GET['cliente'] : ''; ?>" placeholder="Nombre o RUC">
            </div>
            <div class="form-group mb-2 mr-2">
                <label for="fecha_desde" class="mr-2">Desde:</label>
                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''; ?>">
            </div>
            <div class="form-group mb-2 mr-2">
                <label for="fecha_hasta" class="mr-2">Hasta:</label>
                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary mb-2">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="proformas.php" class="btn btn-secondary mb-2 ml-2">
                <i class="fas fa-sync-alt"></i> Limpiar
            </a>
        </form>
    </div>
</div>

<!-- Tabla de Proformas -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Listado de Proformas</h5>
    </div>
    <div class="card-body">
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
                    <?php if (count($proformas) > 0): ?>
                        <?php foreach($proformas as $proforma): ?>
                            <tr>
                                <td><?php echo $proforma['codigo']; ?></td>
                                <td><?php echo $proforma['cliente']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($proforma['fecha_emision'])); ?></td>
                                <td>S/ <?php echo number_format($proforma['total'], 2); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        switch($proforma['estado']) {
                                            case 'emitida': echo 'badge-warning'; break;
                                            case 'aprobada': echo 'badge-success'; break;
                                            case 'rechazada': echo 'badge-danger'; break;
                                            case 'vencida': echo 'badge-secondary'; break;
                                            default: echo 'badge-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($proforma['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="ver_proforma.php?id=<?php echo $proforma['id_proforma']; ?>" class="btn btn-info" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="imprimir_proforma.php?id=<?php echo $proforma['id_proforma']; ?>" class="btn btn-primary" title="Imprimir" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($proforma['estado'] == 'emitida'): ?>
                                            <a href="aprobar_proforma.php?id=<?php echo $proforma['id_proforma']; ?>" class="btn btn-success" title="Aprobar">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="rechazar_proforma.php?id=<?php echo $proforma['id_proforma']; ?>" class="btn btn-danger" title="Rechazar">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No se encontraron proformas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación">
                <ul class="pagination justify-content-center">
                    <?php if ($pagina_actual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=1<?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $rango = 2;
                    $inicio = max(1, $pagina_actual - $rango);
                    $fin = min($total_paginas, $pagina_actual + $rango);
                    
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>