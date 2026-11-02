<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/calidad_preguntas.php';
requiereLogin();
$yo = usuarioActual();
$usuarioId = (int) $yo['id'];

$ronda = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id, nombre FROM calidad_rondas WHERE activa = 1 ORDER BY id DESC LIMIT 1"));

$activas = [];
$resPreg = mysqli_query($conexion, "SELECT clave FROM calidad_preguntas WHERE activa = 1");
while ($f = mysqli_fetch_assoc($resPreg)) {
    if (isset($CALIDAD_PREGUNTAS[$f['clave']])) $activas[$f['clave']] = $CALIDAD_PREGUNTAS[$f['clave']];
}

$yaRespondio = false;
if ($ronda) {
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM calidad_participaciones WHERE ronda_id = ? AND usuario_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $ronda['id'], $usuarioId);
    mysqli_stmt_execute($stmt);
    $yaRespondio = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$error = '';
$gracias = false;

if ($ronda && !$yaRespondio && $activas && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [];
    foreach ($activas as $clave => $p) {
        $valor = $_POST[$clave] ?? '';
        if ($p['tipo'] === 'estrellas') {
            $n = (int) $valor;
            $datos[$clave] = ($n >= 1 && $n <= 5) ? $n : null;
        } elseif ($p['tipo'] === 'resolvio') {
            $datos[$clave] = isset($CALIDAD_RESOLVIO[$valor]) ? $valor : null;
        } elseif ($p['tipo'] === 'falla') {
            $datos[$clave] = in_array($valor, $CALIDAD_FALLAS, true) ? $valor : null;
        } else {
            $texto = trim($valor);
            $datos[$clave] = $texto !== '' ? mb_substr($texto, 0, 2000) : null;
        }
    }

    if (!array_filter($datos, function ($v) { return $v !== null; })) {
        $error = 'Contesta al menos una pregunta antes de enviar.';
    } else {
        // Primero se registra que esta persona ya respondió (evita duplicados);
        // la respuesta se guarda aparte, sin su nombre.
        $stmt = mysqli_prepare($conexion, "INSERT INTO calidad_participaciones (ronda_id, usuario_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $ronda['id'], $usuarioId);
        if (!mysqli_stmt_execute($stmt)) {
            $yaRespondio = true;
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO calidad_respuestas (ronda_id, tiempo_espera, calidad_atencion, trato, claridad, resolvio, falla_frecuente, satisfaccion, queja, sugerencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $v = function ($c) use ($datos) { return isset($datos[$c]) ? $datos[$c] : null; };
            $te = $v('tiempo_espera'); $ca = $v('calidad_atencion'); $tr = $v('trato'); $cl = $v('claridad');
            $re = $v('resolvio'); $ff = $v('falla_frecuente'); $sa = $v('satisfaccion'); $qu = $v('queja'); $su = $v('sugerencia');
            mysqli_stmt_bind_param($stmt, "iiiiississ", $ronda['id'], $te, $ca, $tr, $cl, $re, $ff, $sa, $qu, $su);
            mysqli_stmt_execute($stmt);
            $gracias = true;
        }
    }
}

$estiloCampo = 'width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text); font-family:var(--font-body);';
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
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>⭐ Encuesta de calidad de atención</h1>
    <p><a href="home.php" class="enlace-volver">← Volver al inicio</a></p>

    <?php if ($gracias): ?>
        <div class="aviso-importante" style="max-width:600px;">¡Gracias por tu opinión! Tus respuestas se enviaron de forma anónima y nos ayudan a mejorar el servicio.</div>
    <?php elseif (!$ronda || !$activas): ?>
        <div class="aviso-importante" style="max-width:600px;">Por ahora no hay una encuesta de calidad de atención abierta. Cuando se publique una nueva, te aparecerá el botón "Calidad" arriba.</div>
    <?php elseif ($yaRespondio): ?>
        <div class="aviso-importante" style="max-width:600px;">Ya contestaste la encuesta "<?= htmlspecialchars($ronda['nombre']) ?>". ¡Gracias por participar!</div>
    <?php else: ?>
        <p style="max-width:600px; color:var(--text-muted);">Encuesta: <strong><?= htmlspecialchars($ronda['nombre']) ?></strong>. Es <strong>anónima</strong>: no se guarda tu nombre junto a tus respuestas. Piensa en tu experiencia reciente con la Mesa de Servicios.</p>

        <?php if ($error): ?>
            <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
            <?php foreach ($activas as $clave => $p): ?>
                <div class="campo" style="margin-bottom:20px;">
                    <label for="<?= $clave ?>"><?= htmlspecialchars($p['texto']) ?></label>
                    <?php if ($p['tipo'] === 'estrellas'): ?>
                        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:6px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="display:inline-flex; align-items:center; gap:4px; font-weight:600; cursor:pointer;">
                                    <input type="radio" name="<?= $clave ?>" value="<?= $i ?>" style="width:auto; margin:0;"> <?= $i ?> ⭐
                                </label>
                            <?php endfor; ?>
                        </div>
                        <p style="font-size:12px; color:var(--text-muted); margin:6px 0 0;">1 = muy mal, 5 = excelente</p>
                    <?php elseif ($p['tipo'] === 'resolvio'): ?>
                        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:6px;">
                            <?php foreach ($CALIDAD_RESOLVIO as $valor => $etiqueta): ?>
                                <label style="display:inline-flex; align-items:center; gap:4px; font-weight:600; cursor:pointer;">
                                    <input type="radio" name="<?= $clave ?>" value="<?= $valor ?>" style="width:auto; margin:0;"> <?= htmlspecialchars($etiqueta) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($p['tipo'] === 'falla'): ?>
                        <select id="<?= $clave ?>" name="<?= $clave ?>" style="<?= $estiloCampo ?> margin-top:6px;">
                            <option value="">— Elige una opción —</option>
                            <?php foreach ($CALIDAD_FALLAS as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <textarea id="<?= $clave ?>" name="<?= $clave ?>" rows="3" maxlength="2000" style="<?= $estiloCampo ?> margin-top:6px; resize:vertical;"></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-primario">Enviar respuestas</button>
        </form>
    <?php endif; ?>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
