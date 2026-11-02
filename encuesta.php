<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

$config = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM encuesta_config WHERE id = 1"));
$activa = $config && $config['activa'];

$secciones = ['Inicio', 'Mesa de Servicios / Tickets', 'Comunicados', 'Eventos', 'Calendario', 'Cursos', 'Tutoriales', 'Directorio', 'Cumpleaños', 'Nextcloud', 'Diseño en general', 'Otra'];
$tipos = [
    'sugerencia' => '💡 Sugerencia (una idea nueva)',
    'cambio' => '🔧 Cambio (algo que se podría mejorar)',
    'opinion' => '💬 Opinión (qué te parece)',
    'error' => '🐞 Reporte de falla (algo no funciona)',
];

$error = '';
$exito = '';

if ($activa && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $seccion = $_POST['seccion'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');
    $valoracion = (int) ($_POST['valoracion'] ?? 0);
    $anonimo = !empty($_POST['anonimo']);

    if (!isset($tipos[$tipo])) {
        $error = 'Elige qué tipo de comentario es.';
    } elseif ($mensaje === '') {
        $error = 'Escribe tu comentario antes de enviarlo.';
    } elseif (mb_strlen($mensaje) > 2000) {
        $error = 'El comentario es demasiado largo (máximo 2000 caracteres).';
    } else {
        if (!in_array($seccion, $secciones, true)) $seccion = null;
        $valoracion = ($valoracion >= 1 && $valoracion <= 5) ? $valoracion : null;
        $usuarioId = $anonimo ? null : (int) $yo['id'];

        $stmt = mysqli_prepare($conexion, "INSERT INTO encuesta_respuestas (usuario_id, tipo, valoracion, seccion, mensaje) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isiss", $usuarioId, $tipo, $valoracion, $seccion, $mensaje);
        if (mysqli_stmt_execute($stmt)) {
            // Se anota aparte que esta persona ya contestó (para quitarle el aviso del inicio), sin ligarlo a su respuesta.
            $version = (int) ($config['version'] ?? 1);
            mysqli_query($conexion, "INSERT IGNORE INTO encuesta_participaciones (usuario_id, version) VALUES (" . (int) $yo['id'] . ", $version)");
            $exito = '¡Gracias! Tu comentario se envió correctamente. Si quieres, puedes enviar otro.';
        } else {
            $error = 'No se pudo enviar tu comentario, intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Encuesta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📝 Encuesta de la versión beta</h1>
    <p><a href="home.php" class="enlace-volver">← Volver al inicio</a></p>

    <?php if (!$activa): ?>
        <div class="aviso-importante" style="max-width:600px;">La encuesta no está disponible por ahora. Cuando se active, aquí podrás dejar tus comentarios.</div>
    <?php else: ?>
        <p style="max-width:600px; color:var(--text-muted);"><?= nl2br(htmlspecialchars($config['introduccion'] ?? '')) ?></p>

        <?php if ($error): ?>
            <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <form method="post" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0;">
            <div class="campo">
                <label for="tipo">¿Qué quieres compartir?</label>
                <select id="tipo" name="tipo" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text); font-family:var(--font-body);">
                    <option value="">— Elige una opción —</option>
                    <?php foreach ($tipos as $valor => $etiqueta): ?>
                        <option value="<?= $valor ?>"><?= htmlspecialchars($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo" style="margin-top:14px;">
                <label for="seccion">¿A qué parte se refiere? (opcional)</label>
                <select id="seccion" name="seccion" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text); font-family:var(--font-body);">
                    <option value="">— Ninguna en particular —</option>
                    <?php foreach ($secciones as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo" style="margin-top:14px;">
                <label for="mensaje">Tu comentario</label>
                <textarea id="mensaje" name="mensaje" rows="5" maxlength="2000" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text); font-family:var(--font-body); resize:vertical;"></textarea>
            </div>

            <div class="campo" style="margin-top:14px;">
                <label>¿Cómo calificas la intranet en general? (opcional)</label>
                <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:6px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label style="display:inline-flex; align-items:center; gap:4px; font-weight:600; cursor:pointer;">
                            <input type="radio" name="valoracion" value="<?= $i ?>" style="width:auto; margin:0;"> <?= $i ?> ⭐
                        </label>
                    <?php endfor; ?>
                </div>
                <p style="font-size:12px; color:var(--text-muted); margin:6px 0 0;">1 = muy mal, 5 = excelente</p>
            </div>

            <label style="display:flex; align-items:center; gap:8px; margin:16px 0; font-weight:600; cursor:pointer;">
                <input type="checkbox" name="anonimo" value="1" style="width:auto; margin:0;">
                Enviar de forma anónima (no se guardará mi nombre)
            </label>

            <button type="submit" class="btn-primario">Enviar comentario</button>
        </form>
    <?php endif; ?>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
