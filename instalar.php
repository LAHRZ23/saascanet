<?php
// Instalador de un solo uso: crea la primera cuenta de administrador.
// Ábrelo una vez desde el navegador (http://localhost/.../instalar.php),
// crea tu cuenta, y después ya no se puede volver a usar (por seguridad,
// se desactiva solo en cuanto exista al menos un usuario).
require_once __DIR__ . '/con_db.php';

$resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM usuarios");
$fila = mysqli_fetch_assoc($resultado);
$yaHayUsuarios = $fila['total'] > 0;

$error = '';
$exito = '';

if (!$yaHayUsuarios && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $usuario === '' || $password === '') {
        $error = 'Completa nombre, usuario y contraseña.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, usuario, correo, password_hash, rol) VALUES (?, ?, ?, ?, 'admin')");
        mysqli_stmt_bind_param($stmt, "ssss", $nombre, $usuario, $correo, $hash);
        if (mysqli_stmt_execute($stmt)) {
            $exito = 'Cuenta de administrador creada. Ya puedes iniciar sesión.';
            $yaHayUsuarios = true;
        } else {
            $error = 'No se pudo crear la cuenta (¿el usuario ya existe?).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Instalación - SAASCANET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body class="login-page">
    <div class="login-card">
        <img src="img/logo.png" alt="Logo SAASCAEM" class="logo">
        <h2>Instalación inicial</h2>

        <?php if ($yaHayUsuarios): ?>
            <?php if ($exito): ?>
                <p style="color: var(--dorado-dark); font-weight: bold;"><?= htmlspecialchars($exito) ?></p>
            <?php endif; ?>
            <p style="font-size: 13px; color: var(--text-muted);">Ya existe al menos un usuario registrado, así que por seguridad este instalador está desactivado.</p>
            <a href="index.php" class="btn-accion" style="display:block; margin-top:14px;">Ir al inicio de sesión</a>
        <?php else: ?>
            <p class="sub" style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Crea la primera cuenta de administrador de SAASCANET. Solo puedes hacer esto una vez.</p>
            <?php if ($error): ?>
                <div class="login-error" style="display:block;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="usuario">Usuario para iniciar sesión</label>
                    <input id="usuario" name="usuario" type="text" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="correo">Correo (opcional)</label>
                    <input id="correo" name="correo" type="email" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="password">Contraseña (mínimo 8 caracteres)</label>
                    <input id="password" name="password" type="password" required minlength="8">
                </div>
                <button type="submit" class="btn-primario">Crear cuenta de administrador</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
