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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_unidad') {
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        if ($nombre === '') {
            $error = 'El nombre de la unidad es obligatorio.';
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO directorio_unidades (nombre, orden) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "si", $nombre, $orden);
            if (mysqli_stmt_execute($stmt)) {
                registrarBitacora($conexion, 'Creó unidad del directorio', $nombre);
                $exito = "Unidad \"$nombre\" creada.";
            } else {
                $error = 'No se pudo crear la unidad.';
            }
        }
    } elseif ($accion === 'eliminar_unidad') {
        $id = (int) $_POST['id'];
        $stmtNombre = mysqli_prepare($conexion, "SELECT nombre FROM directorio_unidades WHERE id = ?");
        mysqli_stmt_bind_param($stmtNombre, "i", $id);
        mysqli_stmt_execute($stmtNombre);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNombre));
        $stmt = mysqli_prepare($conexion, "DELETE FROM directorio_unidades WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Eliminó unidad del directorio', $fila['nombre'] ?? "id $id");
        $exito = 'Unidad eliminada (con todos sus contactos).';
    } elseif ($accion === 'crear_contacto') {
        $unidadId = (int) ($_POST['unidad_id'] ?? 0);
        $puesto = trim($_POST['puesto'] ?? '');
        $extension = trim($_POST['extension'] ?? '') ?: null;
        $correo = trim($_POST['correo'] ?? '') ?: null;
        $orden = (int) ($_POST['orden'] ?? 0);
        if ($unidadId === 0 || $puesto === '') {
            $error = 'Elige una unidad y escribe el puesto.';
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO directorio_contactos (unidad_id, puesto, extension, correo, orden, publicado_por) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isssii", $unidadId, $puesto, $extension, $correo, $orden, $yo['id']);
            if (mysqli_stmt_execute($stmt)) {
                $idContacto = mysqli_insert_id($conexion);
                registrarBitacora($conexion, 'Agregó contacto al directorio', $puesto);
                $exito = "Contacto \"$puesto\" agregado.";
                if ($solPublicar) {
                    solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'directorio_contactos', $idContacto);
                    $exito .= ' La solicitud quedó marcada como Publicada y se le avisó a la persona.';
                    $solPublicar = null;
                    $solicitudPublicada = true;
                }
            } else {
                $error = 'No se pudo agregar el contacto.';
            }
        }
    } elseif ($accion === 'editar_contacto') {
        $id = (int) $_POST['id'];
        $puesto = trim($_POST['puesto'] ?? '');
        $extension = trim($_POST['extension'] ?? '') ?: null;
        $correo = trim($_POST['correo'] ?? '') ?: null;
        if ($puesto === '') {
            $error = 'El puesto es obligatorio.';
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE directorio_contactos SET puesto = ?, extension = ?, correo = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $puesto, $extension, $correo, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Editó contacto del directorio', $puesto);
            $exito = 'Contacto actualizado.';
        }
    } elseif ($accion === 'eliminar_contacto') {
        $id = (int) $_POST['id'];
        $stmtPuesto = mysqli_prepare($conexion, "SELECT puesto FROM directorio_contactos WHERE id = ?");
        mysqli_stmt_bind_param($stmtPuesto, "i", $id);
        mysqli_stmt_execute($stmtPuesto);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPuesto));
        $stmt = mysqli_prepare($conexion, "DELETE FROM directorio_contactos WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Eliminó contacto del directorio', $fila['puesto'] ?? "id $id");
        $exito = 'Contacto eliminado.';
    }
}

$unidades = [];
$resultado = mysqli_query($conexion, "SELECT * FROM directorio_unidades ORDER BY orden, nombre");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $fila['contactos'] = [];
    $unidades[$fila['id']] = $fila;
}
$resultado = mysqli_query($conexion, "SELECT * FROM directorio_contactos ORDER BY orden, id");
while ($fila = mysqli_fetch_assoc($resultado)) {
    if (isset($unidades[$fila['unidad_id']])) {
        $unidades[$fila['unidad_id']]['contactos'][] = $fila;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Editar Directorio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>✏️ Editar Directorio</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="directorio.php" class="enlace-ver">Ver Directorio</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?><?= $solicitudPublicada ? ' <a href="admin-solicitudes.php">← Volver a las solicitudes</a>' : '' ?></div>
    <?php endif; ?>

    <?php
    // Con una solicitud: el formulario de contacto abre con la unidad, el puesto, la extensión y el correo pedidos.
    $pre = ['unidad_id' => '', 'puesto' => '', 'extension' => '', 'correo' => ''];
    if ($solPublicar) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_contacto') {
            foreach ($pre as $k => $_) { $pre[$k] = $_POST[$k] ?? ''; }
        } else {
            $datosSol = solicitudDatosEncabezado($solPublicar);
            $pre['puesto'] = $solPublicar['titulo'];
            $pre['extension'] = $datosSol['extension'] ?? '';
            $pre['correo'] = $datosSol['correo'] ?? '';
            // La unidad se busca por nombre (sin importar mayúsculas ni acentos).
            $normalizar = function ($t) { return strtr(mb_strtolower(trim((string) $t), 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']); };
            $unidadPedida = $normalizar($datosSol['unidad'] ?? '');
            foreach ($unidades as $u) {
                if ($unidadPedida !== '' && $normalizar($u['nombre']) === $unidadPedida) { $pre['unidad_id'] = $u['id']; }
            }
        }
        $movimiento = $datosSol['movimiento'] ?? '';
        if ($movimiento !== '' && stripos($movimiento, 'Agregar') !== 0) {
            $nota = '⚠️ Esta solicitud es para <strong>' . htmlspecialchars(mb_strtolower($movimiento, 'UTF-8')) . '</strong> un contacto que ya existe, no para agregar uno nuevo. Hazlo directamente en la lista de contactos de abajo (botón de editar o eliminar) y luego márcala como aceptada en Solicitudes. No uses el formulario de agregar.';
        } elseif (!empty($datosSol['unidad']) && $pre['unidad_id'] === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $nota = 'Se llenaron el puesto, la extensión y el correo. La unidad que pidió (<strong>' . htmlspecialchars($datosSol['unidad']) . '</strong>) no existe todavía: elígela de la lista o crea la unidad arriba.';
        } else {
            $nota = 'Ya se llenaron la unidad, el puesto, la extensión y el correo con lo que pidió. Revísalos antes de agregar.';
        }
        solicitudMostrarBanner($solPublicar, $nota);
    }
    ?>

    <h2>Agregar unidad nueva</h2>
    <?php if (!empty($unidades)): ?>
        <p style="max-width:560px; font-size:13px; color:var(--text-muted); margin-bottom:10px;">
            Orden actual:
            <?php $i = 1; foreach ($unidades as $u): ?>
                <strong><?= $i ?>.</strong> <?= htmlspecialchars($u['nombre']) ?><?= $i < count($unidades) ? ' · ' : '' ?>
                <?php $i++; endforeach; ?>
        </p>
    <?php endif; ?>
    <form method="post" style="max-width:560px; background:var(--card-bg); padding:20px; border-radius:16px; box-shadow:var(--shadow); margin-bottom:30px; display:flex; gap:10px; align-items:end;">
        <input type="hidden" name="accion" value="crear_unidad">
        <div class="campo" style="flex:1; margin-bottom:0;">
            <label for="nombre_unidad">Nombre de la unidad</label>
            <input id="nombre_unidad" name="nombre" type="text" required>
        </div>
        <div class="campo" style="width:110px; margin-bottom:0;">
            <label for="orden_unidad">Orden (1–<?= count($unidades) + 1 ?>)</label>
            <input id="orden_unidad" name="orden" type="number" min="1" max="<?= count($unidades) + 1 ?>" value="<?= count($unidades) + 1 ?>">
        </div>
        <button type="submit" class="btn-primario" style="width:auto; white-space:nowrap;">Agregar</button>
    </form>

    <h2>Agregar contacto</h2>
    <form method="post" style="max-width:560px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin-bottom:40px;">
        <input type="hidden" name="accion" value="crear_contacto">
        <?php if ($solPublicar) solicitudMostrarCamposForm($solPublicar, false, false); ?>
        <div class="campo">
            <label for="unidad_id">Unidad</label>
            <select id="unidad_id" name="unidad_id" required onchange="mostrarOrdenContactos()" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Elige una unidad…</option>
                <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (string) ($pre['unidad_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="puesto">Puesto / nombre</label>
            <input id="puesto" name="puesto" type="text" required value="<?= htmlspecialchars($pre['puesto'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="extension">Extensión (opcional)</label>
            <input id="extension" name="extension" type="text" value="<?= htmlspecialchars($pre['extension'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="correo">Correo (opcional)</label>
            <input id="correo" name="correo" type="text" value="<?= htmlspecialchars($pre['correo'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="orden">Orden dentro de la unidad</label>
            <p id="orden-ayuda" style="font-size:12.5px; color:var(--text-muted); margin:0 0 8px;">Elige primero una unidad para ver el orden actual de sus contactos.</p>
            <input id="orden" name="orden" type="number" min="1" max="1" value="1">
        </div>
        <button type="submit" class="btn-primario">Agregar contacto</button>
    </form>
    <?php if (!empty($pre['unidad_id'])): ?>
    <script>window.addEventListener('load', function () { mostrarOrdenContactos(); });</script>
    <?php endif; ?>
    <script>
        const CONTACTOS_POR_UNIDAD = <?= json_encode(array_map(function ($u) {
            return array_map(function ($c) {
                return ['puesto' => $c['puesto'], 'orden' => (int) $c['orden']];
            }, $u['contactos']);
        }, $unidades), JSON_UNESCAPED_UNICODE) ?>;

        function mostrarOrdenContactos() {
            const unidadId = document.getElementById('unidad_id').value;
            const ayuda = document.getElementById('orden-ayuda');
            const inputOrden = document.getElementById('orden');
            const contactos = CONTACTOS_POR_UNIDAD[unidadId] || [];

            if (!unidadId) {
                ayuda.textContent = 'Elige primero una unidad para ver el orden actual de sus contactos.';
                inputOrden.max = 1;
                inputOrden.value = 1;
                return;
            }
            if (contactos.length === 0) {
                ayuda.textContent = 'Esta unidad todavía no tiene contactos; será el primero.';
            } else {
                ayuda.textContent = 'Orden actual: ' + contactos
                    .slice().sort((a, b) => a.orden - b.orden)
                    .map(c => `${c.orden}. ${c.puesto}`).join(' · ');
            }
            inputOrden.max = contactos.length + 1;
            inputOrden.value = contactos.length + 1;
        }
    </script>

    <h2>Unidades y contactos</h2>
    <?php foreach ($unidades as $u): ?>
        <div class="tarjeta" style="width:100%; margin-bottom:20px; max-width:800px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0;"><?= htmlspecialchars($u['nombre']) ?></h3>
                <form method="post" onsubmit="return confirm('¿Eliminar esta unidad y TODOS sus contactos? No se puede deshacer.');">
                    <input type="hidden" name="accion" value="eliminar_unidad">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar unidad</button>
                </form>
            </div>
            <div class="contenedor-acciones" style="margin-top:14px;">
                <?php foreach ($u['contactos'] as $c): ?>
                    <div class="caja-accion" style="text-align:left;">
                        <h3><?= htmlspecialchars($c['puesto']) ?></h3>
                        <p>Ext: <?= htmlspecialchars($c['extension'] ?: '—') ?></p>
                        <p style="word-break:break-word;"><?= htmlspecialchars($c['correo'] ?: '—') ?></p>
                        <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                            <button type="button" class="btn-secundario" style="border:none; cursor:pointer;" onclick="document.getElementById('editar-<?= $c['id'] ?>').style.display='flex'">✏️ Editar</button>
                            <form method="post" onsubmit="return confirm('¿Eliminar este contacto?');">
                                <input type="hidden" name="accion" value="eliminar_contacto">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                            </form>
                        </div>
                    </div>

                    <div class="popup-overlay" id="editar-<?= $c['id'] ?>" onclick="if(event.target===this) this.style.display='none'">
                        <div class="popup-box" style="text-align:left; max-width:420px;" onclick="event.stopPropagation()">
                            <button class="popup-close" onclick="document.getElementById('editar-<?= $c['id'] ?>').style.display='none'">✖</button>
                            <h3 style="margin-top:0;">Editar contacto</h3>
                            <form method="post">
                                <input type="hidden" name="accion" value="editar_contacto">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <div class="campo">
                                    <label>Puesto / nombre</label>
                                    <input name="puesto" type="text" value="<?= htmlspecialchars($c['puesto']) ?>" required>
                                </div>
                                <div class="campo">
                                    <label>Extensión</label>
                                    <input name="extension" type="text" value="<?= htmlspecialchars($c['extension'] ?? '') ?>">
                                </div>
                                <div class="campo">
                                    <label>Correo</label>
                                    <input name="correo" type="text" value="<?= htmlspecialchars($c['correo'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn-primario">Guardar cambios</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
