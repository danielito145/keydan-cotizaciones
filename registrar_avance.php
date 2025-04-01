<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Activar depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: produccion.php?error=ID de orden de producción no proporcionado");
    exit;
}

$id_orden_produccion = $_GET['id'];
$id_detalle = isset($_GET['detalle']) ? $_GET['detalle'] : null;
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $id_produccion_detalle = $_POST['id_produccion_detalle'];
        $id_etapa = $_POST['id_etapa'];
        $cantidad_producida = $_POST['cantidad_producida'];
        $peso_real = $_POST['peso_real'];
        $comentarios = $_POST['comentarios'];
        
        if (!is_numeric($cantidad_producida) || $cantidad_producida <= 0) {
            throw new Exception("La cantidad producida debe ser un número mayor a cero");
        }
        
        if (!is_numeric($peso_real) || $peso_real <= 0) {
            throw new Exception("El peso real debe ser un número mayor a cero");
        }
        
        $stmt = $conn->prepare("SELECT pd.*, od.cantidad as cantidad_orden
                              FROM produccion_detalles pd
                              JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                              WHERE pd.id_produccion_detalle = :id_detalle");
        $stmt->bindParam(':id_detalle', $id_produccion_detalle);
        $stmt->execute();
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detalle) {
            throw new Exception("El detalle de producción no existe");
        }
        
        if ($cantidad_producida > $detalle['cantidad_programada']) {
            throw new Exception("La cantidad producida no puede superar la cantidad programada (" . number_format($detalle['cantidad_programada']) . ")");
        }
        
        $stmt = $conn->prepare("SELECT * FROM produccion_etapas WHERE id_etapa = :id_etapa AND id_produccion_detalle = :id_detalle");
        $stmt->bindParam(':id_etapa', $id_etapa);
        $stmt->bindParam(':id_detalle', $id_produccion_detalle);
        $stmt->execute();
        $etapa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etapa) {
            throw new Exception("La etapa seleccionada no pertenece a este detalle de producción");
        }
        
        if ($etapa['estado'] !== 'completado') {
            $estado = ($cantidad_producida >= $detalle['cantidad_programada']) ? 'completado' : 'en_proceso';
            $stmt = $conn->prepare("UPDATE produccion_etapas 
                                  SET cantidad_procesada = :cantidad, 
                                  fecha_inicio = COALESCE(fecha_inicio, NOW()),
                                  estado = :estado,
                                  observaciones = CONCAT(COALESCE(observaciones, ''), '\nAvance registrado el ', NOW(), ': ', :comentarios)
                                  WHERE id_etapa = :id_etapa");
            $stmt->bindParam(':cantidad', $cantidad_producida);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':comentarios', $comentarios);
            $stmt->bindParam(':id_etapa', $id_etapa);
            $stmt->execute();
            
            if ($estado === 'completado') {
                $stmt = $conn->prepare("UPDATE produccion_etapas SET fecha_fin = NOW() WHERE id_etapa = :id_etapa");
                $stmt->bindParam(':id_etapa', $id_etapa);
                $stmt->execute();
                
                $stmt = $conn->prepare("INSERT INTO historial_produccion 
                                      (id_orden_produccion, fecha, tipo_evento, descripcion, id_usuario) 
                                      VALUES (:id_orden_produccion, NOW(), 'fin_etapa', :descripcion, :id_usuario)");
                $nombre_etapa = ucfirst($etapa['tipo_etapa']);
                $descripcion = "Etapa de $nombre_etapa completada para el producto #$id_produccion_detalle. Cantidad procesada: " . number_format($cantidad_producida);
                $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':id_usuario', $id_usuario);
                $stmt->execute();
            }
        }
        
        $estado_detalle = ($cantidad_producida >= $detalle['cantidad_programada']) ? 'completado' : 'en_proceso';
        $stmt = $conn->prepare("UPDATE produccion_detalles 
                              SET cantidad_producida = :cantidad_producida, 
                              peso_real = :peso_real,
                              estado = :estado
                              WHERE id_produccion_detalle = :id_detalle");
        $stmt->bindParam(':cantidad_producida', $cantidad_producida);
        $stmt->bindParam(':peso_real', $peso_real);
        $stmt->bindParam(':estado', $estado_detalle);
        $stmt->bindParam(':id_detalle', $id_produccion_detalle);
        $stmt->execute();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados
                              FROM produccion_detalles WHERE id_orden_produccion = :id_orden_produccion");
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] == $resultado['completados']) {
            $stmt = $conn->prepare("UPDATE ordenes_produccion SET estado = 'completada', fecha_fin = NOW() WHERE id_orden_produccion = :id_orden_produccion");
            $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
            $stmt->execute();
            
            $stmt = $conn->prepare("INSERT INTO historial_produccion 
                                  (id_orden_produccion, fecha, tipo_evento, descripcion, id_usuario) 
                                  VALUES (:id_orden_produccion, NOW(), 'cambio_estado', :descripcion, :id_usuario)");
            $descripcion = "Orden de producción completada automáticamente al finalizar todos los productos";
            $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
        }
        
        $conn->commit();
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&success=1&message=" . urlencode("Avance registrado correctamente"));
        exit;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_msg = "Error al registrar avance: " . $e->getMessage();
        error_log($error_msg);
    }
}

// Obtener datos de producción
try {
    $stmt = $conn->prepare("SELECT op.*, o.codigo as codigo_orden
                          FROM ordenes_produccion op
                          JOIN ordenes_venta o ON op.id_orden = o.id_orden
                          WHERE op.id_orden_produccion = :id");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $produccion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produccion) {
        header("Location: produccion.php?error=Orden de producción no encontrada");
        exit;
    }
    
    if ($produccion['estado'] !== 'en_proceso' && $produccion['estado'] !== 'pausada') {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=La producción debe estar en proceso o pausada para registrar avance");
        exit;
    }
    
    if ($id_detalle) {
        $stmt = $conn->prepare("SELECT pd.*, od.descripcion, od.ancho, od.largo, od.micraje, od.espesor, 
                              od.fuelle, od.colores, od.biodegradable, m.nombre as material
                              FROM produccion_detalles pd
                              JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                              JOIN materiales m ON od.id_material = m.id_material
                              WHERE pd.id_produccion_detalle = :id_detalle
                              AND pd.id_orden_produccion = :id_orden_produccion");
        $stmt->bindParam(':id_detalle', $id_detalle);
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->execute();
        $detalles = [$stmt->fetch(PDO::FETCH_ASSOC)];
    } else {
        $stmt = $conn->prepare("SELECT pd.*, od.descripcion, od.ancho, od.largo, od.micraje, od.espesor, 
                              od.fuelle, od.colores, od.biodegradable, m.nombre as material
                              FROM produccion_detalles pd
                              JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                              JOIN materiales m ON od.id_material = m.id_material
                              WHERE pd.id_orden_produccion = :id
                              AND pd.estado != 'completado'
                              ORDER BY pd.id_produccion_detalle");
        $stmt->bindParam(':id', $id_orden_produccion);
        $stmt->execute();
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($detalles)) {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=No hay productos pendientes para registrar avance");
        exit;
    }
    
    foreach ($detalles as $key => $detalle) {
        $stmt = $conn->prepare("SELECT * FROM produccion_etapas 
                              WHERE id_produccion_detalle = :id_detalle
                              ORDER BY FIELD(tipo_etapa, 'corte', 'sellado', 'control_calidad', 'empaque')");
        $stmt->bindParam(':id_detalle', $detalle['id_produccion_detalle']);
        $stmt->execute();
        $detalles[$key]['etapas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    header("Location: produccion.php?error=" . urlencode("Error al obtener datos: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-tasks"></i> Registrar Avance de Producción</h2>
    </div>
    <div class="col-md-4 text-right">
        <a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Registrar Avance de Producción</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Complete el formulario para registrar el avance de producción. Seleccione el producto, la etapa y la cantidad producida.
        </div>
        
        <form method="post" action="registrar_avance.php?id=<?php echo $id_orden_produccion; ?>" id="avanceForm">
            <div class="form-group">
                <label for="id_produccion_detalle">Producto: <span class="text-danger">*</span></label>
                <select class="form-control" id="id_produccion_detalle" name="id_produccion_detalle" required>
                    <option value="">-- Seleccione un producto --</option>
                    <?php foreach ($detalles as $detalle): ?>
                        <option value="<?php echo $detalle['id_produccion_detalle']; ?>" 
                                data-programada="<?php echo $detalle['cantidad_programada']; ?>" 
                                data-producida="<?php echo $detalle['cantidad_producida']; ?>" 
                                data-peso="<?php echo $detalle['peso_estimado']; ?>" 
                                data-peso-real="<?php echo $detalle['peso_real']; ?>" 
                                <?php echo (count($detalles) === 1 || ($id_detalle && $id_detalle == $detalle['id_produccion_detalle'])) ? 'selected' : ''; ?>>
                            <?php echo $detalle['descripcion'] . ' (' . $detalle['ancho'] . 'x' . $detalle['largo'] . ') - ' . $detalle['material']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_etapa">Etapa: <span class="text-danger">*</span></label>
                <select class="form-control" id="id_etapa" name="id_etapa" required>
                    <option value="">-- Seleccione una etapa --</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cantidad_programada">Cantidad Programada:</label>
                        <input type="text" class="form-control" id="cantidad_programada" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cantidad_actual">Cantidad Actual:</label>
                        <input type="text" class="form-control" id="cantidad_actual" readonly>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="cantidad_producida">Nueva Cantidad Producida: <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="cantidad_producida" name="cantidad_producida" min="1" required>
                        <small class="form-text text-muted">Esta es la cantidad total producida hasta el momento, no el incremento.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="peso_real">Peso Real (kg): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="peso_real" name="peso_real" min="0.01" step="0.01" required>
                        <small class="form-text text-muted">Peso real total del producto producido.</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="comentarios">Comentarios:</label>
                <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
            </div>

            <div class="text-right">
                <a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Registrar Avance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const etapasPorDetalle = <?php 
        $etapasJson = [];
        foreach ($detalles as $detalle) {
            $etapasJson[$detalle['id_produccion_detalle']] = $detalle['etapas'];
        }
        echo json_encode($etapasJson); 
    ?>;
    const detalleSelect = document.getElementById('id_produccion_detalle');
    const etapaSelect = document.getElementById('id_etapa');
    const cantidadProgramada = document.getElementById('cantidad_programada');
    const cantidadActual = document.getElementById('cantidad_actual');
    const cantidadProducida = document.getElementById('cantidad_producida');
    const pesoReal = document.getElementById('peso_real');

    function actualizarInfoProducto() {
        const detalleId = detalleSelect.value;
        if (!detalleId) {
            etapaSelect.innerHTML = '<option value="">-- Seleccione una etapa --</option>';
            cantidadProgramada.value = '';
            cantidadActual.value = '';
            cantidadProducida.value = '';
            pesoReal.value = '';
            return;
        }

        const detalle = detalleSelect.options[detalleSelect.selectedIndex];
        cantidadProgramada.value = Number(detalle.dataset.programada).toLocaleString();
        cantidadActual.value = Number(detalle.dataset.producida).toLocaleString();
        cantidadProducida.value = detalle.dataset.producida;
        pesoReal.value = detalle.dataset.pesoReal || detalle.dataset.peso;

        etapaSelect.innerHTML = '<option value="">-- Seleccione una etapa --</option>';
        if (etapasPorDetalle[detalleId]) {
            etapasPorDetalle[detalleId].forEach(etapa => {
                if (etapa.estado !== 'completado') {
                    const option = document.createElement('option');
                    option.value = etapa.id_etapa;
                    let nombreEtapa = '';
                    switch (etapa.tipo_etapa) {
                        case 'corte': nombreEtapa = 'Corte'; break;
                        case 'sellado': nombreEtapa = 'Sellado'; break;
                        case 'control_calidad': nombreEtapa = 'Control de Calidad'; break;
                        case 'empaque': nombreEtapa = 'Empaque'; break;
                        default: nombreEtapa = etapa.tipo_etapa.charAt(0).toUpperCase() + etapa.tipo_etapa.slice(1);
                    }
                    let estadoEtapa = '';
                    switch (etapa.estado) {
                        case 'pendiente': estadoEtapa = '(Pendiente)'; break;
                        case 'en_proceso': estadoEtapa = '(En proceso)'; break;
                        default: estadoEtapa = '';
                    }
                    option.text = `${nombreEtapa} ${estadoEtapa}`;
                    etapaSelect.appendChild(option);
                }
            });
        }
    }

    if (detalleSelect.value) {
        actualizarInfoProducto();
    }

    detalleSelect.addEventListener('change', actualizarInfoProducto);

    document.getElementById('avanceForm').addEventListener('submit', function(e) {
        const cantidadProd = parseInt(cantidadProducida.value);
        const cantidadProg = parseInt(detalleSelect.options[detalleSelect.selectedIndex].dataset.programada);
        if (cantidadProd < 1) {
            e.preventDefault();
            alert('La cantidad producida debe ser mayor a cero');
            return false;
        }
        if (cantidadProd > cantidadProg) {
            e.preventDefault();
            alert('La cantidad producida no puede ser mayor a la cantidad programada (' + cantidadProg + ')');
            return false;
        }
        if (!etapaSelect.value) {
            e.preventDefault();
            alert('Debe seleccionar una etapa');
            return false;
        }
        return true;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>