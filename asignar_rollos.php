<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: produccion.php?error=ID de orden de producción no proporcionado");
    exit;
}

$id_orden_produccion = $_GET['id'];
$id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario actual

// Si se está procesando el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener datos del formulario
        $id_etapa = $_POST['id_etapa'];
        $id_rollo = $_POST['id_rollo'];
        $peso_asignado = $_POST['peso_asignado'];
        $observaciones = $_POST['observaciones'];
        
        // Validar datos
        if (!is_numeric($peso_asignado) || $peso_asignado <= 0) {
            throw new Exception("El peso asignado debe ser un número mayor a cero");
        }
        
        // Obtener información del rollo
        $stmt = $conn->prepare("SELECT * FROM rollos_materia_prima WHERE id_rollo = :id_rollo");
        $stmt->bindParam(':id_rollo', $id_rollo);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("El rollo seleccionado no existe");
        }
        
        $rollo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar que el rollo esté disponible o en uso
        if ($rollo['estado'] === 'agotado') {
            throw new Exception("El rollo seleccionado está agotado y no puede ser asignado");
        }
        
        // Verificar que el peso asignado no exceda el peso actual del rollo
        if ($peso_asignado > $rollo['peso_actual']) {
            throw new Exception("El peso asignado no puede ser mayor al peso actual del rollo (" . number_format($rollo['peso_actual'], 2) . " kg)");
        }
        
        // Verificar que la etapa pertenezca a la orden de producción
        $stmt = $conn->prepare("SELECT pe.*, pd.id_orden_produccion 
                              FROM produccion_etapas pe
                              JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                              WHERE pe.id_etapa = :id_etapa
                              AND pd.id_orden_produccion = :id_orden_produccion");
        $stmt->bindParam(':id_etapa', $id_etapa);
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("La etapa seleccionada no pertenece a esta orden de producción");
        }
        
        $etapa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Insertar la asignación del rollo
        $stmt = $conn->prepare("INSERT INTO asignacion_rollos 
                              (id_etapa, id_rollo, peso_asignado, 
                              fecha_asignacion, id_usuario, observaciones) 
                              VALUES 
                              (:id_etapa, :id_rollo, :peso_asignado, 
                              NOW(), :id_usuario, :observaciones)");
        
        $stmt->bindParam(':id_etapa', $id_etapa);
        $stmt->bindParam(':id_rollo', $id_rollo);
        $stmt->bindParam(':peso_asignado', $peso_asignado);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->execute();
        
        // Actualizar el estado y peso actual del rollo
        $nuevo_peso = $rollo['peso_actual'] - $peso_asignado;
        $nuevo_estado = $nuevo_peso > 0 ? 'en_uso' : 'agotado';
        
        $stmt = $conn->prepare("UPDATE rollos_materia_prima 
                              SET peso_actual = :nuevo_peso, 
                              estado = :nuevo_estado
                              WHERE id_rollo = :id_rollo");
        
        $stmt->bindParam(':nuevo_peso', $nuevo_peso);
        $stmt->bindParam(':nuevo_estado', $nuevo_estado);
        $stmt->bindParam(':id_rollo', $id_rollo);
        $stmt->execute();
        
        // Actualizar la etapa si está pendiente
        if ($etapa['estado'] === 'pendiente') {
            $stmt = $conn->prepare("UPDATE produccion_etapas 
                                  SET estado = 'en_proceso', 
                                  fecha_inicio = NOW()
                                  WHERE id_etapa = :id_etapa");
            $stmt->bindParam(':id_etapa', $id_etapa);
            $stmt->execute();
        }
        
        // Registrar en historial de producción
        $stmt = $conn->prepare("INSERT INTO historial_produccion 
                              (id_orden_produccion, fecha, tipo_evento, descripcion, id_usuario) 
                              VALUES (:id_orden_produccion, NOW(), 'otro', :descripcion, :id_usuario)");
        
        $descripcion = "Asignación de rollo {$rollo['codigo']} ({$peso_asignado} kg) a la etapa #{$id_etapa}";
        
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir con mensaje de éxito
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&success=1&message=" . urlencode("Rollo asignado correctamente"));
        exit;
        
    } catch (Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $error_msg = "Error al asignar rollo: " . $e->getMessage();
    }
}

try {
    // Obtener datos de la orden de producción
    $stmt = $conn->prepare("SELECT op.*, o.codigo as codigo_orden
                          FROM ordenes_produccion op
                          JOIN ordenes_venta o ON op.id_orden = o.id_orden
                          WHERE op.id_orden_produccion = :id");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header("Location: produccion.php?error=Orden de producción no encontrada");
        exit;
    }
    
    $produccion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar que la producción esté en estado válido para asignar rollos
    if ($produccion['estado'] !== 'programada' && $produccion['estado'] !== 'en_proceso' && $produccion['estado'] !== 'pausada') {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=La producción debe estar programada, en proceso o pausada para asignar rollos");
        exit;
    }
    
    // Obtener etapas de la producción que requieren material
    $stmt = $conn->prepare("SELECT pe.*, pd.id_produccion_detalle, od.descripcion, od.id_material,
                          m.nombre as material_nombre
                          FROM produccion_etapas pe
                          JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                          JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                          JOIN materiales m ON od.id_material = m.id_material
                          WHERE pd.id_orden_produccion = :id
                          AND pe.tipo_etapa IN ('corte', 'sellado') -- Etapas que requieren material
                          AND pe.estado != 'completado'
                          ORDER BY pd.id_produccion_detalle, pe.tipo_etapa");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay etapas que requieran material, redirigir
    if (count($etapas) === 0) {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=No hay etapas pendientes que requieran asignación de material");
        exit;
    }
    
    // Obtener rollos disponibles
    $stmt = $conn->prepare("SELECT r.*, m.nombre as material
                          FROM rollos_materia_prima r
                          JOIN materiales m ON r.id_material = m.id_material
                          WHERE r.estado IN ('disponible', 'en_uso')
                          AND r.peso_actual > 0
                          ORDER BY r.fecha_ingreso ASC");
    $stmt->execute();
    $rollos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar rollos por material
    $rollos_por_material = [];
    foreach ($rollos as $rollo) {
        if (!isset($rollos_por_material[$rollo['id_material']])) {
            $rollos_por_material[$rollo['id_material']] = [];
        }
        $rollos_por_material[$rollo['id_material']][] = $rollo;
    }
    
    // Obtener rollos ya asignados a esta producción
    $stmt = $conn->prepare("SELECT ar.*, r.codigo as codigo_rollo, r.color, r.peso_inicial, m.nombre as material,
                          pe.tipo_etapa, u.nombre as usuario_nombre, u.apellido as usuario_apellido,
                          pd.id_produccion_detalle, od.descripcion as producto_descripcion
                          FROM asignacion_rollos ar
                          JOIN rollos_materia_prima r ON ar.id_rollo = r.id_rollo
                          JOIN materiales m ON r.id_material = m.id_material
                          JOIN produccion_etapas pe ON ar.id_etapa = pe.id_etapa
                          JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                          JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                          JOIN usuarios u ON ar.id_usuario = u.id_usuario
                          WHERE pd.id_orden_produccion = :id
                          ORDER BY ar.fecha_asignacion DESC");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $rollos_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header("Location: produccion.php?error=" . urlencode("Error al obtener datos: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-dolly"></i> Asignar Materia Prima</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="produccion.php">Producción</a></li>
                <li class="breadcrumb-item"><a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>"><?php echo $produccion['codigo']; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Asignar Materia Prima</li>
            </ol>
        </nav>
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

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Asignar Rollo a Etapa</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Seleccione la etapa a la que desea asignar un rollo de materia prima y el rollo correspondiente.
                </div>
                
                <form method="post" action="asignar_rollos.php?id=<?php echo $id_orden_produccion; ?>" id="asignarForm">
                    <div class="form-group">
                        <label for="id_etapa">Etapa: <span class="text-danger">*</span></label>
                        <select class="form-control" id="id_etapa" name="id_etapa" required>
                            <option value="">-- Seleccione una etapa --</option>
                            <?php foreach ($etapas as $etapa): ?>
                                <?php
                                $etapa_texto = '';
                                switch($etapa['tipo_etapa']) {
                                    case 'corte': $etapa_texto = 'Etapa de Corte'; break;
                                    case 'sellado': $etapa_texto = 'Etapa de Sellado'; break;
                                    default: $etapa_texto = 'Etapa: ' . ucfirst($etapa['tipo_etapa']);
                                }
                                ?>
                                <option value="<?php echo $etapa['id_etapa']; ?>" data-material="<?php echo $etapa['id_material']; ?>">
                                    <?php echo $etapa_texto . ' - Producto: ' . $etapa['descripcion'] . ' - Material: ' . $etapa['material_nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_rollo">Rollo: <span class="text-danger">*</span></label>
                        <select class="form-control" id="id_rollo" name="id_rollo" required>
                            <option value="">-- Seleccione un rollo --</option>
                            <!-- Se poblará dinámicamente basado en la etapa seleccionada -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="peso_asignado">Peso a Asignar (kg): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="peso_asignado" name="peso_asignado" min="0.01" step="0.01" required>
                        <small class="form-text text-muted">El peso máximo disponible se mostrará después de seleccionar un rollo.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones">Observaciones:</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                        <small class="form-text text-muted">Opcional. Agregue observaciones sobre la asignación del rollo.</small>
                    </div>
                    
                    <div class="text-right">
                        <a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Asignar Rollo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Detalle del Rollo Seleccionado</h5>
            </div>
            <div class="card-body" id="detalle-rollo">
                <div class="text-center text-muted">
                    <i class="fas fa-dolly fa-3x mb-3"></i>
                    <p>Seleccione un rollo para ver su detalle</p>
                </div>
            </div>
        </div>
        
        <!-- Rollos disponibles por material -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Inventario de Rollos Disponibles</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Material</th>
                                <th>Disponibles</th>
                                <th>En Uso</th>
                                <th>Peso Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Calcular resumen de inventario
                            $resumen_inventario = [];
                            foreach ($rollos as $rollo) {
                                $id_material = $rollo['id_material'];
                                if (!isset($resumen_inventario[$id_material])) {
                                    $resumen_inventario[$id_material] = [
                                        'material' => $rollo['material'],
                                        'disponibles' => 0,
                                        'en_uso' => 0,
                                        'peso_total' => 0
                                    ];
                                }
                                
                                if ($rollo['estado'] === 'disponible') {
                                    $resumen_inventario[$id_material]['disponibles']++;
                                } else { // en_uso
                                    $resumen_inventario[$id_material]['en_uso']++;
                                }
                                
                                $resumen_inventario[$id_material]['peso_total'] += $rollo['peso_actual'];
                            }
                            
                            foreach ($resumen_inventario as $material):
                            ?>
                                <tr>
                                    <td><?php echo $material['material']; ?></td>
                                    <td class="text-center"><?php echo $material['disponibles']; ?></td>
                                    <td class="text-center"><?php echo $material['en_uso']; ?></td>
                                    <td class="text-right"><?php echo number_format($material['peso_total'], 2); ?> kg</td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($resumen_inventario) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay rollos disponibles en el inventario</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rollos ya asignados a esta producción -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Rollos Asignados a esta Producción</h5>
    </div>
    <div class="card-body">
        <?php if (count($rollos_asignados) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Código Rollo</th>
                            <th>Material</th>
                            <th>Producto</th>
                            <th>Etapa</th>
                            <th>Peso Asignado</th>
                            <th>Peso Consumido</th>
                            <th>Fecha Asignación</th>
                            <th>Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rollos_asignados as $rollo): ?>
                            <tr>
                                <td><?php echo $rollo['codigo_rollo']; ?></td>
                                <td>
                                    <?php echo $rollo['material']; ?><br>
                                    <small class="text-muted">Color: <?php echo $rollo['color']; ?></small>
                                </td>
                                <td><?php echo $rollo['producto_descripcion']; ?></td>
                                <td>
                                    <?php 
                                    switch($rollo['tipo_etapa']) {
                                        case 'corte': echo '<i class="fas fa-cut mr-1"></i> Corte'; break;
                                        case 'sellado': echo '<i class="fas fa-fire mr-1"></i> Sellado'; break;
                                        default: echo ucfirst($rollo['tipo_etapa']);
                                    }
                                    ?>
                                </td>
                                <td><?php echo number_format($rollo['peso_asignado'], 2); ?> kg</td>
                                <td><?php echo number_format($rollo['peso_consumido'], 2); ?> kg</td>
                                <td><?php echo date('d/m/Y H:i', strtotime($rollo['fecha_asignacion'])); ?></td>
                                <td><?php echo $rollo['usuario_nombre'] . ' ' . $rollo['usuario_apellido']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay rollos asignados a esta producción todavía.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Objeto para almacenar los rollos por material
    const rollosPorMaterial = <?php echo json_encode($rollos_por_material); ?>;
    
    // Referencias a elementos del DOM
    const etapaSelect = document.getElementById('id_etapa');
    const rolloSelect = document.getElementById('id_rollo');
    const pesoInput = document.getElementById('peso_asignado');
    const detalleRollo = document.getElementById('detalle-rollo');
    
    // Función para actualizar los rollos disponibles según la etapa seleccionada
    function actualizarRollosDisponibles() {
        const etapaSeleccionada = etapaSelect.options[etapaSelect.selectedIndex];
        rolloSelect.innerHTML = '<option value="">-- Seleccione un rollo --</option>';
        pesoInput.value = '';
        
        if (!etapaSeleccionada || etapaSeleccionada.value === '') {
            detalleRollo.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-dolly fa-3x mb-3"></i>
                    <p>Seleccione un rollo para ver su detalle</p>
                </div>
            `;
            return;
        }
        
        const idMaterial = etapaSeleccionada.dataset.material;
        const rollosDisponibles = rollosPorMaterial[idMaterial] || [];
        
        if (rollosDisponibles.length === 0) {
            rolloSelect.innerHTML += '<option value="" disabled>No hay rollos disponibles para este material</option>';
            return;
        }
        
        rollosDisponibles.forEach(rollo => {
            const option = document.createElement('option');
            option.value = rollo.id_rollo;
            option.dataset.peso = rollo.peso_actual;
            option.dataset.color = rollo.color;
            option.dataset.codigo = rollo.codigo;
            option.dataset.material = rollo.material;
            option.dataset.estado = rollo.estado;
            option.text = `${rollo.codigo} - ${rollo.material} - ${rollo.color} - ${rollo.peso_actual} kg`;
            rolloSelect.appendChild(option);
        });
    }
    
    // Función para actualizar el detalle del rollo seleccionado
    function actualizarDetalleRollo() {
        const rolloSeleccionado = rolloSelect.options[rolloSelect.selectedIndex];
        
        if (!rolloSeleccionado || rolloSeleccionado.value === '') {
            detalleRollo.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-dolly fa-3x mb-3"></i>
                    <p>Seleccione un rollo para ver su detalle</p>
                </div>
            `;
            pesoInput.value = '';
            return;
        }
        
        const codigo = rolloSeleccionado.dataset.codigo;
        const material = rolloSeleccionado.dataset.material;
        const color = rolloSeleccionado.dataset.color;
        const peso = parseFloat(rolloSeleccionado.dataset.peso);
        const estado = rolloSeleccionado.dataset.estado;
        
        detalleRollo.innerHTML = `
            <h5 class="card-title">${codigo}</h5>
            <dl class="row mb-0">
                <dt class="col-sm-4">Material:</dt>
                <dd class="col-sm-8">${material}</dd>
                
                <dt class="col-sm-4">Color:</dt>
                <dd class="col-sm-8">${color}</dd>
                
                <dt class="col-sm-4">Peso Disponible:</dt>
                <dd class="col-sm-8">${peso.toFixed(2)} kg</dd>
                
                <dt class="col-sm-4">Estado:</dt>
                <dd class="col-sm-8">
                    <span class="badge badge-${estado === 'disponible' ? 'success' : 'primary'}">
                        ${estado === 'disponible' ? 'Disponible' : 'En Uso'}
                    </span>
                </dd>
            </dl>
        `;
        
        // Establecer el peso máximo disponible y valor predeterminado
        pesoInput.max = peso;
        pesoInput.value = peso.toFixed(2);
    }
    
    // Escuchar cambios en la selección de etapa
    etapaSelect.addEventListener('change', function() {
        actualizarRollosDisponibles();
    });
    
    // Escuchar cambios en la selección de rollo
    rolloSelect.addEventListener('change', function() {
        actualizarDetalleRollo();
    });
    
    // Validar formulario antes de enviar
    document.getElementById('asignarForm').addEventListener('submit', function(e) {
        const etapa = etapaSelect.value;
        const rollo = rolloSelect.value;
        const peso = parseFloat(pesoInput.value);
        
        if (!etapa) {
            e.preventDefault();
            alert('Debe seleccionar una etapa');
            return false;
        }
        
        if (!rollo) {
            e.preventDefault();
            alert('Debe seleccionar un rollo');
            return false;
        }
        
        if (!peso || peso <= 0) {
            e.preventDefault();
            alert('El peso asignado debe ser mayor a cero');
            return false;
        }
        
        const pesoMaximo = parseFloat(rolloSelect.options[rolloSelect.selectedIndex].dataset.peso);
        if (peso > pesoMaximo) {
            e.preventDefault();
            alert(`El peso asignado no puede ser mayor al peso disponible del rollo (${pesoMaximo.toFixed(2)} kg)`);
            return false;
        }
        
        return true;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>