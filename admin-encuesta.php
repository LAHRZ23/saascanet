<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$tiposNombre = [
    'sugerencia' => '💡 Sugerencia',
    'cambio' => '🔧 Cambio',
    'opinion' => '💬 Opinión',
    'error' => '🐞 Falla',
];

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_config') {
        $activa = !empty($_POST['activa']) ? 1 : 0;
        $intro = trim($_POST['introduccion'] ?? '');
        $antes = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT activa FROM encuesta_config WHERE id = 1"));
        // Al activarla de nuevo empieza una "vuelta" nueva: el aviso del inicio vuelve a salirle a todos.
        $subeVersion = ($activa && $antes && !$antes['activa']) ? 1 : 0;
        $stmt = mysqli_prepare($conexion, "UPDATE encuesta_config SET activa = ?, introduccion = ?, actualizado_por = ?, version = version + ? WHERE id = 1");
        mysqli_stmt_bind_param($stmt, "isii", $activa, $intro, $yo['id'], $subeVersion);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, $subeVersion ? 'Activó la encuesta beta' : ($activa ? 'Actualizó el mensaje de la encuesta beta' : 'Desactivó la encuesta beta'), $subeVersion ? 'Cuéntanos tus sugerencias y opiniones' : null);
        $exito = $activa ? 'La encuesta ya está activa: todo el personal ve el botón "Encuesta".' : 'La encuesta se desactivó: nadie puede responderla por ahora.';
    } elseif ($accion === 'estado') {
        $id = (int) $_POST['id'];
        mysqli_query($conexion, "UPDATE encuesta_respuestas SET estado = IF(estado = 'nueva', 'revisada', 'nueva') WHERE id = $id");
    } elseif ($accion === 'marcar_todas') {
        mysqli_query($conexion, "UPDATE encuesta_respuestas SET estado = 'revisada' WHERE estado = 'nueva'");
        $exito = 'Todas las respuestas se marcaron como revisadas.';
    } elseif ($accion === 'eliminar') {
        $id = (int) $_POST['id'];
        mysqli_query($conexion, "DELETE FROM encuesta_respuestas WHERE id = $id");
        registrarBitacora($conexion, 'Eliminó una respuesta de la encuesta');
        $exito = 'Respuesta eliminada.';
    } elseif ($accion === 'vaciar') {
        mysqli_query($conexion, "DELETE FROM encuesta_respuestas");
        registrarBitacora($conexion, 'Vació las respuestas de la encuesta');
        $exito = 'Se borraron todas las respuestas.';
    }
}

$config = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT activa, introduccion FROM encuesta_config WHERE id = 1"));

$total = 0;
$nuevas = 0;
$porTipo = ['sugerencia' => 0, 'cambio' => 0, 'opinion' => 0, 'error' => 0];
$promedio = null;
$totalValoraciones = 0;

$r = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS total, SUM(estado = 'nueva') AS nuevas, AVG(valoracion) AS promedio, COUNT(valoracion) AS con_valoracion FROM encuesta_respuestas"));
$total = (int) $r['total'];
$nuevas = (int) $r['nuevas'];
$promedio = $r['promedio'] !== null ? round((float) $r['promedio'], 1) : null;
$totalValoraciones = (int) $r['con_valoracion'];

$resTipos = mysqli_query($conexion, "SELECT tipo, COUNT(*) AS n FROM encuesta_respuestas GROUP BY tipo");
while ($f = mysqli_fetch_assoc($resTipos)) {
    $porTipo[$f['tipo']] = (int) $f['n'];
}

$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';
$condiciones = [];
if (isset($tiposNombre[$filtroTipo])) {
    $condiciones[] = "e.tipo = '" . mysqli_real_escape_string($conexion, $filtroTipo) . "'";
}
$filtroCalificacion = $_GET['calificacion'] ?? '';
$rangosCalificacion = [
    'baja' => 'e.valoracion BETWEEN 1 AND 2',
    'media' => 'e.valoracion = 3',
    'alta' => 'e.valoracion BETWEEN 4 AND 5',
    'sin' => 'e.valoracion IS NULL',
];
if (isset($rangosCalificacion[$filtroCalificacion])) {
    $condiciones[] = $rangosCalificacion[$filtroCalificacion];
}
if (in_array($filtroEstado, ['nueva', 'revisada'], true)) {
    $condiciones[] = "e.estado = '$filtroEstado'";
}
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$respuestas = [];
$resultado = mysqli_query($conexion, "SELECT e.*, u.nombre AS usuario_nombre, u.area AS usuario_area
    FROM encuesta_respuestas e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    $where
    ORDER BY e.creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $respuestas[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Encuesta beta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📝 Encuesta beta</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="encuesta.php" class="enlace-ver">Ver encuesta</a> &nbsp;·&nbsp; <a href="admin-calidad.php" class="enlace-ver">⭐ Calidad de atención</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:700px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:700px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <form method="post" style="max-width:700px; background:var(--card-bg); padding:20px 24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
        <input type="hidden" name="accion" value="guardar_config">
        <h3 style="margin-top:0; color:var(--guinda);">Encuesta para el personal</h3>
        <p style="margin-top:-6px;">Estado: <strong><?= $config['activa'] ? '🟢 Activa (el personal ve el botón "Encuesta")' : '🔴 Desactivada (oculta para todos)' ?></strong></p>
        <div class="campo">
            <label for="introduccion">Mensaje de bienvenida que verá el personal</label>
            <textarea id="introduccion" name="introduccion" rows="3" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text); font-family:var(--font-body); resize:vertical;"><?= htmlspecialchars($config['introduccion'] ?? '') ?></textarea>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
            <?php if ($config['activa']): ?>
                <button type="submit" name="activa" value="1" class="btn-primario" style="width:auto; padding:10px 18px;">Guardar mensaje</button>
                <button type="submit" name="activa" value="" class="btn-secundario" style="border:none; cursor:pointer;">Desactivar encuesta</button>
            <?php else: ?>
                <button type="submit" name="activa" value="1" class="btn-primario" style="width:auto; padding:10px 18px;">Activar encuesta</button>
                <button type="submit" name="activa" value="" class="btn-secundario" style="border:none; cursor:pointer;">Solo guardar mensaje</button>
            <?php endif; ?>
        </div>
    </form>

    <h2>📊 Reporte de respuestas</h2>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; max-width:900px; margin-bottom:20px;">
        <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= $total ?></h3><p style="margin:0;">Respuestas</p></div>
        <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= $nuevas ?></h3><p style="margin:0;">Sin revisar</p></div>
        <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= $promedio !== null ? $promedio . ' ⭐' : '—' ?></h3><p style="margin:0;">Calificación (<?= $totalValoraciones ?>)</p></div>
        <?php foreach ($tiposNombre as $clave => $nombre): ?>
            <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= $porTipo[$clave] ?></h3><p style="margin:0;"><?= $nombre ?></p></div>
        <?php endforeach; ?>
    </div>

    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
        <select name="tipo" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todos los tipos</option>
            <?php foreach ($tiposNombre as $clave => $nombre): ?>
                <option value="<?= $clave ?>" <?= $filtroTipo === $clave ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php endforeach; ?>
        </select>
        <select name="calificacion" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todas las calificaciones</option>
            <option value="baja" <?= $filtroCalificacion === 'baja' ? 'selected' : '' ?>>⭐ Baja (1-2 estrellas)</option>
            <option value="media" <?= $filtroCalificacion === 'media' ? 'selected' : '' ?>>⭐⭐⭐ Media (3 estrellas)</option>
            <option value="alta" <?= $filtroCalificacion === 'alta' ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ Alta (4-5 estrellas)</option>
            <option value="sin" <?= $filtroCalificacion === 'sin' ? 'selected' : '' ?>>Sin calificación</option>
        </select>
        <select name="estado" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todas</option>
            <option value="nueva" <?= $filtroEstado === 'nueva' ? 'selected' : '' ?>>Sin revisar</option>
            <option value="revisada" <?= $filtroEstado === 'revisada' ? 'selected' : '' ?>>Revisadas</option>
        </select>
        <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Filtrar</button>
        <?php if ($total > 0): ?>
            <a href="admin-encuesta-exportar.php" class="btn-secundario">⬇️ Descargar (CSV)</a>
        <?php endif; ?>
    </form>

    <?php if ($nuevas > 0): ?>
        <form method="post" style="margin-bottom:14px;">
            <input type="hidden" name="accion" value="marcar_todas">
            <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">✅ Marcar todas como revisadas</button>
        </form>
    <?php endif; ?>

    <?php if (!$respuestas): ?>
        <p style="color:var(--text-muted);">No hay respuestas<?= ($filtroTipo || $filtroEstado || $filtroCalificacion) ? ' con ese filtro' : ' todavía' ?>.</p>
    <?php endif; ?>

    <?php foreach ($respuestas as $resp): ?>
        <div class="caja-accion" style="max-width:900px; margin-bottom:12px; <?= $resp['estado'] === 'nueva' ? 'border-left:5px solid var(--dorado);' : '' ?>">
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:8px;">
                <strong><?= $tiposNombre[$resp['tipo']] ?></strong>
                <?php if ($resp['valoracion']): ?><span><?= (int) $resp['valoracion'] ?> ⭐</span><?php endif; ?>
                <?php if ($resp['seccion']): ?><span style="background:rgba(122,31,51,0.1); color:var(--guinda); padding:2px 10px; border-radius:999px; font-size:12px; font-weight:700;"><?= htmlspecialchars($resp['seccion']) ?></span><?php endif; ?>
                <?php if ($resp['estado'] === 'nueva'): ?><span style="background:var(--dorado); color:var(--guinda-dark); padding:2px 10px; border-radius:999px; font-size:12px; font-weight:800;">NUEVA</span><?php endif; ?>
            </div>
            <p style="font-size:15px; color:var(--text); margin:0 0 10px; white-space:pre-wrap;"><?= htmlspecialchars($resp['mensaje']) ?></p>
            <p style="font-size:12px; margin:0 0 10px;">
                <?php if ($resp['usuario_id']): ?>
                    De: <strong><?= htmlspecialchars($resp['usuario_nombre'] ?? '') ?></strong><?= $resp['usuario_area'] ? ' · ' . htmlspecialchars($resp['usuario_area']) : '' ?>
                <?php elseif ($resp['usuario_nombre'] === null): ?>
                    De: <strong>Anónimo</strong>
                <?php endif; ?>
                · <?= date('d/m/Y H:i', strtotime($resp['creado_en'])) ?>
            </p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <form method="post">
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id" value="<?= (int) $resp['id'] ?>">
                    <button type="submit" class="btn-secundario" style="border:none; cursor:pointer; padding:6px 14px; font-size:13px;"><?= $resp['estado'] === 'nueva' ? '✅ Marcar como revisada' : '↩️ Marcar como nueva' ?></button>
                </form>
                <form method="post" onsubmit="return confirm('¿Eliminar esta respuesta?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $resp['id'] ?>">
                    <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($total > 0): ?>
        <form method="post" onsubmit="return confirm('¿Borrar TODAS las respuestas de la encuesta? Descarga el CSV antes si quieres conservarlas. Esto no se puede deshacer.');" style="margin:20px 0 40px;">
            <input type="hidden" name="accion" value="vaciar">
            <button type="submit" class="btn-peligro">🗑 Borrar todas las respuestas (<?= $total ?>)</button>
        </form>
    <?php endif; ?>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
