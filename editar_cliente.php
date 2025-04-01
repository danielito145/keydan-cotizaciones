<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID de cliente
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de cliente no válido.</div>";
    require_once 'includes/footer.php';
    exit;
}

$id_cliente = $_GET['id'];

// Obtener los datos del cliente
try {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = :id_cliente");
    $stmt->bindParam(':id_cliente', $id_cliente);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        echo "<div class='alert alert-danger'>Cliente no encontrado.</div>";
        require_once 'includes/footer.php';
        exit;
    }
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    require_once 'includes/footer.php';
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $razon_social = $_POST['razon_social'];
        $ruc = $_POST['ruc'] ?: null;
        $direccion = $_POST['direccion'] ?: null;
        $telefono = $_POST['telefono'] ?: null;
        $email = $_POST['email'] ?: null;
        $contacto_nombre = $_POST['contacto_nombre'] ?: null;
        $contacto_cargo = $_POST['contacto_cargo'] ?: null;
        $contacto_telefono = $_POST['contacto_telefono'] ?: null;

        $stmt = $conn->prepare("UPDATE clientes SET 
                                razon_social = :razon_social, 
                                ruc = :ruc, 
                                direccion = :direccion, 
                                telefono = :telefono, 
                                email = :email, 
                                contacto_nombre = :contacto_nombre, 
                                contacto_cargo = :contacto_cargo, 
                                contacto_telefono = :contacto_telefono 
                                WHERE id_cliente = :id_cliente");
        $stmt->bindParam(':razon_social', $razon_social);
        $stmt->bindParam(':ruc', $ruc);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':contacto_nombre', $contacto_nombre);
        $stmt->bindParam(':contacto_cargo', $contacto_cargo);
        $stmt->bindParam(':contacto_telefono', $contacto_telefono);
        $stmt->bindParam(':id_cliente', $id_cliente);
        $stmt->execute();

        echo "<script>alert('Cliente actualizado con éxito'); window.location.href = 'clientes.php';</script>";
        exit;
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>Error al actualizar el cliente: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-edit"></i> Editar Cliente</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="clientes.php">Clientes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Editar Cliente</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-user-edit"></i> Datos del Cliente</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="form-group">
                <label for="razon_social">Razón Social:</label>
                <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?php echo htmlspecialchars($cliente['razon_social']); ?>" required>
            </div>
            <div class="form-group">
                <label for="ruc">RUC:</label>
                <input type="text" class="form-control" id="ruc" name="ruc" value="<?php echo htmlspecialchars($cliente['ruc']); ?>" maxlength="11">
            </div>
            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($cliente['direccion']); ?>">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>">
            </div>
            <div class="form-group">
                <label for="contacto_nombre">Nombre del Contacto:</label>
                <input type="text" class="form-control" id="contacto_nombre" name="contacto_nombre" value="<?php echo htmlspecialchars($cliente['contacto_nombre']); ?>">
            </div>
            <div class="form-group">
                <label for="contacto_cargo">Cargo del Contacto:</label>
                <input type="text" class="form-control" id="contacto_cargo" name="contacto_cargo" value="<?php echo htmlspecialchars($cliente['contacto_cargo']); ?>">
            </div>
            <div class="form-group">
                <label for="contacto_telefono">Teléfono del Contacto:</label>
                <input type="text" class="form-control" id="contacto_telefono" name="contacto_telefono" value="<?php echo htmlspecialchars($cliente['contacto_telefono']); ?>">
            </div>
            <div class="row mt-4">
                <div class="col-md-12 text-right">
                    <a href="clientes.php" class="btn btn-secondary mr-2">
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

<?php require_once 'includes/footer.php'; ?>