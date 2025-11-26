<?php
/**
 * Página de Login - Sistema ICASE
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

iniciarSesion();

// Si ya está logueado, redirigir al dashboard correspondiente
if (estaLogueado()) {
    if (esFacilitador()) {
        redirigir(BASE_URL . 'modules/dashboard/facilitador.php');
    } else {
        redirigir(BASE_URL . 'modules/dashboard/socio.php');
    }
}

$error = '';
$mensaje = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $tipo_login = $_POST['tipo_login'] ?? '';

    if ($tipo_login === 'facilitador') {
        // Login de Facilitador
        $usuario = limpiar($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';
        $contrasena_md5 = md5($contrasena);

        $stmt = $db->prepare("SELECT id, nombre, tipo FROM usuarios WHERE nombre = ? AND contrasena = ? AND tipo = 'facilitador' AND activo = 1");
        $stmt->bind_param("ss", $usuario, $contrasena_md5);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['tipo_usuario'] = 'facilitador';
            $_SESSION['nombre_usuario'] = $user['nombre'];
            redirigir(BASE_URL . 'modules/dashboard/facilitador.php');
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } elseif ($tipo_login === 'socio') {
        // Login de Socio
        $empresa = limpiar($_POST['empresa'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';
        $contrasena_md5 = md5($contrasena);

        $stmt = $db->prepare("
            SELECT u.id, u.nombre, u.tipo, s.id as socio_id, s.nombre_empresa
            FROM usuarios u
            INNER JOIN socios s ON s.usuario_id = u.id
            WHERE u.nombre = ? AND u.contrasena = ? AND u.tipo = 'socio' AND u.activo = 1
        ");
        $stmt->bind_param("ss", $empresa, $contrasena_md5);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['socio_id'] = $user['socio_id'];
            $_SESSION['tipo_usuario'] = 'socio';
            $_SESSION['nombre_usuario'] = $user['nombre_empresa'];
            redirigir(BASE_URL . 'modules/dashboard/socio.php');
        } else {
            $error = 'Empresa o contraseña incorrectos';
        }
    }
}

// Obtener lista de socios para el dropdown
$db = Database::getInstance();
$socios_result = $db->query("SELECT nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
$socios = [];
while ($row = $socios_result->fetch_assoc()) {
    $socios[] = $row['nombre_empresa'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema ICASE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Sistema de Gestión de Inspecciones</h1>
            <p class="subtitle">ICASE - Índice de Cumplimiento Ambiental</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="login-options">
            <!-- Opción 1: Login como Facilitador -->
            <div class="login-card">
                <h2>Acceso Facilitador</h2>
                <form method="POST" action="">
                    <input type="hidden" name="tipo_login" value="facilitador">

                    <div class="form-group">
                        <label for="usuario_facilitador">Usuario</label>
                        <input type="text" id="usuario_facilitador" name="usuario" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="contrasena_facilitador">Contraseña</label>
                        <input type="password" id="contrasena_facilitador" name="contrasena" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Ingresar como Facilitador</button>
                </form>
            </div>

            <!-- Opción 2: Login como Socio -->
            <div class="login-card">
                <h2>Acceso Socio</h2>
                <form method="POST" action="">
                    <input type="hidden" name="tipo_login" value="socio">

                    <div class="form-group">
                        <label for="empresa">Seleccione su Empresa</label>
                        <select id="empresa" name="empresa" class="form-control" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($socios as $socio): ?>
                                <option value="<?php echo htmlspecialchars($socio); ?>"><?php echo htmlspecialchars($socio); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="contrasena_socio">Contraseña</label>
                        <input type="password" id="contrasena_socio" name="contrasena" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">Ingresar como Socio</button>
                </form>
            </div>
        </div>

        <div class="login-footer">
            <p><small>Contraseña por defecto: 123456</small></p>
        </div>
    </div>
</body>
</html>
