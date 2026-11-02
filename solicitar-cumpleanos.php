<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
$yo = usuarioActual();

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$areas = obtenerAreas($conexion);
$error = '';
$val = ['nombre' => '', 'area' => '', 'dia' => '1', 'mes' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST)) {
        $error = 'No se recibió la información. Es probable que la foto sea demasiado pesada (máximo 5 MB).';
    } else {
        foreach ($val as $k => $_) {
            if (isset($_POST[$k])) {
                $val[$k] = trim((string) $_POST[$k]);
            }
        }
        $dia = (int) $val['dia'];
        $mes = (int) $val['mes'];
        $area = in_array($val['area'], $areas, true) ? $val['area'] : null;

        if ($val['nombre'] === '' || mb_strlen($val['nombre']) > 120) {
            $error = 'Escribe el nombre completo de la persona (máximo 120 caracteres).';
        } elseif ($mes < 1 || $mes > 12 || $dia < 1 || !checkdate($mes, $dia, 2000)) {
            $error = 'Elige un día y un mes válidos.';
        }

        $nombreImagen = null;
        if (!$error && !empty($_FILES['imagen']['name'])) {
            $f = $_FILES['imagen'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir la foto, intenta de nuevo.';
            } elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || @getimagesize($f['tmp_name']) === false) {
                $error = 'La foto debe ser jpg, png, gif o webp.';
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $error = 'La foto no puede pesar más de 5 MB.';
            } else {
                $nombreImagen = uniqid('sol_img_', true) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], solicitudCarpeta() . '/' . $nombreImagen)) {
                    $error = 'No se pudo guardar la foto.';
                    $nombreImagen = null;
                }
            }
        }

        if (!$error) {
            // "Para cuándo": la próxima vez que cae ese cumpleaños.
            $anio = (int) date('Y');
            $proximo = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            if ($proximo < date('Y-m-d')) {
                $anio++;
                $proximo = sprintf('%04d-%02d-%02d', $anio, $mes, checkdate($mes, $dia, $anio) ? $dia : 28);
            }
            $titulo = $val['nombre'];
            $descripcion = 'Cumpleaños de ' . $val['nombre'] . ($area ? " ($area)" : '') . ' — ' . $dia . ' de ' . $meses[$mes] . '.';

            $stmt = mysqli_prepare($conexion, "INSERT INTO solicitudes_publicacion (usuario_id, seccion, titulo, descripcion, imagen, cumple_area, cumple_dia, cumple_mes, fecha_requerida) VALUES (?, 'cumpleanos', ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issssiis", $yo['id'], $titulo, $descripcion, $nombreImagen, $area, $dia, $mes, $proximo);
            if (mysqli_stmt_execute($stmt)) {
                registrarBitacora($conexion, 'Solicitó un cumpleaños', $titulo . " ($dia/$mes)");
                header('Location: mis-solicitudes.php?ok=1');
                exit;
            }
            solicitudBorrarArchivos(['imagen' => $nombreImagen, 'pdf' => null]);
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
    <title>SAASCAEM - Solicitar cumpleaños</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🎂 Solicitar cumpleaños</h1>
    <p style="display:flex; gap:14px; flex-wrap:wrap; align-items:center; margin-bottom:20px;">
        <a href="solicitar-publicacion.php" class="enlace-volver">← Volver a Solicitar publicación</a>
        <a href="mis-solicitudes.php" class="btn-secundario" style="text-decoration:none;">📋 Mis solicitudes</a>
    </p>
    <p style="max-width:560px; color:var(--text-muted);">¿Quieres que el cumpleaños de alguien aparezca en la intranet y en el calendario? Llena estos pocos datos y la UATI lo revisará.</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:560px; margin-bottom:18px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="max-width:560px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:16px 0 40px;">
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input id="nombre" name="nombre" type="text" maxlength="120" required value="<?= htmlspecialchars($val['nombre']) ?>">
        </div>
        <div class="campo">
            <label for="area">Área / Unidad</label>
            <select id="area" name="area" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Sin área definida</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= $val['area'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:10px;">
            <div class="campo" style="flex:1;">
                <label for="dia">Día</label>
                <select id="dia" name="dia" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>" <?= (int) $val['dia'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="campo" style="flex:2;">
                <label for="mes">Mes</label>
                <select id="mes" name="mes" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= (int) $val['mes'] === $m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="campo">
            <label for="imagen">Foto (opcional — jpg, png, gif o webp, máx. 5 MB)</label>
            <input id="imagen" name="imagen" type="file" accept="image/*">
        </div>
        <button type="submit" class="btn-primario">Enviar solicitud</button>
    </form>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
