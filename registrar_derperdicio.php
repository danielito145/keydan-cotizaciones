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
        $tipo = $_POST['tipo'];
        $peso = $_POST['peso'];
        $motivo = $_POST['motivo'];
        $observaciones = $_POST['observaciones'];
        
        // Validar datos
        if (!is_numeric($peso) || $peso <= 0) {
            throw new Exception("El peso del desperdicio debe ser un número mayor a cero");
        }
        
        if (empty($motivo)) {
            throw new Exception("Debe especificar un motivo para el desperdicio");
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
        
        // Registrar el desperdicio
        $stmt = $conn->prepare("INSERT INTO desperdicios_produccion 
                              (id_etapa, fecha, tipo, peso, motivo, id_usuario, observaciones) 
                              VALUES 
                              (:id_etapa, NOW(), :tipo, :peso, :motivo, :id_usuario, :observaciones)");
        
        $stmt->bindParam(':id_etapa', $id_etapa);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':peso', $peso);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->execute();
        
        // Si el desperdicio está asociado a un rollo, actualizar su consumo
        if (isset($_POST['id_asignacion']) && !empty($_POST['id_asignacion'])) {
            $id_asignacion = $_POST['id_asignacion'];
            
            // Obtener información de la asignación
            $stmt = $conn->prepare("SELECT * FROM asignacion_rollos WHERE id_asignacion = :id_asignacion");
            $stmt->bindParam(':id_asignacion', $id_asignacion);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Actualizar el peso consumido
                $nuevo_consumo = $asignacion['peso_consumido'] + $peso;
                
                // Verificar que el consumo no exceda el peso asignado
                if ($nuevo_consumo > $asignacion['peso_asignado']) {
                    throw new Exception("El peso consumido no puede ser mayor al peso asignado al rollo (" . number_format($asignacion['peso_asignado'], 2) . " kg)");
                }
                
                $stmt = $conn->prepare("UPDATE asignacion_rollos 
                                      SET peso_consumido = :nuevo_consumo
                                      WHERE id_asignacion = :id_asignacion");
                $stmt->bindParam(':nuevo_consumo', $nuevo_consumo);
                $stmt->bindParam(':id_asignacion', $id_asignacion);
                $stmt->execute();
                
                // Si el consumo es igual al asignado, marcar como finalizado
                if ($nuevo_consumo >= $asignacion['peso_asignado']) {
                    $stmt = $conn->prepare("UPDATE asignacion_rollos 
                                          SET fecha_finalizacion = NOW()
                                          WHERE id_asignacion = :id_asignacion");
                    $stmt->bindParam(':id_asignacion', $id_asignacion);
                    $stmt->execute();
                }
            }
        }
        
        // Registrar en historial de producción
        $stmt = $conn->prepare("INSERT INTO historial_produccion 
                              (id_orden_produccion, fecha, tipo_evento, descripcion, id_usuario) 
                              VALUES (:id_orden_produccion, NOW(), 'otro', :descripcion, :id_usuario)");
        
        $descripcion = "Registro de desperdicio: " . ucfirst($tipo) . " (" . number_format($peso, 2) . " kg). Motivo: " . $motivo;
        
        $stmt->bindParam(':id_orden_produccion', $id_orden_produccion);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir con mensaje de éxito
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&success=1&message=" . urlencode("Desperdicio registrado correctamente"));
        exit;
        
    } catch (Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $error_msg = "Error al registrar desperdicio: " . $e->getMessage();
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
    
    // Verificar que la producción esté en estado válido para registrar desperdicios
    if ($produccion['estado'] !== 'en_proceso' && $produccion['estado'] !== 'pausada') {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=La producción debe estar en proceso o pausada para registrar desperdicios");
        exit;
    }
    
    // Obtener etapas de la producción
    $stmt = $conn->prepare("SELECT pe.*, pd.id_produccion_detalle, od.descripcion, od.id_material,
                          m.nombre as material_nombre
                          FROM produccion_etapas pe
                          JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                          JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                          JOIN materiales m ON od.id_material = m.id_material
                          WHERE pd.id_orden_produccion = :id
                          AND pe.estado IN ('en_proceso')
                          ORDER BY pd.id_produccion_detalle, pe.tipo_etapa");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay etapas en proceso, redirigir
    if (count($etapas) === 0) {
        header("Location: ver_produccion.php?id=" . $id_orden_produccion . "&error=No hay etapas en proceso para registrar desperdicios");
        exit;
    }
    
    // Obtener rollos asignados a esta producción
    $stmt = $conn->prepare("SELECT ar.*, r.codigo as codigo_rollo, r.color, r.peso_asignado - r.peso_consumido as peso_disponible,
                          pe.tipo_etapa, pe.id_etapa, m.nombre as material,
                          pd.id_produccion_detalle, od.descripcion as producto_descripcion
                          FROM asignacion_rollos ar
                          JOIN rollos_materia_prima r ON ar.id_rollo = r.id_rollo
                          JOIN materiales m ON r.id_material = m.id_material
                          JOIN produccion_etapas pe ON ar.id_etapa = pe.id_etapa
                          JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                          JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                          WHERE pd.id_orden_produccion = :id
                          AND ar.fecha_finalizacion IS NULL
                          AND ar.peso_asignado > ar.peso_consumido
                          ORDER BY ar.fecha_asignacion DESC");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $rollos_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar rollos por etapa
    $rollos_por_etapa = [];
    foreach ($rollos_asignados as $rollo) {
        if (!isset($rollos_por_etapa[$rollo['id_etapa']])) {
            $rollos_por_etapa[$rollo['id_etapa']] = [];
        }
        $rollos_por_etapa[$rollo['id_etapa']][] = $rollo;
    }
    
} catch (PDOException $e) {
    header("Location: produccion.php?error=" . urlencode("Error al obtener datos: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-trash-alt"></i> Registrar Desperdicio</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="produccion.php">Producción</a></li>
                <li class="breadcrumb-item"><a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>"><?php echo $produccion['codigo']; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Registrar Desperdicio</li>
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
                <h5 class="mb-0">Registrar Desperdicio</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Complete el formulario para registrar un desperdicio de material generado durante la producción.
                </div>
                
                <form method="post" action="registrar_desperdicio.php?id=<?php echo $id_orden_produccion; ?>" id="desperdicioForm">
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
                                    case 'control_calidad': $etapa_texto = 'Control de Calidad'; break;
                                    case 'empaque': $etapa_texto = 'Empaque'; break;
                                    default: $etapa_texto = 'Etapa: ' . ucfirst($etapa['tipo_etapa']);
                                }
                                ?>
                                <option value="<?php echo $etapa['id_etapa']; ?>" 
                                        data-tipo="<?php echo $etapa['tipo_etapa']; ?>"
                                        data-material="<?php echo $etapa['id_material']; ?>">
                                    <?php echo $etapa_texto . ' - Producto: ' . $etapa['descripcion']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="rolloGroup" style="display: none;">
                        <label for="id_asignacion">Asignar a Rollo (opcional):</label>
                        <select class="form-control" id="id_asignacion" name="id_asignacion">
                            <option value="">-- Ninguno (desperdicio general) --</option>
                            <!-- Se poblará dinámicamente basado en la etapa seleccionada -->
                        </select>
                        <small class="form-text text-muted">Si el desperdicio corresponde a un rollo específico, selecciónelo para actualizar su consumo.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo de Desperdicio: <span class="text-danger">*</span></label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="">-- Seleccione un tipo --</option>
                            <option value="cono">Cono</option>
                            <option value="scrap">Scrap (Recortes)</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="peso">Peso (kg): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="peso" name="peso" min="0.01" step="0.01" required>
                        <small class="form-text text-muted">Ingrese el peso del desperdicio en kilogramos.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="motivo">Motivo: <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="motivo" name="motivo" required>
                        <small class="form-text text-muted">Ingrese el motivo por el cual se generó el desperdicio.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones">Observaciones:</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                        <small class="form-text text-muted">Opcional. Agregue observaciones sobre el desperdicio.</small>
                    </div>
                    
                    <div class="text-right">
                        <a href="ver_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Registrar Desperdicio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                <h6 class="card-title">Tipos de Desperdicios</h6>
                <dl>
                    <dt>Cono</dt>
                    <dd>Cilindro central de cartón o plástico donde se enrolla el material. Se vende al precio de S/0.50/kg.</dd>
                    
                    <dt>Scrap (Recortes)</dt>
                    <dd>Material plástico residual del proceso de corte y sellado. Se vende al precio de S/2.00/kg.</dd>
                    
                    <dt>Otro</dt>
                    <dd>Cualquier otro tipo de desperdicio generado durante la producción.</dd>
                </dl>
                
                <hr>
                
                <h6 class="card-title">Consideraciones Importantes</h6>
                <ul>
                    <li>Todos los desperdicios deben ser pesados antes de su registro.</li>
                    <li>Es importante identificar el origen del desperdicio para mejorar los procesos.</li>
                    <li>La reducción de desperdicios mejora la eficiencia y rentabilidad de la producción.</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Rollos Disponibles por Etapa</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Etapa</th>
                                <th>Rollos Asignados</th>
                                <th>Material Disponible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($etapas as $etapa) {
                                $id_etapa = $etapa['id_etapa'];
                                $rollos = isset($rollos_por_etapa[$id_etapa]) ? $rollos_por_etapa[$id_etapa] : [];
                                $total_rollos = count($rollos);
                                
                                // Calcular material disponible
                                $material_disponible = 0;
                                foreach ($rollos as $rollo) {
                                    $material_disponible += ($rollo['peso_asignado'] - $rollo['peso_consumido']);
                                }
                                
                                // Determinar tipo de etapa
                                $tipo_etapa = '';
                                switch($etapa['tipo_etapa']) {
                                    case 'corte': $tipo_etapa = 'Corte'; break;
                                    case 'sellado': $tipo_etapa = 'Sellado'; break;
                                    case 'control_calidad': $tipo_etapa = 'Control de Calidad'; break;
                                    case 'empaque': $tipo_etapa = 'Empaque'; break;
                                    default: $tipo_etapa = ucfirst($etapa['tipo_etapa']);
                                }
                            ?>
                                <tr>
                                    <td>
                                        <?php echo $tipo_etapa; ?><br>
                                        <small class="text-muted"><?php echo $etapa['descripcion']; ?></small>
                                    </td>
                                    <td class="text-center"><?php echo $total_rollos; ?></td>
                                    <td class="text-right">
                                        <?php if ($total_rollos > 0): ?>
                                            <?php echo number_format($material_disponible, 2); ?> kg
                                        <?php else: ?>
                                            <span class="text-muted">Sin rollos</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            
                            <?php if (count($etapas) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No hay etapas en proceso</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Objeto para almacenar los rollos por etapa
    const rollosPorEtapa = <?php echo json_encode($rollos_por_etapa); ?>;
    
    // Referencias a elementos del DOM
    const etapaSelect = document.getElementById('id_etapa');
    const rolloGroup = document.getElementById('rolloGroup');
    const rolloSelect = document.getElementById('id_asignacion');
    const pesoInput = document.getElementById('peso');
    
    // Función para actualizar los rollos disponibles según la etapa seleccionada
    function actualizarRollosDisponibles() {
        const etapaSeleccionada = etapaSelect.value;
        rolloSelect.innerHTML = '<option value="">-- Ninguno (desperdicio general) --</option>';
        
        if (!etapaSeleccionada) {
            rolloGroup.style.display = 'none';
            return;
        }
        
        const tipoEtapa = etapaSelect.options[etapaSelect.selectedIndex].dataset.tipo;
        
        // Solo mostrar rollos para etapas de corte y sellado
        if (tipoEtapa === 'corte' || tipoEtapa === 'sellado') {
            rolloGroup.style.display = 'block';
            
            const rollosDisponibles = rollosPorEtapa[etapaSeleccionada] || [];
            
            if (rollosDisponibles.length === 0) {
                rolloSelect.innerHTML += '<option value="" disabled>No hay rollos asignados a esta etapa</option>';
                return;
            }
            
            rollosDisponibles.forEach(rollo => {
                const option = document.createElement('option');
                option.value = rollo.id_asignacion;
                option.dataset.pesoDisponible = rollo.peso_disponible;
                option.text = `${rollo.codigo_rollo} - ${rollo.material} - ${rollo.color} - Disp: ${parseFloat(rollo.peso_disponible).toFixed(2)} kg`;
                rolloSelect.appendChild(option);
            });
        } else {
            rolloGroup.style.display = 'none';
        }
    }
    
    // Escuchar cambios en la selección de etapa
    etapaSelect.addEventListener('change', function() {
        actualizarRollosDisponibles();
    });
    
    // Escuchar cambios en la selección de rollo
    rolloSelect.addEventListener('change', function() {
        const rolloSeleccionado = rolloSelect.options[rolloSelect.selectedIndex];
        
        if (rolloSeleccionado && rolloSeleccionado.value !== '') {
            const pesoDisponible = parseFloat(rolloSeleccionado.dataset.pesoDisponible);
            pesoInput.max = pesoDisponible;
            pesoInput.value = '';
            pesoInput.placeholder = `Máximo: ${pesoDisponible.toFixed(2)} kg`;
        } else {
            pesoInput.removeAttribute('max');
            pesoInput.placeholder = '';
        }
    });
    
    // Validar formulario antes de enviar
    document.getElementById('desperdicioForm').addEventListener('submit', function(e) {
        const etapa = etapaSelect.value;
        const tipo = document.getElementById('tipo').value;
        const peso = parseFloat(pesoInput.value);
        const motivo = document.getElementById('motivo').value;
        
        if (!etapa) {
            e.preventDefault();
            alert('Debe seleccionar una etapa');
            return false;
        }
        
        if (!tipo) {
            e.preventDefault();
            alert('Debe seleccionar un tipo de desperdicio');
            return false;
        }
        
        if (!peso || peso <= 0) {
            e.preventDefault();
            alert('El peso debe ser mayor a cero');
            return false;
        }
        
        if (!motivo.trim()) {
            e.preventDefault();
            alert('Debe ingresar un motivo para el desperdicio');
            return false;
        }
        
        // Verificar límite de peso si está asociado a un rollo
        const rolloSeleccionado = rolloSelect.options[rolloSelect.selectedIndex];
        if (rolloSeleccionado && rolloSeleccionado.value !== '') {
            const pesoDisponible = parseFloat(rolloSeleccionado.dataset.pesoDisponible);
            if (peso > pesoDisponible) {
                e.preventDefault();
                alert(`El peso no puede ser mayor al disponible en el rollo (${pesoDisponible.toFixed(2)} kg)`);
                return false;
            }
        }
        
        return true;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>