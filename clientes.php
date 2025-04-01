<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Obtener lista de clientes
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'activo';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

try {
    $query = "SELECT * FROM clientes WHERE 1=1";
    $params = [];

    // Filtrar por estado
    if ($estado_filtro) {
        $query .= " AND estado = :estado";
        $params[':estado'] = $estado_filtro;
    }

    // Filtrar por búsqueda
    if ($busqueda) {
        $query .= " AND (razon_social LIKE :busqueda OR ruc LIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }

    $query .= " ORDER BY razon_social";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-users"></i> Gestión de Clientes</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Clientes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Lista de Clientes</h5>
    </div>
    <div class="card-body">
        <!-- Filtros y buscador -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="buscarCliente">Buscar cliente:</label>
                    <input type="text" class="form-control" id="buscarCliente" placeholder="Razón social o RUC" value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="filtroEstado">Filtrar por estado:</label>
                    <select class="form-control" id="filtroEstado">
                        <option value="" <?php echo $estado_filtro == '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="activo" <?php echo $estado_filtro == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $estado_filtro == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4 text-right">
                <button type="button" class="btn btn-success mt-4" data-toggle="modal" data-target="#modalNuevoCliente">
                    <i class="fas fa-plus"></i> Agregar Cliente
                </button>
            </div>
        </div>

        <!-- Tabla de clientes -->
        <?php if(count($clientes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Razón Social</th>
                            <th>RUC</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['razon_social']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['ruc']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $cliente['estado'] == 'activo' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($cliente['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="editar_cliente.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-<?php echo $cliente['estado'] == 'activo' ? 'warning' : 'success'; ?> btn-cambiar-estado" 
                                            data-id="<?php echo $cliente['id_cliente']; ?>" 
                                            data-estado="<?php echo $cliente['estado']; ?>" 
                                            title="<?php echo $cliente['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fas fa-<?php echo $cliente['estado'] == 'activo' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    <a href="cotizaciones.php?id_cliente=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-info" title="Ver Cotizaciones">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No hay clientes registrados.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para nuevo cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" role="dialog" aria-labelledby="modalNuevoClienteLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNuevoClienteLabel">Agregar Nuevo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
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
                    <div class="form-group">
                        <label for="contacto_nombre">Nombre del Contacto:</label>
                        <input type="text" class="form-control" id="contacto_nombre">
                    </div>
                    <div class="form-group">
                        <label for="contacto_cargo">Cargo del Contacto:</label>
                        <input type="text" class="form-control" id="contacto_cargo">
                    </div>
                    <div class="form-group">
                        <label for="contacto_telefono">Teléfono del Contacto:</label>
                        <input type="text" class="form-control" id="contacto_telefono">
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
document.addEventListener('DOMContentLoaded', function() {
    // Buscador de clientes
    document.getElementById('buscarCliente').addEventListener('keyup', function() {
        const busqueda = this.value;
        const estado = document.getElementById('filtroEstado').value;
        window.location.href = 'clientes.php?busqueda=' + encodeURIComponent(busqueda) + '&estado=' + estado;
    });

    // Filtro por estado
    document.getElementById('filtroEstado').addEventListener('change', function() {
        const estado = this.value;
        const busqueda = document.getElementById('buscarCliente').value;
        window.location.href = 'clientes.php?busqueda=' + encodeURIComponent(busqueda) + '&estado=' + estado;
    });

    // Guardar nuevo cliente
    document.getElementById('btnGuardarCliente').addEventListener('click', function() {
        const razonSocial = document.getElementById('razon_social').value.trim();
        const ruc = document.getElementById('ruc').value.trim();
        const direccion = document.getElementById('direccion').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const email = document.getElementById('email').value.trim();
        const contactoNombre = document.getElementById('contacto_nombre').value.trim();
        const contactoCargo = document.getElementById('contacto_cargo').value.trim();
        const contactoTelefono = document.getElementById('contacto_telefono').value.trim();

        if (!razonSocial) {
            alert('Por favor ingrese la razón social del cliente');
            return;
        }

        if (ruc && ruc.length !== 11) {
            alert('El RUC debe tener 11 dígitos');
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'guardar_cliente.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const respuesta = JSON.parse(this.responseText);
                    if (respuesta.exito) {
                        alert('Cliente añadido con éxito');
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
        xhr.send('razon_social=' + encodeURIComponent(razonSocial) + 
                 '&ruc=' + encodeURIComponent(ruc) + 
                 '&direccion=' + encodeURIComponent(direccion) + 
                 '&telefono=' + encodeURIComponent(telefono) + 
                 '&email=' + encodeURIComponent(email) + 
                 '&contacto_nombre=' + encodeURIComponent(contactoNombre) + 
                 '&contacto_cargo=' + encodeURIComponent(contactoCargo) + 
                 '&contacto_telefono=' + encodeURIComponent(contactoTelefono));
    });

    // Cambiar estado del cliente
    document.querySelectorAll('.btn-cambiar-estado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idCliente = this.getAttribute('data-id');
            const estadoActual = this.getAttribute('data-estado');
            const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';

            if (confirm(`¿Está seguro de que desea ${nuevoEstado === 'activo' ? 'activar' : 'desactivar'} este cliente?`)) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'cambiar_estado_cliente.php', true);
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
                xhr.send('id_cliente=' + idCliente + '&estado=' + nuevoEstado);
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>