<?php
require_once __DIR__ . '/includes/sesion.php';

// Si ya hay una sesión activa, no tiene caso ver el login otra vez.
if (usuarioActual()) {
    header('Location: home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conexion, "SELECT id, nombre, usuario, password_hash, rol, debe_cambiar_password FROM usuarios WHERE usuario = ? AND activo = 1");
    mysqli_stmt_bind_param($stmt, "s", $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);

    if ($fila && password_verify($password, $fila['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $fila['id'];
        $_SESSION['usuario_nombre'] = $fila['nombre'];
        $_SESSION['usuario_login'] = $fila['usuario'];
        $_SESSION['usuario_rol'] = $fila['rol'];
        $_SESSION['debe_cambiar_password'] = (bool) $fila['debe_cambiar_password'];
        header('Location: home.php');
        exit;
    }

    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Intranet SAASCAEM - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body class="login-page">
    <div class="login-card">
        <img src="img/logo.png" alt="Logo SAASCAEM" class="logo">
        <h2>INTRANET SAASCAEM</h2>
        <?php if ($error): ?>
            <div class="login-error" style="display:block;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <div class="campo">
                <label for="username">Usuario</label>
                <input id="username" name="username" type="text" autocomplete="username" required>
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <div class="campo-password-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="btn-mostrar-password" onclick="toggleMostrarPassword('password', this)">👁</button>
                </div>
            </div>
            <button type="submit" class="btn-primario">Ingresar</button>
        </form>
    </div>
    <script src="js/nav.js?v=3"></script>
</body>
</html>
