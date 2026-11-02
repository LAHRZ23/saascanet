<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/temas.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tema']) && isset($TEMAS[$_POST['tema']])) {
    $nuevoTema = $_POST['tema'];
    $stmt = mysqli_prepare($conexion, "UPDATE tema_config SET tema = ?, actualizado_por = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "si", $nuevoTema, $yo['id']);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Cambió el tema de color de la intranet', $TEMAS[$nuevoTema]['nombre']);
    $exito = 'Listo, la intranet ahora usa el tema "' . $TEMAS[$nuevoTema]['nombre'] . '" para todo el personal.';
}

$temaActual = 'institucional';
$resultado = mysqli_query($conexion, "SELECT tema FROM tema_config WHERE id = 1");
if ($resultado) {
    $fila = mysqli_fetch_assoc($resultado);
    if ($fila && isset($TEMAS[$fila['tema']])) {
        $temaActual = $fila['tema'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Tema de color</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🎨 Tema de color</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a></p>
    <p style="max-width:700px; color:var(--text-muted);">Cambia los colores de toda la intranet para todo el personal, por ejemplo para no verse ligada a un partido en temporada electoral, o para una fecha señalada como Independencia, Día de Muertos o Navidad. El cambio aplica de inmediato, sin tocar ningún contenido.</p>

    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:700px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <form method="post">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; max-width:960px; margin-top:10px;">
            <?php foreach ($TEMAS as $clave => $t): ?>
                <label style="display:block; cursor:pointer; background:var(--card-bg); border-radius:14px; padding:16px; box-shadow:var(--shadow); border:3px solid <?= $clave === $temaActual ? $t['vars']['--guinda'] : 'transparent' ?>;">
                    <input type="radio" name="tema" value="<?= $clave ?>" <?= $clave === $temaActual ? 'checked' : '' ?> onchange="this.form.submit()" style="width:auto; margin-bottom:10px;">
                    <div style="display:flex; gap:6px; margin-bottom:10px;">
                        <span style="width:28px; height:28px; border-radius:50%; background:<?= $t['vars']['--guinda'] ?>; display:inline-block;" title="Botones"></span>
                        <span style="width:28px; height:28px; border-radius:50%; background:<?= $t['vars']['--dorado'] ?>; display:inline-block;" title="Insignias"></span>
                        <?php if ($t['vars']['--titulo'] !== $t['vars']['--guinda']): ?>
                            <span style="width:28px; height:28px; border-radius:50%; background:<?= $t['vars']['--titulo'] ?>; display:inline-block;" title="Títulos"></span>
                        <?php endif; ?>
                        <span style="width:28px; height:28px; border-radius:50%; background:<?= $t['vars']['--bg'] ?>; border:1px solid var(--border); display:inline-block;" title="Fondo"></span>
                    </div>
                    <strong><?= $t['icono'] ?> <?= htmlspecialchars($t['nombre']) ?></strong>
                    <?php if ($clave === $temaActual): ?>
                        <p style="margin:6px 0 0; font-size:12px; color:var(--text-muted); font-weight:700;">🟢 Activo ahora</p>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
