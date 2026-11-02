<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$error = '';
$exito = '';
// Si se abrió desde "Publicar esto" en Solicitudes, aquí viene la solicitud.
$solPublicar = solicitudCargarParaPublicar($conexion);
$solicitudPublicada = false;

function subirArchivo($campo, $carpeta, $permitidas, $tamañoMaxMb, &$error) {
    if (empty($_FILES[$campo]['name'])) {
        return null;
    }
    $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    $tamañoMax = $tamañoMaxMb * 1024 * 1024;

    if (!in_array($extension, $permitidas, true)) {
        $error = 'El archivo de "' . $campo . '" debe ser: ' . implode(', ', $permitidas) . '.';
        return null;
    }
    if ($_FILES[$campo]['size'] > $tamañoMax) {
        $error = 'El archivo de "' . $campo . '" no puede pesar más de ' . $tamañoMaxMb . ' MB.';
        return null;
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ocurrió un error al subir el archivo de "' . $campo . '".';
        return null;
    }
    $nombre = uniqid('extra_', true) . '.' . $extension;
    $destino = __DIR__ . '/uploads/' . $carpeta . '/' . $nombre;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
        $error = 'No se pudo guardar el archivo de "' . $campo . '".';
        return null;
    }
    return $nombre;
}

$actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM pagina_extra WHERE id = 1"));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar') {
    mysqli_query($conexion, "UPDATE pagina_extra SET activa = 0 WHERE id = 1");
    registrarBitacora($conexion, 'Desactivó la página adicional');
    $exito = 'Se quitó la página adicional del menú y del inicio.';
    $actual['activa'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_contenido') {
    foreach (['imagen1', 'imagen2', 'video'] as $campo) {
        if (!empty($actual[$campo])) {
            @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual[$campo]);
        }
    }
    mysqli_query($conexion, "UPDATE pagina_extra SET activa = 0, titulo = NULL, texto = NULL, imagen1 = NULL, imagen2 = NULL, video = NULL, enlace1_texto = NULL, enlace1_url = NULL, enlace2_texto = NULL, enlace2_url = NULL WHERE id = 1");
    registrarBitacora($conexion, 'Borró el contenido de la página adicional');
    $exito = 'Se borró todo el contenido. El formulario está en blanco, listo para capturar algo nuevo cuando lo necesites.';
    $actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM pagina_extra WHERE id = 1"));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? '') ?: null;
    $enlace1Texto = trim($_POST['enlace1_texto'] ?? '') ?: null;
    $enlace1Url = trim($_POST['enlace1_url'] ?? '') ?: null;
    $enlace2Texto = trim($_POST['enlace2_texto'] ?? '') ?: null;
    $enlace2Url = trim($_POST['enlace2_url'] ?? '') ?: null;

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        $imagenesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoPermitidos = ['mp4', 'webm', 'mov', 'ogg'];

        $imagen1 = subirArchivo('imagen1', 'pagina_extra', $imagenesPermitidas, 5, $error);
        $imagen2 = $error ? null : subirArchivo('imagen2', 'pagina_extra', $imagenesPermitidas, 5, $error);
        $video = $error ? null : subirArchivo('video', 'pagina_extra', $videoPermitidos, 80, $error);
        if (!$error && $solPublicar && $imagen1 === null && !empty($_POST['usar_imagen_solicitud'])) {
            $imagen1 = solicitudCopiarArchivo($solPublicar, 'imagen', __DIR__ . '/uploads/pagina_extra', 'extra_');
        }
        if (!$error && $solPublicar && $imagen2 === null && !empty($_POST['usar_imagen2_solicitud'])) {
            $imagen2 = solicitudCopiarArchivo($solPublicar, 'imagen2', __DIR__ . '/uploads/pagina_extra', 'extra_');
        }
        if (!$error && $solPublicar && $video === null && !empty($_POST['usar_video_solicitud'])) {
            $video = solicitudCopiarArchivo($solPublicar, 'video', __DIR__ . '/uploads/pagina_extra', 'extra_');
        }

        if (!$error) {
            // Si no se subió un archivo nuevo, conserva el que ya había,
            // salvo que se haya marcado la casilla de "quitar".
            $imagen1Final = !empty($_POST['quitar_imagen1']) ? null : ($imagen1 ?? $actual['imagen1']);
            $imagen2Final = !empty($_POST['quitar_imagen2']) ? null : ($imagen2 ?? $actual['imagen2']);
            $videoFinal = !empty($_POST['quitar_video']) ? null : ($video ?? $actual['video']);

            // Borra del disco los archivos reemplazados o quitados
            if ($imagen1 && $actual['imagen1']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['imagen1']);
            if (!empty($_POST['quitar_imagen1']) && $actual['imagen1']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['imagen1']);
            if ($imagen2 && $actual['imagen2']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['imagen2']);
            if (!empty($_POST['quitar_imagen2']) && $actual['imagen2']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['imagen2']);
            if ($video && $actual['video']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['video']);
            if (!empty($_POST['quitar_video']) && $actual['video']) @unlink(__DIR__ . '/uploads/pagina_extra/' . $actual['video']);

            $stmt = mysqli_prepare($conexion, "UPDATE pagina_extra SET activa = 1, titulo = ?, texto = ?, imagen1 = ?, imagen2 = ?, video = ?, enlace1_texto = ?, enlace1_url = ?, enlace2_texto = ?, enlace2_url = ?, actualizado_por = ? WHERE id = 1");
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssi",
                $titulo, $texto, $imagen1Final, $imagen2Final, $videoFinal,
                $enlace1Texto, $enlace1Url, $enlace2Texto, $enlace2Url, $yo['id']
            );
            if (mysqli_stmt_execute($stmt)) {
                registrarBitacora($conexion, 'Publicó/actualizó la página adicional', $titulo);
                $exito = 'Página adicional publicada. Ya aparece en el menú y en el inicio.';
                if ($solPublicar) {
                    solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'pagina_extra', 1);
                    $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                    $solPublicar = null;
                    $solicitudPublicada = true;
                }
                $actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM pagina_extra WHERE id = 1"));
            } else {
                $error = 'No se pudo guardar la página.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Página adicional</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📄 Página adicional</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="pagina-extra.php" class="enlace-ver">Ver página</a></p>
    <p style="max-width:600px; color:var(--text-muted);">Úsala cuando necesites agregar temporalmente una página extra (por ejemplo una campaña, un curso puntual o un aviso especial) sin pedir que se programe una nueva. Es una sola página con título, texto, hasta 2 imágenes o un video, y hasta 2 enlaces. Aparece en "Accesos rápidos" del inicio y en el menú lateral solo mientras esté activa.</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario abre lleno (o conserva lo escrito si hubo error).
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
            foreach (['titulo', 'texto', 'enlace1_texto', 'enlace1_url', 'enlace2_texto', 'enlace2_url'] as $k) { $actual[$k] = $_POST[$k] ?? ''; }
        } else {
            $actual['titulo'] = $solPublicar['titulo'];
            $actual['texto'] = $solPublicar['descripcion'];
            $actual['enlace1_texto'] = $solPublicar['enlace1_texto'];
            $actual['enlace1_url'] = $solPublicar['enlace1_url'];
            $actual['enlace2_texto'] = $solPublicar['enlace2_texto'];
            $actual['enlace2_url'] = $solPublicar['enlace2_url'];
        }
        $avisoReemplazo = 'Ojo: la página adicional es <strong>una sola</strong>; al publicar esto <strong>reemplaza su contenido actual</strong>.';
        if (!empty($actual['activa']) || !empty($actual['imagen1']) || !empty($actual['imagen2']) || !empty($actual['video'])) {
            $avisoReemplazo .= ' Si ya tiene imágenes o video, se conservan a menos que marques "Quitar" abajo.';
        }
        solicitudMostrarBanner($solPublicar, $avisoReemplazo . ' Esta sección no lleva PDF.');
    }
    ?>

    <p style="max-width:600px;">Estado actual: <strong><?= $actual['activa'] ? '🟢 Activa (se está mostrando)' : '🔴 Desactivada (no se muestra nada)' ?></strong></p>

    <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
        <input type="hidden" name="accion" value="guardar">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, true, false, true); ?>
        <div class="campo">
            <label for="titulo">Título</label>
            <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($actual['titulo'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="texto">Texto</label>
            <textarea id="texto" name="texto" rows="4" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($actual['texto'] ?? '') ?></textarea>
        </div>

        <div class="campo">
            <label for="imagen1">Imagen 1 (opcional, máx. 5 MB)</label>
            <input id="imagen1" name="imagen1" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
            <?php if (!empty($actual['imagen1'])): ?>
                <label style="font-size:12px; font-weight:400; margin-top:6px; display:block;">
                    <input type="checkbox" name="quitar_imagen1" value="1"> Quitar la imagen actual
                </label>
            <?php endif; ?>
        </div>
        <div class="campo">
            <label for="imagen2">Imagen 2 (opcional, máx. 5 MB)</label>
            <input id="imagen2" name="imagen2" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
            <?php if (!empty($actual['imagen2'])): ?>
                <label style="font-size:12px; font-weight:400; margin-top:6px; display:block;">
                    <input type="checkbox" name="quitar_imagen2" value="1"> Quitar la imagen actual
                </label>
            <?php endif; ?>
        </div>
        <div class="campo">
            <label for="video">Video (opcional, en vez de las imágenes — mp4, webm, mov u ogg, máx. 80 MB)</label>
            <input id="video" name="video" type="file" accept=".mp4,.webm,.mov,.ogg">
            <?php if (!empty($actual['video'])): ?>
                <label style="font-size:12px; font-weight:400; margin-top:6px; display:block;">
                    <input type="checkbox" name="quitar_video" value="1"> Quitar el video actual
                </label>
            <?php endif; ?>
        </div>

        <div class="campo">
            <label for="enlace1_texto">Enlace 1 — texto del botón (opcional)</label>
            <input id="enlace1_texto" name="enlace1_texto" type="text" value="<?= htmlspecialchars($actual['enlace1_texto'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="enlace1_url">Enlace 1 — dirección (ej. https://... o comunicados.php)</label>
            <input id="enlace1_url" name="enlace1_url" type="text" value="<?= htmlspecialchars($actual['enlace1_url'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="enlace2_texto">Enlace 2 — texto del botón (opcional)</label>
            <input id="enlace2_texto" name="enlace2_texto" type="text" value="<?= htmlspecialchars($actual['enlace2_texto'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="enlace2_url">Enlace 2 — dirección (opcional)</label>
            <input id="enlace2_url" name="enlace2_url" type="text" value="<?= htmlspecialchars($actual['enlace2_url'] ?? '') ?>">
        </div>

        <button type="submit" class="btn-primario">Publicar / actualizar página</button>
    </form>

    <p style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($actual['activa']): ?>
            <form method="post" onsubmit="return confirm('¿Quitar la página adicional del menú y del inicio? El contenido no se borra, solo deja de mostrarse.');">
                <input type="hidden" name="accion" value="quitar">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Quitar del menú (dejar de mostrarla)</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($actual['titulo']) || !empty($actual['imagen1']) || !empty($actual['imagen2']) || !empty($actual['video']) || !empty($actual['enlace1_url']) || !empty($actual['enlace2_url'])): ?>
            <form method="post" onsubmit="return confirm('¿Borrar TODO el contenido (título, texto, imágenes/video y enlaces)? Esto no se puede deshacer. Úsalo cuando ya no necesites lo que hay capturado y quieras empezar de cero.');">
                <input type="hidden" name="accion" value="borrar_contenido">
                <button type="submit" class="btn-peligro">🗑 Borrar contenido</button>
            </form>
        <?php endif; ?>
    </p>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
