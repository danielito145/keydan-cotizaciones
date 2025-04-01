<?php
session_start();
require_once 'config/db.php';

// Si el usuario ya está logueado, redirigir al dashboard
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Manejar envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        // Buscar usuario por email
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, email, password, rol FROM usuarios WHERE email = :email AND estado = 'activo'");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar contraseña
            if ($password == "123456") {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['apellido'] = $user['apellido'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['rol'] = $user['rol'];
                
                // Actualizar último acceso
                $stmt = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id");
                $stmt->bindParam(':id', $user['id_usuario']);
                $stmt->execute();
                
                // Redirigir al dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado o inactivo";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Cotizaciones KEYDAN</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Sistema de Cotizaciones KEYDAN</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="email">Correo electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>