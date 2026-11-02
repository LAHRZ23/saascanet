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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;

    if ($titulo === '') {
        $error = 'El título es obligatorio.';
    } elseif (empty($_FILES['video']['name']) && !($solPublicar && !empty($solPublicar['video']) && !empty($_POST['usar_video_solicitud']))) {
        $error = 'Selecciona un archivo de video.';
    } elseif (empty($_FILES['video']['name'])) {
        // Sin archivo nuevo: se usa el video (o GIF) que mandó la persona en su solicitud.
        $nombreVideo = solicitudCopiarArchivo($solPublicar, 'video', __DIR__ . '/uploads/tutoriales', 'tuto_');
        if (!$nombreVideo) {
            $error = 'No se pudo copiar el video de la solicitud. Súbelo de nuevo desde el campo de abajo.';
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO tutoriales (titulo, descripcion, video, publicado_por) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descripcion, $nombreVideo, $yo['id']);
            if (mysqli_stmt_execute($stmt)) {
                $idTutorial = mysqli_insert_id($conexion);
                registrarBitacora($conexion, 'Publicó tutorial', $titulo, $idTutorial);
                $exito = 'Tutorial publicado.';
                solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'tutoriales', $idTutorial);
                $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                $solPublicar = null;
                $solicitudPublicada = true;
            } else {
                @unlink(__DIR__ . '/uploads/tutoriales/' . $nombreVideo);
                $error = 'No se pudo guardar el tutorial.';
            }
        }
    } else {
        $permitidas = ['mp4', 'webm', 'mov', 'ogg', 'gif'];
        $extension = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $tamañoMax = 80 * 1024 * 1024; // 80 MB

        if (!in_array($extension, $permitidas, true)) {
            $error = 'El archivo debe ser un video (mp4, webm, mov u ogg) o un GIF animado.';
        } elseif ($_FILES['video']['size'] > $tamañoMax) {
            $error = 'El video no puede pesar más de 80 MB.';
        } elseif ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $error = $_FILES['video']['error'] === UPLOAD_ERR_INI_SIZE
                ? 'El video es más grande de lo que el servidor permite subir por ahora (revisa el archivo .htaccess o el php.ini).'
                : 'Ocurrió un error al subir el video, intenta de nuevo.';
        } else {
            $nombreVideo = uniqid('tuto_', true) . '.' . $extension;
            $destino = __DIR__ . '/uploads/tutoriales/' . $nombreVideo;
            if (!move_uploaded_file($_FILES['video']['tmp_name'], $destino)) {
                $error = 'No se pudo guardar el video.';
            } else {
                $stmt = mysqli_prepare($conexion, "INSERT INTO tutoriales (titulo, descripcion, video, publicado_por) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descripcion, $nombreVideo, $yo['id']);
                if (mysqli_stmt_execute($stmt)) {
                    $idTutorial = mysqli_insert_id($conexion);
                    registrarBitacora($conexion, 'Publicó tutorial', $titulo, $idTutorial);
                    $exito = 'Tutorial publicado.';
                    if ($solPublicar) {
                        solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'tutoriales', $idTutorial);
                        $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                        $solPublicar = null;
                        $solicitudPublicada = true;
                    }
                } else {
                    $error = 'No se pudo guardar el tutorial.';
                }
            }
        }
    }
}

// Editar un tutorial existente (corrige título, descripción o reemplaza el video)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $id = (int) $_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;

    $stmtActual = mysqli_prepare($conexion, "SELECT video FROM tutoriales WHERE id = ?");
    mysqli_stmt_bind_param($stmtActual, "i", $id);
    mysqli_stmt_execute($stmtActual);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));

    if (!$actual) {
        $error = 'No se encontró ese tutorial.';
    } elseif ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        $nombreVideo = $actual['video'];
        if (!empty($_FILES['video']['name'])) {
            $permitidas = ['mp4', 'webm', 'mov', 'ogg', 'gif'];
            $extension = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $tamañoMax = 80 * 1024 * 1024;
            if (!in_array($extension, $permitidas, true)) {
                $error = 'El archivo debe ser un video (mp4, webm, mov u ogg) o un GIF animado.';
            } elseif ($_FILES['video']['size'] > $tamañoMax) {
                $error = 'El video no puede pesar más de 80 MB.';
            } elseif ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir el video, intenta de nuevo.';
            } else {
                $nuevoVideo = uniqid('tuto_', true) . '.' . $extension;
                if (move_uploaded_file($_FILES['video']['tmp_name'], __DIR__ . '/uploads/tutoriales/' . $nuevoVideo)) {
                    if (!empty($actual['video'])) @unlink(__DIR__ . '/uploads/tutoriales/' . $actual['video']);
                    $nombreVideo = $nuevoVideo;
                } else {
                    $error = 'No se pudo guardar el nuevo video.';
                }
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conexion, "UPDATE tutoriales SET titulo = ?, descripcion = ?, video = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descripcion, $nombreVideo, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Editó tutorial', $titulo);
            $exito = 'Tutorial actualizado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ocultar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo FROM tutoriales WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $tituloT = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT)) ?: [])['titulo'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "UPDATE tutoriales SET vigente = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Ocultó tutorial', $tituloT);
    $exito = 'Tutorial ocultado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int) $_POST['id'];
    $stmtV = mysqli_prepare($conexion, "SELECT titulo, video FROM tutoriales WHERE id = ?");
    mysqli_stmt_bind_param($stmtV, "i", $id);
    mysqli_stmt_execute($stmtV);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtV));
    if ($fila && !empty($fila['video'])) {
        @unlink(__DIR__ . '/uploads/tutoriales/' . $fila['video']);
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM tutoriales WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Eliminó tutorial', $fila['titulo'] ?? "id $id");
    $exito = 'Tutorial eliminado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar_todos') {
    $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tutoriales");
    $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];

    $resVideos = mysqli_query($conexion, "SELECT video FROM tutoriales WHERE video IS NOT NULL");
    while ($f = mysqli_fetch_assoc($resVideos)) {
        @unlink(__DIR__ . '/uploads/tutoriales/' . $f['video']);
    }

    mysqli_query($conexion, "DELETE FROM tutoriales");
    registrarBitacora($conexion, 'Vació todos los tutoriales', "$cuantos tutoriales eliminados");
    $exito = "Se eliminaron los $cuantos tutoriales.";
}

$tutoriales = [];
$resultado = mysqli_query($conexion, "SELECT * FROM tutoriales ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tutoriales[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Publicar tutoriales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🎓 Publicar tutorial</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="tutoriales.php" class="enlace-ver">Ver Tutoriales</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario abre con el título y la descripción pedidos
    // (o conserva lo escrito si hubo error).
    $pre = ['titulo' => '', 'descripcion' => ''];
    $usaVideoSolicitud = false;
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar') {
            foreach ($pre as $k => $_) { $pre[$k] = $_POST[$k] ?? ''; }
        } else {
            $pre['titulo'] = $solPublicar['titulo'];
            $pre['descripcion'] = $solPublicar['descripcion'];
        }
        $usaVideoSolicitud = !empty($solPublicar['video']);
        solicitudMostrarBanner($solPublicar, $usaVideoSolicitud
            ? 'Ya se llenaron el título y la descripción. El video de la solicitud queda marcado para usarse; si subes otro abajo, se usa el nuevo.'
            : 'Esta solicitud no trae video ni GIF: pídelo a la persona o súbelo tú abajo.');
    }
    ?>

    <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0 40px;">
        <input type="hidden" name="accion" value="publicar">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, false, false, true); ?>
        <div class="campo">
            <label for="titulo">Título</label>
            <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($pre['titulo']) ?>">
        </div>
        <div class="campo">
            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" rows="3" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($pre['descripcion']) ?></textarea>
        </div>
        <div class="campo">
            <label for="video"><?= $usaVideoSolicitud ? 'Otro video o GIF (opcional, solo si no quieres usar el de la solicitud)' : 'Video o GIF animado (mp4, webm, mov, ogg o gif — máx. 80 MB)' ?></label>
            <input id="video" name="video" type="file" accept=".mp4,.webm,.mov,.ogg,.gif" <?= $usaVideoSolicitud ? '' : 'required' ?>>
        </div>
        <button type="submit" class="btn-primario">Publicar tutorial</button>
    </form>

    <h2>Tutoriales publicados (<?= count($tutoriales) ?>)</h2>
    <?php if (!empty($tutoriales)): ?>
        <p style="margin-bottom:16px;">
            <form method="post" onsubmit="return confirm('¿Eliminar TODOS los tutoriales (<?= count($tutoriales) ?>) para siempre, incluyendo sus videos? Esto no se puede deshacer.');">
                <input type="hidden" name="accion" value="vaciar_todos">
                <button type="submit" class="btn-peligro">🗑 Borrar todos los tutoriales</button>
            </form>
        </p>
    <?php endif; ?>
    <div class="contenedor-acciones">
        <?php foreach ($tutoriales as $t): ?>
            <div class="caja-accion">
                <h3><?= htmlspecialchars($t['titulo']) ?></h3>
                <p><?= date('d/m/Y', strtotime($t['creado_en'])) ?> — <?= $t['vigente'] ? 'Vigente' : 'Oculto' ?></p>
                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                    <?php if ($t['vigente']): ?>
                        <form method="post" onsubmit="return confirm('¿Ocultar este tutorial?');">
                            <input type="hidden" name="accion" value="ocultar">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Ocultar</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('¿Eliminar este tutorial y su video para siempre?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                    </form>
                </div>

                <details style="margin-top:10px; text-align:left;">
                    <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">✏️ Editar</summary>
                    <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <div class="campo">
                            <label>Título</label>
                            <input name="titulo" type="text" required value="<?= htmlspecialchars($t['titulo']) ?>">
                        </div>
                        <div class="campo">
                            <label>Descripción</label>
                            <textarea name="descripcion" rows="3" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);"><?= htmlspecialchars($t['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div class="campo">
                            <label>Reemplazar video o GIF (opcional, ahora: <?= htmlspecialchars($t['video']) ?>)</label>
                            <input name="video" type="file" accept=".mp4,.webm,.mov,.ogg,.gif">
                        </div>
                        <button type="submit" class="btn-primario">Guardar cambios</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
