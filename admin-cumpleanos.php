<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$error = '';
$exito = '';
// Si se abrió desde "Publicar esto" en Solicitudes, aquí viene la solicitud.
$solPublicar = solicitudCargarParaPublicar($conexion);
$solicitudPublicada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
    $nombre = trim($_POST['nombre'] ?? '');
    $area = trim($_POST['area'] ?? '') ?: null;
    $dia = (int) ($_POST['dia'] ?? 0);
    $mes = (int) ($_POST['mes'] ?? 0);
    $nombreImagen = null;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } elseif ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12) {
        $error = 'Selecciona un día y mes válidos.';
    } elseif (!empty($_FILES['imagen']['name'])) {
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $tamañoMax = 5 * 1024 * 1024; // 5 MB

        if (!in_array($extension, $permitidas, true)) {
            $error = 'La imagen debe ser jpg, png, gif o webp.';
        } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
            $error = 'La imagen no puede pesar más de 5 MB.';
        } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
        } else {
            $nombreImagen = uniqid('cumple_', true) . '.' . $extension;
            $destino = __DIR__ . '/uploads/cumpleanos/' . $nombreImagen;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $error = 'No se pudo guardar la imagen.';
                $nombreImagen = null;
            }
        }
    }

    if (!$error && $solPublicar && $nombreImagen === null && !empty($_POST['usar_imagen_solicitud'])) {
        $nombreImagen = solicitudCopiarArchivo($solPublicar, 'imagen', __DIR__ . '/uploads/cumpleanos', 'cumple_');
    }

    if (!$error) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO cumpleanos (nombre, area, dia, mes, imagen, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiisi", $nombre, $area, $dia, $mes, $nombreImagen, $yo['id']);
        if (mysqli_stmt_execute($stmt)) {
            $idCumple = mysqli_insert_id($conexion);
            registrarBitacora($conexion, 'Agregó cumpleaños', "$nombre ($dia/$mes)");
            $exito = "Cumpleaños de $nombre agregado.";
            if ($solPublicar) {
                solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'cumpleanos', $idCumple);
                $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                $solPublicar = null;
                $solicitudPublicada = true;
            }
        } else {
            $error = 'No se pudo guardar.';
        }
    }
}

// Editar un cumpleaños existente (corrige nombre, área, fecha o foto)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $id = (int) $_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $area = trim($_POST['area'] ?? '') ?: null;
    $dia = (int) ($_POST['dia'] ?? 0);
    $mes = (int) ($_POST['mes'] ?? 0);

    $stmtActual = mysqli_prepare($conexion, "SELECT imagen FROM cumpleanos WHERE id = ?");
    mysqli_stmt_bind_param($stmtActual, "i", $id);
    mysqli_stmt_execute($stmtActual);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));

    if (!$actual) {
        $error = 'No se encontró ese registro.';
    } elseif ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } elseif ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12) {
        $error = 'Selecciona un día y mes válidos.';
    } else {
        $nombreImagen = $actual['imagen'];
        if (!empty($_FILES['imagen']['name'])) {
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $tamañoMax = 5 * 1024 * 1024;
            if (!in_array($extension, $permitidas, true)) {
                $error = 'La imagen debe ser jpg, png, gif o webp.';
            } elseif ($_FILES['imagen']['size'] > $tamañoMax) {
                $error = 'La imagen no puede pesar más de 5 MB.';
            } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ocurrió un error al subir la imagen, intenta de nuevo.';
            } else {
                $nuevaImagen = uniqid('cumple_', true) . '.' . $extension;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/uploads/cumpleanos/' . $nuevaImagen)) {
                    if (!empty($actual['imagen'])) @unlink(__DIR__ . '/uploads/cumpleanos/' . $actual['imagen']);
                    $nombreImagen = $nuevaImagen;
                } else {
                    $error = 'No se pudo guardar la nueva imagen.';
                }
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conexion, "UPDATE cumpleanos SET nombre = ?, area = ?, dia = ?, mes = ?, imagen = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssiisi", $nombre, $area, $dia, $mes, $nombreImagen, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Editó cumpleaños', "$nombre ($dia/$mes)");
            $exito = 'Cumpleaños actualizado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado') {
    $id = (int) $_POST['id'];
    $stmt = mysqli_prepare($conexion, "UPDATE cumpleanos SET activo = 1 - activo WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Cambió el estado de un cumpleaños', "id $id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int) $_POST['id'];
    $stmtN = mysqli_prepare($conexion, "SELECT nombre, imagen FROM cumpleanos WHERE id = ?");
    mysqli_stmt_bind_param($stmtN, "i", $id);
    mysqli_stmt_execute($stmtN);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN));
    if ($fila && !empty($fila['imagen'])) {
        @unlink(__DIR__ . '/uploads/cumpleanos/' . $fila['imagen']);
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM cumpleanos WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Eliminó cumpleaños', $fila['nombre'] ?? "id $id");
    $exito = 'Cumpleaños eliminado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar_todos') {
    $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM cumpleanos");
    $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];

    $resImgs = mysqli_query($conexion, "SELECT imagen FROM cumpleanos WHERE imagen IS NOT NULL");
    while ($f = mysqli_fetch_assoc($resImgs)) {
        @unlink(__DIR__ . '/uploads/cumpleanos/' . $f['imagen']);
    }

    mysqli_query($conexion, "DELETE FROM cumpleanos");
    registrarBitacora($conexion, 'Vació la lista de cumpleaños', "$cuantos cumpleaños eliminados");
    $exito = "Se eliminaron los $cuantos cumpleaños.";
}

$lista = [];
$resultado = mysqli_query($conexion, "SELECT * FROM cumpleanos ORDER BY mes, dia, nombre");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $lista[] = $fila;
}

$areas = obtenerAreas($conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Cumpleaños</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🎂 Cumpleaños</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="calendario.php" class="enlace-ver">Ver Calendario</a></p>
    <p style="max-width:600px; color:var(--text-muted);">Se muestran automáticamente cada año en el Calendario y, el día que corresponda, en una tarjeta especial en el Inicio. Es solo decorativo — no se guarda el año de nacimiento, ni se vincula a ninguna cuenta.</p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario abre lleno (o conserva lo escrito si hubo error).
    $pre = ['nombre' => '', 'area' => '', 'dia' => 1, 'mes' => 1];
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
            foreach ($pre as $k => $_) { $pre[$k] = $_POST[$k] ?? $pre[$k]; }
        } else {
            $pre['nombre'] = $solPublicar['titulo'];
            $pre['area'] = $solPublicar['cumple_area'] ?? '';
            $pre['dia'] = (int) ($solPublicar['cumple_dia'] ?: 1);
            $pre['mes'] = (int) ($solPublicar['cumple_mes'] ?: 1);
        }
        solicitudMostrarBanner($solPublicar, 'Revisa el nombre, el área y la fecha antes de guardar.');
    }
    ?>

    <form method="post" enctype="multipart/form-data" style="max-width:600px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin:20px 0 40px;">
        <input type="hidden" name="accion" value="agregar">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, true, false); ?>
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars($pre['nombre']) ?>">
        </div>
        <div class="campo">
            <label for="area">Área / Unidad (opcional)</label>
            <select id="area" name="area" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Sin área definida</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= $pre['area'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:10px;">
            <div class="campo" style="flex:1;">
                <label for="dia">Día</label>
                <select id="dia" name="dia" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>" <?= (int) $pre['dia'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="campo" style="flex:2;">
                <label for="mes">Mes</label>
                <select id="mes" name="mes" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= (int) $pre['mes'] === $m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="campo">
            <label for="imagen">Foto (opcional, máx. 5 MB)</label>
            <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
        </div>
        <button type="submit" class="btn-primario">Agregar cumpleaños</button>
    </form>

    <h2>Lista de cumpleaños (<?= count($lista) ?>)</h2>
    <?php if (!empty($lista)): ?>
        <p style="margin-bottom:16px;">
            <form method="post" onsubmit="return confirm('¿Eliminar TODOS los cumpleaños (<?= count($lista) ?>) para siempre, incluyendo sus fotos? Esto no se puede deshacer.');">
                <input type="hidden" name="accion" value="vaciar_todos">
                <button type="submit" class="btn-peligro">🗑 Borrar todos los cumpleaños</button>
            </form>
        </p>
    <?php endif; ?>
    <?php if (!empty($lista)): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0 0 6px;">
            <input id="filtro-cumple-texto" type="search" placeholder="🔍 Buscar por nombre o área" style="flex:1; min-width:220px; max-width:340px; padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <select id="filtro-cumple-mes" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Todos los meses</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>"><?= $meses[$m] ?><?= $m === (int) date('n') ? ' (este mes)' : '' ?></option>
                <?php endfor; ?>
            </select>
            <select id="filtro-cumple-area" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Todas las áreas</option>
                <option value="__sin__">Sin área definida</option>
                <?php foreach (array_unique(array_merge($areas, array_filter(array_column($lista, 'area')))) as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="filtro-cumple-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
        </div>
        <p id="filtro-cumple-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 14px;"></p>
    <?php endif; ?>
    <div class="contenedor-acciones">
        <?php foreach ($lista as $c): ?>
            <div class="caja-accion tarjeta-cumple" data-mes="<?= (int) $c['mes'] ?>" data-area="<?= htmlspecialchars($c['area'] ?? '') ?>" data-buscar="<?= htmlspecialchars(($c['nombre'] ?? '') . ' ' . ($c['area'] ?? '')) ?>">
                <?php if (!empty($c['imagen'])): ?>
                    <img src="uploads/cumpleanos/<?= htmlspecialchars($c['imagen']) ?>" alt="" style="width:80px; height:80px; object-fit:cover; border-radius:50%; margin:0 auto 10px; display:block; border:3px solid var(--dorado);">
                <?php endif; ?>
                <h3>🎂 <?= htmlspecialchars($c['nombre']) ?></h3>
                <?php if (!empty($c['area'])): ?>
                    <p><?= htmlspecialchars($c['area']) ?></p>
                <?php endif; ?>
                <p><?= (int) $c['dia'] ?> de <?= $meses[(int) $c['mes']] ?> — <?= $c['activo'] ? 'Visible' : 'Oculto' ?></p>
                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                    <form method="post">
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;"><?= $c['activo'] ? 'Ocultar' : 'Mostrar' ?></button>
                    </form>
                    <form method="post" onsubmit="return confirm('¿Eliminar este cumpleaños para siempre?');">
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
                            <label>Nombre</label>
                            <input name="nombre" type="text" required value="<?= htmlspecialchars($c['nombre']) ?>">
                        </div>
                        <div class="campo">
                            <label>Área / Unidad</label>
                            <select name="area" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                                <option value="">Sin área definida</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $c['area'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div class="campo" style="flex:1;">
                                <label>Día</label>
                                <select name="dia" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= (int) $c['dia'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="campo" style="flex:2;">
                                <label>Mes</label>
                                <select name="mes" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= (int) $c['mes'] === $m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="campo">
                            <label>Reemplazar foto (opcional<?= !empty($c['imagen']) ? ", ahora: {$c['imagen']}" : '' ?>)</label>
                            <input name="imagen" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">
                        </div>
                        <button type="submit" class="btn-primario">Guardar cambios</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
        <?php if (empty($lista)): ?>
            <p style="color:var(--text-muted);">Aún no hay cumpleaños registrados.</p>
        <?php endif; ?>
    </div>
    <p id="filtro-cumple-vacio" style="display:none; color:var(--text-muted);">No hay cumpleaños que coincidan con el filtro.</p>
</div>

<script>
    (function () {
        const texto = document.getElementById('filtro-cumple-texto');
        if (!texto) return;
        const mes = document.getElementById('filtro-cumple-mes');
        const area = document.getElementById('filtro-cumple-area');
        const contador = document.getElementById('filtro-cumple-contador');
        const vacio = document.getElementById('filtro-cumple-vacio');
        const tarjetas = Array.from(document.querySelectorAll('.tarjeta-cumple'));
        // Sin acentos ni mayúsculas, para que "maria" encuentre "María".
        const normalizar = t => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

        function filtrar() {
            const q = normalizar(texto.value);
            let visibles = 0;
            tarjetas.forEach(t => {
                const mostrar = (!q || normalizar(t.dataset.buscar).includes(q))
                    && (!mes.value || t.dataset.mes === mes.value)
                    && (!area.value || (area.value === '__sin__' ? t.dataset.area === '' : t.dataset.area === area.value));
                t.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });
            contador.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' cumpleaños';
            vacio.style.display = visibles === 0 ? '' : 'none';
            try { sessionStorage.setItem('filtroCumple', JSON.stringify({ t: texto.value, m: mes.value, a: area.value })); } catch (e) {}
        }

        // Conserva el filtro al recargar (p. ej. después de editar u ocultar uno).
        try {
            const g = JSON.parse(sessionStorage.getItem('filtroCumple') || 'null');
            if (g) { texto.value = g.t || ''; mes.value = g.m || ''; area.value = g.a || ''; if (area.value !== (g.a || '')) area.value = ''; }
        } catch (e) {}

        texto.addEventListener('input', filtrar);
        mes.addEventListener('change', filtrar);
        area.addEventListener('change', filtrar);
        document.getElementById('filtro-cumple-limpiar').addEventListener('click', function () {
            texto.value = ''; mes.value = ''; area.value = ''; filtrar();
        });
        filtrar();
    })();
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
