<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Obtener lista de proveedores para el filtro
try {
    $stmt = $conn->query("SELECT id_proveedor, razon_social FROM proveedores WHERE estado = 'activo' ORDER BY razon_social");
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Obtener el proveedor seleccionado (si existe)
$proveedor_filtro = isset($_GET['proveedor']) ? $_GET['proveedor'] : '';

// Obtener lista de materiales
try {
    $query = "SELECT * FROM materiales ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener precios de materiales según el proveedor seleccionado
    $precios = [];
    if ($proveedor_filtro) {
        $stmt = $conn->prepare("SELECT id_material, precio, moneda, fecha_vigencia 
                                FROM precios_materiales 
                                WHERE id_proveedor = :id_proveedor AND estado = 'activo'");
        $stmt->bindParam(':id_proveedor', $proveedor_filtro);
        $stmt->execute();
        $precios_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($precios_result as $precio) {
            $precios[$precio['id_material']] = $precio;
        }
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-boxes"></i> Gestión de Materiales</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Materiales</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Lista de Materiales</h5>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="filtroProveedor">Filtrar por proveedor:</label>
                    <select class="form-control" id="filtroProveedor" name="proveedor">
                        <option value="">Todos los proveedores</option>
                        <?php foreach($proveedores as $proveedor): ?>
                            <option value="<?php echo $proveedor['id_proveedor']; ?>" <?php echo $proveedor_filtro == $proveedor['id_proveedor'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proveedor['razon_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-8 text-right">
                <button type="button" class="btn btn-success mt-4" data-toggle="modal" data-target="#modalNuevoMaterial">
                    <i class="fas fa-plus"></i> Agregar Material
                </button>
            </div>
        </div>

        <!-- Tabla de materiales -->
        <?php if(count($materiales) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Proveedor</th>
                            <th>Ubicación del Proveedor</th>
                            <th>Estado</th>
                            <th>Precios</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($materiales as $material): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($material['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($material['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($material['color']); ?></td>
                                <td>
                                    <?php if ($proveedor_filtro && isset($precios[$material['id_material']])): ?>
                                        <?php 
                                        $stmt = $conn->prepare("SELECT razon_social FROM proveedores WHERE id_proveedor = :id_proveedor");
                                        $stmt->bindParam(':id_proveedor', $proveedor_filtro);
                                        $stmt->execute();
                                        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo htmlspecialchars($proveedor['razon_social']);
                                        ?>
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($proveedor_filtro && isset($precios[$material['id_material']])): ?>
                                        <?php 
                                        $stmt = $conn->prepare("SELECT direccion FROM proveedores WHERE id_proveedor = :id_proveedor");
                                        $stmt->bindParam(':id_proveedor', $proveedor_filtro);
                                        $stmt->execute();
                                        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo htmlspecialchars($proveedor['direccion']);
                                        ?>
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $material['estado'] == 'activo' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($material['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($proveedor_filtro && isset($precios[$material['id_material']])): ?>
                                        <?php echo htmlspecialchars($precios[$material['id_material']]['precio']) . ' ' . $precios[$material['id_material']]['moneda']; ?>
                                        <br><small>(Vigente hasta: <?php echo date('d/m/Y', strtotime($precios[$material['id_material']]['fecha_vigencia'])); ?>)</small>
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar_material.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-<?php echo $material['estado'] == 'activo' ? 'warning' : 'success'; ?> btn-cambiar-estado" 
                                            data-id="<?php echo $material['id_material']; ?>" 
                                            data-estado="<?php echo $material['estado']; ?>" 
                                            title="<?php echo $material['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fas fa-<?php echo $material['estado'] == 'activo' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info btn-gestionar-precios" 
                                            data-id="<?php echo $material['id_material']; ?>" 
                                            data-nombre="<?php echo htmlspecialchars($material['nombre']); ?>" 
                                            title="Gestionar Precios">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No hay materiales registrados.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para nuevo material -->
<div class="modal fade" id="modalNuevoMaterial" tabindex="-1" role="dialog" aria-labelledby="modalNuevoMaterialLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNuevoMaterialLabel">Agregar Nuevo Material</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formNuevoMaterial">
                    <div class="form-group">
                        <label for="codigo">Código:</label>
                        <input type="text" class="form-control" id="codigo" required>
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" class="form-control" id="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Tipo:</label>
                        <select class="form-control" id="tipo" required>
                            <option value="">Seleccione un tipo</option>
                            <option value="R1">R1</option>
                            <option value="R2">R2</option>
                            <option value="Virgen">Virgen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="color">Color:</label>
                        <select class="form-control" id="color" required>
                            <option value="">Seleccione un color</option>
                            <option value="Negro">Negro</option>
                            <option value="Colores">Colores</option>
                            <option value="Transparente">Transparente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción:</label>
                        <textarea class="form-control" id="descripcion" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarMaterial">Guardar Material</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para gestionar precios -->
<div class="modal fade" id="modalGestionarPrecios" tabindex="-1" role="dialog" aria-labelledby="modalGestionarPreciosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGestionarPreciosLabel">Gestionar Precios de Material</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="id_material_precios">
                <div class="row mb-3">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-success" id="btnAgregarPrecio">
                            <i class="fas fa-plus"></i> Agregar Precio
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="tablaPrecios">
                        <thead class="thead-light">
                            <tr>
                                <th>Proveedor</th>
                                <th>Precio</th>
                                <th>Moneda</th>
                                <th>Fecha de Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar precio -->
<div class="modal fade" id="modalPrecioMaterial" tabindex="-1" role="dialog" aria-labelledby="modalPrecioMaterialLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPrecioMaterialLabel">Agregar/Editar Precio</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formPrecioMaterial">
                    <input type="hidden" id="id_precio">
                    <input type="hidden" id="id_material_precio">
                    <div class="form-group">
                        <label for="id_proveedor_precio">Proveedor:</label>
                        <select class="form-control" id="id_proveedor_precio" required>
                            <option value="">Seleccione un proveedor</option>
                            <?php foreach($proveedores as $proveedor): ?>
                                <option value="<?php echo $proveedor['id_proveedor']; ?>">
                                    <?php echo htmlspecialchars($proveedor['razon_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="precio">Precio:</label>
                        <input type="number" step="0.01" class="form-control" id="precio" required>
                    </div>
                    <div class="form-group">
                        <label for="moneda">Moneda:</label>
                        <select class="form-control" id="moneda" required>
                            <option value="PEN">PEN</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_vigencia">Fecha de Vigencia:</label>
                        <input type="date" class="form-control" id="fecha_vigencia" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarPrecio">Guardar Precio</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtro por proveedor
    document.getElementById('filtroProveedor').addEventListener('change', function() {
        const proveedor = this.value;
        window.location.href = 'materiales.php?proveedor=' + encodeURIComponent(proveedor);
    });

    // Guardar nuevo material
    document.getElementById('btnGuardarMaterial').addEventListener('click', function() {
        const codigo = document.getElementById('codigo').value.trim();
        const nombre = document.getElementById('nombre').value.trim();
        const tipo = document.getElementById('tipo').value;
        const color = document.getElementById('color').value;
        const descripcion = document.getElementById('descripcion').value.trim();

        if (!codigo || !nombre || !tipo || !color) {
            alert('Por favor complete todos los campos obligatorios');
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'guardar_material.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const respuesta = JSON.parse(this.responseText);
                    if (respuesta.exito) {
                        alert('Material añadido con éxito');
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
        xhr.send('codigo=' + encodeURIComponent(codigo) + 
                 '&nombre=' + encodeURIComponent(nombre) + 
                 '&tipo=' + encodeURIComponent(tipo) + 
                 '&color=' + encodeURIComponent(color) + 
                 '&descripcion=' + encodeURIComponent(descripcion));
    });

    // Cambiar estado del material
    document.querySelectorAll('.btn-cambiar-estado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idMaterial = this.getAttribute('data-id');
            const estadoActual = this.getAttribute('data-estado');
            const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';

            if (confirm(`¿Está seguro de que desea ${nuevoEstado === 'activo' ? 'activar' : 'desactivar'} este material?`)) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'cambiar_estado_material.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const respuesta = JSON.parse(this.responseText);
                            if (respuesta.exito) {
                                alert('Estado actualizado con éxito');
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
                xhr.send('id_material=' + idMaterial + '&estado=' + nuevoEstado);
            }
        });
    });

    // Gestionar precios
    document.querySelectorAll('.btn-gestionar-precios').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idMaterial = this.getAttribute('data-id');
            const nombreMaterial = this.getAttribute('data-nombre');
            document.getElementById('modalGestionarPreciosLabel').innerText = 'Gestionar Precios de ' + nombreMaterial;
            document.getElementById('id_material_precios').value = idMaterial;

            // Cargar precios del material
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'obtener_precios_material.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const precios = JSON.parse(this.responseText);
                        const tbody = document.getElementById('tablaPrecios').getElementsByTagName('tbody')[0];
                        tbody.innerHTML = '';
                        precios.forEach(function(precio) {
                            const row = tbody.insertRow();
                            row.insertCell(0).innerHTML = precio.razon_social;
                            row.insertCell(1).innerHTML = precio.precio;
                            row.insertCell(2).innerHTML = precio.moneda;
                            row.insertCell(3).innerHTML = new Date(precio.fecha_vigencia).toLocaleDateString('es-ES');
                            row.insertCell(4).innerHTML = '<span class="badge ' + (precio.estado == 'activo' ? 'badge-success' : 'badge-danger') + '">' + precio.estado.charAt(0).toUpperCase() + precio.estado.slice(1) + '</span>';
                            const accionesCell = row.insertCell(5);
                            accionesCell.innerHTML = `
                                <button type="button" class="btn btn-sm btn-primary btn-editar-precio mr-1" 
                                        data-id="${precio.id_precio}" 
                                        data-id_proveedor="${precio.id_proveedor}" 
                                        data-precio="${precio.precio}" 
                                        data-moneda="${precio.moneda}" 
                                        data-fecha_vigencia="${precio.fecha_vigencia}" 
                                        title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-${precio.estado == 'activo' ? 'warning' : 'success'} btn-cambiar-estado-precio" 
                                        data-id="${precio.id_precio}" 
                                        data-estado="${precio.estado}" 
                                        title="${precio.estado == 'activo' ? 'Desactivar' : 'Activar'}">
                                    <i class="fas fa-${precio.estado == 'activo' ? 'ban' : 'check'}"></i>
                                </button>
                            `;
                        });

                        // Evento para editar precio
                        document.querySelectorAll('.btn-editar-precio').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                document.getElementById('modalPrecioMaterialLabel').innerText = 'Editar Precio';
                                document.getElementById('id_precio').value = this.getAttribute('data-id');
                                document.getElementById('id_material_precio').value = idMaterial;
                                document.getElementById('id_proveedor_precio').value = this.getAttribute('data-id_proveedor');
                                document.getElementById('precio').value = this.getAttribute('data-precio');
                                document.getElementById('moneda').value = this.getAttribute('data-moneda');
                                document.getElementById('fecha_vigencia').value = this.getAttribute('data-fecha_vigencia');
                                $('#modalPrecioMaterial').modal('show');
                            });
                        });

                        // Evento para cambiar estado de precio
                        document.querySelectorAll('.btn-cambiar-estado-precio').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                const idPrecio = this.getAttribute('data-id');
                                const estadoActual = this.getAttribute('data-estado');
                                const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';

                                if (confirm(`¿Está seguro de que desea ${nuevoEstado === 'activo' ? 'activar' : 'desactivar'} este precio?`)) {
                                    const xhr = new XMLHttpRequest();
                                    xhr.open('POST', 'cambiar_estado_precio.php', true);
                                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                    xhr.onload = function() {
                                        if (this.status === 200) {
                                            try {
                                                const respuesta = JSON.parse(this.responseText);
                                                if (respuesta.exito) {
                                                    alert('Estado actualizado con éxito');
                                                    document.querySelector('.btn-gestionar-precios[data-id="' + idMaterial + '"]').click();
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
                                    xhr.send('id_precio=' + idPrecio + '&estado=' + nuevoEstado);
                                }
                            });
                        });
                    } catch (e) {
                        alert('Error al procesar la respuesta del servidor');
                        console.error(e);
                    }
                } else {
                    alert('Error en la solicitud');
                }
            };
            xhr.send('id_material=' + idMaterial);

            $('#modalGestionarPrecios').modal('show');
        });
    });

    // Agregar nuevo precio
    document.getElementById('btnAgregarPrecio').addEventListener('click', function() {
        document.getElementById('modalPrecioMaterialLabel').innerText = 'Agregar Precio';
        document.getElementById('id_precio').value = '';
        document.getElementById('id_material_precio').value = document.getElementById('id_material_precios').value;
        document.getElementById('id_proveedor_precio').value = '';
        document.getElementById('precio').value = '';
        document.getElementById('moneda').value = 'PEN';
        document.getElementById('fecha_vigencia').value = '';
        $('#modalPrecioMaterial').modal('show');
    });

    // Guardar precio
    document.getElementById('btnGuardarPrecio').addEventListener('click', function() {
        const idPrecio = document.getElementById('id_precio').value;
        const idMaterial = document.getElementById('id_material_precio').value;
        const idProveedor = document.getElementById('id_proveedor_precio').value;
        const precio = document.getElementById('precio').value;
        const moneda = document.getElementById('moneda').value;
        const fechaVigencia = document.getElementById('fecha_vigencia').value;

        if (!idProveedor || !precio || !moneda || !fechaVigencia) {
            alert('Por favor complete todos los campos obligatorios');
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'guardar_precio_material.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const respuesta = JSON.parse(this.responseText);
                    if (respuesta.exito) {
                        alert('Precio guardado con éxito');
                        $('#modalPrecioMaterial').modal('hide');
                        document.querySelector('.btn-gestionar-precios[data-id="' + idMaterial + '"]').click();
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
        xhr.send('id_precio=' + encodeURIComponent(idPrecio) + 
                 '&id_material=' + idMaterial + 
                 '&id_proveedor=' + idProveedor + 
                 '&precio=' + precio + 
                 '&moneda=' + encodeURIComponent(moneda) + 
                 '&fecha_vigencia=' + encodeURIComponent(fechaVigencia));
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>