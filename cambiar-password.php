<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

$forzado = !empty($_SESSION['debe_cambiar_password']);
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    $stmt = mysqli_prepare($conexion, "SELECT password_hash FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $yo['id']);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$fila || !password_verify($actual, $fila['password_hash'])) {
        $error = 'Tu contraseña actual no es correcta.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt2 = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "si", $hash, $yo['id']);
        mysqli_stmt_execute($stmt2);
        $_SESSION['debe_cambiar_password'] = false;
        registrarBitacora($conexion, 'Cambió su propia contraseña');

        if ($forzado) {
            header('Location: home.php');
            exit;
        }
        $exito = 'Tu contraseña se actualizó correctamente.';
        $forzado = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Cambiar contraseña</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body<?= $forzado ? ' class="login-page"' : '' ?>>

<?php if ($forzado): ?>
    <div class="login-card">
        <img src="img/logo.png" alt="Logo SAASCAEM" class="logo">
        <h2>Establece tu contraseña</h2>
        <p style="font-size:13.5px; color:var(--text-muted); margin-top:-8px;">Por seguridad, antes de continuar debes cambiar la contraseña temporal que te dieron por una propia.</p>
<?php else: ?>
    <div id="nav-placeholder"></div>
    <div class="main-content">
    <h1>🔑 Cambiar mi contraseña</h1>
    <p><a href="home.php" class="enlace-volver">← Volver al inicio</a></p>
    <div class="login-card" style="margin:20px auto;">
<?php endif; ?>

        <?php if ($error): ?>
            <div class="login-error" style="display:block;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="aviso-importante"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="campo">
                <label for="actual"><?= $forzado ? 'Contraseña temporal (la que te dieron)' : 'Contraseña actual' ?></label>
                <div class="campo-password-wrap">
                    <input id="actual" name="actual" type="password" autocomplete="current-password" required>
                    <button type="button" class="btn-mostrar-password" onclick="toggleMostrarPassword('actual', this)">👁</button>
                </div>
            </div>
            <div class="campo">
                <label for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
                <div class="campo-password-wrap">
                    <input id="nueva" name="nueva" type="password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="btn-mostrar-password" onclick="toggleMostrarPassword('nueva', this)">👁</button>
                </div>
            </div>
            <div class="campo">
                <label for="confirmar">Confirma tu nueva contraseña</label>
                <div class="campo-password-wrap">
                    <input id="confirmar" name="confirmar" type="password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="btn-mostrar-password" onclick="toggleMostrarPassword('confirmar', this)">👁</button>
                </div>
            </div>
            <button type="submit" class="btn-primario">Guardar nueva contraseña</button>
        </form>

        <?php if ($forzado): ?>
            <p style="margin-top:16px; font-size:13px;"><a href="logout.php">Cerrar sesión</a></p>
        <?php endif; ?>

<?php if ($forzado): ?>
    </div>
<?php else: ?>
    </div>
    </div>
<?php endif; ?>

<?php if (!$forzado): ?>
<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
<?php else: ?>
<script src="js/nav.js?v=3"></script>
<?php endif; ?>
</body>
</html>
