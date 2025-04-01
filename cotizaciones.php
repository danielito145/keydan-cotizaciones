<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si hay un filtro de estado
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Consulta base para obtener cotizaciones
$sql = "SELECT c.id_cotizacion, c.codigo, c.fecha_cotizacion, c.validez, 
               c.total, c.estado, cl.razon_social as cliente
        FROM cotizaciones c
        JOIN clientes cl ON c.id_cliente = cl.id_cliente";

// Añadir filtro si existe
if (!empty($filtro_estado)) {
    $sql .= " WHERE c.estado = :estado";
}

$sql .= " ORDER BY c.fecha_cotizacion DESC";

try {
    $stmt = $conn->prepare($sql);
    
    // Vincular parámetro si hay filtro
    if (!empty($filtro_estado)) {
        $stmt->bindParam(':estado', $filtro_estado);
    }
    
    $stmt->execute();
    $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Obtener la cantidad de cotizaciones por estado para estadísticas
try {
    $stmt = $conn->query("SELECT estado, COUNT(*) as cantidad FROM cotizaciones GROUP BY estado");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $estadisticas = [
        'pendiente' => 0,
        'aprobada' => 0,
        'rechazada' => 0,
        'convertida' => 0,
        'vencida' => 0
    ];
    
    foreach ($estados as $estado) {
        $estadisticas[$estado['estado']] = $estado['cantidad'];
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-file-invoice-dollar"></i> Gestión de Cotizaciones</h2>
    </div>
    <div class="col-md-4 text-right">
        <a href="nueva_cotizacion.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nueva Cotización
        </a>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Total</h5>
                <h3><?php echo array_sum($estadisticas); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Pendientes</h5>
                <h3><?php echo $estadisticas['pendiente']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-white bg-success">
            <div class="card-body text-center">
                <h5>Aprobadas</h5>
                <h3><?php echo $estadisticas['aprobada']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-white bg-danger">
            <div class="card-body text-center">
                <h5>Rechazadas</h5>
                <h3><?php echo $estadisticas['rechazada']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-white bg-info">
            <div class="card-body text-center">
                <h5>Convertidas</h5>
                <h3><?php echo $estadisticas['convertida']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-white bg-secondary">
            <div class="card-body text-center">
                <h5>Vencidas</h5>
                <h3><?php echo $estadisticas['vencida']; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
    </div>
    <div class="card-body">
        <form action="" method="get" class="form-inline">
            <div class="form-group mb-2 mr-3">
                <label for="estado" class="mr-2">Estado:</label>
                <select name="estado" id="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="pendiente" <?php echo ($filtro_estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="aprobada" <?php echo ($filtro_estado == 'aprobada') ? 'selected' : ''; ?>>Aprobada</option>
                    <option value="rechazada" <?php echo ($filtro_estado == 'rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                    <option value="convertida" <?php echo ($filtro_estado == 'convertida') ? 'selected' : ''; ?>>Convertida</option>
                    <option value="vencida" <?php echo ($filtro_estado == 'vencida') ? 'selected' : ''; ?>>Vencida</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mb-2">Filtrar</button>
            <?php if (!empty($filtro_estado)): ?>
                <a href="cotizaciones.php" class="btn btn-outline-secondary mb-2 ml-2">Limpiar filtros</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Listado de cotizaciones -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-list"></i> Listado de Cotizaciones</h5>
    </div>
    <div class="card-body">
        <?php if (count($cotizaciones) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Validez</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cotizaciones as $cotizacion): ?>
                            <tr>
                                <td><?php echo $cotizacion['codigo']; ?></td>
                                <td><?php echo $cotizacion['cliente']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cotizacion['fecha_cotizacion'])); ?></td>
                                <td><?php echo $cotizacion['validez']; ?> días</td>
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
                                        case 'vencida':
                                            $badge_class = 'badge-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($cotizacion['estado']); ?></span>
                                </td>
                                <td>
                                    <a href="ver_cotizacion.php?id=<?php echo $cotizacion['id_cotizacion']; ?>" class="btn btn-sm btn-info" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar_cotizacion.php?id=<?php echo $cotizacion['id_cotizacion']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($cotizacion['estado'] == 'aprobada'): ?>
                                        <a href="convertir_proforma.php?id=<?php echo $cotizacion['id_cotizacion']; ?>" class="btn btn-sm btn-success" title="Convertir a Proforma">
                                            <i class="fas fa-file-contract"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger btn-eliminar-cotizacion" data-id="<?php echo $cotizacion['id_cotizacion']; ?>" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay cotizaciones <?php echo (!empty($filtro_estado)) ? "con el estado '$filtro_estado'" : ''; ?>.
                <a href="nueva_cotizacion.php" class="alert-link">Crear una nueva cotización</a>.
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-eliminar-cotizacion').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Evitar cualquier comportamiento predeterminado del botón
            const idCotizacion = this.getAttribute('data-id');
            if (confirm('¿Estás seguro de eliminar esta cotización?')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'eliminar_cotizacion.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const respuesta = JSON.parse(this.responseText);
                            if (respuesta.exito) {
                                alert(respuesta.mensaje);
                                window.location.reload();
                            } else {
                                alert('Error: ' + respuesta.mensaje);
                            }
                        } catch (e) {
                            alert('Error al procesar la respuesta del servidor');
                            console.error(e);
                        }
                    } else {
                        alert('Error en la solicitud');
                    }
                };
                xhr.send('id_cotizacion=' + idCotizacion);
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>