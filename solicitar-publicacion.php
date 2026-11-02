<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
$yo = usuarioActual();

$SECCIONES = solicitudSecciones();
unset($SECCIONES['cumpleanos']); // los cumpleaños tienen su propio formulario corto
$CATEGORIAS = solicitudCategorias();
$error = '';

$val = [
    'seccion' => $_GET['seccion'] ?? '',
    'categoria' => 'general',
    'titulo' => '',
    'descripcion' => '',
    'fecha_requerida' => '',
    'archivos' => 'ninguno',
    'num_enlaces' => '0',
    'enlace1_texto' => '',
    'enlace1_url' => '',
    'enlace2_texto' => '',
    'enlace2_url' => '',
    // Datos propios de cada tipo de publicación (se juntan al inicio de la descripción).
    'x_fecha_evento' => '',
    'x_hora' => '',
    'x_lugar' => '',
    'x_institucion' => '',
    'x_modalidad' => '',
    'x_vigencia' => '',
    'x_unidad' => '',
    'x_movimiento' => 'alta',
    'x_extension' => '',
    'x_correo' => '',
    'x_fecha_cal' => '',
];

$MOVIMIENTOS = [
    'alta' => 'Agregar un contacto nuevo',
    'cambio' => 'Corregir los datos de un contacto que ya existe',
    'baja' => 'Quitar un contacto',
];

// Qué lleva el formulario según la sección: tipo de archivos y máximo de botones de enlace.
// (El formulario en pantalla se adapta con los mismos datos; aquí se vuelve a exigir.)
$PERFIL_SECCION = [
    'comunicado' => ['archivos' => 'normal', 'enlaces' => 0],
    'evento' => ['archivos' => 'normal', 'enlaces' => 1],
    'curso' => ['archivos' => 'ninguno', 'enlaces' => 1],
    'tutorial' => ['archivos' => 'tutorial', 'enlaces' => 0],
    'directorio' => ['archivos' => 'ninguno', 'enlaces' => 0],
    'calendario' => ['archivos' => 'ninguno', 'enlaces' => 0],
    'pagina' => ['archivos' => 'pagina', 'enlaces' => 2],
    'otro' => ['archivos' => 'normal', 'enlaces' => 2],
];

// Valida un archivo subido opcional (imagen o video). Regresa el mensaje de
// error, o '' si está bien o si no se subió nada.
function validarArchivoOpcional($campo, $tipo, $etiqueta) {
    if (empty($_FILES[$campo]['name'])) {
        return '';
    }
    $f = $_FILES[$campo];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return "Ocurrió un error al subir $etiqueta, intenta de nuevo.";
    }
    if ($tipo === 'imagen') {
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || @getimagesize($f['tmp_name']) === false) {
            return ucfirst($etiqueta) . ' debe ser jpg, png, gif o webp.';
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            return ucfirst($etiqueta) . ' no puede pesar más de 5 MB.';
        }
    } else {
        // El tutorial también acepta GIF animado, igual que la sección de Tutoriales.
        $permitidas = $tipo === 'tutorial' ? ['mp4', 'webm', 'mov', 'ogg', 'gif'] : ['mp4', 'webm', 'mov', 'ogg'];
        if (!in_array($ext, $permitidas, true)) {
            return $tipo === 'tutorial' ? 'El tutorial debe ser un video (mp4, webm, mov u ogg) o un GIF.' : 'El video debe ser mp4, webm, mov u ogg.';
        }
        if ($f['size'] > 80 * 1024 * 1024) {
            return 'El video no puede pesar más de 80 MB.';
        }
    }
    return '';
}

function guardarArchivoOpcional($campo, $prefijo, $carpeta) {
    if (empty($_FILES[$campo]['name'])) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    $nombre = uniqid($prefijo, true) . '.' . $ext;
    return move_uploaded_file($_FILES[$campo]['tmp_name'], $carpeta . '/' . $nombre) ? $nombre : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST)) {
        $error = 'No se recibió la información. Es probable que los archivos sean demasiado pesados (imágenes y PDF máximo 5 MB, video máximo 80 MB).';
    } else {
        foreach ($val as $k => $_) {
            if (isset($_POST[$k])) {
                $val[$k] = trim((string) $_POST[$k]);
            }
        }

        $perfil = $PERFIL_SECCION[$val['seccion']] ?? ['archivos' => 'ninguno', 'enlaces' => 0];
        $esPagina = $perfil['archivos'] === 'pagina';
        $esTutorial = $perfil['archivos'] === 'tutorial';
        $adjuntosNormales = $perfil['archivos'] === 'normal';
        $quiereImagen = $adjuntosNormales && in_array($val['archivos'], ['imagen', 'ambos'], true);
        $quierePdf = $adjuntosNormales && in_array($val['archivos'], ['pdf', 'ambos'], true);
        $numEnlaces = in_array($val['num_enlaces'], ['1', '2'], true) ? min((int) $val['num_enlaces'], $perfil['enlaces']) : 0;
        $categoria = ($val['seccion'] === 'comunicado' && isset($CATEGORIAS[$val['categoria']])) ? $val['categoria'] : null;
        $tamanoMax = 5 * 1024 * 1024;

        if (!isset($SECCIONES[$val['seccion']])) {
            $error = 'Elige en qué sección necesitas la publicación.';
        } elseif ($val['titulo'] === '' || mb_strlen($val['titulo']) > 150) {
            $error = 'Escribe un título o asunto (máximo 150 caracteres).';
        } elseif (($val['descripcion'] === '' && !in_array($val['seccion'], ['directorio', 'calendario'], true)) || mb_strlen($val['descripcion']) > 5000) {
            $error = 'Describe qué debe decir la publicación (máximo 5000 caracteres).';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val['fecha_requerida'])
            || !checkdate((int) substr($val['fecha_requerida'], 5, 2), (int) substr($val['fecha_requerida'], 8, 2), (int) substr($val['fecha_requerida'], 0, 4))) {
            $error = 'Elige la fecha en que necesitas la publicación.';
        } elseif ($val['fecha_requerida'] < date('Y-m-d')) {
            $error = 'La fecha en que necesitas la publicación no puede ser anterior a hoy.';
        }

        for ($i = 1; $i <= $numEnlaces && !$error; $i++) {
            $texto = $val["enlace{$i}_texto"];
            $url = $val["enlace{$i}_url"];
            if ($texto === '' || mb_strlen($texto) > 100) {
                $error = "Escribe el texto del botón de enlace $i (máximo 100 caracteres).";
            } elseif ($url === '' || mb_strlen($url) > 255 || !solicitudUrlValida($url)) {
                $error = "El enlace $i debe ser una dirección completa que empiece con http:// o https://";
            }
        }

        // Datos propios de eventos y cursos: se anteponen a la descripción para que la UATI los tenga juntos.
        $encabezado = [];
        if (!$error && $val['seccion'] === 'evento') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val['x_fecha_evento'])
                || !checkdate((int) substr($val['x_fecha_evento'], 5, 2), (int) substr($val['x_fecha_evento'], 8, 2), (int) substr($val['x_fecha_evento'], 0, 4))) {
                $error = 'Elige la fecha en que será el evento.';
            } elseif (mb_strlen($val['x_hora']) > 60 || mb_strlen($val['x_lugar']) > 150) {
                $error = 'La hora o el lugar del evento son demasiado largos.';
            } else {
                $encabezado[] = '📅 Fecha del evento: ' . date('d/m/Y', strtotime($val['x_fecha_evento']));
                if ($val['x_hora'] !== '') { $encabezado[] = '🕒 Hora: ' . $val['x_hora']; }
                if ($val['x_lugar'] !== '') { $encabezado[] = '📍 Lugar: ' . $val['x_lugar']; }
            }
        }
        if (!$error && $val['seccion'] === 'curso') {
            if (mb_strlen($val['x_institucion']) > 150 || mb_strlen($val['x_modalidad']) > 100 || mb_strlen($val['x_vigencia']) > 150) {
                $error = 'Alguno de los datos del curso es demasiado largo.';
            } else {
                if ($val['x_institucion'] !== '') { $encabezado[] = '🏫 Institución: ' . $val['x_institucion']; }
                if ($val['x_modalidad'] !== '') { $encabezado[] = '💻 Modalidad: ' . $val['x_modalidad']; }
                if ($val['x_vigencia'] !== '') { $encabezado[] = '⏳ Vigencia: ' . $val['x_vigencia']; }
            }
        }
        if (!$error && $val['seccion'] === 'directorio') {
            if (!isset($MOVIMIENTOS[$val['x_movimiento']])) {
                $error = 'Elige qué quieres hacer con el contacto.';
            } elseif ($val['x_unidad'] === '' || mb_strlen($val['x_unidad']) > 150 || mb_strlen($val['x_extension']) > 60 || mb_strlen($val['x_correo']) > 150) {
                $error = 'Escribe la unidad o área del contacto (los datos no pueden ser tan largos).';
            } else {
                $encabezado[] = '🔁 Movimiento: ' . ['alta' => 'Agregar', 'cambio' => 'Corregir', 'baja' => 'Quitar'][$val['x_movimiento']];
                $encabezado[] = '🏢 Unidad: ' . $val['x_unidad'];
                if ($val['x_extension'] !== '') { $encabezado[] = '☎️ Extensión: ' . $val['x_extension']; }
                if ($val['x_correo'] !== '') { $encabezado[] = '✉️ Correo: ' . $val['x_correo']; }
            }
        }
        if (!$error && $val['seccion'] === 'calendario') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val['x_fecha_cal'])
                || !checkdate((int) substr($val['x_fecha_cal'], 5, 2), (int) substr($val['x_fecha_cal'], 8, 2), (int) substr($val['x_fecha_cal'], 0, 4))) {
                $error = 'Elige el día del calendario en que debe aparecer.';
            } else {
                $encabezado[] = '🗓️ Día en el calendario: ' . date('d/m/Y', strtotime($val['x_fecha_cal']));
            }
        }
        $descripcionFinal = $val['descripcion'];
        if (!$error && $encabezado) {
            $descripcionFinal = implode("\n", $encabezado) . ($val['descripcion'] !== '' ? "\n\n" . $val['descripcion'] : '');
            if (mb_strlen($descripcionFinal) > 5000) {
                $error = 'La descripción es demasiado larga (máximo 5000 caracteres).';
            }
        }

        if (!$error && $esTutorial) {
            if (empty($_FILES['video']['name'])) {
                $error = 'Adjunta el video o GIF del tutorial.';
            } else {
                $error = validarArchivoOpcional('video', 'tutorial', 'el tutorial');
            }
        }

        if (!$error && $esPagina) {
            // Página adicional: hasta 2 imágenes y un video, todos opcionales.
            $error = validarArchivoOpcional('imagen', 'imagen', 'la imagen 1')
                ?: validarArchivoOpcional('imagen2', 'imagen', 'la imagen 2')
                ?: validarArchivoOpcional('video', 'video', 'el video');
        }

        if (!$error && $quiereImagen) {
            if (empty($_FILES['imagen']['name'])) {
                $error = 'Elegiste adjuntar una imagen, pero no seleccionaste ninguna.';
            } else {
                $error = validarArchivoOpcional('imagen', 'imagen', 'la imagen');
            }
        }

        if (!$error && $quierePdf) {
            $f = $_FILES['pdf'] ?? null;
            $ext = $f ? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) : '';
            if (!$f || empty($f['name'])) {
                $error = 'Elegiste adjuntar un PDF, pero no seleccionaste ninguno.';
            } elseif ($f['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir el PDF, intenta de nuevo.';
            } elseif ($ext !== 'pdf' || file_get_contents($f['tmp_name'], false, null, 0, 4) !== '%PDF') {
                $error = 'El archivo debe ser un PDF.';
            } elseif ($f['size'] > $tamanoMax) {
                $error = 'El PDF no puede pesar más de 5 MB.';
            }
        }

        $nombreImagen = $nombreImagen2 = $nombrePdf = $nombreVideo = null;

        if (!$error) {
            $carpeta = solicitudCarpeta();
            if ($quiereImagen || $esPagina) {
                $nombreImagen = guardarArchivoOpcional('imagen', 'sol_img_', $carpeta);
            }
            if ($esPagina) {
                $nombreImagen2 = guardarArchivoOpcional('imagen2', 'sol_img2_', $carpeta);
                $nombreVideo = guardarArchivoOpcional('video', 'sol_vid_', $carpeta);
            }
            if ($esTutorial) {
                $nombreVideo = guardarArchivoOpcional('video', 'sol_vid_', $carpeta);
            }
            if ($quierePdf) {
                $nombrePdf = uniqid('sol_pdf_', true) . '.pdf';
                if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $carpeta . '/' . $nombrePdf)) {
                    $nombrePdf = false;
                }
            }
            if ($nombreImagen === false || $nombreImagen2 === false || $nombreVideo === false || $nombrePdf === false) {
                $error = 'No se pudo guardar uno de los archivos.';
                solicitudBorrarArchivos(['imagen' => $nombreImagen ?: null, 'imagen2' => $nombreImagen2 ?: null, 'pdf' => $nombrePdf ?: null, 'video' => $nombreVideo ?: null]);
            }
        }

        if (!$error) {
            $e1t = $numEnlaces >= 1 ? $val['enlace1_texto'] : null;
            $e1u = $numEnlaces >= 1 ? $val['enlace1_url'] : null;
            $e2t = $numEnlaces >= 2 ? $val['enlace2_texto'] : null;
            $e2u = $numEnlaces >= 2 ? $val['enlace2_url'] : null;

            $stmt = mysqli_prepare($conexion, "INSERT INTO solicitudes_publicacion (usuario_id, seccion, categoria, titulo, descripcion, imagen, imagen2, pdf, video, enlace1_texto, enlace1_url, enlace2_texto, enlace2_url, fecha_requerida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "i" . str_repeat("s", 13), $yo['id'], $val['seccion'], $categoria, $val['titulo'], $descripcionFinal, $nombreImagen, $nombreImagen2, $nombrePdf, $nombreVideo, $e1t, $e1u, $e2t, $e2u, $val['fecha_requerida']);
            if (mysqli_stmt_execute($stmt)) {
                registrarBitacora($conexion, 'Solicitó una publicación', $val['seccion'] . ': ' . $val['titulo']);
                header('Location: mis-solicitudes.php?ok=1');
                exit;
            }
            solicitudBorrarArchivos(['imagen' => $nombreImagen, 'imagen2' => $nombreImagen2, 'pdf' => $nombrePdf, 'video' => $nombreVideo]);
            $error = 'No se pudo registrar la solicitud, intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Solicitar publicación</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📨 Solicitar publicación</h1>
    <p style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="mis-solicitudes.php" class="btn-secundario" style="text-decoration:none;">📋 Mis solicitudes</a>
        <a href="solicitar-cumpleanos.php" class="btn-secundario" style="text-decoration:none;">🎂 Solicitar un cumpleaños (formulario corto)</a>
    </p>
    <p style="max-width:640px; color:var(--text-muted);">¿Quieres que se publique un comunicado, evento, curso u otra cosa en la intranet? Llena este formulario y la Unidad de Apoyo Técnico e Informática (UATI) lo revisará. Te avisaremos aquí mismo si se acepta o se rechaza (y por qué).</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:640px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="max-width:640px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:16px 0 40px;">
        <div class="campo">
            <label for="seccion">¿En qué sección necesitas la publicación?</label>
            <select id="seccion" name="seccion" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Selecciona una sección…</option>
                <?php foreach ($SECCIONES as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>" <?= $val['seccion'] === $clave ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="ayuda-seccion" class="aviso-importante" style="display:none; margin:0 0 18px; font-size:14px;"></div>

        <div id="resto-formulario" style="display:none;">

        <div class="campo" id="campo-categoria" style="display:none;">
            <label for="categoria">Categoría del comunicado</label>
            <select id="categoria" name="categoria" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <?php foreach ($CATEGORIAS as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>" <?= $val['categoria'] === $clave ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
            <p style="font-size:12px; color:var(--text-muted); margin:6px 0 0;">Elige "Urgente" solo si de verdad lo es: avisa a todo el personal en la pantalla de inicio y la UATI decide si se publica así.</p>
        </div>

        <div class="campo">
            <label for="titulo" id="etiqueta-titulo">Título o asunto</label>
            <input id="titulo" name="titulo" type="text" maxlength="150" required value="<?= htmlspecialchars($val['titulo']) ?>">
        </div>

        <!-- Datos propios de un evento -->
        <div id="extras-evento" style="display:none;">
            <div class="campo">
                <label for="x_fecha_evento">¿Qué día será el evento?</label>
                <input id="x_fecha_evento" name="x_fecha_evento" type="date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($val['x_fecha_evento']) ?>">
            </div>
            <div class="campo">
                <label for="x_hora">Hora (opcional)</label>
                <input id="x_hora" name="x_hora" type="text" maxlength="60" placeholder="Ej. 10:00 am" value="<?= htmlspecialchars($val['x_hora']) ?>">
            </div>
            <div class="campo">
                <label for="x_lugar">Lugar (opcional)</label>
                <input id="x_lugar" name="x_lugar" type="text" maxlength="150" placeholder="Ej. Auditorio principal" value="<?= htmlspecialchars($val['x_lugar']) ?>">
            </div>
        </div>

        <!-- Datos propios de un curso -->
        <div id="extras-curso" style="display:none;">
            <div class="campo">
                <label for="x_institucion">¿Quién imparte el curso? (opcional)</label>
                <input id="x_institucion" name="x_institucion" type="text" maxlength="150" placeholder="Ej. INAP, CONOCER…" value="<?= htmlspecialchars($val['x_institucion']) ?>">
            </div>
            <div class="campo">
                <label for="x_modalidad">Modalidad (opcional)</label>
                <input id="x_modalidad" name="x_modalidad" type="text" maxlength="100" placeholder="Ej. En línea, presencial" value="<?= htmlspecialchars($val['x_modalidad']) ?>">
            </div>
            <div class="campo">
                <label for="x_vigencia">¿Hasta cuándo está disponible? (opcional)</label>
                <input id="x_vigencia" name="x_vigencia" type="text" maxlength="150" placeholder="Ej. Hasta el 30 de junio" value="<?= htmlspecialchars($val['x_vigencia']) ?>">
            </div>
        </div>

        <!-- Datos propios de un cambio en el directorio -->
        <div id="extras-directorio" style="display:none;">
            <div class="campo">
                <label for="x_movimiento">¿Qué necesitas?</label>
                <select id="x_movimiento" name="x_movimiento" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php foreach ($MOVIMIENTOS as $clave => $etiqueta): ?>
                        <option value="<?= $clave ?>" <?= $val['x_movimiento'] === $clave ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="x_unidad">Unidad o área a la que pertenece</label>
                <input id="x_unidad" name="x_unidad" type="text" maxlength="150" placeholder="Ej. Recursos Humanos" value="<?= htmlspecialchars($val['x_unidad']) ?>">
            </div>
            <div class="campo">
                <label for="x_extension">Extensión (opcional)</label>
                <input id="x_extension" name="x_extension" type="text" maxlength="60" value="<?= htmlspecialchars($val['x_extension']) ?>">
            </div>
            <div class="campo">
                <label for="x_correo">Correo (opcional)</label>
                <input id="x_correo" name="x_correo" type="text" maxlength="150" value="<?= htmlspecialchars($val['x_correo']) ?>">
            </div>
        </div>

        <!-- Datos propios de una fecha del calendario -->
        <div id="extras-calendario" style="display:none;">
            <div class="campo">
                <label for="x_fecha_cal">¿En qué día del calendario debe aparecer?</label>
                <input id="x_fecha_cal" name="x_fecha_cal" type="date" value="<?= htmlspecialchars($val['x_fecha_cal']) ?>">
            </div>
        </div>

        <div class="campo">
            <label for="descripcion" id="etiqueta-descripcion">¿Qué debe decir la publicación?</label>
            <textarea id="descripcion" name="descripcion" rows="5" maxlength="5000" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($val['descripcion']) ?></textarea>
        </div>

        <div class="campo">
            <label for="fecha_requerida" id="etiqueta-fecha">¿Para cuándo la necesitas?</label>
            <input id="fecha_requerida" name="fecha_requerida" type="date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($val['fecha_requerida']) ?>">
        </div>

        <div id="bloque-archivos-normal">
            <div class="campo">
                <label for="archivos">¿Necesitas adjuntar archivos?</label>
                <select id="archivos" name="archivos" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <option value="ninguno" <?= $val['archivos'] === 'ninguno' ? 'selected' : '' ?>>No, sin archivos</option>
                    <option value="imagen" <?= $val['archivos'] === 'imagen' ? 'selected' : '' ?>>Sí, una imagen</option>
                    <option value="pdf" <?= $val['archivos'] === 'pdf' ? 'selected' : '' ?>>Sí, un PDF</option>
                    <option value="ambos" <?= $val['archivos'] === 'ambos' ? 'selected' : '' ?>>Sí, imagen y PDF</option>
                </select>
            </div>
            <div class="campo" id="campo-imagen" style="display:none;">
                <label for="imagen">Imagen (jpg, png, gif o webp — máximo 5 MB)</label>
                <input id="imagen" name="imagen" type="file" accept="image/*">
            </div>
            <div class="campo" id="campo-pdf" style="display:none;">
                <label for="pdf">PDF (máximo 5 MB)</label>
                <input id="pdf" name="pdf" type="file" accept=".pdf,application/pdf">
            </div>
        </div>

        <div id="bloque-archivos-pagina" style="display:none;">
            <p style="font-size:13px; color:var(--text-muted); margin:0 0 10px;">Para la página adicional puedes mandar hasta <strong>2 imágenes</strong> y <strong>un video</strong> (todo opcional).</p>
            <div class="campo">
                <label for="pagina_imagen1">Imagen 1 (opcional — máximo 5 MB)</label>
                <input id="pagina_imagen1" type="file" accept="image/*">
            </div>
            <div class="campo">
                <label for="pagina_imagen2">Imagen 2 (opcional — máximo 5 MB)</label>
                <input id="pagina_imagen2" type="file" accept="image/*">
            </div>
            <div class="campo">
                <label for="pagina_video">Video (opcional — mp4, webm, mov u ogg, máximo 80 MB)</label>
                <input id="pagina_video" type="file" accept=".mp4,.webm,.mov,.ogg,video/*">
            </div>
        </div>

        <div id="bloque-archivos-tutorial" style="display:none;">
            <div class="campo">
                <label for="tutorial_video">Video o GIF del tutorial (mp4, webm, mov, ogg o gif — máximo 80 MB)</label>
                <input id="tutorial_video" type="file" accept=".mp4,.webm,.mov,.ogg,.gif,video/*,image/gif">
            </div>
        </div>

        <div id="bloque-enlaces">
        <div class="campo">
            <label for="num_enlaces" id="etiqueta-enlaces">¿Necesitas botones de enlace?</label>
            <select id="num_enlaces" name="num_enlaces" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="0" <?= $val['num_enlaces'] === '0' ? 'selected' : '' ?>>No, sin enlaces</option>
                <option value="1" <?= $val['num_enlaces'] === '1' ? 'selected' : '' ?>>Sí, 1 botón</option>
                <option value="2" id="opcion-2-enlaces" <?= $val['num_enlaces'] === '2' ? 'selected' : '' ?>>Sí, 2 botones</option>
            </select>
        </div>
        <div id="campo-enlace-1" style="display:none;">
            <div class="campo">
                <label for="enlace1_texto">Texto del botón 1</label>
                <input id="enlace1_texto" name="enlace1_texto" type="text" maxlength="100" placeholder="Ej. Inscribirme al curso" value="<?= htmlspecialchars($val['enlace1_texto']) ?>">
            </div>
            <div class="campo">
                <label for="enlace1_url">Dirección del botón 1</label>
                <input id="enlace1_url" name="enlace1_url" type="text" maxlength="255" placeholder="https://..." value="<?= htmlspecialchars($val['enlace1_url']) ?>">
            </div>
        </div>
        <div id="campo-enlace-2" style="display:none;">
            <div class="campo">
                <label for="enlace2_texto">Texto del botón 2</label>
                <input id="enlace2_texto" name="enlace2_texto" type="text" maxlength="100" placeholder="Ej. Ver más información" value="<?= htmlspecialchars($val['enlace2_texto']) ?>">
            </div>
            <div class="campo">
                <label for="enlace2_url">Dirección del botón 2</label>
                <input id="enlace2_url" name="enlace2_url" type="text" maxlength="255" placeholder="https://..." value="<?= htmlspecialchars($val['enlace2_url']) ?>">
            </div>
        </div>
        </div><!-- /bloque-enlaces -->

        <button type="submit" class="btn-primario">Enviar solicitud</button>
        </div><!-- /resto-formulario -->
    </form>
</div>

<script>
    // Los campos de archivo de cada modo solo llevan "name" cuando se están
    // usando, para que lo oculto no se mande junto con el formulario.
    function nombrarCampo(id, nombre, activo) {
        const el = document.getElementById(id);
        if (activo) { el.setAttribute('name', nombre); } else { el.removeAttribute('name'); el.value = ''; }
    }

    // Cómo cambia el formulario según lo que se quiere publicar.
    // archivos: normal (imagen/PDF opcionales) | pagina (2 imágenes y video) | tutorial (video o GIF obligatorio) | ninguno
    // enlaces: máximo de botones de enlace (0 = no se muestra la opción).
    const PERFILES = {
        comunicado: {
            ayuda: '📢 <strong>Comunicado:</strong> un aviso para todo el personal. Elige la categoría, escribe el texto y, si hace falta, adjunta una imagen o un PDF.',
            titulo: 'Título del comunicado', tituloEj: 'Ej. Suspensión de labores el viernes',
            descripcion: 'Texto del comunicado', descripcionEj: 'Escribe el aviso completo, tal como debe leerse.',
            fecha: '¿Para qué día necesitas que ya esté publicado?',
            archivos: 'normal', enlaces: 0, categoria: true,
        },
        evento: {
            ayuda: '📅 <strong>Evento:</strong> aparecerá en la sección Eventos y en el calendario. Indica el día, la hora y el lugar.',
            titulo: 'Nombre del evento', tituloEj: 'Ej. Conferencia de transparencia',
            descripcion: 'Descripción del evento', descripcionEj: 'De qué trata, a quién va dirigido y cualquier detalle que el personal deba saber.',
            fecha: '¿Para qué día necesitas que ya esté publicado?',
            archivos: 'normal', enlaces: 1, extras: 'evento',
            enlacesEtiqueta: '¿Quieres un botón de enlace? (por ejemplo, para registrarse)', enlaceEj: 'Ej. Registrarme al evento',
        },
        curso: {
            ayuda: '🎓 <strong>Curso:</strong> aparecerá en la sección Cursos. Los cursos no llevan imagen ni PDF; lo importante es el nombre, quién lo imparte y el enlace.',
            titulo: 'Nombre del curso', tituloEj: 'Ej. Introducción a la Ley de Transparencia',
            descripcion: 'Descripción del curso', descripcionEj: 'De qué trata, a quién va dirigido y cómo inscribirse.',
            fecha: '¿Para qué día necesitas que ya esté publicado?',
            archivos: 'ninguno', enlaces: 1, extras: 'curso',
            enlacesEtiqueta: '¿El curso tiene enlace para inscribirse o tomarlo?', enlaceEj: 'Ej. Ir al curso',
        },
        tutorial: {
            ayuda: '🎬 <strong>Tutorial:</strong> aparecerá en la sección Tutoriales con su video o GIF. Adjunta el archivo y explica brevemente qué enseña.',
            titulo: 'Título del tutorial', tituloEj: 'Ej. Cómo restablecer tu contraseña',
            descripcion: '¿De qué trata el tutorial?', descripcionEj: 'Explica brevemente qué se aprende o a quién le sirve.',
            fecha: '¿Para qué día necesitas que ya esté publicado?',
            archivos: 'tutorial', enlaces: 0,
        },
        directorio: {
            ayuda: '📇 <strong>Directorio:</strong> para agregar, corregir o quitar a una persona o área del directorio telefónico.',
            titulo: 'Puesto o nombre de la persona', tituloEj: 'Ej. Jefa del Departamento de Nóminas',
            descripcion: 'Detalles u observaciones (opcional)', descripcionEj: 'Cualquier dato extra que la UATI deba saber. Si es una corrección o una baja, indica qué contacto es y qué cambia.',
            descripcionOpcional: true,
            fecha: '¿Para qué día necesitas el cambio?',
            archivos: 'ninguno', enlaces: 0, extras: 'directorio',
        },
        calendario: {
            ayuda: '🗓️ <strong>Calendario:</strong> para que se agregue o cambie una fecha o actividad en el calendario.',
            titulo: 'Qué debe decir ese día en el calendario', tituloEj: 'Ej. Entrega de informes trimestrales',
            descripcion: 'Detalles (opcional)', descripcionEj: 'Horario, lugar o cualquier indicación importante.',
            descripcionOpcional: true,
            fecha: '¿Para qué día necesitas el cambio?',
            archivos: 'ninguno', enlaces: 0, extras: 'calendario',
        },
        pagina: {
            ayuda: '📄 <strong>Página adicional:</strong> la página especial de la intranet. Puedes mandar hasta 2 imágenes, un video y 2 botones de enlace.',
            titulo: 'Título de la página', tituloEj: '',
            descripcion: 'Contenido de la página', descripcionEj: 'Escribe el texto que debe llevar.',
            fecha: '¿Para qué día necesitas que ya esté publicada?',
            archivos: 'pagina', enlaces: 2,
            enlacesEtiqueta: '¿Necesitas botones de enlace?', enlaceEj: 'Ej. Ver más información',
        },
        otro: {
            ayuda: '✏️ <strong>Otra publicación:</strong> cuéntanos qué necesitas y la UATI te dirá dónde y cómo publicarlo.',
            titulo: 'Título o asunto', tituloEj: '',
            descripcion: '¿Qué necesitas que se publique?', descripcionEj: 'Explica con detalle qué debe decir y dónde lo necesitas.',
            fecha: '¿Para cuándo lo necesitas?',
            archivos: 'normal', enlaces: 2,
            enlacesEtiqueta: '¿Necesitas botones de enlace?', enlaceEj: 'Ej. Ver más información',
        },
    };

    function actualizarCamposSolicitud() {
        const seccion = document.getElementById('seccion').value;
        const perfil = PERFILES[seccion];

        // Mientras no se elija qué se quiere publicar, no se muestra el resto del formulario.
        document.getElementById('resto-formulario').style.display = perfil ? '' : 'none';
        const ayuda = document.getElementById('ayuda-seccion');
        ayuda.style.display = perfil ? '' : 'none';
        if (!perfil) { return; }
        ayuda.innerHTML = perfil.ayuda;

        const esPagina = perfil.archivos === 'pagina';
        const esTutorial = perfil.archivos === 'tutorial';
        const esNormal = perfil.archivos === 'normal';

        document.getElementById('etiqueta-titulo').textContent = perfil.titulo;
        document.getElementById('titulo').placeholder = perfil.tituloEj;
        document.getElementById('etiqueta-descripcion').textContent = perfil.descripcion;
        document.getElementById('descripcion').placeholder = perfil.descripcionEj;
        document.getElementById('etiqueta-fecha').textContent = perfil.fecha;

        // Datos propios de eventos y cursos.
        document.getElementById('extras-evento').style.display = perfil.extras === 'evento' ? '' : 'none';
        document.getElementById('extras-curso').style.display = perfil.extras === 'curso' ? '' : 'none';
        document.getElementById('extras-directorio').style.display = perfil.extras === 'directorio' ? '' : 'none';
        document.getElementById('extras-calendario').style.display = perfil.extras === 'calendario' ? '' : 'none';
        document.getElementById('x_fecha_evento').required = perfil.extras === 'evento';
        document.getElementById('x_unidad').required = perfil.extras === 'directorio';
        document.getElementById('x_fecha_cal').required = perfil.extras === 'calendario';
        document.getElementById('descripcion').required = !perfil.descripcionOpcional;

        document.getElementById('campo-categoria').style.display = perfil.categoria ? '' : 'none';
        document.getElementById('bloque-archivos-normal').style.display = esNormal ? '' : 'none';
        document.getElementById('bloque-archivos-pagina').style.display = esPagina ? '' : 'none';
        document.getElementById('bloque-archivos-tutorial').style.display = esTutorial ? '' : 'none';
        document.getElementById('tutorial_video').required = esTutorial;

        // Botones de enlace: solo en las secciones que los usan y hasta el máximo que admiten.
        document.getElementById('bloque-enlaces').style.display = perfil.enlaces > 0 ? '' : 'none';
        if (perfil.enlaces === 0) { document.getElementById('num_enlaces').value = '0'; }
        else {
            const sel = document.getElementById('num_enlaces');
            document.getElementById('opcion-2-enlaces').hidden = perfil.enlaces < 2;
            document.getElementById('opcion-2-enlaces').disabled = perfil.enlaces < 2;
            if (perfil.enlaces < 2 && sel.value === '2') { sel.value = '1'; }
            document.getElementById('etiqueta-enlaces').textContent = perfil.enlacesEtiqueta || '¿Necesitas botones de enlace?';
            document.getElementById('enlace1_texto').placeholder = perfil.enlaceEj || '';
        }

        const archivos = esNormal ? document.getElementById('archivos').value : 'ninguno';
        const verImagen = archivos === 'imagen' || archivos === 'ambos';
        const verPdf = archivos === 'pdf' || archivos === 'ambos';
        document.getElementById('campo-imagen').style.display = verImagen ? '' : 'none';
        document.getElementById('campo-pdf').style.display = verPdf ? '' : 'none';
        document.getElementById('imagen').required = verImagen;
        document.getElementById('pdf').required = verPdf;

        // "imagen" lo usan los dos modos; el resto solo en la página adicional.
        const imagenNormal = document.getElementById('imagen');
        const imagenPagina = document.getElementById('pagina_imagen1');
        if (esPagina) { imagenNormal.removeAttribute('name'); imagenNormal.value = ''; imagenPagina.setAttribute('name', 'imagen'); }
        else { imagenPagina.removeAttribute('name'); imagenPagina.value = ''; imagenNormal.setAttribute('name', 'imagen'); }
        nombrarCampo('pagina_imagen2', 'imagen2', esPagina);
        // El video de la página adicional y el del tutorial comparten el nombre "video"; solo uno está activo.
        nombrarCampo('pagina_video', 'video', esPagina);
        nombrarCampo('tutorial_video', 'video', esTutorial);

        const n = Number(document.getElementById('num_enlaces').value);
        [1, 2].forEach(i => {
            const visible = n >= i;
            document.getElementById('campo-enlace-' + i).style.display = visible ? '' : 'none';
            document.getElementById('enlace' + i + '_texto').required = visible;
            document.getElementById('enlace' + i + '_url').required = visible;
        });
    }
    ['seccion', 'archivos', 'num_enlaces'].forEach(id => document.getElementById(id).addEventListener('change', actualizarCamposSolicitud));
    actualizarCamposSolicitud();
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
