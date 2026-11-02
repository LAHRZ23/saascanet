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
    $fecha = trim($_POST['fecha'] ?? '') ?: null;
    $hora = trim($_POST['hora'] ?? '') ?: null;
    $lugar = trim($_POST['lugar'] ?? '') ?: null;
    $nota = trim($_POST['nota'] ?? '') ?: null;
    $enlaceTexto = trim($_POST['enlace_texto'] ?? '') ?: null;
    $enlaceUrl = trim($_POST['enlace_url'] ?? '') ?: null;
    $nombreImagen = null;
    $nombrePdf = null;
    $tamañoMax = 5 * 1024 * 1024; // 5 MB

    if ($titulo === '') {
        $error = 'El título del evento es obligatorio.';
    } elseif (!empty($_FILES['imagen']['name'])) {
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $permitidas, true)) {
            $error = 'La imagen debe ser jpg, png, gif o webp.';
        } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
            $error = 'La imagen no puede pesar más de 5 MB.';
        } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
        } else {
            $nombreImagen = uniqid('evt_', true) . '.' . $extension;
            $destino = __DIR__ . '/uploads/eventos/' . $nombreImagen;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $error = 'No se pudo guardar la imagen.';
                $nombreImagen = null;
            }
        }
    }

    if (!$error && !empty($_FILES['pdf']['name'])) {
        $extension = strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $error = 'El segundo archivo debe ser un PDF.';
        } elseif ($_FILES['pdf']['size'] > $tamañoMax) {
            $error = 'El PDF no puede pesar más de 5 MB.';
        } elseif ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ocurrió un error al subir el PDF, intenta de nuevo.';
        } else {
            $nombrePdf = uniqid('evt_pdf_', true) . '.pdf';
            if (!move_uploaded_file($_FILES['pdf']['tmp_name'], __DIR__ . '/uploads/eventos/' . $nombrePdf)) {
                $error = 'No se pudo guardar el PDF.';
                $nombrePdf = null;
            }
        }
    }

    if (!$error && $solPublicar) {
        list($nombreImagen, $nombrePdf) = solicitudAdjuntosParaPublicar($solPublicar, __DIR__ . '/uploads/eventos', 'evt_', 'evt_pdf_', $nombreImagen, $nombrePdf);
    }

    if (!$error) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO eventos_programados (titulo, fecha, hora, lugar, nota, imagen, pdf, enlace_texto, enlace_url, publicado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssssssssi", $titulo, $fecha, $hora, $lugar, $nota, $nombreImagen, $nombrePdf, $enlaceTexto, $enlaceUrl, $yo['id']);
        if (mysqli_stmt_execute($stmt)) {
            $idEvento = mysqli_insert_id($conexion);
            registrarBitacora($conexion, 'Publicó evento', $titulo, $idEvento);
            $exito = 'Evento publicado.';
            if ($solPublicar) {
                solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'eventos_programados', $idEvento);
                $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                $solPublicar = null;
                $solicitudPublicada = true;
            }

            // Si el evento tiene fecha, también aparece automáticamente en el
            // Calendario compartido, guardando el vínculo para el botón
            // "Más información". Un mismo día puede tener varios eventos.
            if ($fecha) {
                $stmtCal = mysqli_prepare($conexion, "INSERT INTO calendario_eventos (titulo, fecha, tipo, creado_por, evento_id) VALUES (?, ?, 'normal', ?, ?)");
                mysqli_stmt_bind_param($stmtCal, "ssii", $titulo, $fecha, $yo['id'], $idEvento);
                if (mysqli_stmt_execute($stmtCal)) {
                    $exito .= ' También se agregó al Calendario.';
                } else {
                    $exito .= ' (Aviso: no se pudo reflejar en el Calendario — revisa que la actualización de base de datos "actualizacion_9" ya esté importada.)';
                }
            }
        } else {
            $error = 'No se pudo publicar el evento.';
        }
    }
}

// Editar un evento existente (corrige título, fecha, lugar, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $id = (int) $_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $fecha = trim($_POST['fecha'] ?? '') ?: null;
    $hora = trim($_POST['hora'] ?? '') ?: null;
    $lugar = trim($_POST['lugar'] ?? '') ?: null;
    $nota = trim($_POST['nota'] ?? '') ?: null;
    $enlaceTexto = trim($_POST['enlace_texto'] ?? '') ?: null;
    $enlaceUrl = trim($_POST['enlace_url'] ?? '') ?: null;

    $stmtActual = mysqli_prepare($conexion, "SELECT imagen, pdf FROM eventos_programados WHERE id = ?");
    mysqli_stmt_bind_param($stmtActual, "i", $id);
    mysqli_stmt_execute($stmtActual);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));

    if (!$actual) {
        $error = 'No se encontró ese evento.';
    } elseif ($titulo === '') {
        $error = 'El título del evento es obligatorio.';
    } else {
        $tamañoMax = 5 * 1024 * 1024;
        $nombreImagen = $actual['imagen'];
        $nombrePdf = $actual['pdf'];
        if (!empty($_FILES['imagen']['name'])) {
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $permitidas, true)) {
                $error = 'La imagen debe ser jpg, png, gif o webp.';
            } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
                $error = 'La imagen no puede pesar más de 5 MB.';
            } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
            } else {
                $nuevaImagen = uniqid('evt_', true) . '.' . $extension;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/uploads/eventos/' . $nuevaImagen)) {
                    if (!empty($actual['imagen'])) @unlink(__DIR__ . '/uploads/eventos/' . $actual['imagen']);
                    $nombreImagen = $nuevaImagen;
                } else {
                    $error = 'No se pudo guardar la nueva imagen.';
                }
            }
        }

        if (!$error && !empty($_FILES['pdf']['name'])) {
            $extension = strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $error = 'El segundo archivo debe ser un PDF.';
            } elseif ($_FILES['pdf']['size'] > $tamañoMax) {
                $error = 'El PDF no puede pesar más de 5 MB.';
            } elseif ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir el PDF, intenta de nuevo.';
            } else {
                $nuevoPdf = uniqid('evt_pdf_', true) . '.pdf';
                if (move_uploaded_file($_FILES['pdf']['tmp_name'], __DIR__ . '/uploads/eventos/' . $nuevoPdf)) {
                    if (!empty($actual['pdf'])) @unlink(__DIR__ . '/uploads/eventos/' . $actual['pdf']);
                    $nombrePdf = $nuevoPdf;
                } else {
                    $error = 'No se pudo guardar el nuevo PDF.';
                }
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conexion, "UPDATE eventos_programados SET titulo = ?, fecha = ?, hora = ?, lugar = ?, nota = ?, imagen = ?, pdf = ?, enlace_texto = ?, enlace_url = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssssssssi", $titulo, $fecha, $hora, $lugar, $nota, $nombreImagen, $nombrePdf, $enlaceTexto, $enlaceUrl, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Editó evento', $titulo);

            // Mantiene sincronizada su entrada en el Calendario compartido.
            $stmtCalExiste = mysqli_prepare($conexion, "SELECT id FROM calendario_eventos WHERE evento_id = ?");
            mysqli_stmt_bind_param($stmtCalExiste, "i", $id);
            mysqli_stmt_execute($stmtCalExiste);
            $calExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCalExiste));

            if ($fecha && $calExistente) {
                $stmtCal = mysqli_prepare($conexion, "UPDATE calendario_eventos SET titulo = ?, fecha = ? WHERE evento_id = ?");
                mysqli_stmt_bind_param($stmtCal, "ssi", $titulo, $fecha, $id);
                mysqli_stmt_execute($stmtCal);
            } elseif ($fecha && !$calExistente) {
                $stmtCal = mysqli_prepare($conexion, "INSERT INTO calendario_eventos (titulo, fecha, tipo, creado_por, evento_id) VALUES (?, ?, 'normal', ?, ?)");
                mysqli_stmt_bind_param($stmtCal, "ssii", $titulo, $fecha, $yo['id'], $id);
                mysqli_stmt_execute($stmtCal);
            } elseif (!$fecha && $calExistente) {
                $stmtCalDel = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE evento_id = ?");
                mysqli_stmt_bind_param($stmtCalDel, "i", $id);
                mysqli_stmt_execute($stmtCalDel);
            }

            $exito = 'Evento actualizado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ocultar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo FROM eventos_programados WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $tituloEv = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT)) ?: [])['titulo'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "UPDATE eventos_programados SET vigente = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Ocultó evento', $tituloEv);
    $exito = 'Evento ocultado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo, imagen, pdf FROM eventos_programados WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $filaEv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
    if ($filaEv && !empty($filaEv['imagen'])) {
        @unlink(__DIR__ . '/uploads/eventos/' . $filaEv['imagen']);
    }
    if ($filaEv && !empty($filaEv['pdf'])) {
        @unlink(__DIR__ . '/uploads/eventos/' . $filaEv['pdf']);
    }
    // Quita también su entrada del Calendario (si no, quedaría un texto suelto sin evento detrás).
    $stmtCalDel = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE evento_id = ?");
    mysqli_stmt_bind_param($stmtCalDel, "i", $id);
    mysqli_stmt_execute($stmtCalDel);
    $stmt = mysqli_prepare($conexion, "DELETE FROM eventos_programados WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Eliminó evento', $filaEv['titulo'] ?? "id $id");
    $exito = 'Evento eliminado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar_todos') {
    $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM eventos_programados");
    $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];

    $resImgs = mysqli_query($conexion, "SELECT imagen, pdf FROM eventos_programados WHERE imagen IS NOT NULL OR pdf IS NOT NULL");
    while ($f = mysqli_fetch_assoc($resImgs)) {
        if (!empty($f['imagen'])) @unlink(__DIR__ . '/uploads/eventos/' . $f['imagen']);
        if (!empty($f['pdf'])) @unlink(__DIR__ . '/uploads/eventos/' . $f['pdf']);
    }

    mysqli_query($conexion, "DELETE FROM calendario_eventos WHERE evento_id IS NOT NULL");
    mysqli_query($conexion, "DELETE FROM eventos_programados");
    registrarBitacora($conexion, 'Vació todos los eventos', "$cuantos eventos eliminados");
    $exito = "Se eliminaron los $cuantos eventos.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar_pasados') {
    $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM eventos_programados WHERE fecha IS NOT NULL AND fecha < CURDATE()");
    $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];

    $resImgs = mysqli_query($conexion, "SELECT imagen, pdf FROM eventos_programados WHERE fecha IS NOT NULL AND fecha < CURDATE() AND (imagen IS NOT NULL OR pdf IS NOT NULL)");
    while ($f = mysqli_fetch_assoc($resImgs)) {
        if (!empty($f['imagen'])) @unlink(__DIR__ . '/uploads/eventos/' . $f['imagen']);
        if (!empty($f['pdf'])) @unlink(__DIR__ . '/uploads/eventos/' . $f['pdf']);
    }

    mysqli_query($conexion, "DELETE c FROM calendario_eventos c INNER JOIN eventos_programados e ON e.id = c.evento_id WHERE e.fecha < CURDATE()");
    mysqli_query($conexion, "DELETE FROM eventos_programados WHERE fecha IS NOT NULL AND fecha < CURDATE()");
    registrarBitacora($conexion, 'Vació eventos pasados', "$cuantos eventos eliminados");
    $exito = "Se eliminaron los $cuantos eventos pasados.";
}

$eventos = [];
$resultado = mysqli_query($conexion, "SELECT * FROM eventos_programados ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $eventos[] = $fila;
}

$totalEventos = count($eventos);
$totalPasados = 0;
$rPasados = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM eventos_programados WHERE fecha IS NOT NULL AND fecha < CURDATE()");
if ($rPasados) $totalPasados = (int) mysqli_fetch_assoc($rPasados)['total'];

// Si se llegó aquí desde el botón "Agregar desde Eventos" del calendario,
// precapturamos la fecha que ya se había seleccionado ahí.
$fechaPrecargada = '';
if (!empty($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'])) {
    $fechaPrecargada = $_GET['fecha'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Publicar eventos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>➕ Publicar evento</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="eventos.php" class="enlace-ver">Ver Eventos</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario abre lleno (o conserva lo escrito si hubo error).
    $pre = ['titulo' => '', 'nota' => '', 'enlace_texto' => '', 'enlace_url' => '', 'fecha' => $fechaPrecargada, 'hora' => '', 'lugar' => ''];
    $datosSol = [];
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar') {
            foreach ($pre as $k => $_) { $pre[$k] = $_POST[$k] ?? ''; }
        } else {
            // Fecha, hora y lugar vienen en el encabezado de la descripción (formulario de evento).
            $datosSol = solicitudDatosEncabezado($solPublicar);
            $pre['titulo'] = $solPublicar['titulo'];
            $pre['nota'] = $datosSol['texto'];
            $pre['fecha'] = $datosSol['fecha_evento'] ?? $pre['fecha'];
            $pre['hora'] = $datosSol['hora'] ?? '';
            $pre['lugar'] = $datosSol['lugar'] ?? '';
            $pre['enlace_texto'] = $solPublicar['enlace1_texto'] ?? '';
            $pre['enlace_url'] = $solPublicar['enlace1_url'] ?? '';
            if (!empty($solPublicar['enlace2_url'])) {
                $pre['nota'] .= "\n\n🔗 " . $solPublicar['enlace2_texto'] . ': ' . $solPublicar['enlace2_url'];
            }
        }
        solicitudMostrarBanner($solPublicar, isset($datosSol['fecha_evento'])
            ? 'Ya se llenaron el título, la fecha, la hora, el lugar y la nota con lo que pidió. Revísalos antes de publicar.'
            : 'Ojo: esta solicitud no trae la <strong>fecha del evento</strong> (solo para cuándo se necesita la publicación). Revisa lo que pidió y captura la fecha, hora y lugar.');
    }
    ?>

    <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0 40px;">
        <input type="hidden" name="accion" value="publicar">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, true, true); ?>
        <div class="campo">
            <label for="titulo">Título del evento</label>
            <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($pre['titulo']) ?>">
        </div>
        <div class="campo">
            <label for="fecha">Fecha (opcional)</label>
            <input id="fecha" name="fecha" type="date" value="<?= htmlspecialchars($pre['fecha'] ?? $fechaPrecargada) ?>">
        </div>
        <div class="campo">
            <label for="hora">Hora (opcional)</label>
            <input id="hora" name="hora" type="text" placeholder="Ej. 10:00 am" value="<?= htmlspecialchars($pre['hora'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="lugar">Lugar (opcional)</label>
            <input id="lugar" name="lugar" type="text" value="<?= htmlspecialchars($pre['lugar'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="nota">Nota (opcional)</label>
            <textarea id="nota" name="nota" rows="3" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($pre['nota']) ?></textarea>
        </div>
        <div class="campo">
            <label for="imagen">Imagen (opcional — jpg, png, gif o webp, máx. 5 MB)</label>
            <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
        </div>
        <div class="campo">
            <label for="pdf">PDF adjunto (opcional — ej. la convocatoria o el programa, máx. 5 MB)</label>
            <input id="pdf" name="pdf" type="file" accept=".pdf">
        </div>
        <div class="campo">
            <label for="enlace_texto">Texto del botón de enlace (opcional — ej. "Unirse por Zoom")</label>
            <input id="enlace_texto" name="enlace_texto" type="text" value="<?= htmlspecialchars($pre['enlace_texto']) ?>">
        </div>
        <div class="campo">
            <label for="enlace_url">Dirección del enlace (opcional — ej. la liga de la reunión)</label>
            <input id="enlace_url" name="enlace_url" type="text" value="<?= htmlspecialchars($pre['enlace_url']) ?>">
        </div>
        <button type="submit" class="btn-primario">Publicar evento</button>
    </form>

    <h2>Eventos publicados (<?= $totalEventos ?>)</h2>
    <p style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
        <?php if ($totalPasados > 0): ?>
            <form method="post" onsubmit="return confirm('Vas a eliminar PARA SIEMPRE los <?= $totalPasados ?> eventos con fecha ya pasada (incluyendo sus imágenes). Esto no se puede deshacer.\n\n¿Continuar?');">
                <input type="hidden" name="accion" value="vaciar_pasados">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">🗑 Vaciar eventos pasados (<?= $totalPasados ?>)</button>
            </form>
        <?php endif; ?>
        <?php if ($totalEventos > 0): ?>
            <form method="post" onsubmit="return confirm('Vas a eliminar PARA SIEMPRE los <?= $totalEventos ?> eventos publicados (pasados y futuros, con sus imágenes) y también desaparecerán del Calendario. Esto no se puede deshacer.\n\n¿Continuar?');">
                <input type="hidden" name="accion" value="vaciar_todos">
                <button type="submit" class="btn-peligro">🗑 Borrar todos los eventos (<?= $totalEventos ?>)</button>
            </form>
        <?php endif; ?>
    </p>
    <div class="contenedor-acciones">
        <?php foreach ($eventos as $ev): ?>
            <div class="caja-accion">
                <?php if (!empty($ev['imagen'])): ?>
                    <img src="uploads/eventos/<?= htmlspecialchars($ev['imagen']) ?>" alt="" style="width:100%; border-radius:10px; margin-bottom:10px; max-height:140px; object-fit:cover;">
                <?php endif; ?>
                <h3><?= htmlspecialchars($ev['titulo']) ?></h3>
                <p><?= $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : 'Sin fecha' ?> — <?= $ev['vigente'] ? 'Vigente' : 'Oculto' ?></p>
                <?php if (!empty($ev['enlace_url'])): ?>
                    <p style="font-size:12px; color:var(--text-muted);">🔗 <?= htmlspecialchars($ev['enlace_texto'] ?: 'Enlace') ?></p>
                <?php endif; ?>
                <?php if (!empty($ev['pdf'])): ?>
                    <p style="font-size:12px; color:var(--text-muted);">📎 Con PDF</p>
                <?php endif; ?>
                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                    <?php if ($ev['vigente']): ?>
                        <form method="post" onsubmit="return confirm('¿Ocultar este evento?');">
                            <input type="hidden" name="accion" value="ocultar">
                            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                            <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Ocultar</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('¿Eliminar este evento para siempre? No se puede deshacer.');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                        <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                    </form>
                </div>

                <details style="margin-top:10px; text-align:left;">
                    <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">✏️ Editar</summary>
                    <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                        <div class="campo">
                            <label>Título</label>
                            <input name="titulo" type="text" required value="<?= htmlspecialchars($ev['titulo']) ?>">
                        </div>
                        <div class="campo">
                            <label>Fecha</label>
                            <input name="fecha" type="date" value="<?= htmlspecialchars($ev['fecha'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Hora</label>
                            <input name="hora" type="text" value="<?= htmlspecialchars($ev['hora'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Lugar</label>
                            <input name="lugar" type="text" value="<?= htmlspecialchars($ev['lugar'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Nota</label>
                            <textarea name="nota" rows="3" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);"><?= htmlspecialchars($ev['nota'] ?? '') ?></textarea>
                        </div>
                        <div class="campo">
                            <label>Reemplazar imagen (opcional<?= !empty($ev['imagen']) ? ", ahora: {$ev['imagen']}" : '' ?>)</label>
                            <input name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
                        </div>
                        <div class="campo">
                            <label>Reemplazar PDF (opcional<?= !empty($ev['pdf']) ? ", ahora: {$ev['pdf']}" : '' ?>)</label>
                            <input name="pdf" type="file" accept=".pdf">
                        </div>
                        <div class="campo">
                            <label>Texto del botón de enlace</label>
                            <input name="enlace_texto" type="text" value="<?= htmlspecialchars($ev['enlace_texto'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Dirección del enlace</label>
                            <input name="enlace_url" type="text" value="<?= htmlspecialchars($ev['enlace_url'] ?? '') ?>">
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
