<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID de cotización
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de cotización no válido.</div>";
    require_once 'includes/footer.php';
    exit;
}

$id_cotizacion = $_GET['id'];

// Obtener los datos de la cotización
try {
    $stmt = $conn->prepare("SELECT c.*, cl.razon_social 
                            FROM cotizaciones c 
                            JOIN clientes cl ON c.id_cliente = cl.id_cliente 
                            WHERE c.id_cotizacion = :id_cotizacion");
    $stmt->bindParam(':id_cotizacion', $id_cotizacion);
    $stmt->execute();
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cotizacion) {
        echo "<div class='alert alert-danger'>Cotización no encontrada.</div>";
        require_once 'includes/footer.php';
        exit;
    }

    // Obtener los ítems de la cotización
    $stmt = $conn->prepare("SELECT cd.*, m.nombre AS material_nombre 
                            FROM cotizacion_detalles cd 
                            JOIN materiales m ON cd.id_material = m.id_material 
                            WHERE cd.id_cotizacion = :id_cotizacion");
    $stmt->bindParam(':id_cotizacion', $id_cotizacion);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de clientes
    $stmt = $conn->query("SELECT id_cliente, razon_social FROM clientes WHERE estado = 'activo' ORDER BY razon_social");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de materiales
    $stmt = $conn->query("SELECT id_material, nombre, tipo FROM materiales WHERE estado = 'activo' ORDER BY nombre");
    $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    require_once 'includes/footer.php';
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Actualizar datos de la cotización
        $id_cliente = $_POST['id_cliente'];
        $fecha_cotizacion = $_POST['fecha_cotizacion'];
        $validez = $_POST['validez'];
        $condiciones_pago = $_POST['condiciones_pago'];
        $tiempo_entrega = $_POST['tiempo_entrega'];
        $subtotal = $_POST['subtotal'];
        $impuestos = $_POST['impuestos'];
        $total = $_POST['total'];
        $notas = $_POST['notas'];

        $stmt = $conn->prepare("UPDATE cotizaciones SET 
                                id_cliente = :id_cliente, 
                                fecha_cotizacion = :fecha_cotizacion, 
                                validez = :validez, 
                                condiciones_pago = :condiciones_pago, 
                                tiempo_entrega = :tiempo_entrega, 
                                subtotal = :subtotal, 
                                impuestos = :impuestos, 
                                total = :total, 
                                notas = :notas, 
                                fecha_modificacion = NOW() 
                                WHERE id_cotizacion = :id_cotizacion");
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->bindParam(':fecha_cotizacion', $fecha_cotizacion);
        $stmt->bindParam(':validez', $validez);
        $stmt->bindParam(':condiciones_pago', $condiciones_pago);
        $stmt->bindParam(':tiempo_entrega', $tiempo_entrega);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':impuestos', $impuestos);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':notas', $notas);
        $stmt->bindParam(':id_cotizacion', $id_cotizacion);
        $stmt->execute();

        // Eliminar ítems existentes
        $stmt = $conn->prepare("DELETE FROM cotizacion_detalles WHERE id_cotizacion = :id_cotizacion");
        $stmt->bindParam(':id_cotizacion', $id_cotizacion);
        $stmt->execute();

        // Insertar nuevos ítems
        $items_json = $_POST['items_json'];
        $items = json_decode($items_json, true);

        if (!empty($items)) {
            foreach ($items as $item) {
                $stmt = $conn->prepare("INSERT INTO cotizacion_detalles (id_cotizacion, id_material, ancho, largo, 
                                       micraje, fuelle, colores, biodegradable, cantidad, costo_unitario, 
                                       precio_unitario, subtotal, espesor, medida_referencial) 
                                      VALUES (:id_cotizacion, :id_material, :ancho, :largo, 
                                       :micraje, :fuelle, :colores, :biodegradable, :cantidad, :costo_unitario, 
                                       :precio_unitario, :subtotal, :espesor, :medida_referencial)");
                
                $colores = 0;
                if (isset($item['color']) && strpos($item['color'], 'Colores') !== false) {
                    $colores = 1;
                }

                $id_material = $item['id_material'];
                $ancho = $item['ancho'];
                $largo = $item['largo'];
                $micraje = $item['micraje'];
                $fuelle = $item['fuelle'];
                $biodegradable = $item['biodegradable'] ? 1 : 0;
                $cantidad = $item['cantidad'];
                $costo_unitario = isset($item['costo_unitario']) ? $item['costo_unitario'] : 0;
                $precio_unitario = $item['precio_unitario'];
                $subtotal_item = $item['subtotal'];
                $espesor = isset($item['espesor']) ? $item['espesor'] : '';
                $medida_referencial = isset($item['medida_referencial']) ? $item['medida_referencial'] : '';

                $stmt->bindParam(':id_cotizacion', $id_cotizacion);
                $stmt->bindParam(':id_material', $id_material);
                $stmt->bindParam(':ancho', $ancho);
                $stmt->bindParam(':largo', $largo);
                $stmt->bindParam(':micraje', $micraje);
                $stmt->bindParam(':fuelle', $fuelle);
                $stmt->bindParam(':colores', $colores);
                $stmt->bindParam(':biodegradable', $biodegradable, PDO::PARAM_INT);
                $stmt->bindParam(':cantidad', $cantidad);
                $stmt->bindParam(':costo_unitario', $costo_unitario);
                $stmt->bindParam(':precio_unitario', $precio_unitario);
                $stmt->bindParam(':subtotal', $subtotal_item);
                $stmt->bindParam(':espesor', $espesor);
                $stmt->bindParam(':medida_referencial', $medida_referencial);
                $stmt->execute();
            }
        }

        $conn->commit();
        echo "<script>alert('Cotización actualizada con éxito'); window.location.href = 'cotizaciones.php';</script>";
        exit;
    } catch(PDOException $e) {
        $conn->rollBack();
        echo "<div class='alert alert-danger'>Error al actualizar la cotización: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-edit"></i> Editar Cotización</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="cotizaciones.php">Cotizaciones</a></li>
                <li class="breadcrumb-item active" aria-current="page">Editar Cotización</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Información de la Cotización</h5>
    </div>
    <div class="card-body">
        <form id="formCotizacion" method="post" action="">
            <!-- Datos generales -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="codigo">Código:</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($cotizacion['codigo']); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="fecha_cotizacion">Fecha:</label>
                        <input type="date" class="form-control" id="fecha_cotizacion" name="fecha_cotizacion" value="<?php echo htmlspecialchars($cotizacion['fecha_cotizacion']); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="validez">Validez (días):</label>
                        <input type="number" class="form-control" id="validez" name="validez" value="<?php echo htmlspecialchars($cotizacion['validez']); ?>" required min="1">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <select class="form-control" id="id_cliente" name="id_cliente" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>" <?php echo $cliente['id_cliente'] == $cotizacion['id_cliente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['razon_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="condiciones_pago">Condiciones de Pago:</label>
                        <input type="text" class="form-control" id="condiciones_pago" name="condiciones_pago" value="<?php echo htmlspecialchars($cotizacion['condiciones_pago']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tiempo_entrega">Tiempo de Entrega:</label>
                        <input type="text" class="form-control" id="tiempo_entrega" name="tiempo_entrega" value="<?php echo htmlspecialchars($cotizacion['tiempo_entrega']); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Agregar ítems -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Detalle de Productos</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="ancho">Ancho (pulg):</label>
                                <input type="number" step="0.01" class="form-control" id="ancho">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="largo">Largo (pulg):</label>
                                <input type="number" step="0.01" class="form-control" id="largo">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="micraje">Micraje:</label>
                                <input type="number" step="0.01" class="form-control" id="micraje">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="fuelle">Fuelle (pulg):</label>
                                <input type="number" step="0.01" class="form-control" id="fuelle" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="espesor">Espesor para proforma:</label>
                                <input type="text" class="form-control" id="espesor" placeholder="Ej: 2.5 micras">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="medida_referencial">Medida referencial:</label>
                                <input type="text" class="form-control" id="medida_referencial" placeholder="Ej: 50 litros">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="id_material">Material:</label>
                                <select class="form-control" id="id_material">
                                    <option value="">Seleccione un material</option>
                                    <?php foreach($materiales as $material): ?>
                                        <option value="<?php echo $material['id_material']; ?>" data-tipo="<?php echo $material['tipo']; ?>">
                                            <?php echo htmlspecialchars($material['nombre']); ?> (<?php echo $material['tipo']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="color">Color:</label>
                                <select class="form-control" id="color">
                                    <option value="Negro">Negro</option>
                                    <option value="Colores">Colores</option>
                                    <option value="Transparente">Transparente</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3" id="colorDetalleDiv" style="display:none;">
                            <div class="form-group">
                                <label for="colorDetalle">Especificar color:</label>
                                <input type="text" class="form-control" id="colorDetalle" placeholder="Ej: Azul, Rojo, etc.">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="cantidad">Cantidad:</label>
                                <input type="number" class="form-control" id="cantidad" value="1000" min="1">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="biodegradable">Biodegradable:</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="biodegradable">
                                    <label class="form-check-label" for="biodegradable">Sí</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12 text-right">
                            <button type="button" id="btnCalcular" class="btn btn-info mr-2">
                                <i class="fas fa-calculator"></i> Calcular
                            </button>
                            <button type="button" id="btnAgregarItem" class="btn btn-success">
                                <i class="fas fa-plus"></i> Agregar Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tabla de ítems -->
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered" id="tablaItems">
                            <thead class="thead-light">
                                <tr>
                                    <th>Material</th>
                                    <th>Medidas</th>
                                    <th>Color</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['material_nombre']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($item['ancho']) . ' x ' . htmlspecialchars($item['largo']) . ' x ' . htmlspecialchars($item['micraje']); ?>
                                            <?php echo $item['fuelle'] > 0 ? ' (Fuelle: ' . htmlspecialchars($item['fuelle']) . ')' : ''; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $color = $item['colores'] == 1 ? 'Colores' : 'Negro'; // Simplificación, ajustar según lógica real
                                            echo htmlspecialchars($color) . ($item['biodegradable'] ? ' (Biodegradable)' : '');
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['cantidad']); ?></td>
                                        <td>S/ <?php echo number_format($item['precio_unitario'], 2); ?></td>
                                        <td>S/ <?php echo number_format($item['subtotal'], 2); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary btn-editar-item mr-1" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-item" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">Subtotal:</th>
                                    <th><span id="subtotal"><?php echo number_format($cotizacion['subtotal'], 2); ?></span></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="text-right">IGV (18%):</th>
                                    <th><span id="igv"><?php echo number_format($cotizacion['impuestos'], 2); ?></span></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="text-right">Total:</th>
                                    <th><span id="total"><?php echo number_format($cotizacion['total'], 2); ?></span></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Notas -->
            <div class="form-group mt-4">
                <label for="notas">Notas y Observaciones:</label>
                <textarea class="form-control" id="notas" name="notas" rows="3"><?php echo htmlspecialchars($cotizacion['notas']); ?></textarea>
            </div>
            
            <!-- Campos ocultos -->
            <input type="hidden" id="costoUnitarioCalculado" value="0">
            <input type="hidden" id="items_json" name="items_json" value='<?php echo json_encode($items); ?>'>
            <input type="hidden" id="subtotal_hidden" name="subtotal" value="<?php echo $cotizacion['subtotal']; ?>">
            <input type="hidden" id="igv_hidden" name="impuestos" value="<?php echo $cotizacion['impuestos']; ?>">
            <input type="hidden" id="total_hidden" name="total" value="<?php echo $cotizacion['total']; ?>">
            
            <div class="row mt-4">
                <div class="col-md-12 text-right">
                    <a href="cotizaciones.php" class="btn btn-secondary mr-2">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Código JavaScript similar al de nueva_cotizacion.php para manejar ítems dinámicamente
document.addEventListener('DOMContentLoaded', function() {
    const preciosMateriales = {
        'R2': { 'Negro': 4.50, 'Colores': 5.00, 'Transparente': 5.50 },
        'R1': { 'Negro': 6.50, 'Colores': 7.00, 'Transparente': 7.20 },
        'Virgen': { 'Negro': 7.80, 'Colores': 7.80, 'Transparente': 7.40 }
    };
    const costoBiodegradable = 1.00;
    const gastosOperativos = 0.87;
    const ganancia = 1.00;

    // Mostrar/ocultar campo de detalle de color
    const colorSelect = document.getElementById('color');
    colorSelect.addEventListener('change', function() {
        const colorDetalleDiv = document.getElementById('colorDetalleDiv');
        if (this.value === 'Colores') {
            colorDetalleDiv.style.display = 'block';
        } else {
            colorDetalleDiv.style.display = 'none';
        }
    });

    // Calcular precio
    document.getElementById('btnCalcular').addEventListener('click', function() {
        const ancho = parseFloat(document.getElementById('ancho').value) || 0;
        const largo = parseFloat(document.getElementById('largo').value) || 0;
        const micraje = parseFloat(document.getElementById('micraje').value) || 0;
        const cantidad = parseInt(document.getElementById('cantidad').value) || 1000;

        const materialSelect = document.getElementById('id_material');
        const materialOption = materialSelect.options[materialSelect.selectedIndex];
        const materialText = materialOption ? materialOption.text : '';
        const materialTipo = materialOption ? materialOption.getAttribute('data-tipo') : '';

        const colorSelect = document.getElementById('color');
        const color = colorSelect.options[colorSelect.selectedIndex].value;
        let colorTexto = color;
        if (color === 'Colores') {
            const colorDetalle = document.getElementById('colorDetalle').value;
            if (colorDetalle) {
                colorTexto = `Colores (${colorDetalle})`;
            }
        }

        const esBiodegradable = document.getElementById('biodegradable').checked;

        const pesoPorMillar = ancho * largo * 0.0303 * micraje;
        let costoBase = 0;
        if (preciosMateriales[materialTipo] && preciosMateriales[materialTipo][color]) {
            costoBase = preciosMateriales[materialTipo][color];
        }
        const costoBio = esBiodegradable ? costoBiodegradable : 0;
        const costoTotal = costoBase + costoBio + gastosOperativos;
        const precioVentaKg = costoTotal + ganancia;
        const pesoTotal = pesoPorMillar * (cantidad / 1000);
        const costoTotalProducto = costoTotal * pesoTotal;
        const precioVentaTotal = precioVentaKg * pesoTotal;
        const precioUnitario = precioVentaTotal / cantidad;

        document.getElementById('costoUnitarioCalculado').value = (costoTotal * pesoTotal / cantidad).toFixed(4);

        // Aquí podrías mostrar los detalles del cálculo como en nueva_cotizacion.php
        return { pesoPorMillar, costoUnitario: (costoTotal * pesoTotal / cantidad), precioUnitario };
    });

    // Agregar ítems
    document.getElementById('btnAgregarItem').addEventListener('click', function() {
        const ancho = parseFloat(document.getElementById('ancho').value) || 0;
        const largo = parseFloat(document.getElementById('largo').value) || 0;
        const micraje = parseFloat(document.getElementById('micraje').value) || 0;
        const fuelle = parseFloat(document.getElementById('fuelle').value) || 0;
        const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
        const espesor = document.getElementById('espesor').value || '';
        const medidaReferencial = document.getElementById('medida_referencial').value || '';

        const materialSelect = document.getElementById('id_material');
        const materialOption = materialSelect.options[materialSelect.selectedIndex];
        if (!materialOption || materialOption.value === '') {
            alert('Por favor seleccione un material');
            return;
        }
        const materialId = materialOption.value;
        const materialText = materialOption.text;
        const materialTipo = materialOption.getAttribute('data-tipo');

        const colorSelect = document.getElementById('color');
        const color = colorSelect.options[colorSelect.selectedIndex].value;
        let colorTexto = color;
        if (color === 'Colores') {
            const colorDetalle = document.getElementById('colorDetalle').value;
            if (colorDetalle) {
                colorTexto = `Colores (${colorDetalle})`;
            }
        }

        const esBiodegradable = document.getElementById('biodegradable').checked;

        const precioUnitario = parseFloat(document.getElementById('costoUnitarioCalculado').value) || 0; // Ajustar según cálculo
        const subtotalItem = precioUnitario * cantidad;

        const tbody = document.getElementById('tablaItems').getElementsByTagName('tbody')[0];
        const newRow = tbody.insertRow();

        newRow.insertCell(0).innerHTML = materialText;
        newRow.insertCell(1).innerHTML = `${ancho.toFixed(2)} x ${largo.toFixed(2)} x ${micraje.toFixed(2)}` + (fuelle > 0 ? ` (Fuelle: ${fuelle.toFixed(2)})` : '');
        newRow.insertCell(2).innerHTML = colorTexto + (esBiodegradable ? ' (Biodegradable)' : '');
        newRow.insertCell(3).innerHTML = cantidad;
        newRow.insertCell(4).innerHTML = 'S/ ' + precioUnitario.toFixed(2);
        newRow.insertCell(5).innerHTML = 'S/ ' + subtotalItem.toFixed(2);
        newRow.insertCell(6).innerHTML = `
            <button type="button" class="btn btn-sm btn-primary btn-editar-item mr-1" title="Editar">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-danger btn-eliminar-item" title="Eliminar">
                <i class="fas fa-trash"></i>
            </button>
        `;

        // Actualizar totales
        actualizarTotales();

        const item = {
            id_material: materialId,
            material: materialText,
            ancho: ancho,
            largo: largo,
            micraje: micraje,
            fuelle: fuelle,
            color: colorTexto,
            biodegradable: esBiodegradable,
            cantidad: cantidad,
            costo_unitario: precioUnitario,
            precio_unitario: precioUnitario,
            subtotal: subtotalItem,
            espesor: espesor,
            medida_referencial: medidaReferencial
        };

        const itemsJson = document.getElementById('items_json');
        const items = JSON.parse(itemsJson.value);
        items.push(item);
        itemsJson.value = JSON.stringify(items);
    });

    // Eliminar ítems
    document.querySelectorAll('.btn-eliminar-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const rowIndex = Array.from(row.parentElement.rows).indexOf(row);
            const items = JSON.parse(document.getElementById('items_json').value);
            items.splice(rowIndex, 1);
            document.getElementById('items_json').value = JSON.stringify(items);
            row.remove();
            actualizarTotales();
        });
    });

    // Editar ítems
    document.querySelectorAll('.btn-editar-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const rowIndex = Array.from(row.parentElement.rows).indexOf(row);
            const items = JSON.parse(document.getElementById('items_json').value);
            const item = items[rowIndex];

            document.getElementById('ancho').value = item.ancho;
            document.getElementById('largo').value = item.largo;
            document.getElementById('micraje').value = item.micraje;
            document.getElementById('fuelle').value = item.fuelle;
            document.getElementById('cantidad').value = item.cantidad;
            document.getElementById('espesor').value = item.espesor || '';
            document.getElementById('medida_referencial').value = item.medida_referencial || '';

            const materialSelect = document.getElementById('id_material');
            for (let i = 0; i < materialSelect.options.length; i++) {
                if (materialSelect.options[i].value === item.id_material) {
                    materialSelect.selectedIndex = i;
                    break;
                }
            }

            const colorSelect = document.getElementById('color');
            const colorDetails = item.color.split('(');
            const baseColor = colorDetails[0].trim();
            for (let i = 0; i < colorSelect.options.length; i++) {
                if (colorSelect.options[i].value === baseColor) {
                    colorSelect.selectedIndex = i;
                    colorSelect.dispatchEvent(new Event('change'));
                    break;
                }
            }

            if (colorDetails.length > 1) {
                const detailColor = colorDetails[1].replace(')', '').trim();
                document.getElementById('colorDetalle').value = detailColor;
            }

            document.getElementById('biodegradable').checked = item.biodegradable;

            items.splice(rowIndex, 1);
            document.getElementById('items_json').value = JSON.stringify(items);
            row.remove();
            actualizarTotales();
        });
    });

    function actualizarTotales() {
        let subtotalGeneral = 0;
        const rows = document.getElementById('tablaItems').getElementsByTagName('tbody')[0].rows;
        for (let i = 0; i < rows.length; i++) {
            const subtotalText = rows[i].cells[5].innerText.replace('S/ ', '');
            subtotalGeneral += parseFloat(subtotalText);
        }

        const igv = subtotalGeneral * 0.18;
        const total = subtotalGeneral + igv;

        document.getElementById('subtotal').innerText = subtotalGeneral.toFixed(2);
        document.getElementById('igv').innerText = igv.toFixed(2);
        document.getElementById('total').innerText = total.toFixed(2);

        document.getElementById('subtotal_hidden').value = subtotalGeneral.toFixed(2);
        document.getElementById('igv_hidden').value = igv.toFixed(2);
        document.getElementById('total_hidden').value = total.toFixed(2);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>