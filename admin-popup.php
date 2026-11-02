<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'quitar') {
        mysqli_query($conexion, "UPDATE popup_aviso SET activo = 0 WHERE id = 1");
        registrarBitacora($conexion, 'Quitó el aviso emergente de inicio');
        $exito = 'Se quitó el aviso emergente del inicio.';
    } elseif ($accion === 'borrar_contenido') {
        $actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT imagen FROM popup_aviso WHERE id = 1"));
        if ($actual && !empty($actual['imagen'])) {
            @unlink(__DIR__ . '/uploads/comunicados/' . $actual['imagen']);
        }
        mysqli_query($conexion, "UPDATE popup_aviso SET activo = 0, titulo = NULL, imagen = NULL, enlace = NULL, texto_boton = NULL WHERE id = 1");
        registrarBitacora($conexion, 'Borró el contenido del aviso emergente');
        $exito = 'Se borró todo el contenido del aviso. El formulario está en blanco, listo para capturar uno nuevo cuando lo necesites.';
    } elseif ($accion === 'guardar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $enlace = trim($_POST['enlace'] ?? '');
        $textoBoton = trim($_POST['texto_boton'] ?? '') ?: '➭ MÁS INFORMACIÓN';

        if ($titulo === '') {
            $error = 'El título es obligatorio.';
        } else {
            // Imagen actual (por si no se sube una nueva)
            $actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT imagen FROM popup_aviso WHERE id = 1"));
            $nombreImagen = $actual['imagen'] ?? null;

            if (!empty($_FILES['imagen']['name'])) {
                $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                $tamañoMax = 5 * 1024 * 1024;

                if (!in_array($extension, $permitidas, true)) {
                    $error = 'La imagen debe ser jpg, png, gif o webp.';
                } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
                    $error = 'La imagen no puede pesar más de 5 MB.';
                } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
                } else {
                    $nombreImagen = uniqid('popup_', true) . '.' . $extension;
                    $destino = __DIR__ . '/uploads/comunicados/' . $nombreImagen;
                    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                        $error = 'No se pudo guardar la imagen.';
                    }
                }
            }

            if (!$error) {
                $stmt = mysqli_prepare($conexion, "UPDATE popup_aviso SET activo = 1, titulo = ?, imagen = ?, enlace = ?, texto_boton = ?, actualizado_por = ?, actualizado_en = NOW() WHERE id = 1");
                mysqli_stmt_bind_param($stmt, "ssssi", $titulo, $nombreImagen, $enlace, $textoBoton, $yo['id']);
                mysqli_stmt_execute($stmt);
                registrarBitacora($conexion, 'Publicó/actualizó el aviso emergente de inicio', $titulo);
                $exito = 'Aviso emergente publicado. Ya se muestra en el inicio.';
            }
        }
    }
}

$aviso = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM popup_aviso WHERE id = 1"));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Aviso de inicio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📣 Aviso emergente de inicio</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="home.php" class="enlace-ver">Ver inicio</a></p>
    <p style="max-width:600px; color:var(--text-muted);">Este aviso aparece como una ventana emergente en el inicio para todo el personal, útil para avisos importantes o eventos puntuales. Si no hay nada urgente, déjalo desactivado.</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <p style="max-width:600px;">Estado actual: <strong><?= $aviso['activo'] ? '🟢 Activo (se está mostrando)' : '🔴 Desactivado (no se muestra nada)' ?></strong></p>

    <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
        <input type="hidden" name="accion" value="guardar">
        <div class="campo">
            <label for="titulo">Título</label>
            <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($aviso['titulo'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="imagen">Imagen (opcional, máx. 5 MB)</label>
            <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
            <?php if (!empty($aviso['imagen'])): ?>
                <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">Imagen actual: <?= htmlspecialchars($aviso['imagen']) ?> (sube otra para reemplazarla)</p>
            <?php endif; ?>
        </div>
        <div class="campo">
            <label for="enlace">Enlace al dar clic en el botón (opcional, ej. comunicados.php)</label>
            <input id="enlace" name="enlace" type="text" value="<?= htmlspecialchars($aviso['enlace'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="texto_boton">Texto del botón</label>
            <input id="texto_boton" name="texto_boton" type="text" value="<?= htmlspecialchars($aviso['texto_boton'] ?? '➭ MÁS INFORMACIÓN') ?>">
        </div>
        <button type="submit" class="btn-primario">Publicar / actualizar aviso</button>
    </form>

    <p style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($aviso['activo']): ?>
            <form method="post" onsubmit="return confirm('¿Quitar el aviso emergente del inicio?');">
                <input type="hidden" name="accion" value="quitar">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Quitar aviso (dejar de mostrarlo)</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($aviso['titulo']) || !empty($aviso['imagen']) || !empty($aviso['enlace'])): ?>
            <form method="post" onsubmit="return confirm('¿Borrar TODO el contenido del aviso (título, imagen y enlace)? Esto no se puede deshacer.');">
                <input type="hidden" name="accion" value="borrar_contenido">
                <button type="submit" class="btn-peligro">🗑 Borrar formulario</button>
            </form>
        <?php endif; ?>
    </p>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
