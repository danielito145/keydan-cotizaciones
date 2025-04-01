<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Procesar filtros si existen
$where = "";
$params = array();

if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $where .= " AND op.estado = :estado";
    $params[':estado'] = $_GET['estado'];
}

if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
    $where .= " AND (op.codigo LIKE :codigo OR o.codigo LIKE :codigo)";
    $params[':codigo'] = '%' . $_GET['codigo'] . '%';
}

if (isset($_GET['cliente']) && !empty($_GET['cliente'])) {
    $where .= " AND (cl.razon_social LIKE :cliente OR cl.ruc LIKE :cliente)";
    $params[':cliente'] = '%' . $_GET['cliente'] . '%';
}

if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
    $where .= " AND op.fecha_inicio >= :fecha_desde";
    $params[':fecha_desde'] = $_GET['fecha_desde'] . ' 00:00:00';
}

if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
    $where .= " AND op.fecha_inicio <= :fecha_hasta";
    $params[':fecha_hasta'] = $_GET['fecha_hasta'] . ' 23:59:59';
}

if (isset($_GET['responsable']) && !empty($_GET['responsable'])) {
    $where .= " AND op.id_responsable = :responsable";
    $params[':responsable'] = $_GET['responsable'];
}

// Obtener estadísticas para los contadores
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN op.estado = 'programada' THEN 1 ELSE 0 END) as programadas,
                SUM(CASE WHEN op.estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN op.estado = 'pausada' THEN 1 ELSE 0 END) as pausadas,
                SUM(CASE WHEN op.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN op.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
              FROM ordenes_produccion op";

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener total de registros para paginación
$sql_count = "SELECT COUNT(*) AS total 
              FROM ordenes_produccion op 
              JOIN ordenes_venta o ON op.id_orden = o.id_orden
              JOIN clientes cl ON o.id_cliente = cl.id_cliente
              JOIN usuarios u ON op.id_responsable = u.id_usuario
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

// Obtener el campo para ordenar
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'fecha_inicio';
$order_dir = isset($_GET['order_dir']) && strtolower($_GET['order_dir']) === 'asc' ? 'ASC' : 'DESC';

// Lista de campos válidos para ordenar
$valid_fields = ['codigo', 'fecha_inicio', 'fecha_estimada_fin', 'estado', 'responsable'];
if (!in_array($order_by, $valid_fields)) {
    $order_by = 'fecha_inicio';
}

// Construir la consulta de ordenación
$order_clause = "";
if ($order_by === 'responsable') {
    $order_clause = " ORDER BY u.nombre {$order_dir}, u.apellido {$order_dir}";
} elseif ($order_by === 'codigo') {
    $order_clause = " ORDER BY op.codigo {$order_dir}";
} else {
    $order_clause = " ORDER BY op.{$order_by} {$order_dir}";
}

// Obtener lista de órdenes de producción
$sql = "SELECT op.*, o.codigo as codigo_orden, cl.razon_social as cliente, 
        CONCAT(u.nombre, ' ', u.apellido) as responsable,
        DATEDIFF(CURRENT_DATE(), op.fecha_inicio) as dias_transcurridos,
        DATEDIFF(op.fecha_estimada_fin, CURRENT_DATE()) as dias_restantes
        FROM ordenes_produccion op
        JOIN ordenes_venta o ON op.id_orden = o.id_orden
        JOIN clientes cl ON o.id_cliente = cl.id_cliente
        JOIN usuarios u ON op.id_responsable = u.id_usuario
        WHERE 1=1" . $where . $order_clause . "
        LIMIT :offset, :limit";

$stmt = $conn->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();

$ordenes_produccion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de responsables para el filtro
$stmt_responsables = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE activo = 1 ORDER BY nombre");
$responsables = $stmt_responsables->fetchAll(PDO::FETCH_ASSOC);

// Función para generar URL de ordenación
function getOrderUrl($field) {
    $params = $_GET;
    $params['order_by'] = $field;
    $params['order_dir'] = (isset($_GET['order_by']) && $_GET['order_by'] === $field && isset($_GET['order_dir']) && $_GET['order_dir'] === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

// Función para mostrar icono de ordenación
function getOrderIcon($field) {
    if (isset($_GET['order_by']) && $_GET['order_by'] === $field) {
        return (isset($_GET['order_dir']) && $_GET['order_dir'] === 'asc') ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
    }
    return '<i class="fas fa-sort ml-1 text-muted"></i>';
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-industry"></i> Gestión de Producción</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Producción</li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="ordenes.php" class="btn btn-primary">
            <i class="fas fa-file-invoice"></i> Órdenes de Venta
        </a>
        <a href="nueva_produccion.php" class="btn btn-success">
            <i class="fas fa-plus-circle"></i> Nueva Producción
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

<!-- Contadores de estado -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['total']; ?></h3>
                <p class="mb-0">TOTAL</p>
                <i class="fas fa-file-invoice fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['programadas']; ?></h3>
                <p class="mb-0">PROGRAMADAS</p>
                <i class="fas fa-calendar-alt fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['en_proceso']; ?></h3>
                <p class="mb-0">EN PROCESO</p>
                <i class="fas fa-cogs fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['pausadas']; ?></h3>
                <p class="mb-0">PAUSADAS</p>
                <i class="fas fa-pause-circle fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['completadas']; ?></h3>
                <p class="mb-0">COMPLETADAS</p>
                <i class="fas fa-check-circle fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3 class="display-4"><?php echo $stats['canceladas']; ?></h3>
                <p class="mb-0">CANCELADAS</p>
                <i class="fas fa-times-circle fa-2x position-absolute" style="top:10px; right:10px; opacity:0.3;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filtros Rápidos -->
<div class="mb-3">
    <div class="btn-group">
        <a href="produccion.php" class="btn <?php echo !isset($_GET['estado']) ? 'btn-primary' : 'btn-outline-primary'; ?>">
            Todas (<?php echo $stats['total']; ?>)
        </a>
        <a href="produccion.php?estado=programada" class="btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === 'programada') ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
            Programadas (<?php echo $stats['programadas']; ?>)
        </a>
        <a href="produccion.php?estado=en_proceso" class="btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === 'en_proceso') ? 'btn-info' : 'btn-outline-info'; ?>">
            En Proceso (<?php echo $stats['en_proceso']; ?>)
        </a>
        <a href="produccion.php?estado=pausada" class="btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === 'pausada') ? 'btn-warning' : 'btn-outline-warning'; ?>">
            Pausadas (<?php echo $stats['pausadas']; ?>)
        </a>
        <a href="produccion.php?estado=completada" class="btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === 'completada') ? 'btn-success' : 'btn-outline-success'; ?>">
            Completadas (<?php echo $stats['completadas']; ?>)
        </a>
        <a href="produccion.php?estado=cancelada" class="btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === 'cancelada') ? 'btn-danger' : 'btn-outline-danger'; ?>">
            Canceladas (<?php echo $stats['canceladas']; ?>)
        </a>
    </div>
</div>

<!-- Filtros Avanzados -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Filtros avanzados</h5>
    </div>
    <div class="card-body">
        <form method="get" action="produccion.php">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="codigo">Código:</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo isset($_GET['codigo']) ? $_GET['codigo'] : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="cliente">Cliente:</label>
                        <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo isset($_GET['cliente']) ? $_GET['cliente'] : ''; ?>" placeholder="Nombre o RUC">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="responsable">Responsable:</label>
                        <select class="form-control" id="responsable" name="responsable">
                            <option value="">-- Todos --</option>
                            <?php foreach($responsables as $resp): ?>
                                <option value="<?php echo $resp['id_usuario']; ?>" <?php echo (isset($_GET['responsable']) && $_GET['responsable'] == $resp['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo $resp['nombre'] . ' ' . $resp['apellido']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="fecha_desde">Desde:</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="fecha_hasta">Hasta:</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''; ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select class="form-control" id="estado" name="estado">
                            <option value="">-- Todos --</option>
                            <option value="programada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'programada') ? 'selected' : ''; ?>>Programada</option>
                            <option value="en_proceso" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="pausada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'pausada') ? 'selected' : ''; ?>>Pausada</option>
                            <option value="completada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                            <option value="cancelada" <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-8 text-right" style="margin-top: 32px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="produccion.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Órdenes de Producción -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Listado de Órdenes de Producción</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>
                            <a href="<?php echo getOrderUrl('codigo'); ?>" class="text-dark">
                                Código <?php echo getOrderIcon('codigo'); ?>
                            </a>
                        </th>
                        <th>Orden</th>
                        <th>Cliente</th>
                        <th>
                            <a href="<?php echo getOrderUrl('fecha_inicio'); ?>" class="text-dark">
                                Fecha Inicio <?php echo getOrderIcon('fecha_inicio'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getOrderUrl('fecha_estimada_fin'); ?>" class="text-dark">
                                Fecha Est. Fin <?php echo getOrderIcon('fecha_estimada_fin'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getOrderUrl('responsable'); ?>" class="text-dark">
                                Responsable <?php echo getOrderIcon('responsable'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getOrderUrl('estado'); ?>" class="text-dark">
                                Estado <?php echo getOrderIcon('estado'); ?>
                            </a>
                        </th>
                        <th>Progreso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ordenes_produccion) > 0): ?>
                        <?php foreach($ordenes_produccion as $orden): ?>
                            <?php 
                            // Determinar si la producción está retrasada
                            $clase_fila = '';
                            if ($orden['estado'] == 'programada' || $orden['estado'] == 'en_proceso' || $orden['estado'] == 'pausada') {
                                if (isset($orden['dias_restantes']) && $orden['dias_restantes'] < 0) {
                                    $clase_fila = 'table-danger'; // Retrasada
                                } elseif (isset($orden['dias_restantes']) && $orden['dias_restantes'] <= 2) {
                                    $clase_fila = 'table-warning'; // Por vencer (2 días o menos)
                                }
                            }
                            
                            // Obtener porcentaje de progreso de la producción
                            $stmt_progreso = $conn->prepare("
                                SELECT 
                                    SUM(cantidad_programada) as total_programado,
                                    SUM(cantidad_producida) as total_producido
                                FROM produccion_detalles
                                WHERE id_orden_produccion = :id_orden_produccion
                            ");
                            $stmt_progreso->bindParam(':id_orden_produccion', $orden['id_orden_produccion']);
                            $stmt_progreso->execute();
                            $progreso = $stmt_progreso->fetch(PDO::FETCH_ASSOC);
                            
                            $porcentaje_progreso = 0;
                            if ($progreso && $progreso['total_programado'] > 0) {
                                $porcentaje_progreso = round(($progreso['total_producido'] / $progreso['total_programado']) * 100);
                            }
                            ?>
                            <tr class="<?php echo $clase_fila; ?>">
                                <td><?php echo $orden['codigo']; ?></td>
                                <td>
                                    <a href="ver_orden.php?id=<?php echo $orden['id_orden']; ?>" target="_blank">
                                        <?php echo $orden['codigo_orden']; ?>
                                    </a>
                                </td>
                                <td><?php echo $orden['cliente']; ?></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($orden['fecha_inicio'])); ?>
                                    <br>
                                    <small class="text-muted">
                                        Hace <?php echo $orden['dias_transcurridos']; ?> días
                                    </small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($orden['fecha_estimada_fin'])); ?>
                                    <?php if ($orden['estado'] != 'completada' && $orden['estado'] != 'cancelada'): ?>
                                        <?php if ($orden['dias_restantes'] < 0): ?>
                                            <br><span class="badge badge-danger">Retrasada (<?php echo abs($orden['dias_restantes']); ?> días)</span>
                                        <?php elseif ($orden['dias_restantes'] <= 2): ?>
                                            <br><span class="badge badge-warning">Por vencer (<?php echo $orden['dias_restantes']; ?> días)</span>
                                        <?php else: ?>
                                            <br><span class="badge badge-info">Faltan <?php echo $orden['dias_restantes']; ?> días</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $orden['responsable']; ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        switch($orden['estado']) {
                                            case 'programada': echo 'badge-secondary'; break;
                                            case 'en_proceso': echo 'badge-info'; break;
                                            case 'pausada': echo 'badge-warning'; break;
                                            case 'completada': echo 'badge-success'; break;
                                            case 'cancelada': echo 'badge-danger'; break;
                                            default: echo 'badge-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar 
                                            <?php 
                                            if ($porcentaje_progreso == 100) {
                                                echo 'bg-success';
                                            } elseif ($porcentaje_progreso >= 75) {
                                                echo 'bg-info';
                                            } elseif ($porcentaje_progreso >= 50) {
                                                echo 'bg-primary';
                                            } elseif ($porcentaje_progreso >= 25) {
                                                echo 'bg-warning';
                                            } else {
                                                echo 'bg-danger';
                                            }
                                            ?>" 
                                            role="progressbar" 
                                            style="width: <?php echo $porcentaje_progreso; ?>%" 
                                            aria-valuenow="<?php echo $porcentaje_progreso; ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            <?php echo $porcentaje_progreso; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="ver_produccion.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-info" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($orden['estado'] == 'programada'): ?>
                                            <a href="iniciar_proceso.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-primary" title="Iniciar Proceso">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($orden['estado'] == 'en_proceso'): ?>
                                            <a href="pausar_proceso.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-warning" title="Pausar Proceso">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                            <a href="completar_produccion.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-success" title="Completar Producción">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($orden['estado'] == 'pausada'): ?>
                                            <a href="reanudar_proceso.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-primary" title="Reanudar Proceso">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($orden['estado'] != 'completada' && $orden['estado'] != 'cancelada'): ?>
                                            <a href="registrar_avance.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-secondary" title="Registrar Avance">
                                                <i class="fas fa-tasks"></i>
                                            </a>
                                            <a href="cancelar_produccion.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-danger" title="Cancelar Producción" onclick="return confirm('¿Está seguro de cancelar esta producción?');">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="imprimir_produccion.php?id=<?php echo $orden['id_orden_produccion']; ?>" class="btn btn-dark" title="Imprimir" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No se encontraron órdenes de producción</td>
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
                            <a class="page-link" href="?pagina=1<?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['codigo']) ? '&codigo='.$_GET['codigo'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['responsable']) ? '&responsable='.$_GET['responsable'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by='.$_GET['order_by'] : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir='.$_GET['order_dir'] : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['codigo']) ? '&codigo='.$_GET['codigo'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['responsable']) ? '&responsable='.$_GET['responsable'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by='.$_GET['order_by'] : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir='.$_GET['order_dir'] : ''; ?>">
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
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['codigo']) ? '&codigo='.$_GET['codigo'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['responsable']) ? '&responsable='.$_GET['responsable'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by='.$_GET['order_by'] : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir='.$_GET['order_dir'] : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['codigo']) ? '&codigo='.$_GET['codigo'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['responsable']) ? '&responsable='.$_GET['responsable'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by='.$_GET['order_by'] : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir='.$_GET['order_dir'] : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo isset($_GET['estado']) ? '&estado='.$_GET['estado'] : ''; ?><?php echo isset($_GET['codigo']) ? '&codigo='.$_GET['codigo'] : ''; ?><?php echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; ?><?php echo isset($_GET['responsable']) ? '&responsable='.$_GET['responsable'] : ''; ?><?php echo isset($_GET['fecha_desde']) ? '&fecha_desde='.$_GET['fecha_desde'] : ''; ?><?php echo isset($_GET['fecha_hasta']) ? '&fecha_hasta='.$_GET['fecha_hasta'] : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by='.$_GET['order_by'] : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir='.$_GET['order_dir'] : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<style>
    .table-hover-highlight {
        background-color: rgba(0,123,255,0.05) !important;
    }
    .badge {
        font-size: 85%;
        padding: 0.4em 0.6em;
    }
    .card .position-absolute {
        z-index: 1;
    }
    .display-4 {
        font-size: 2.5rem;
        font-weight: 300;
        line-height: 1.2;
    }
    .progress {
        background-color: #e9ecef;
        border-radius: 0.25rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight de la fila al pasar el mouse
        const filas = document.querySelectorAll('tbody tr');
        filas.forEach(fila => {
            fila.addEventListener('mouseenter', function() {
                if (!this.classList.contains('table-danger') && !this.classList.contains('table-warning')) {
                    this.classList.add('table-hover-highlight');
                }
            });
            fila.addEventListener('mouseleave', function() {
                this.classList.remove('table-hover-highlight');
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>