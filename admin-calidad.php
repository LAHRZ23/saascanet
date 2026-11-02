<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/calidad_preguntas.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'nueva_ronda') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $error = 'Ponle un nombre a la encuesta, por ejemplo "Octubre 2026".';
        } else {
            mysqli_query($conexion, "UPDATE calidad_rondas SET activa = 0, cerrada_en = NOW() WHERE activa = 1");
            $stmt = mysqli_prepare($conexion, "INSERT INTO calidad_rondas (nombre, creado_por) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "si", $nombre, $yo['id']);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Abrió encuesta de calidad de atención', $nombre);
            $exito = "Se abrió la encuesta \"$nombre\". El personal ya ve el botón \"Calidad\".";
        }
    } elseif ($accion === 'cerrar_ronda') {
        mysqli_query($conexion, "UPDATE calidad_rondas SET activa = 0, cerrada_en = NOW() WHERE activa = 1");
        registrarBitacora($conexion, 'Cerró la encuesta de calidad de atención');
        $exito = 'La encuesta se cerró. Ya nadie puede responderla y tus resultados se conservan.';
    } elseif ($accion === 'guardar_preguntas') {
        $marcadas = $_POST['preguntas'] ?? [];
        foreach (array_keys($CALIDAD_PREGUNTAS) as $clave) {
            $activa = in_array($clave, (array) $marcadas, true) ? 1 : 0;
            $stmt = mysqli_prepare($conexion, "UPDATE calidad_preguntas SET activa = ? WHERE clave = ?");
            mysqli_stmt_bind_param($stmt, "is", $activa, $clave);
            mysqli_stmt_execute($stmt);
        }
        registrarBitacora($conexion, 'Cambió las preguntas de la encuesta de calidad');
        $exito = 'Preguntas guardadas.';
    } elseif ($accion === 'eliminar_ronda') {
        $id = (int) $_POST['id'];
        $stmt = mysqli_prepare($conexion, "SELECT nombre FROM calidad_rondas WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $stmt = mysqli_prepare($conexion, "DELETE FROM calidad_rondas WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Eliminó una encuesta de calidad y sus respuestas', $fila['nombre'] ?? "id $id");
        $exito = 'Encuesta eliminada junto con sus respuestas.';
    }
}

$rondaActiva = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM calidad_rondas WHERE activa = 1 ORDER BY id DESC LIMIT 1"));

$estadoPreguntas = [];
$resPreg = mysqli_query($conexion, "SELECT clave, activa FROM calidad_preguntas");
while ($f = mysqli_fetch_assoc($resPreg)) {
    $estadoPreguntas[$f['clave']] = (int) $f['activa'];
}

$rondas = [];
$resRondas = mysqli_query($conexion, "SELECT r.*, COUNT(x.id) AS total,
        AVG(x.satisfaccion) AS prom_satisfaccion, AVG(x.calidad_atencion) AS prom_calidad, AVG(x.tiempo_espera) AS prom_espera
    FROM calidad_rondas r
    LEFT JOIN calidad_respuestas x ON x.ronda_id = r.id
    GROUP BY r.id
    ORDER BY r.id DESC");
while ($f = mysqli_fetch_assoc($resRondas)) {
    $rondas[] = $f;
}

$rondaId = isset($_GET['ronda']) ? (int) $_GET['ronda'] : ($rondas[0]['id'] ?? 0);
$rondaVista = null;
foreach ($rondas as $r) {
    if ((int) $r['id'] === $rondaId) $rondaVista = $r;
}

$promedios = [];
$resolvio = ['si' => 0, 'parcial' => 0, 'no' => 0];
$fallas = [];
$comentarios = [];
$totalParticipaciones = 0;

if ($rondaVista) {
    $cols = [];
    foreach ($CALIDAD_PREGUNTAS as $clave => $p) {
        if ($p['tipo'] === 'estrellas') $cols[] = "AVG($clave) AS $clave, COUNT($clave) AS n_$clave";
    }
    $stmt = mysqli_prepare($conexion, "SELECT " . implode(', ', $cols) . " FROM calidad_respuestas WHERE ronda_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $rondaId);
    mysqli_stmt_execute($stmt);
    $prom = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    foreach ($CALIDAD_PREGUNTAS as $clave => $p) {
        if ($p['tipo'] === 'estrellas') {
            $promedios[$clave] = ['valor' => $prom[$clave] !== null ? round((float) $prom[$clave], 1) : null, 'n' => (int) $prom["n_$clave"]];
        }
    }

    $stmt = mysqli_prepare($conexion, "SELECT resolvio, COUNT(*) AS n FROM calidad_respuestas WHERE ronda_id = ? AND resolvio IS NOT NULL GROUP BY resolvio");
    mysqli_stmt_bind_param($stmt, "i", $rondaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($f = mysqli_fetch_assoc($res)) $resolvio[$f['resolvio']] = (int) $f['n'];

    $stmt = mysqli_prepare($conexion, "SELECT falla_frecuente, COUNT(*) AS n FROM calidad_respuestas WHERE ronda_id = ? AND falla_frecuente IS NOT NULL GROUP BY falla_frecuente ORDER BY n DESC");
    mysqli_stmt_bind_param($stmt, "i", $rondaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($f = mysqli_fetch_assoc($res)) $fallas[] = $f;

    $stmt = mysqli_prepare($conexion, "SELECT queja, sugerencia, creado_en FROM calidad_respuestas WHERE ronda_id = ? AND (queja IS NOT NULL OR sugerencia IS NOT NULL) ORDER BY creado_en DESC");
    mysqli_stmt_bind_param($stmt, "i", $rondaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($f = mysqli_fetch_assoc($res)) $comentarios[] = $f;

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM calidad_participaciones WHERE ronda_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $rondaId);
    mysqli_stmt_execute($stmt);
    $totalParticipaciones = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
}
$totalUsuarios = (int) mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuarios WHERE activo = 1"))['n'];

function estrellasTexto($v) {
    return $v === null ? '—' : number_format($v, 1) . ' ⭐';
}
function colorPromedio($v) {
    if ($v === null) return 'var(--text)';
    return $v >= 4 ? '#2E7D4F' : ($v >= 3 ? '#B7791F' : '#B23A3A');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Calidad de atención</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
    <style>
        .solo-imprimir { display: none; }
        #resultados { scroll-margin-top: 20px; }
        @media print {
            .solo-imprimir { display: block !important; }
            .caja-accion { break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>⭐ Calidad de atención</h1>
    <p class="no-imprimir"><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="admin-encuesta.php" class="enlace-ver">Encuesta beta (sugerencias)</a> &nbsp;·&nbsp; <a href="calidad.php" class="enlace-ver">Ver encuesta</a></p>
    <p class="no-imprimir" style="max-width:700px; color:var(--text-muted);">Encuesta anónima sobre la Mesa de Servicios que puedes lanzar cada cierto tiempo. Cada lanzamiento es una "ronda" con su propio nombre, así puedes comparar los resultados entre una y otra.</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:700px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:700px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <div class="no-imprimir" style="max-width:700px; background:var(--card-bg); padding:20px 24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
        <h3 style="margin-top:0; color:var(--guinda);">Estado de la encuesta</h3>
        <?php if ($rondaActiva): ?>
            <p>🟢 Abierta: <strong><?= htmlspecialchars($rondaActiva['nombre']) ?></strong> (desde el <?= date('d/m/Y', strtotime($rondaActiva['creado_en'])) ?>). El personal ve el botón "Calidad" hasta que conteste.</p>
            <form method="post" onsubmit="return confirm('¿Cerrar la encuesta? Nadie podrá responderla, pero los resultados se conservan.');">
                <input type="hidden" name="accion" value="cerrar_ronda">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Cerrar encuesta</button>
            </form>
        <?php else: ?>
            <p>🔴 No hay ninguna encuesta abierta. Ponle un nombre y ábrela para que el personal pueda contestar:</p>
        <?php endif; ?>
        <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:14px;">
            <input type="hidden" name="accion" value="nueva_ronda">
            <div class="campo" style="flex:1; min-width:200px;">
                <label for="nombre"><?= $rondaActiva ? 'O abre una nueva (cierra la actual)' : 'Nombre de la encuesta' ?></label>
                <input id="nombre" name="nombre" type="text" maxlength="100" placeholder="Ej. Octubre 2026" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text);">
            </div>
            <button type="submit" class="btn-primario" style="width:auto; padding:12px 18px;">Abrir encuesta</button>
        </form>
    </div>

    <form method="post" class="no-imprimir" style="max-width:700px; background:var(--card-bg); padding:20px 24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
        <input type="hidden" name="accion" value="guardar_preguntas">
        <h3 style="margin-top:0; color:var(--guinda);">Preguntas que se muestran</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-top:-6px;">Palomea las que quieras incluir. Puedes cambiarlas cuando quieras; lo que ya se contestó no se pierde.</p>
        <?php foreach ($CALIDAD_PREGUNTAS as $clave => $p): ?>
            <label style="display:flex; align-items:flex-start; gap:10px; padding:6px 0; cursor:pointer;">
                <input type="checkbox" name="preguntas[]" value="<?= $clave ?>" <?= !empty($estadoPreguntas[$clave]) ? 'checked' : '' ?> style="width:auto; margin:4px 0 0;">
                <span><?= htmlspecialchars($p['texto']) ?></span>
            </label>
        <?php endforeach; ?>
        <button type="submit" class="btn-secundario" style="border:none; cursor:pointer; margin-top:10px;">Guardar preguntas</button>
    </form>

    <h2 id="resultados">📊 Resultados</h2>
    <?php if (!$rondas): ?>
        <p style="color:var(--text-muted);">Todavía no has abierto ninguna encuesta.</p>
    <?php else: ?>
        <form method="get" action="#resultados" class="no-imprimir" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
            <select name="ronda" onchange="this.form.submit()" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <?php foreach ($rondas as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $rondaId ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?><?= $r['activa'] ? ' (abierta)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Ver</button></noscript>
            <?php if ($rondaVista && $rondaVista['total'] > 0): ?>
                <a href="admin-calidad-exportar.php?ronda=<?= $rondaId ?>" class="btn-secundario">⬇️ Descargar (CSV)</a>
                <button type="button" class="enlace-ver" onclick="window.print()" style="border:none; cursor:pointer;">🖨️ Imprimir resultados</button>
            <?php endif; ?>
        </form>

        <?php if ($rondaVista): ?>
            <div class="solo-imprimir" style="margin-bottom:14px;">
                <h2 style="margin:0;">Encuesta de calidad de atención: <?= htmlspecialchars($rondaVista['nombre']) ?></h2>
                <p style="margin:4px 0 0; font-size:13px;">Intranet SAASCAEM · Impreso el <?= date('d/m/Y H:i') ?></p>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; max-width:900px; margin-bottom:20px;">
                <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= (int) $rondaVista['total'] ?></h3><p style="margin:0;">Respuestas</p></div>
                <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px;"><?= $totalUsuarios > 0 ? round($totalParticipaciones * 100 / $totalUsuarios) : 0 ?>%</h3><p style="margin:0;">Participación (<?= $totalParticipaciones ?> de <?= $totalUsuarios ?>)</p></div>
                <?php foreach ($promedios as $clave => $d): ?>
                    <div class="caja-accion" style="text-align:center;"><h3 style="margin:0; font-size:28px; color:<?= colorPromedio($d['valor']) ?>;"><?= estrellasTexto($d['valor']) ?></h3><p style="margin:0;"><?= htmlspecialchars($CALIDAD_PREGUNTAS[$clave]['corto']) ?></p></div>
                <?php endforeach; ?>
            </div>

            <?php if (array_sum($resolvio) > 0): ?>
                <div class="caja-accion" style="max-width:900px; margin-bottom:14px;">
                    <h3>¿Se resolvió el problema?</h3>
                    <?php $tr = array_sum($resolvio); foreach ($CALIDAD_RESOLVIO as $k => $etq): $pct = round($resolvio[$k] * 100 / $tr); ?>
                        <div style="display:flex; align-items:center; gap:10px; margin:6px 0; font-size:14px;">
                            <span style="width:130px;"><?= htmlspecialchars($etq) ?></span>
                            <div style="flex:1; background:rgba(0,0,0,0.08); border-radius:999px; height:14px; overflow:hidden;"><div style="width:<?= $pct ?>%; height:100%; background:var(--guinda);"></div></div>
                            <span style="width:80px; text-align:right;"><?= $resolvio[$k] ?> (<?= $pct ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($fallas): $maxF = $fallas[0]['n']; ?>
                <div class="caja-accion" style="max-width:900px; margin-bottom:14px;">
                    <h3>Fallas más frecuentes</h3>
                    <?php foreach ($fallas as $f): ?>
                        <div style="display:flex; align-items:center; gap:10px; margin:6px 0; font-size:14px;">
                            <span style="width:220px;"><?= htmlspecialchars($f['falla_frecuente']) ?></span>
                            <div style="flex:1; background:rgba(0,0,0,0.08); border-radius:999px; height:14px; overflow:hidden;"><div style="width:<?= round($f['n'] * 100 / $maxF) ?>%; height:100%; background:var(--dorado);"></div></div>
                            <span style="width:40px; text-align:right;"><?= (int) $f['n'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($comentarios): ?>
                <div class="caja-accion" style="max-width:900px; margin-bottom:14px;">
                    <h3>Quejas y sugerencias (anónimas)</h3>
                    <?php foreach ($comentarios as $c): ?>
                        <div style="border-top:1px solid var(--border); padding:10px 0; font-size:14px;">
                            <?php if ($c['queja']): ?><p style="margin:0 0 4px; color:var(--text); white-space:pre-wrap;"><strong style="color:#B23A3A;">Queja:</strong> <?= htmlspecialchars($c['queja']) ?></p><?php endif; ?>
                            <?php if ($c['sugerencia']): ?><p style="margin:0 0 4px; color:var(--text); white-space:pre-wrap;"><strong style="color:#1E5AA8;">Sugerencia:</strong> <?= htmlspecialchars($c['sugerencia']) ?></p><?php endif; ?>
                            <span style="font-size:12px; color:var(--text-muted);"><?= date('d/m/Y', strtotime($c['creado_en'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <h3 class="no-imprimir">Comparativo entre encuestas</h3>
        <div class="no-imprimir" style="overflow-x:auto; max-width:900px;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <tr style="text-align:left; border-bottom:2px solid var(--border);">
                    <th style="padding:8px;">Encuesta</th><th style="padding:8px;">Respuestas</th><th style="padding:8px;">Satisfacción</th><th style="padding:8px;">Calidad</th><th style="padding:8px;">Espera</th><th></th>
                </tr>
                <?php foreach ($rondas as $r): ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px;"><a href="?ronda=<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></a><?= $r['activa'] ? ' 🟢' : '' ?></td>
                        <td style="padding:8px;"><?= (int) $r['total'] ?></td>
                        <td style="padding:8px;"><?= estrellasTexto($r['prom_satisfaccion'] !== null ? round((float) $r['prom_satisfaccion'], 1) : null) ?></td>
                        <td style="padding:8px;"><?= estrellasTexto($r['prom_calidad'] !== null ? round((float) $r['prom_calidad'], 1) : null) ?></td>
                        <td style="padding:8px;"><?= estrellasTexto($r['prom_espera'] !== null ? round((float) $r['prom_espera'], 1) : null) ?></td>
                        <td style="padding:8px;"><div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                            <a href="?ronda=<?= (int) $r['id'] ?>#resultados" class="enlace-ver" style="padding:6px 12px; font-size:13px; white-space:nowrap;">📊 Ir a resultados</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar esta encuesta y TODAS sus respuestas? Descarga el CSV antes si quieres conservarlas. No se puede deshacer.');">
                                <input type="hidden" name="accion" value="eliminar_ronda">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    <div style="height:40px;"></div>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
