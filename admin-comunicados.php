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

// Categorías disponibles: valor guardado en BD => [texto que se muestra, color]
$CATEGORIAS = [
    'general' => ['texto' => 'General', 'color' => '#2d73dd', 'fondo' => 'rgba(45,115,221,.12)'],
    'urgente' => ['texto' => '🚨 Urgente', 'color' => 'var(--danger, #B23A3A)', 'fondo' => 'rgba(178,58,58,.15)'],
    'informativo' => ['texto' => 'Informativo', 'color' => 'var(--dorado-dark)', 'fondo' => 'rgba(201,162,39,.15)'],
];

// Publicar un nuevo comunicado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'publicar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $categoria = isset($CATEGORIAS[$_POST['categoria'] ?? '']) ? $_POST['categoria'] : 'general';
    $nombreImagen = null;
    $nombrePdf = null;

    if ($titulo === '' || $texto === '') {
        $error = 'El título y el texto son obligatorios.';
    } else {
        $tamañoMax = 5 * 1024 * 1024; // 5 MB

        if (!empty($_FILES['imagen']['name'])) {
            $permitidasImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $permitidasImg, true)) {
                $error = 'La imagen debe ser jpg, png, gif o webp.';
            } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
                $error = 'La imagen no puede pesar más de 5 MB.';
            } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
            } else {
                $nombreImagen = uniqid('com_img_', true) . '.' . $extension;
                if (!move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/uploads/comunicados/' . $nombreImagen)) {
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
                $nombrePdf = uniqid('com_pdf_', true) . '.pdf';
                if (!move_uploaded_file($_FILES['pdf']['tmp_name'], __DIR__ . '/uploads/comunicados/' . $nombrePdf)) {
                    $error = 'No se pudo guardar el PDF.';
                    $nombrePdf = null;
                }
            }
        }
    }

    if (!$error && $solPublicar) {
        list($nombreImagen, $nombrePdf) = solicitudAdjuntosParaPublicar($solPublicar, __DIR__ . '/uploads/comunicados', 'com_img_', 'com_pdf_', $nombreImagen, $nombrePdf);
    }

    if (!$error) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO comunicados (titulo, texto, categoria, imagen, pdf, publicado_por) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssssi", $titulo, $texto, $categoria, $nombreImagen, $nombrePdf, $yo['id']);
        if (mysqli_stmt_execute($stmt)) {
            $idComunicado = mysqli_insert_id($conexion);
            registrarBitacora($conexion, 'Publicó comunicado', $titulo, $idComunicado);
            $exito = 'Comunicado publicado correctamente.';
            if ($solPublicar) {
                solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'comunicados', $idComunicado);
                $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                $solPublicar = null;
                $solicitudPublicada = true;
            }
        } else {
            $error = 'No se pudo publicar el comunicado.';
        }
    }
}

// Editar un comunicado existente (corrige título, texto o reemplaza sus archivos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id = (int) $_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $categoria = isset($CATEGORIAS[$_POST['categoria'] ?? '']) ? $_POST['categoria'] : 'general';

    $stmtActual = mysqli_prepare($conexion, "SELECT imagen, pdf FROM comunicados WHERE id = ?");
    mysqli_stmt_bind_param($stmtActual, "i", $id);
    mysqli_stmt_execute($stmtActual);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));

    if (!$actual) {
        $error = 'No se encontró ese comunicado.';
    } elseif ($titulo === '' || $texto === '') {
        $error = 'El título y el texto son obligatorios.';
    } else {
        $tamañoMax = 5 * 1024 * 1024;
        $nombreImagen = $actual['imagen'];
        $nombrePdf = $actual['pdf'];

        if (!empty($_FILES['imagen']['name'])) {
            $permitidasImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $permitidasImg, true)) {
                $error = 'La imagen debe ser jpg, png, gif o webp.';
            } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
                $error = 'La imagen no puede pesar más de 5 MB.';
            } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
            } else {
                $nuevaImagen = uniqid('com_img_', true) . '.' . $extension;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/uploads/comunicados/' . $nuevaImagen)) {
                    if (!empty($actual['imagen'])) @unlink(__DIR__ . '/uploads/comunicados/' . $actual['imagen']);
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
                $nuevoPdf = uniqid('com_pdf_', true) . '.pdf';
                if (move_uploaded_file($_FILES['pdf']['tmp_name'], __DIR__ . '/uploads/comunicados/' . $nuevoPdf)) {
                    if (!empty($actual['pdf'])) @unlink(__DIR__ . '/uploads/comunicados/' . $actual['pdf']);
                    $nombrePdf = $nuevoPdf;
                } else {
                    $error = 'No se pudo guardar el nuevo PDF.';
                }
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conexion, "UPDATE comunicados SET titulo = ?, texto = ?, categoria = ?, imagen = ?, pdf = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssssi", $titulo, $texto, $categoria, $nombreImagen, $nombrePdf, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Editó comunicado', $titulo);
            $exito = 'Comunicado actualizado.';
        }
    }
}

// Ocultar (dar de baja) un comunicado existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ocultar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo FROM comunicados WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $tituloOcultado = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT)) ?: [])['titulo'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "UPDATE comunicados SET vigente = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Ocultó comunicado', $tituloOcultado);
    $exito = 'Comunicado ocultado.';
}

// Eliminar definitivamente un comunicado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = (int) $_POST['id'];
    $stmtArchivo = mysqli_prepare($conexion, "SELECT titulo, imagen, pdf FROM comunicados WHERE id = ?");
    mysqli_stmt_bind_param($stmtArchivo, "i", $id);
    mysqli_stmt_execute($stmtArchivo);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtArchivo));
    if ($fila && !empty($fila['imagen'])) {
        @unlink(__DIR__ . '/uploads/comunicados/' . $fila['imagen']);
    }
    if ($fila && !empty($fila['pdf'])) {
        @unlink(__DIR__ . '/uploads/comunicados/' . $fila['pdf']);
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM comunicados WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Eliminó comunicado', $fila['titulo'] ?? "id $id");
    $exito = 'Comunicado eliminado.';
}

// Vaciar todos los comunicados de un jalón
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'vaciar_todos') {
    $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM comunicados");
    $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];

    $resArchivos = mysqli_query($conexion, "SELECT imagen, pdf FROM comunicados WHERE imagen IS NOT NULL OR pdf IS NOT NULL");
    while ($f = mysqli_fetch_assoc($resArchivos)) {
        if (!empty($f['imagen'])) @unlink(__DIR__ . '/uploads/comunicados/' . $f['imagen']);
        if (!empty($f['pdf'])) @unlink(__DIR__ . '/uploads/comunicados/' . $f['pdf']);
    }

    mysqli_query($conexion, "DELETE FROM comunicados");
    registrarBitacora($conexion, 'Vació todos los comunicados', "$cuantos comunicados eliminados");
    $exito = "Se eliminaron los $cuantos comunicados.";
}

$comunicados = [];
$resultado = mysqli_query($conexion, "SELECT id, titulo, texto, categoria, creado_en, vigente, imagen, pdf FROM comunicados ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $comunicados[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SAASCAEM - Publicar comunicados</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

    <div id="nav-placeholder"></div>

    <div class="main-content">
        <h1>➕ Publicar comunicado</h1>
        <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="comunicados.php" class="enlace-ver">Ver Comunicados</a></p>

        <?php if ($error): ?>
            <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
        <?php endif; ?>

        <?php
        // Con una solicitud: el formulario abre lleno (o conserva lo escrito si hubo error).
        $preTitulo = $preTexto = '';
        $preCategoria = 'general';
        if ($solPublicar) {
            $enviado = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar';
            $preTitulo = $enviado ? ($_POST['titulo'] ?? '') : $solPublicar['titulo'];
            $preTexto = $enviado ? ($_POST['texto'] ?? '') : solicitudTextoConEnlaces($solPublicar);
            $preCategoria = $enviado ? ($_POST['categoria'] ?? 'general') : ($solPublicar['categoria'] ?: 'general');
            solicitudMostrarBanner($solPublicar);
        }
        ?>

        <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0 40px;">
            <input type="hidden" name="accion" value="publicar">
            <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, true, true); ?>
            <div class="campo">
                <label for="titulo">Título</label>
                <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($preTitulo) ?>">
            </div>
            <div class="campo">
                <label for="texto">Texto del comunicado</label>
                <textarea id="texto" name="texto" rows="5" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($preTexto) ?></textarea>
            </div>
            <div class="campo">
                <label for="categoria">Categoría</label>
                <select id="categoria" name="categoria" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php foreach ($CATEGORIAS as $clave => $cat): ?>
                        <option value="<?= $clave ?>" <?= $clave === $preCategoria ? 'selected' : '' ?>><?= htmlspecialchars($cat['texto']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="imagen">Imagen (opcional, máx. 5 MB)</label>
                <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
            </div>
            <div class="campo">
                <label for="pdf">PDF adjunto (opcional, máx. 5 MB)</label>
                <input id="pdf" name="pdf" type="file" accept=".pdf">
            </div>
            <button type="submit" class="btn-primario">Publicar</button>
        </form>

        <h2>Comunicados publicados (<?= count($comunicados) ?>)</h2>
        <?php if (!empty($comunicados)): ?>
            <p style="margin-bottom:16px;">
                <form method="post" onsubmit="return confirm('¿Eliminar TODOS los comunicados (<?= count($comunicados) ?>) para siempre, incluyendo sus imágenes y PDF? Esto no se puede deshacer.');">
                    <input type="hidden" name="accion" value="vaciar_todos">
                    <button type="submit" class="btn-peligro">🗑 Borrar todos los comunicados</button>
                </form>
            </p>
        <?php endif; ?>
        <div class="contenedor-acciones">
            <?php foreach ($comunicados as $c): ?>
                <?php $cat = $CATEGORIAS[$c['categoria']] ?? $CATEGORIAS['general']; ?>
                <div class="caja-accion">
                    <span class="pill" style="background: <?= $cat['fondo'] ?>; color: <?= $cat['color'] ?>;"><?= $cat['texto'] ?></span>
                    <h3><?= htmlspecialchars($c['titulo']) ?></h3>
                    <p><?= date('d/m/Y', strtotime($c['creado_en'])) ?> — <?= $c['vigente'] ? 'Vigente' : 'Oculto' ?></p>
                    <?php if (!empty($c['imagen']) || !empty($c['pdf'])): ?>
                        <p style="font-size:12px; color:var(--text-muted);">
                            <?= !empty($c['imagen']) ? '🖼️ Con imagen ' : '' ?><?= !empty($c['pdf']) ? '📎 Con PDF' : '' ?>
                        </p>
                    <?php endif; ?>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                        <?php if ($c['vigente']): ?>
                            <form method="post" onsubmit="return confirm('¿Ocultar este comunicado?');">
                                <input type="hidden" name="accion" value="ocultar">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Ocultar</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar este comunicado para siempre? No se puede deshacer.');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                        </form>
                    </div>

                    <details style="margin-top:10px; text-align:left;">
                        <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">✏️ Editar</summary>
                        <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                            <input type="hidden" name="accion" value="editar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <div class="campo">
                                <label>Título</label>
                                <input name="titulo" type="text" required value="<?= htmlspecialchars($c['titulo']) ?>">
                            </div>
                            <div class="campo">
                                <label>Texto</label>
                                <textarea name="texto" rows="4" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);"><?= htmlspecialchars($c['texto']) ?></textarea>
                            </div>
                            <div class="campo">
                                <label>Categoría</label>
                                <select name="categoria" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                                    <?php foreach ($CATEGORIAS as $clave => $catOpcion): ?>
                                        <option value="<?= $clave ?>" <?= $c['categoria'] === $clave ? 'selected' : '' ?>><?= htmlspecialchars($catOpcion['texto']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label>Reemplazar imagen (opcional<?= !empty($c['imagen']) ? ", ahora: {$c['imagen']}" : '' ?>)</label>
                                <input name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                            <div class="campo">
                                <label>Reemplazar PDF (opcional<?= !empty($c['pdf']) ? ", ahora: {$c['pdf']}" : '' ?>)</label>
                                <input name="pdf" type="file" accept=".pdf">
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
