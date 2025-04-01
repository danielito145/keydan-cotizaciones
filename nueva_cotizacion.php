<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Código de procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener datos de la cotización
        $codigo = $_POST['codigo'];
        $id_cliente = $_POST['id_cliente'];
        $fecha_cotizacion = $_POST['fecha_cotizacion'];
        $validez = $_POST['validez'];
        $condiciones_pago = $_POST['condiciones_pago'];
        $tiempo_entrega = $_POST['tiempo_entrega'];
        $total = floatval($_POST['total']); // Total ingresado (incluye IGV)
        $notas = $_POST['notas'];
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario logueado o usar valor por defecto
        
        // Calcular el subtotal (Monto Base) y el IGV
        $subtotal = $total / 1.18; // Monto Base (sin IGV)
        $impuestos = $subtotal * 0.18; // IGV (18% del Monto Base)
        
        // Insertar cotización
        $stmt = $conn->prepare("INSERT INTO cotizaciones (codigo, id_cliente, fecha_cotizacion, validez, 
                                condiciones_pago, tiempo_entrega, id_usuario, subtotal, impuestos, total, 
                                estado, notas, fecha_creacion) 
                               VALUES (:codigo, :id_cliente, :fecha_cotizacion, :validez, 
                                :condiciones_pago, :tiempo_entrega, :id_usuario, :subtotal, :impuestos, :total, 
                                'pendiente', :notas, NOW())");
        
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->bindParam(':fecha_cotizacion', $fecha_cotizacion);
        $stmt->bindParam(':validez', $validez);
        $stmt->bindParam(':condiciones_pago', $condiciones_pago);
        $stmt->bindParam(':tiempo_entrega', $tiempo_entrega);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':impuestos', $impuestos);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':notas', $notas);
        
        $stmt->execute();
        
        // Obtener el ID de la cotización insertada
        $id_cotizacion = $conn->lastInsertId();
        
        // Obtener los ítems de la cotización
        $items_json = $_POST['items_json'];
        $items = json_decode($items_json, true);
        
        // Verificar si hay ítems
        if (!empty($items)) {
            // Insertar cada ítem
            foreach ($items as $item) {
    $stmt = $conn->prepare("INSERT INTO cotizacion_detalles (id_cotizacion, id_material, ancho, largo, 
                           micraje, fuelle, colores, color_texto, biodegradable, cantidad, costo_unitario, 
                           precio_unitario, subtotal, espesor, medida_referencial) 
                          VALUES (:id_cotizacion, :id_material, :ancho, :largo, 
                           :micraje, :fuelle, :colores, :color_texto, :biodegradable, :cantidad, :costo_unitario, 
                           :precio_unitario, :subtotal, :espesor, :medida_referencial)");
    
    // Procesamiento del color
    $colores = 0;
    $color_texto = '';
    
    if (isset($item['color'])) {
        // Verificar si el color es del formato "Colores (específico)"
        if (preg_match('/Colores \((.*?)\)/', $item['color'], $matches)) {
            $colores = 1;
            $color_especifico = $matches[1];
            $color_texto = $color_especifico; // Guardamos solo el valor específico
        } else if ($item['color'] === 'Colores') {
            $colores = 1;
            $color_texto = ''; // Si solo dice "Colores" sin especificar
        } else {
            // Es "Negro" o "Transparente"
            $color_texto = $item['color'];
            $colores = 0;
        }
    }
    
    // Para depuración
    error_log("Color original: " . $item['color']);
    error_log("Color procesado: " . $color_texto);
    error_log("Colores (0/1): " . $colores);
    
    // Asignar valores a variables antes de pasarlas por referencia a bindParam
    $id_material = $item['id_material'];
    $ancho = $item['ancho'];
    $largo = $item['largo'];
    $micraje = $item['micraje'];
    $fuelle = $item['fuelle'];
    $biodegradable = $item['biodegradable'] ? 1 : 0; // Convertir booleano a entero
                $cantidad = $item['cantidad'] * 100; // Convertir paquetes a unidades (1 paquete = 100 unidades)
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
                $stmt->bindParam(':color_texto', $color_texto);
                $stmt->bindParam(':biodegradable', $biodegradable, PDO::PARAM_INT);
                $stmt->bindParam(':cantidad', $cantidad);
                $stmt->bindParam(':costo_unitario', $costo_unitario);
                $stmt->bindParam(':precio_unitario', $precio_unitario);
                $stmt->bindParam(':subtotal', $subtotal_item);
                $stmt->bindParam(':espesor', $espesor);
                $stmt->bindParam(':medida_referencial', $medida_referencial);
                // Depuración - guardar en un archivo log
                $log_message = "ID Material: " . $id_material . "\n";
                $log_message .= "Ancho: " . $ancho . "\n";
                $log_message .= "Largo: " . $largo . "\n";
                $log_message .= "Micraje: " . $micraje . "\n";
                $log_message .= "Color: " . $color_texto . "\n";
                $log_message .= "Colores: " . $colores . "\n";
                $log_message .= "------------------------\n";
                file_put_contents('debug_cotizacion.log', $log_message, FILE_APPEND);
                
                $stmt->execute();
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver cotización con mensaje de éxito
        echo "<script>
            window.location.href = 'ver_cotizacion.php?id=" . $id_cotizacion . "&success=1';
        </script>";
        exit;
        
    } catch(PDOException $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Guardar mensaje de error para mostrar
        $error_message = "Error al guardar la cotización: " . $e->getMessage();
    }
}

// Obtener lista de clientes
try {
    $stmt = $conn->query("SELECT id_cliente, razon_social FROM clientes WHERE estado = 'activo' ORDER BY razon_social");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Obtener lista de materiales
try {
    $stmt = $conn->query("SELECT id_material, codigo, nombre, tipo FROM materiales WHERE estado = 'activo' ORDER BY nombre");
    $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Obtener lista de proveedores
try {
    $stmt = $conn->query("SELECT id_proveedor, razon_social FROM proveedores WHERE estado = 'activo' ORDER BY razon_social");
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Obtener medidas estándar
try {
    $stmt = $conn->query("SELECT id_medida, nombre, ancho, largo, micraje_recomendado, fuelle FROM medidas_estandar WHERE estado = 'activo' ORDER BY nombre");
    $medidas_estandar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Generar código único para la cotización (COT-AÑO-MES-NÚMERO)
$codigo = 'COT-' . date('Y') . '-' . date('m') . '-001';

// Verificar si ya existen cotizaciones con un código similar y aumentar el número
try {
    $stmt = $conn->prepare("SELECT codigo FROM cotizaciones WHERE codigo LIKE :codigo_like ORDER BY codigo DESC LIMIT 1");
    $codigo_like = 'COT-' . date('Y') . '-' . date('m') . '-%';
    $stmt->bindParam(':codigo_like', $codigo_like);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $ultimo_codigo = $stmt->fetch(PDO::FETCH_ASSOC)['codigo'];
        $numero = intval(substr($ultimo_codigo, -3));
        $nuevo_numero = $numero + 1;
        $codigo = 'COT-' . date('Y') . '-' . date('m') . '-' . str_pad($nuevo_numero, 3, '0', STR_PAD_LEFT);
    }
} catch(PDOException $e) {
    // Si hay error, se mantiene el código por defecto
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-plus-circle"></i> Nueva Cotización</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="cotizaciones.php">Cotizaciones</a></li>
                <li class="breadcrumb-item active" aria-current="page">Nueva Cotización</li>
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
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="fecha_cotizacion">Fecha:</label>
                        <input type="date" class="form-control" id="fecha_cotizacion" name="fecha_cotizacion" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="validez">Validez (días):</label>
                        <input type="number" class="form-control" id="validez" name="validez" value="15" required min="1">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <div class="input-group">
                            <select class="form-control" id="id_cliente" name="id_cliente" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id_cliente']; ?>"><?php echo $cliente['razon_social']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalNuevoCliente">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <input type="text" class="form-control" id="buscarCliente" placeholder="Buscar cliente...">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="condiciones_pago">Condiciones de Pago:</label>
                        <input type="text" class="form-control" id="condiciones_pago" name="condiciones_pago" value="50% adelanto, 50% contra entrega">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tiempo_entrega">Tiempo de Entrega:</label>
                        <input type="text" class="form-control" id="tiempo_entrega" name="tiempo_entrega" value="3 dias después de recibido el adelanto">
                    </div>
                </div>
            </div>
            
            <!-- Medidas estándar -->
            <div class="card mt-4 mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Medidas Estándar</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <?php foreach($medidas_estandar as $medida): ?>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-block medida-estandar" 
                                        data-ancho="<?php echo $medida['ancho']; ?>" 
                                        data-largo="<?php echo $medida['largo']; ?>" 
                                        data-micraje="<?php echo $medida['micraje_recomendado']; ?>" 
                                        data-fuelle="<?php echo $medida['fuelle']; ?>"
                                        data-nombre="<?php echo $medida['nombre']; ?>">
                                    <?php echo $medida['nombre']; ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Micrajes estándar -->
            <div class="card mt-4 mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Micrajes Estándar</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="1.5">
                                1.5 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="2.0">
                                2.0 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="2.5">
                                2.5 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="3.0">
                                3.0 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="3.5">
                                3.5 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="4.0">
                                4.0 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="4.5">
                                4.5 micras
                            </button>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-info btn-block micraje-estandar" data-micraje="5.0">
                                5.0 micras
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Agregar items -->
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
                                            <?php echo $material['nombre']; ?> (<?php echo $material['tipo']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="id_proveedor">Proveedor:</label>
                                <select class="form-control" id="id_proveedor">
                                    <option value="">Seleccione un proveedor</option>
                                    <?php foreach($proveedores as $proveedor): ?>
                                        <option value="<?php echo $proveedor['id_proveedor']; ?>">
                                            <?php echo $proveedor['razon_social']; ?>
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
                                <label for="cantidad">Cantidad (paquetes):</label>
                                <input type="number" class="form-control" id="cantidad" value="10" min="1">
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
                    
                    <div class="row mt-4">
                        <div class="col-md-12 text-right">
                            <button type="button" id="btnCalcular" class="btn btn-info mr-2">
                                <i class="fas fa-calculator"></i> Calcular
                            </button>
                            <button type="button" id="btnAgregarItem" class="btn btn-success">
                                <i class="fas fa-plus"></i> Agregar Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tabla de items -->
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered" id="tablaItems">
                            <thead class="thead-light">
                                <tr>
                                    <th>Material</th>
                                    <th>Medidas</th>
                                    <th>Color</th>
                                    <th>Cantidad (paquetes)</th>
                                    <th>Precio por Paquete</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los items se agregarán dinámicamente -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">Subtotal:</th>
                                    <th><span id="subtotal">0.00</span></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="text-right">IGV (18%):</th>
                                    <th><span id="igv">0.00</span></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="text-right">Total:</th>
                                    <th><span id="total">0.00</span></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mostrar el Precio de Venta por Paquete de manera destacada -->
                    <div class="alert alert-info mt-3" id="precioVentaDestacado" style="display: none;">
                        <h5 class="mb-0 d-flex align-items-center">
                            Precio de Venta por Paquete: S/
                            <input type="number" class="form-control d-inline-block ml-2 mr-2" id="precioVentaDestacadoInput" style="width: 100px;" step="0.01" min="0">
                            <span id="precioVentaDestacadoValor">0.00</span>
                        </h5>
                    </div>
                    
                    <!-- Notas -->
                    <div class="form-group mt-4">
                        <label for="notas">Notas y Observaciones:</label>
                        <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                    </div>
                    
                    <!-- Tabla con precio detallado -->
                    <div class="card mt-4" id="detalleCalculo" style="display: none;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Cálculo detallado de precio</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Información básica:</h6>
                                    <p class="mb-1"><strong>Peso por paquete:</strong> <span id="pesoPorPaquete">0.00</span> kg</p>
                                    <p class="mb-1"><strong>Material:</strong> <span id="materialDetalleText">-</span></p>
                                    <p class="mb-1"><strong>Proveedor:</strong> <span id="proveedorDetalleText">-</span></p>
                                    <p class="mb-1"><strong>Color:</strong> <span id="colorDetalleText">-</span></p>
                                    <p class="mb-1"><strong>Biodegradable:</strong> <span id="biodegradableDetalle">No</span></p>
                                    <p class="mb-1"><strong>Cantidad (paquetes):</strong> <span id="cantidadPaquetesDetalle">10</span></p>
                                    <p class="mb-1"><strong>Cantidad (unidades):</strong> <span id="cantidadUnidadesDetalle">1000</span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Costos:</h6>
                                    <table class="table table-sm table-bordered">
                                        <tbody>
                                            <tr>
                                                <td>Costo Base (S/ por kg)</td>
                                                <td class="text-right" id="costoBase">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Costo Base Total (S/)</td>
                                                <td class="text-right" id="costoBaseTotal">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Aditivo Biodegradable (S/ por kg)</td>
                                                <td class="text-right" id="costoBio">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Aditivo Biodegradable Total (S/)</td>
                                                <td class="text-right" id="costoBioTotal">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Gastos Operativos (S/ por kg)</td>
                                                <td class="text-right" id="gastosOperativos">0.87</td>
                                            </tr>
                                            <tr>
                                                <td>Gastos Operativos Total (S/)</td>
                                                <td class="text-right" id="gastosOperativosTotal">0.00</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Costo Total (S/ por kg)</strong></td>
                                                <td class="text-right"><strong id="costoTotal">0.00</strong></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Costo Total Producto (S/)</strong></td>
                                                <td class="text-right"><strong id="costoTotalProducto">0.00</strong></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div>Precio de Venta (S/ por paquete)</div>
                                                    <div class="input-group input-group-sm mt-1">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">S/</span>
                                                        </div>
                                                        <input type="number" class="form-control" id="precioVentaInput" step="0.01" min="0">
                                                    </div>
                                                </td>
                                                <td class="text-right align-middle"><strong id="precioVentaTotal">0.00</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Ganancia acumulada (S/)</td>
                                                <td class="text-right" id="gananciaAcumulada">0.00</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>Precio por Paquete (S/)</strong></td>
                                                <td class="text-right"><strong id="precioUnitario">0.00</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <p class="mb-1"><strong>Resumen:</strong></p>
                                    <ul class="mb-0">
                                        <li>Peso total: <span id="pesoTotal">0.00</span> kg</li>
                                        <li>Costo total producto: S/ <span id="costoTotalProductoResumen">0.00</span></li>
                                        <li>Precio de venta total: S/ <span id="precioVentaTotalResumen">0.00</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Campos ocultos para almacenar los datos de los items -->
            <input type="hidden" id="costoUnitarioCalculado" value="0">
            <input type="hidden" id="items_json" name="items_json" value="[]">
            <input type="hidden" id="subtotal_hidden" name="subtotal" value="0">
            <input type="hidden" id="igv_hidden" name="impuestos" value="0">
            <input type="hidden" id="total_hidden" name="total" value="0">
            <input type="hidden" id="id_proveedor_hidden" name="id_proveedor" value="0">
            
            <div class="row mt-4">
                <div class="col-md-12 text-right">
                    <a href="cotizaciones.php" class="btn btn-secondary mr-2">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cotización
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal para nuevo cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" role="dialog" aria-labelledby="modalNuevoClienteLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNuevoClienteLabel">Agregar Nuevo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formNuevoCliente">
                    <div class="form-group">
                        <label for="razon_social">Razón Social:</label>
                        <input type="text" class="form-control" id="razon_social" required>
                    </div>
                    <div class="form-group">
                        <label for="ruc">RUC:</label>
                        <input type="text" class="form-control" id="ruc" maxlength="11">
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección:</label>
                        <input type="text" class="form-control" id="direccion">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" class="form-control" id="telefono">
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" class="form-control" id="email">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarCliente">Guardar Cliente</button>
            </div>
        </div>
    </div>
</div>

<script>
// Este código se ejecutará una vez que el documento esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Tabla de precios por tipo de material y color
    const preciosMateriales = {
        'R2': {
            'Negro': 4.50,
            'Colores': 5.00,
            'Transparente': 5.50 // Valor supuesto
        },
        'R1': {
            'Negro': 6.50,
            'Colores': 7.00,
            'Transparente': 7.20 // Valor supuesto
        },
        'Virgen': {
            'Negro': 7.80,
            'Colores': 7.80,
            'Transparente': 7.40
        }
    };
    
    // Costo adicional por biodegradable
    const costoBiodegradable = 1.00;
    
    // Gastos operativos por kg
    const gastosOperativos = 0.87;
    
    // Ganancia por kg
    const ganancia = 1.00;
    
    // Unidades por paquete
    const unidadesPorPaquete = 100;
    
    // Establecer por defecto el material como "Polietileno R2" cuando carga la página
    const materialSelect = document.getElementById('id_material');
    for (let i = 0; i < materialSelect.options.length; i++) {
        if (materialSelect.options[i].text.includes('R2')) {
            materialSelect.selectedIndex = i;
            break;
        }
    }
    
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
    
    // Manejar clic en botones de medidas estándar
    const botonesMedida = document.querySelectorAll('.medida-estandar');
    botonesMedida.forEach(function(boton) {
        boton.addEventListener('click', function() {
            // Obtener el nombre para la medida referencial
            const medidaReferencial = this.getAttribute('data-nombre') || this.textContent.trim();
            
            // Las medidas ya están en pulgadas, no se necesita conversión
            const ancho = parseFloat(this.getAttribute('data-ancho'));
            const largo = parseFloat(this.getAttribute('data-largo'));
            const micraje = parseFloat(this.getAttribute('data-micraje'));
            const fuelle = parseFloat(this.getAttribute('data-fuelle')) || 0;
            
            document.getElementById('ancho').value = ancho.toFixed(2);
            document.getElementById('largo').value = largo.toFixed(2);
            document.getElementById('micraje').value = micraje.toFixed(2);
            document.getElementById('fuelle').value = fuelle.toFixed(2);
            
            // Establecer la medida referencial
            document.getElementById('medida_referencial').value = medidaReferencial;
            
            // También establecemos el espesor igual al micraje para la proforma
            document.getElementById('espesor').value = micraje.toFixed(2) + ' micras';
        });
    });
    
    // Manejar clic en botones de micraje estándar
    const botonesMicraje = document.querySelectorAll('.micraje-estandar');
    botonesMicraje.forEach(function(boton) {
        boton.addEventListener('click', function() {
            const micraje = parseFloat(this.getAttribute('data-micraje'));
            
            // Establecer el micraje en el campo
            document.getElementById('micraje').value = micraje.toFixed(2);
            
            // También establecemos el espesor para la proforma
            document.getElementById('espesor').value = micraje.toFixed(2) + ' micras';
        });
    });
    
    // Manejar clic en botón calcular
    document.getElementById('btnCalcular').addEventListener('click', function() {
        calcularPrecio();
    });
    
    // Función para calcular el precio según la fórmula específica
function calcularPrecio() {
    // Obtener valores del formulario
    const ancho = parseFloat(document.getElementById('ancho').value) || 0;
    const largo = parseFloat(document.getElementById('largo').value) || 0;
    const micraje = parseFloat(document.getElementById('micraje').value) || 0;
    const cantidadPaquetes = parseInt(document.getElementById('cantidad').value) || 10;
    const cantidadUnidades = cantidadPaquetes * unidadesPorPaquete; // Convertir paquetes a unidades
    
    // Obtener tipo de material
    const materialSelect = document.getElementById('id_material');
    const materialOption = materialSelect.options[materialSelect.selectedIndex];
    const materialText = materialOption ? materialOption.text : '';
    const materialTipo = materialOption ? materialOption.getAttribute('data-tipo') : '';
    
    // Obtener proveedor seleccionado
    const proveedorSelect = document.getElementById('id_proveedor');
    const proveedorId = proveedorSelect.value;
    const proveedorText = proveedorSelect.options[proveedorSelect.selectedIndex] ? 
                         proveedorSelect.options[proveedorSelect.selectedIndex].text : 'No especificado';
    
    // Guardar el ID del proveedor en el campo oculto
    document.getElementById('id_proveedor_hidden').value = proveedorId;
    
    // Obtener color
    const colorSelect = document.getElementById('color');
    const color = colorSelect.options[colorSelect.selectedIndex].value;
    let colorTexto = color;
    
    // Si es "Colores", obtener el detalle específico y validar
    if (color === 'Colores') {
        const colorDetalle = document.getElementById('colorDetalle').value.trim();
        if (!colorDetalle) {
            alert('Por favor especifique un color en el campo "Especificar color".');
            return; // Detener el cálculo si el campo está vacío
        }
        colorTexto = `Colores (${colorDetalle})`;
    }
    
    // Verificar si es biodegradable
    const esBiodegradable = document.getElementById('biodegradable').checked;
    
    // Calcular peso por paquete (100 unidades) según la fórmula: ancho x largo x 0.0303 x micraje
    const pesoPorPaquete = ancho * largo * 0.0303 * micraje * (unidadesPorPaquete / 1000); // Ajustado para 100 unidades
    
    // Calcular peso total (para todos los paquetes)
    const pesoTotal = pesoPorPaquete * cantidadPaquetes;
    
    // Obtener costo base según material y color (usar el valor base del color)
    let costoBase = 0;
    if (preciosMateriales[materialTipo] && preciosMateriales[materialTipo][color]) {
        costoBase = preciosMateriales[materialTipo][color];
        
        // Si se seleccionó un proveedor, ajustar el precio basado en el proveedor
        if (proveedorId) {
            const adjustments = {
                '1': 1.0,   // Proveedor 1: precio normal
                '2': 0.95,  // Proveedor 2: 5% descuento
                '3': 1.05   // Proveedor 3: 5% más caro
            };
            
            if (adjustments[proveedorId]) {
                costoBase *= adjustments[proveedorId];
            }
        }
    }
    
    // Calcular costo base total (para todos los paquetes)
    const costoBaseTotal = costoBase * pesoTotal;
    
    // Calcular aditivo biodegradable
    const costoBio = esBiodegradable ? costoBiodegradable : 0;
    
    // Calcular costo biodegradable total
    const costoBioTotal = costoBio * pesoTotal;
    
    // Calcular gastos operativos total
    const gastosOperativosTotal = gastosOperativos * pesoTotal;
    
    // Calcular costo total por kg
    const costoTotal = costoBase + costoBio + gastosOperativos;
    
    // Calcular costo total producto (para todos los paquetes)
    const costoTotalProducto = costoTotal * pesoTotal;
    
    // Calcular precio de venta total con ganancia estándar (para todos los paquetes)
    const precioVentaTotal = costoTotalProducto + (ganancia * pesoTotal);
    
    // Calcular precio de venta por paquete (para 100 unidades)
    const precioVentaPorPaquete = precioVentaTotal / cantidadPaquetes;
    
    // Calcular precio unitario (igual al precio por paquete)
    const precioUnitario = precioVentaPorPaquete;
    
    // Calcular ganancia acumulada (ganancia por paquete × cantidad de paquetes)
    const costoPorPaquete = costoTotalProducto / cantidadPaquetes;
    const gananciaPorPaquete = precioVentaPorPaquete - costoPorPaquete;
    const gananciaAcumulada = gananciaPorPaquete * cantidadPaquetes;
    
    // Guardar el costo unitario para usarlo después
    document.getElementById('costoUnitarioCalculado').value = (costoTotal * pesoTotal / cantidadUnidades).toFixed(4);
    
    // Mostrar resultados en la interfaz
    document.getElementById('pesoPorPaquete').innerText = pesoPorPaquete.toFixed(2);
    document.getElementById('materialDetalleText').innerText = materialText;
    document.getElementById('proveedorDetalleText').innerText = proveedorText;
    document.getElementById('colorDetalleText').innerText = colorTexto;
    document.getElementById('biodegradableDetalle').innerText = esBiodegradable ? 'Sí' : 'No';
    document.getElementById('cantidadPaquetesDetalle').innerText = cantidadPaquetes;
    document.getElementById('cantidadUnidadesDetalle').innerText = cantidadUnidades;
    
    document.getElementById('costoBase').innerText = costoBase.toFixed(2);
    document.getElementById('costoBaseTotal').innerText = costoBaseTotal.toFixed(2);
    document.getElementById('costoBio').innerText = costoBio.toFixed(2);
    document.getElementById('costoBioTotal').innerText = costoBioTotal.toFixed(2);
    document.getElementById('gastosOperativos').innerText = gastosOperativos.toFixed(2);
    document.getElementById('gastosOperativosTotal').innerText = gastosOperativosTotal.toFixed(2);
    document.getElementById('costoTotal').innerText = costoTotal.toFixed(2);
    document.getElementById('costoTotalProducto').innerText = costoTotalProducto.toFixed(2);
    document.getElementById('costoTotalProductoResumen').innerText = costoTotalProducto.toFixed(2);
    
    document.getElementById('pesoTotal').innerText = pesoTotal.toFixed(2);
    document.getElementById('precioVentaTotalResumen').innerText = precioVentaTotal.toFixed(2);
    document.getElementById('precioVentaTotal').innerText = precioVentaPorPaquete.toFixed(2);
    document.getElementById('precioVentaInput').value = precioVentaPorPaquete.toFixed(2);
    document.getElementById('gananciaAcumulada').innerText = gananciaAcumulada.toFixed(2);
    document.getElementById('precioUnitario').innerText = precioUnitario.toFixed(2);
    
    // Mostrar el precio de venta destacado
    document.getElementById('precioVentaDestacadoInput').value = precioVentaPorPaquete.toFixed(2);
    document.getElementById('precioVentaDestacadoValor').innerText = precioVentaPorPaquete.toFixed(2);
    document.getElementById('precioVentaDestacado').style.display = 'block';
    
    // Mostrar el detalle del cálculo
    document.getElementById('detalleCalculo').style.display = 'block';
    
    return {
        pesoPorPaquete,
        costoUnitario: (costoTotal * pesoTotal / cantidadUnidades),
        precioUnitario
    };
}
    
    // Agregar event listener para el input de precio de venta en la sección destacada
        document.getElementById('precioVentaDestacadoInput').addEventListener('input', function() {
            const precioVentaPorPaquete = parseFloat(this.value) || 0;
            const cantidadPaquetes = parseInt(document.getElementById('cantidadPaquetesDetalle').innerText) || 10;
            const costoTotalProducto = parseFloat(document.getElementById('costoTotalProducto').innerText) || 0;
            
            // Calcular precio total (para todos los paquetes)
            const precioVentaTotal = precioVentaPorPaquete * cantidadPaquetes;
            
            // Actualizar precio total
            document.getElementById('precioVentaTotal').innerText = precioVentaPorPaquete.toFixed(2);
            document.getElementById('precioVentaTotalResumen').innerText = precioVentaTotal.toFixed(2);
            
            // Calcular y actualizar ganancia acumulada
            const costoPorPaquete = costoTotalProducto / cantidadPaquetes;
            const gananciaPorPaquete = precioVentaPorPaquete - costoPorPaquete;
            const gananciaAcumulada = gananciaPorPaquete * cantidadPaquetes;
            document.getElementById('gananciaAcumulada').innerText = gananciaAcumulada.toFixed(2);
            
            // Actualizar precio unitario (igual al precio por paquete)
            const precioUnitario = precioVentaPorPaquete;
            document.getElementById('precioUnitario').innerText = precioUnitario.toFixed(2);
            
            // Actualizar precio en la tabla de cálculo detallado
            document.getElementById('precioVentaInput').value = precioVentaPorPaquete.toFixed(2);
            document.getElementById('precioVentaDestacadoValor').innerText = precioVentaPorPaquete.toFixed(2);
        });
    
// Agregar event listener para el input de precio de venta en la tabla de cálculo detallado
    document.getElementById('precioVentaInput').addEventListener('input', function() {
        const precioVentaPorPaquete = parseFloat(this.value) || 0;
        const cantidadPaquetes = parseInt(document.getElementById('cantidadPaquetesDetalle').innerText) || 10;
        const costoTotalProducto = parseFloat(document.getElementById('costoTotalProducto').innerText) || 0;
        
        // Calcular precio total (para todos los paquetes)
        const precioVentaTotal = precioVentaPorPaquete * cantidadPaquetes;
        
        // Actualizar precio total
        document.getElementById('precioVentaTotal').innerText = precioVentaPorPaquete.toFixed(2);
        document.getElementById('precioVentaTotalResumen').innerText = precioVentaTotal.toFixed(2);
        
        // Calcular y actualizar ganancia acumulada
        const costoPorPaquete = costoTotalProducto / cantidadPaquetes;
        const gananciaPorPaquete = precioVentaPorPaquete - costoPorPaquete;
        const gananciaAcumulada = gananciaPorPaquete * cantidadPaquetes;
        document.getElementById('gananciaAcumulada').innerText = gananciaAcumulada.toFixed(2);
        
        // Actualizar precio unitario (igual al precio por paquete)
        const precioUnitario = precioVentaPorPaquete;
        document.getElementById('precioUnitario').innerText = precioUnitario.toFixed(2);
        
        // Actualizar precio destacado
        document.getElementById('precioVentaDestacadoInput').value = precioVentaPorPaquete.toFixed(2);
        document.getElementById('precioVentaDestacadoValor').innerText = precioVentaPorPaquete.toFixed(2);
    });
    
    // Para agregar items a la cotización
    document.getElementById('btnAgregarItem').addEventListener('click', function() {
        // Obtener valores del formulario
        const ancho = parseFloat(document.getElementById('ancho').value) || 0;
        const largo = parseFloat(document.getElementById('largo').value) || 0;
        const micraje = parseFloat(document.getElementById('micraje').value) || 0;
        const fuelle = parseFloat(document.getElementById('fuelle').value) || 0;
        const cantidadPaquetes = parseInt(document.getElementById('cantidad').value) || 0;
        const cantidadUnidades = cantidadPaquetes * unidadesPorPaquete; // Convertir paquetes a unidades
        const espesor = document.getElementById('espesor').value || micraje.toFixed(2) + ' micras';
        const medidaReferencial = document.getElementById('medida_referencial').value || '';
        
        // Obtener material
        const materialSelect = document.getElementById('id_material');
        const materialOption = materialSelect.options[materialSelect.selectedIndex];
        if (!materialOption || materialOption.value === '') {
            alert('Por favor seleccione un material');
            return;
        }
        const materialId = materialOption.value;
        const materialText = materialOption.text;
        const materialTipo = materialOption.getAttribute('data-tipo');
        
        // Obtener proveedor
        const proveedorSelect = document.getElementById('id_proveedor');
        const proveedorId = proveedorSelect.value;
        if (!proveedorId) {
            alert('Por favor seleccione un proveedor');
            return;
        }
        const proveedorText = proveedorSelect.options[proveedorSelect.selectedIndex].text;
        
        // Obtener color
        const colorSelect = document.getElementById('color');
        const color = colorSelect.options[colorSelect.selectedIndex].value;
        let colorTexto = color;
        
        // Si es "Colores", obtener el detalle específico
        if (color === 'Colores') {
            const colorDetalle = document.getElementById('colorDetalle').value;
            if (colorDetalle) {
                colorTexto = `Colores (${colorDetalle})`;
            }
        }
        
        // Verificar si es biodegradable
        const esBiodegradable = document.getElementById('biodegradable').checked;
        
        // Asegurarse de que se haya calculado el precio primero
        if (document.getElementById('detalleCalculo').style.display !== 'block') {
            calcularPrecio();
        }
        
        // Obtener valores calculados
        const precioUnitario = parseFloat(document.getElementById('precioUnitario').innerText) || 0;
        const costoUnitario = parseFloat(document.getElementById('costoUnitarioCalculado').value) || 0;
        const precioVentaPorPaquete = parseFloat(document.getElementById('precioVentaTotal').innerText) || 0;
        const subtotalItem = precioVentaPorPaquete * cantidadPaquetes;
        
        // Crear nueva fila en la tabla
        const tbody = document.getElementById('tablaItems').getElementsByTagName('tbody')[0];
        const newRow = tbody.insertRow();
        
        // Calcular medidas en centímetros para mostrar
        const anchoCm = (ancho * 2.54).toFixed(2);
        const largoCm = (largo * 2.54).toFixed(2);
        const fuelleCm = fuelle > 0 ? (fuelle * 2.54).toFixed(2) : 0;
        
        // Insertar celdas
        const cell1 = newRow.insertCell(0);
        cell1.innerHTML = materialText;
        
        const cell2 = newRow.insertCell(1);
        const medidasText = `${ancho.toFixed(2)}" x ${largo.toFixed(2)}" (${anchoCm} x ${largoCm} cm) x ${micraje.toFixed(2)} mic`;
        cell2.innerHTML = medidasText + (fuelle > 0 ? ` - Fuelle: ${fuelle.toFixed(2)}" (${fuelleCm} cm)` : '');
        if (medidaReferencial) {
            cell2.innerHTML += `<br><small>Medida referencial: ${medidaReferencial}</small>`;
        }
        
        const cell3 = newRow.insertCell(2);
        cell3.innerHTML = colorTexto + (esBiodegradable ? ' (Biodegradable)' : '');
        
        const cell4 = newRow.insertCell(3);
        cell4.innerHTML = cantidadPaquetes;
        
        const cell5 = newRow.insertCell(4);
        cell5.innerHTML = 'S/ ' + precioUnitario.toFixed(2);
        
        const cell6 = newRow.insertCell(5);
        cell6.innerHTML = 'S/ ' + subtotalItem.toFixed(2);
        
        const cell7 = newRow.insertCell(6);
        cell7.innerHTML = `
            <button type="button" class="btn btn-sm btn-primary btn-editar-item mr-1" title="Editar">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-danger btn-eliminar-item" title="Eliminar">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        // Agregar evento para eliminar item
        cell7.querySelector('.btn-eliminar-item').addEventListener('click', function() {
            const row = this.closest('tr');
            const rowIndex = Array.from(tbody.rows).indexOf(row);
            
            // Eliminar el ítem de la lista JSON
            const items = JSON.parse(document.getElementById('items_json').value);
            items.splice(rowIndex, 1);
            document.getElementById('items_json').value = JSON.stringify(items);
            
            // Eliminar la fila de la tabla
            row.remove();
            
            // Actualizar totales
            actualizarTotales();
            
            // Ocultar el precio destacado si no hay ítems
            if (tbody.rows.length === 0) {
                document.getElementById('precioVentaDestacado').style.display = 'none';
            }
        });
        
        // Agregar evento para editar item
        cell7.querySelector('.btn-editar-item').addEventListener('click', function() {
            const row = this.closest('tr');
            const rowIndex = Array.from(tbody.rows).indexOf(row);
            
            // Obtener los datos del ítem desde el JSON almacenado
            const items = JSON.parse(document.getElementById('items_json').value);
            const item = items[rowIndex];
            
            // Llenar el formulario con los datos del ítem
            document.getElementById('ancho').value = item.ancho;
            document.getElementById('largo').value = item.largo;
            document.getElementById('micraje').value = item.micraje;
            document.getElementById('fuelle').value = item.fuelle;
            document.getElementById('cantidad').value = item.cantidad;
            document.getElementById('espesor').value = item.espesor || '';
            document.getElementById('medida_referencial').value = item.medida_referencial || '';
            
            // Seleccionar el material
            const materialSelect = document.getElementById('id_material');
            for (let i = 0; i < materialSelect.options.length; i++) {
                if (materialSelect.options[i].value === item.id_material) {
                    materialSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Seleccionar el proveedor si existe
            if (item.id_proveedor) {
                const proveedorSelect = document.getElementById('id_proveedor');
                for (let i = 0; i < proveedorSelect.options.length; i++) {
                    if (proveedorSelect.options[i].value === item.id_proveedor) {
                        proveedorSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Seleccionar el color
            const colorSelect = document.getElementById('color');
            const colorDetails = item.color.split('(');
            const baseColor = colorDetails[0].trim();
            
            for (let i = 0; i < colorSelect.options.length; i++) {
                if (colorSelect.options[i].value === baseColor) {
                    colorSelect.selectedIndex = i;
                    // Disparar evento de cambio para mostrar/ocultar campo de detalle
                    colorSelect.dispatchEvent(new Event('change'));
                    break;
                }
            }
            
            // Si hay detalle de color, establecerlo
            if (colorDetails.length > 1) {
                const detailColor = colorDetails[1].replace(')', '').trim();
                document.getElementById('colorDetalle').value = detailColor;
            }
            
            // Establecer biodegradable
            document.getElementById('biodegradable').checked = item.biodegradable;
            
            // Eliminar el ítem de la lista y la tabla
            items.splice(rowIndex, 1);
            document.getElementById('items_json').value = JSON.stringify(items);
            row.remove();
            
            // Actualizar totales
            actualizarTotales();
            
            // Ocultar el precio destacado
            document.getElementById('precioVentaDestacado').style.display = 'none';
            
            // Calcular el precio para mostrar los detalles
            calcularPrecio();
            
            // Mostrar mensaje
            alert('Ítem cargado para edición. Realice los cambios y haga clic en "Agregar Item" para actualizar.');
        });
        
        // Actualizar totales
        actualizarTotales();
        
        // Crear objeto JSON para el ítem
        const item = {
            id_material: materialId,
            material: materialText,
            id_proveedor: proveedorId,
            proveedor: proveedorText,
            ancho: ancho,
            largo: largo,
            micraje: micraje,
            fuelle: fuelle,
            color: colorTexto,
            biodegradable: esBiodegradable,
            cantidad: cantidadPaquetes, // Guardar la cantidad en paquetes
            costo_unitario: costoUnitario,
            precio_unitario: precioUnitario,
            subtotal: subtotalItem,
            espesor: espesor,
            medida_referencial: medidaReferencial
        };
        
        // Añadir a la lista de items
        const itemsJson = document.getElementById('items_json');
        const items = JSON.parse(itemsJson.value);
        items.push(item);
        itemsJson.value = JSON.stringify(items);
        
        // Limpiar algunos campos para facilitar la entrada de un nuevo item
        document.getElementById('cantidad').focus();
    });
    
    // Función para actualizar totales
// Función para actualizar totales
    function actualizarTotales() {
        let subtotalGeneral = 0;
        
        // Recorrer todas las filas de la tabla y sumar los subtotales
        const rows = document.getElementById('tablaItems').getElementsByTagName('tbody')[0].rows;
        for (let i = 0; i < rows.length; i++) {
            const subtotalText = rows[i].cells[5].innerText.replace('S/ ', '');
            subtotalGeneral += parseFloat(subtotalText);
        }
        
        // Calcular IGV y total
        const igv = subtotalGeneral * 0.18;
        const total = subtotalGeneral + igv;
        
        // Actualizar valores en la interfaz
        document.getElementById('subtotal').innerText = subtotalGeneral.toFixed(2);
        document.getElementById('igv').innerText = igv.toFixed(2);
        document.getElementById('total').innerText = total.toFixed(2);
        
        // Actualizar valores ocultos
        document.getElementById('subtotal_hidden').value = subtotalGeneral.toFixed(2);
        document.getElementById('igv_hidden').value = igv.toFixed(2);
        document.getElementById('total_hidden').value = total.toFixed(2);
    }
    
    // Código para el buscador de clientes
    document.getElementById('buscarCliente').addEventListener('keyup', function() {
        const texto = this.value.toLowerCase();
        const select = document.getElementById('id_cliente');
        const opciones = select.options;
        
        for (let i = 0; i < opciones.length; i++) {
            const opcion = opciones[i];
            const contenido = opcion.text.toLowerCase();
            
            if (contenido.includes(texto) || opcion.value === '') {
                opcion.style.display = '';
            } else {
                opcion.style.display = 'none';
            }
        }
    });
    
    // Código para guardar nuevo cliente
    document.getElementById('btnGuardarCliente').addEventListener('click', function() {
        const razonSocial = document.getElementById('razon_social').value.trim();
        const ruc = document.getElementById('ruc').value.trim();
        const direccion = document.getElementById('direccion').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const email = document.getElementById('email').value.trim();
        
        if (!razonSocial) {
            alert('Por favor ingrese la razón social del cliente');
            return;
        }
        
        // Realizar una petición AJAX para guardar el cliente
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'guardar_cliente.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const respuesta = JSON.parse(this.responseText);
                    if (respuesta.exito) {
                        // Agregar el nuevo cliente al select
                        const select = document.getElementById('id_cliente');
                        const nuevaOpcion = document.createElement('option');
                        nuevaOpcion.value = respuesta.id_cliente;
                        nuevaOpcion.text = razonSocial;
                        select.add(nuevaOpcion);
                        
                        // Seleccionar el nuevo cliente
                        select.value = respuesta.id_cliente;
                        
                        // Cerrar el modal
                        $('#modalNuevoCliente').modal('hide');
                        
                        // Limpiar el formulario
                        document.getElementById('formNuevoCliente').reset();
                        
                        // Mostrar mensaje de éxito
                        alert('Cliente añadido con éxito');
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
        xhr.send('razon_social=' + encodeURIComponent(razonSocial) + 
                 '&ruc=' + encodeURIComponent(ruc) + 
                 '&direccion=' + encodeURIComponent(direccion) + 
                 '&telefono=' + encodeURIComponent(telefono) + 
                 '&email=' + encodeURIComponent(email));
    });
    
    // Agregado validación al formulario antes de enviar
    document.getElementById('formCotizacion').addEventListener('submit', function(e) {
        // Verificar que haya al menos un item en la tabla
        const items = JSON.parse(document.getElementById('items_json').value);
        if (items.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto a la cotización');
            return false;
        }
        
        return true;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>