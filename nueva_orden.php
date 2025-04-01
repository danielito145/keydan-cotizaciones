<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener datos del formulario
        $id_cliente = $_POST['id_cliente'];
        $condiciones_pago = $_POST['condiciones_pago'];
        $tiempo_entrega = $_POST['tiempo_entrega'];
        $observaciones = $_POST['observaciones'];
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Obtener ID del usuario actual
        
        // Validar cliente
        $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = :id_cliente");
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("El cliente seleccionado no existe");
        }
        
        // Generar código único para la orden
        $fecha_actual = date('Y-m-d');
        $anio_mes = date('Y-m');
        
        // Obtener último número de orden del mes actual
        $stmt = $conn->prepare("SELECT MAX(SUBSTRING_INDEX(codigo, '-', -1)) as ultimo 
                               FROM ordenes_venta 
                               WHERE codigo LIKE :codigo_pattern");
        $codigo_pattern = 'ORD-' . $anio_mes . '-%';
        $stmt->bindParam(':codigo_pattern', $codigo_pattern);
        $stmt->execute();
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo'];
        
        // Generar nuevo número
        $numero = ($ultimo) ? intval($ultimo) + 1 : 1;
        $codigo = 'ORD-' . $anio_mes . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
        
        // Calcular totales
        $subtotal = 0;
        $impuestos = 0;
        $total = 0;
        
        // Insertar la orden
        $stmt = $conn->prepare("INSERT INTO ordenes_venta 
                                (codigo, id_cliente, id_usuario, fecha_emision, 
                                condiciones_pago, tiempo_entrega, subtotal, 
                                impuestos, total, estado, observaciones) 
                                VALUES 
                                (:codigo, :id_cliente, :id_usuario, NOW(), 
                                :condiciones_pago, :tiempo_entrega, :subtotal, 
                                :impuestos, :total, 'pendiente', :observaciones)");
        
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':condiciones_pago', $condiciones_pago);
        $stmt->bindParam(':tiempo_entrega', $tiempo_entrega);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':impuestos', $impuestos);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->execute();
        
        // Obtener el ID de la orden insertada
        $id_orden = $conn->lastInsertId();
        
        // Procesar productos
        $detalles_guardados = 0;
        
        if (isset($_POST['material']) && is_array($_POST['material'])) {
            $materiales = $_POST['material'];
            $descripciones = $_POST['descripcion'];
            $anchos = $_POST['ancho'];
            $largos = $_POST['largo'];
            $micrajes = $_POST['micraje'];
            $espesores = $_POST['espesor'];
            $fuelles = $_POST['fuelle'];
            $medidas_referenciales = $_POST['medida_referencial'];
            $colores_array = $_POST['colores'];
            $biodegradables = isset($_POST['biodegradable']) ? $_POST['biodegradable'] : [];
            $cantidades = $_POST['cantidad'];
            $precios_unitarios = $_POST['precio_unitario'];
            
            for ($i = 0; $i < count($materiales); $i++) {
                if (!empty($materiales[$i]) && !empty($cantidades[$i]) && !empty($precios_unitarios[$i])) {
                    // Calcular subtotal
                    $cantidad = floatval(str_replace(',', '', $cantidades[$i]));
                    $precio_unitario = floatval(str_replace(',', '', $precios_unitarios[$i]));
                    $subtotal_item = $cantidad * $precio_unitario;
                    
                    // Verificar si es biodegradable
                    $biodegradable = in_array($i, $biodegradables) ? 1 : 0;
                    
                    // Valores por defecto para campos opcionales
                    $micraje = !empty($micrajes[$i]) ? $micrajes[$i] : null;
                    $espesor = !empty($espesores[$i]) ? $espesores[$i] : null;
                    $fuelle = !empty($fuelles[$i]) ? $fuelles[$i] : 0;
                    $medida_referencial = !empty($medidas_referenciales[$i]) ? $medidas_referenciales[$i] : null;
                    $colores = !empty($colores_array[$i]) ? $colores_array[$i] : 0;
                    
                    // Insertar detalle de la orden
                    $stmt = $conn->prepare("INSERT INTO orden_detalles 
                                          (id_orden, id_material, descripcion, ancho, 
                                           largo, micraje, espesor, fuelle, 
                                           medida_referencial, colores, biodegradable, 
                                           cantidad, precio_unitario, subtotal) 
                                          VALUES 
                                          (:id_orden, :id_material, :descripcion, :ancho, 
                                           :largo, :micraje, :espesor, :fuelle, 
                                           :medida_referencial, :colores, :biodegradable, 
                                           :cantidad, :precio_unitario, :subtotal)");
                    
                    $stmt->bindParam(':id_orden', $id_orden);
                    $stmt->bindParam(':id_material', $materiales[$i]);
                    $stmt->bindParam(':descripcion', $descripciones[$i]);
                    $stmt->bindParam(':ancho', $anchos[$i]);
                    $stmt->bindParam(':largo', $largos[$i]);
                    $stmt->bindParam(':micraje', $micraje);
                    $stmt->bindParam(':espesor', $espesor);
                    $stmt->bindParam(':fuelle', $fuelle);
                    $stmt->bindParam(':medida_referencial', $medida_referencial);
                    $stmt->bindParam(':colores', $colores);
                    $stmt->bindParam(':biodegradable', $biodegradable);
                    $stmt->bindParam(':cantidad', $cantidad);
                    $stmt->bindParam(':precio_unitario', $precio_unitario);
                    $stmt->bindParam(':subtotal', $subtotal_item);
                    $stmt->execute();
                    
                    // Acumular totales
                    $subtotal += $subtotal_item;
                    $detalles_guardados++;
                }
            }
        }
        
        if ($detalles_guardados === 0) {
            throw new Exception("Debe agregar al menos un producto a la orden");
        }
        
        // Calcular impuestos (18%)
        $impuestos = $subtotal * 0.18;
        $total = $subtotal + $impuestos;
        
        // Actualizar totales en la orden
        $stmt = $conn->prepare("UPDATE ordenes_venta 
                               SET subtotal = :subtotal, impuestos = :impuestos, total = :total 
                               WHERE id_orden = :id_orden");
        
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':impuestos', $impuestos);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->execute();
        
        // Registrar en historial de cambios
        $stmt = $conn->prepare("INSERT INTO historial_orden 
                                (id_orden, id_usuario, fecha_cambio, estado_anterior, estado_nuevo, comentario) 
                                VALUES (:id_orden, :id_usuario, NOW(), '', 'pendiente', :comentario)");
        
        $comentario = "Orden creada manualmente";
        
        $stmt->bindParam(':id_orden', $id_orden);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':comentario', $comentario);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        // Redirigir a la página de ver orden con mensaje de éxito
        header("Location: ver_orden.php?id=" . $id_orden . "&success=1&message=" . urlencode("La orden ha sido creada correctamente"));
        exit;
        
    } catch(Exception $e) {
        // Deshacer transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $error_msg = "Error al crear la orden: " . $e->getMessage();
    }
}

// Obtener lista de clientes
$stmt_clientes = $conn->query("SELECT id_cliente, razon_social, ruc FROM clientes WHERE estado = 'activo' ORDER BY razon_social");
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de materiales
$stmt_materiales = $conn->query("SELECT id_material, nombre, descripcion FROM materiales WHERE estado = 'activo' ORDER BY nombre");
$materiales = $stmt_materiales->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-plus-circle"></i> Nueva Orden de Venta</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="ordenes.php">Órdenes de Venta</a></li>
                <li class="breadcrumb-item active" aria-current="page">Nueva Orden</li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="ordenes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Órdenes
        </a>
    </div>
</div>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<form method="post" action="nueva_orden.php" id="ordenForm">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Información General de la Orden</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="id_cliente">Cliente: <span class="text-danger">*</span></label>
                        <select class="form-control" id="id_cliente" name="id_cliente" required>
                            <option value="">-- Seleccione un cliente --</option>
                            <?php foreach($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>">
                                    <?php echo $cliente['razon_social'] . ' (' . $cliente['ruc'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="condiciones_pago">Condiciones de Pago: <span class="text-danger">*</span></label>
                        <select class="form-control" id="condiciones_pago" name="condiciones_pago" required>
                            <option value="Contado">Contado</option>
                            <option value="Crédito 15 días">Crédito 15 días</option>
                            <option value="Crédito 30 días">Crédito 30 días</option>
                            <option value="Crédito 45 días">Crédito 45 días</option>
                            <option value="Crédito 60 días">Crédito 60 días</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tiempo_entrega">Tiempo de Entrega: <span class="text-danger">*</span></label>
                        <select class="form-control" id="tiempo_entrega" name="tiempo_entrega" required>
                            <option value="Inmediato">Inmediato</option>
                            <option value="3 días" selected>3 días</option>
                            <option value="5 días">5 días</option>
                            <option value="1 semana">1 semana</option>
                            <option value="2 semanas">2 semanas</option>
                            <option value="3 semanas">3 semanas</option>
                            <option value="1 mes">1 mes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones">Observaciones:</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Detalle de Productos</h5>
            <button type="button" class="btn btn-sm btn-light" id="agregarProducto">
                <i class="fas fa-plus"></i> Agregar Producto
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaProductos">
                    <thead class="thead-light">
                        <tr>
                            <th>Material <span class="text-danger">*</span></th>
                            <th>Descripción <span class="text-danger">*</span></th>
                            <th>Medidas <span class="text-danger">*</span></th>
                            <th>Detalles</th>
                            <th>Cantidad <span class="text-danger">*</span></th>
                            <th>Precio Unit. <span class="text-danger">*</span></th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select class="form-control material-select" name="material[]" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach($materiales as $material): ?>
                                        <option value="<?php echo $material['id_material']; ?>" data-descripcion="<?php echo htmlspecialchars($material['descripcion']); ?>">
                                            <?php echo $material['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="descripcion[]" required>
                            </td>
                            <td>
                                <div class="input-group mb-1">
                                    <input type="text" class="form-control" name="ancho[]" placeholder="Ancho" required>
                                    <div class="input-group-prepend input-group-append">
                                        <span class="input-group-text">x</span>
                                    </div>
                                    <input type="text" class="form-control" name="largo[]" placeholder="Largo" required>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" name="micraje[]" placeholder="Micraje">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" name="espesor[]" placeholder="Espesor">
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1" name="fuelle[]" placeholder="Fuelle">
                                <input type="text" class="form-control form-control-sm mt-1" name="medida_referencial[]" placeholder="Medida ref.">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm mb-1" name="colores[]" placeholder="Colores" min="0" value="0">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="biodegradable_0" name="biodegradable[]" value="0">
                                    <label class="custom-control-label" for="biodegradable_0">Biodegradable</label>
                                </div>
                            </td>
                            <td>
                                <input type="text" class="form-control cantidad-input" name="cantidad[]" required>
                            </td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">S/</span>
                                    </div>
                                    <input type="text" class="form-control precio-input" name="precio_unitario[]" required>
                                </div>
                            </td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">S/</span>
                                    </div>
                                    <input type="text" class="form-control subtotal-input" readonly>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger eliminar-fila">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-right"><strong>Subtotal:</strong></td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">S/</span>
                                    </div>
                                    <input type="text" class="form-control" id="subtotal_total" readonly>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-right"><strong>IGV (18%):</strong></td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">S/</span>
                                    </div>
                                    <input type="text" class="form-control" id="igv_total" readonly>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-right"><strong>Total:</strong></td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">S/</span>
                                    </div>
                                    <input type="text" class="form-control" id="gran_total" readonly>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <div class="form-group text-right">
        <a href="ordenes.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary" id="guardarOrden">
            <i class="fas fa-save"></i> Guardar Orden
        </button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let filaIndice = 0;
        
        // Agregar producto
        document.getElementById('agregarProducto').addEventListener('click', function() {
            filaIndice++;
            
            const nuevaFila = document.querySelector('#tablaProductos tbody tr').cloneNode(true);
            
            // Actualizar ID del checkbox biodegradable
            const checkboxBiodegradable = nuevaFila.querySelector('input[type="checkbox"]');
            checkboxBiodegradable.id = 'biodegradable_' + filaIndice;
            checkboxBiodegradable.value = filaIndice;
            nuevaFila.querySelector('label.custom-control-label').setAttribute('for', 'biodegradable_' + filaIndice);
            
            // Limpiar valores
            nuevaFila.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => {
                input.value = '';
            });
            
            nuevaFila.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;
            });
            
            nuevaFila.querySelector('input[type="checkbox"]').checked = false;
            
            // Colores por defecto
            nuevaFila.querySelector('input[name="colores[]"]').value = '0';
            
            // Agregar al tbody
            document.querySelector('#tablaProductos tbody').appendChild(nuevaFila);
            
            // Reiniciar eventos en la nueva fila
            inicializarEventos();
        });
        
        // Función para inicializar eventos en todas las filas
        function inicializarEventos() {
            // Eliminar fila
            document.querySelectorAll('.eliminar-fila').forEach(boton => {
                boton.onclick = function() {
                    const filas = document.querySelectorAll('#tablaProductos tbody tr');
                    if (filas.length > 1) {
                        this.closest('tr').remove();
                        calcularTotales();
                    } else {
                        alert('Debe haber al menos un producto en la orden');
                    }
                };
            });
            
            // Auto-rellenar descripción desde material
            document.querySelectorAll('.material-select').forEach(select => {
                select.onchange = function() {
                    const descripcionInput = this.closest('tr').querySelector('input[name="descripcion[]"]');
                    const option = this.options[this.selectedIndex];
                    if (option.dataset.descripcion) {
                        descripcionInput.value = option.dataset.descripcion;
                    }
                };
            });
            
            // Calcular subtotal
            function calcularSubtotal(fila) {
                const cantidad = fila.querySelector('.cantidad-input').value.replace(/,/g, '');
                const precio = fila.querySelector('.precio-input').value.replace(/,/g, '');
                
                if (cantidad && precio) {
                    const subtotal = parseFloat(cantidad) * parseFloat(precio);
                    fila.querySelector('.subtotal-input').value = subtotal.toFixed(2);
                } else {
                    fila.querySelector('.subtotal-input').value = '';
                }
                
                calcularTotales();
            }
            
            // Asignar eventos para cálculo automático
            document.querySelectorAll('.cantidad-input, .precio-input').forEach(input => {
                input.oninput = function() {
                    // Formatear como número con comas
                    this.value = this.value.replace(/[^0-9.]/g, '');
                    
                    calcularSubtotal(this.closest('tr'));
                };
            });
        }
        
        // Calcular totales generales
        function calcularTotales() {
            let subtotalTotal = 0;
            
            document.querySelectorAll('.subtotal-input').forEach(input => {
                if (input.value) {
                    subtotalTotal += parseFloat(input.value);
                }
            });
            
            const igv = subtotalTotal * 0.18;
            const total = subtotalTotal + igv;
            
            document.getElementById('subtotal_total').value = subtotalTotal.toFixed(2);
            document.getElementById('igv_total').value = igv.toFixed(2);
            document.getElementById('gran_total').value = total.toFixed(2);
        }
        
        // Validación de formulario
        document.getElementById('ordenForm').onsubmit = function(e) {
            const filas = document.querySelectorAll('#tablaProductos tbody tr');
            let productosValidos = 0;
            
            filas.forEach(fila => {
                const material = fila.querySelector('select[name="material[]"]').value;
                const descripcion = fila.querySelector('input[name="descripcion[]"]').value;
                const ancho = fila.querySelector('input[name="ancho[]"]').value;
                const largo = fila.querySelector('input[name="largo[]"]').value;
                const cantidad = fila.querySelector('input[name="cantidad[]"]').value;
                const precio = fila.querySelector('input[name="precio_unitario[]"]').value;
                
                if (material && descripcion && ancho && largo && cantidad && precio) {
                    productosValidos++;
                }
            });
            
            if (productosValidos === 0) {
                alert('Debe agregar al menos un producto a la orden con todos los datos requeridos');
                e.preventDefault();
                return false;
            }
            
            return true;
        };
        
        // Inicializar eventos al cargar la página
        inicializarEventos();
    });
</script>

<?php require_once 'includes/footer.php'; ?>