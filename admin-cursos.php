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
    $institucion = trim($_POST['institucion'] ?? '') ?: null;
    $modalidad = trim($_POST['modalidad'] ?? '') ?: null;
    $estadoTexto = trim($_POST['estado_texto'] ?? '') ?: null;
    $fechaVigencia = trim($_POST['fecha_vigencia'] ?? '') ?: null;
    $enlace = trim($_POST['enlace'] ?? '') ?: null;

    if ($titulo === '') {
        $error = 'El título del curso es obligatorio.';
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO cursos (titulo, institucion, modalidad, estado_texto, fecha_vigencia, enlace, publicado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssssi", $titulo, $institucion, $modalidad, $estadoTexto, $fechaVigencia, $enlace, $yo['id']);
        if (mysqli_stmt_execute($stmt)) {
            $idCurso = mysqli_insert_id($conexion);
            registrarBitacora($conexion, 'Agregó curso', $titulo, $idCurso);
            $exito = 'Curso agregado.';
            if ($solPublicar) {
                solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'cursos', $idCurso);
                $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                $solPublicar = null;
                $solicitudPublicada = true;
            }
        } else {
            $error = 'No se pudo agregar el curso.';
        }
    }
}

// Editar un curso existente (corrige título, institución, enlace, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $id = (int) $_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $institucion = trim($_POST['institucion'] ?? '') ?: null;
    $modalidad = trim($_POST['modalidad'] ?? '') ?: null;
    $estadoTexto = trim($_POST['estado_texto'] ?? '') ?: null;
    $fechaVigencia = trim($_POST['fecha_vigencia'] ?? '') ?: null;
    $enlace = trim($_POST['enlace'] ?? '') ?: null;

    if ($titulo === '') {
        $error = 'El título del curso es obligatorio.';
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE cursos SET titulo = ?, institucion = ?, modalidad = ?, estado_texto = ?, fecha_vigencia = ?, enlace = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $titulo, $institucion, $modalidad, $estadoTexto, $fechaVigencia, $enlace, $id);
        if (mysqli_stmt_execute($stmt)) {
            registrarBitacora($conexion, 'Editó curso', $titulo);
            $exito = 'Curso actualizado.';
        } else {
            $error = 'No se pudo actualizar el curso.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ocultar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo FROM cursos WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $tituloCurso = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT)) ?: [])['titulo'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "UPDATE cursos SET vigente = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Ocultó curso', $tituloCurso);
    $exito = 'Curso ocultado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int) $_POST['id'];
    $stmtT = mysqli_prepare($conexion, "SELECT titulo FROM cursos WHERE id = ?");
    mysqli_stmt_bind_param($stmtT, "i", $id);
    mysqli_stmt_execute($stmtT);
    $tituloCurso = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT)) ?: [])['titulo'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "DELETE FROM cursos WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Eliminó curso', $tituloCurso);
    $exito = 'Curso eliminado.';
}

$cursos = [];
$resultado = mysqli_query($conexion, "SELECT * FROM cursos ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $cursos[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Agregar cursos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>➕ Agregar curso</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="cursos.php" class="enlace-ver">Ver Cursos</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario abre con el título y el enlace; los cursos no
    // tienen campo de descripción, por eso todo lo que pidió se ve en el recuadro.
    $pre = ['titulo' => '', 'enlace' => '', 'institucion' => '', 'modalidad' => '', 'estado_texto' => ''];
    $datosSol = [];
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar') {
            foreach ($pre as $k => $_) { $pre[$k] = $_POST[$k] ?? ''; }
        } else {
            $datosSol = solicitudDatosEncabezado($solPublicar);
            $pre['titulo'] = $solPublicar['titulo'];
            $pre['enlace'] = $solPublicar['enlace1_url'] ?? '';
            $pre['institucion'] = $datosSol['institucion'] ?? '';
            $pre['modalidad'] = $datosSol['modalidad'] ?? '';
            $pre['estado_texto'] = $datosSol['vigencia'] ?? '';
        }
        solicitudMostrarBanner($solPublicar, 'Ya se llenaron título, institución, modalidad, vigencia y enlace con lo que pidió. Los cursos no llevan imagen, PDF ni descripción. Si quieres que avise cuando esté por vencer, captura la fecha de vigencia.');
    }
    ?>

    <form method="post" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0 40px;">
        <input type="hidden" name="accion" value="publicar">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, false, false); ?>
        <div class="campo">
            <label for="titulo">Título del curso</label>
            <input id="titulo" name="titulo" type="text" required value="<?= htmlspecialchars($pre['titulo']) ?>">
        </div>
        <div class="campo">
            <label for="institucion">Institución (opcional)</label>
            <input id="institucion" name="institucion" type="text" value="<?= htmlspecialchars($pre['institucion'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="modalidad">Modalidad (opcional)</label>
            <input id="modalidad" name="modalidad" type="text" placeholder="Ej. En línea" value="<?= htmlspecialchars($pre['modalidad'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="estado_texto">Estado / vigencia (opcional)</label>
            <input id="estado_texto" name="estado_texto" type="text" placeholder="Ej. Disponible hasta el 30 de junio de 2026" value="<?= htmlspecialchars($pre['estado_texto'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="fecha_vigencia">Fecha de vigencia (opcional)</label>
            <input id="fecha_vigencia" name="fecha_vigencia" type="date">
            <p style="font-size:12px; color:var(--text-muted); margin:4px 0 0;">Si la pones, en Cursos aparecerá automáticamente un aviso de "Vence hoy" o "Vence pronto" cuando se acerque esa fecha.</p>
        </div>
        <div class="campo">
            <label for="enlace">Enlace al curso (opcional)</label>
            <input id="enlace" name="enlace" type="text" placeholder="https://..." value="<?= htmlspecialchars($pre['enlace']) ?>">
        </div>
        <button type="submit" class="btn-primario">Agregar curso</button>
    </form>

    <h2>Cursos publicados</h2>
    <div class="contenedor-acciones">
        <?php foreach ($cursos as $c): ?>
            <div class="caja-accion">
                <h3><?= htmlspecialchars($c['titulo']) ?></h3>
                <p><?= $c['vigente'] ? 'Vigente' : 'Oculto' ?></p>
                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                    <?php if ($c['vigente']): ?>
                        <form method="post" onsubmit="return confirm('¿Ocultar este curso?');">
                            <input type="hidden" name="accion" value="ocultar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Ocultar</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('¿Eliminar este curso para siempre?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                    </form>
                </div>

                <details style="margin-top:10px; text-align:left;">
                    <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">✏️ Editar</summary>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <div class="campo">
                            <label>Título</label>
                            <input name="titulo" type="text" required value="<?= htmlspecialchars($c['titulo']) ?>">
                        </div>
                        <div class="campo">
                            <label>Institución</label>
                            <input name="institucion" type="text" value="<?= htmlspecialchars($c['institucion'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Modalidad</label>
                            <input name="modalidad" type="text" value="<?= htmlspecialchars($c['modalidad'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Estado / vigencia</label>
                            <input name="estado_texto" type="text" value="<?= htmlspecialchars($c['estado_texto'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Fecha de vigencia</label>
                            <input name="fecha_vigencia" type="date" value="<?= htmlspecialchars($c['fecha_vigencia'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Enlace al curso</label>
                            <input name="enlace" type="text" value="<?= htmlspecialchars($c['enlace'] ?? '') ?>">
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
