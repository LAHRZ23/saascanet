<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$SECCIONES = solicitudSecciones();
$ESTATUS = solicitudEstatus();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmtF = mysqli_prepare($conexion, "SELECT * FROM solicitudes_publicacion WHERE id = ?");
    mysqli_stmt_bind_param($stmtF, "i", $id);
    mysqli_stmt_execute($stmtF);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtF));

    if ($accion === 'revisar' && $fila) {
        $decision = $_POST['decision'] ?? '';
        $respuesta = trim($_POST['respuesta'] ?? '');
        if ($decision === 'rechazar' && $respuesta === '') {
            $error = 'Para rechazar una solicitud tienes que escribir el motivo, para que la persona sepa por qué.';
        } elseif ($decision === 'aceptar' || $decision === 'rechazar') {
            $nuevo = $decision === 'aceptar' ? 'aceptada' : 'rechazada';
            $respuestaDb = $respuesta === '' ? null : $respuesta;
            $stmt = mysqli_prepare($conexion, "UPDATE solicitudes_publicacion SET estatus = ?, respuesta_admin = ?, revisado_por = ?, revisado_en = NOW(), usuario_notificado = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssii", $nuevo, $respuestaDb, $yo['id'], $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, $decision === 'aceptar' ? 'Aceptó solicitud de publicación' : 'Rechazó solicitud de publicación', $fila['titulo']);
            // Rechazar una que ya estaba publicada: si se dejó marcada la
            // casilla, también se quita de la intranet lo que se publicó.
            $m = $nuevo;
            if ($decision === 'rechazar' && $fila['estatus'] === 'publicada' && !empty($_POST['quitar_publicado'])) {
                $quitado = solicitudRevertirPublicacion($conexion, $fila);
                if ($quitado) {
                    registrarBitacora($conexion, 'Quitó lo publicado al rechazar una solicitud', $fila['titulo'] . ' — ' . $quitado);
                    $m = 'revertida';
                } else {
                    $m = 'no_revertida';
                }
            }
            header('Location: admin-solicitudes.php?m=' . $m);
            exit;
        }
    } elseif ($accion === 'eliminar' && $fila) {
        solicitudBorrarArchivos($fila);
        $stmt = mysqli_prepare($conexion, "DELETE FROM solicitudes_publicacion WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Eliminó solicitud de publicación', $fila['titulo']);
        header('Location: admin-solicitudes.php?m=eliminada');
        exit;
    } elseif ($accion === 'vaciar_todas') {
        $todas = mysqli_query($conexion, "SELECT imagen, imagen2, pdf, video FROM solicitudes_publicacion");
        $cuantas = 0;
        while ($todas && ($f = mysqli_fetch_assoc($todas))) {
            solicitudBorrarArchivos($f);
            $cuantas++;
        }
        mysqli_query($conexion, "DELETE FROM solicitudes_publicacion");
        registrarBitacora($conexion, 'Vació las solicitudes de publicación', "$cuantas solicitudes eliminadas");
        header('Location: admin-solicitudes.php?m=vaciadas');
        exit;
    }
}

$solicitudes = [];
// ?solo=ID abre una sola solicitud (para trabajarla en otra pestaña).
$solo = (int) ($_GET['solo'] ?? 0);
$resultado = mysqli_query($conexion, "SELECT s.*, u.nombre AS solicitante, u.area AS solicitante_area, r.nombre AS revisor_nombre
    FROM solicitudes_publicacion s
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios r ON r.id = s.revisado_por
    " . ($solo > 0 ? "WHERE s.id = $solo" : '') . "
    ORDER BY CASE s.estatus WHEN 'pendiente' THEN 0 WHEN 'aceptada' THEN 1 WHEN 'publicada' THEN 2 ELSE 3 END,
        CASE WHEN s.estatus = 'pendiente' THEN s.fecha_requerida END ASC,
        s.revisado_en DESC, s.creado_en DESC");
// Orden: primero las pendientes (la más urgente arriba), luego las aceptadas
// que faltan por publicar (la recién aceptada arriba), luego las publicadas y
// al final las rechazadas; en cada grupo, lo más reciente primero.
while ($resultado && ($fila = mysqli_fetch_assoc($resultado))) {
    $solicitudes[] = $fila;
}
$totalPendientes = count(array_filter($solicitudes, function ($s) { return $s['estatus'] === 'pendiente'; }));

$mensajes = [
    'aceptada' => '✅ Solicitud aceptada. Se le avisó a la persona.',
    'rechazada' => '❌ Solicitud rechazada. Se le avisó a la persona con tu justificación.',
    'revertida' => '❌ Solicitud rechazada y se quitó de la intranet lo que se había publicado. Se le avisó a la persona con tu justificación.',
    'no_revertida' => '❌ Solicitud rechazada, pero no se encontró lo publicado (quizá ya se había borrado a mano). Revisa la sección por si acaso.',
    'eliminada' => '🗑 Solicitud eliminada.',
    'vaciadas' => '🗑 Se eliminaron todas las solicitudes.',
];
$mensaje = $mensajes[$_GET['m'] ?? ''] ?? '';
$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Solicitudes de publicación</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📨 Solicitudes de publicación</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a></p>

    <?php if ($mensaje): ?>
        <div class="aviso-importante" style="max-width:820px; margin-bottom:18px;"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:820px; margin-bottom:18px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($solo > 0): ?>
        <p><a href="admin-solicitudes.php" class="enlace-volver">← Ver todas las solicitudes</a></p>
    <?php endif; ?>

    <div id="aviso-pendientes-wrap">
    <?php if ($totalPendientes > 0): ?>
        <div class="aviso-importante" style="max-width:820px; margin-bottom:18px;">🔔 Tienes <strong><?= $totalPendientes ?></strong> solicitud<?= $totalPendientes === 1 ? '' : 'es' ?> pendiente<?= $totalPendientes === 1 ? '' : 's' ?> de revisar.</div>
    <?php endif; ?>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:18px 0 6px;">
        <select id="filtro-solicitudes" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todas</option>
            <option value="pendiente">⏳ Pendientes de revisar</option>
            <option value="aceptada">✅ Aceptadas, sin publicar</option>
            <option value="publicada">📢 Publicadas</option>
            <option value="rechazada">❌ Rechazadas</option>
        </select>
        <span id="borrar-todas-wrap">
        <?php if (!empty($solicitudes)): ?>
            <form method="post" onsubmit="return confirm('Vas a eliminar PARA SIEMPRE las <?= count($solicitudes) ?> solicitudes (pendientes y ya revisadas) junto con sus archivos. Las personas dejarán de verlas en \'Mis solicitudes\'.\n\n¿Continuar?');" style="margin:0;">
                <input type="hidden" name="accion" value="vaciar_todas">
                <button type="submit" class="btn-peligro">🗑 Borrar todas (<?= count($solicitudes) ?>)</button>
            </form>
        <?php endif; ?>
        </span>
    </div>
    <p id="filtro-solicitudes-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 10px;"></p>

    <div id="contenedor-solicitudes" style="max-width:820px; display:flex; flex-direction:column; gap:16px;">
        <?php if (empty($solicitudes)): ?>
            <p>Todavía no hay solicitudes de publicación.</p>
        <?php endif; ?>
        <?php foreach ($solicitudes as $s):
            $e = $ESTATUS[$s['estatus']] ?? $ESTATUS['pendiente'];
            $pendiente = $s['estatus'] === 'pendiente';
            $diasFalta = (int) floor((strtotime($s['fecha_requerida']) - strtotime($hoy)) / 86400);
        ?>
            <div class="caja-accion tarjeta-solicitud" id="solicitud-<?= $s['id'] ?>" data-estatus="<?= $s['estatus'] ?>" style="text-align:left; <?= $pendiente ? 'border-top-color: var(--dorado);' : '' ?>">
                <span class="pill" style="background: <?= $e['fondo'] ?>; color: <?= $e['color'] ?>;"><?= $e['texto'] ?></span>
                <?php if (!empty($s['categoria'])): ?>
                    <span class="pill" style="background: <?= $s['categoria'] === 'urgente' ? 'rgba(178,58,58,.15)' : 'rgba(0,0,0,.06)' ?>; color: <?= $s['categoria'] === 'urgente' ? 'var(--danger, #B23A3A)' : 'var(--text)' ?>; margin-left:6px;">Categoría pedida: <?= htmlspecialchars(solicitudCategorias()[$s['categoria']] ?? $s['categoria']) ?></span>
                <?php endif; ?>
                <h3 style="margin:8px 0 4px; text-align:left;"><?= htmlspecialchars($s['titulo']) ?></h3>
                <p style="font-size:13px; color:var(--text-muted); margin:0 0 4px;">
                    <?= htmlspecialchars($SECCIONES[$s['seccion']] ?? $s['seccion']) ?>
                    · De: <strong><?= htmlspecialchars($s['solicitante'] ?? 'Cuenta eliminada') ?></strong><?= !empty($s['solicitante_area']) ? ' (' . htmlspecialchars($s['solicitante_area']) . ')' : '' ?>
                    · Enviada el <?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?>
                </p>
                <p style="margin:0 0 8px; font-weight:700; color: <?= $pendiente && $diasFalta <= 2 ? 'var(--danger, #B23A3A)' : 'var(--text)' ?>;">
                    📅 La necesita para el <?= date('d/m/Y', strtotime($s['fecha_requerida'])) ?>
                    <?php if ($pendiente): ?>
                        <?= $diasFalta < 0 ? '(¡ya pasó la fecha!)' : ($diasFalta === 0 ? '(¡es hoy!)' : ($diasFalta <= 2 ? '(en ' . $diasFalta . ' día' . ($diasFalta === 1 ? '' : 's') . ')' : '')) ?>
                    <?php endif; ?>
                </p>
                <p><?= nl2br(htmlspecialchars($s['descripcion'])) ?></p>

                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <?php foreach (['imagen' => 'Imagen de la solicitud', 'imagen2' => 'Segunda imagen de la solicitud'] as $campoImg => $altImg): if (!empty($s[$campoImg])): ?>
                    <a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=<?= $campoImg ?>" target="_blank" title="Clic para abrir en grande">
                        <img src="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=<?= $campoImg ?>" alt="<?= $altImg ?>" style="max-width:260px; max-height:180px; border-radius:10px; border:1px solid var(--border); display:block; margin-bottom:6px;">
                    </a>
                <?php endif; endforeach; ?>
                </div>
                <?php if ($s['imagen'] || $s['imagen2'] || $s['pdf'] || $s['video']): ?>
                    <p style="display:flex; gap:12px; flex-wrap:wrap; margin:14px 0;">
                        <?php if ($s['imagen']): ?><a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=imagen&descargar=1" class="btn-secundario" style="text-decoration:none;">⬇️ Descargar imagen<?= $s['imagen2'] ? ' 1' : '' ?></a><?php endif; ?>
                        <?php if ($s['imagen2']): ?><a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=imagen2&descargar=1" class="btn-secundario" style="text-decoration:none;">⬇️ Descargar imagen 2</a><?php endif; ?>
                        <?php if ($s['video']): ?><a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=video" target="_blank" class="btn-secundario" style="text-decoration:none;">🎬 Ver video</a>
                        <a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=video&descargar=1" class="btn-secundario" style="text-decoration:none;">⬇️ Descargar video</a><?php endif; ?>
                        <?php if ($s['pdf']): ?><a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=pdf" target="_blank" class="btn-secundario" style="text-decoration:none;">📎 Ver PDF</a>
                        <a href="solicitud_archivo.php?id=<?= $s['id'] ?>&tipo=pdf&descargar=1" class="btn-secundario" style="text-decoration:none;">⬇️ Descargar PDF</a><?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php foreach ([1, 2] as $n): if ($s["enlace{$n}_url"]): ?>
                    <p style="font-size:13px; margin:2px 0;">🔗 Botón: <strong><?= htmlspecialchars($s["enlace{$n}_texto"]) ?></strong> →
                        <a href="<?= htmlspecialchars($s["enlace{$n}_url"]) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($s["enlace{$n}_url"]) ?></a></p>
                <?php endif; endforeach; ?>

                <?php
                $paginasPublicar = solicitudPaginasPublicar();
                $pendienteOAceptada = in_array($s['estatus'], ['pendiente', 'aceptada'], true);
                $puedePublicar = isset($paginasPublicar[$s['seccion']]) && $pendienteOAceptada;
                $textoCopia = solicitudTextoConEnlaces($s);
                ?>
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin:16px 0 4px; padding-top:14px; border-top:1px solid var(--border);">
                    <?php if ($puedePublicar): ?>
                        <?php
                        $etiquetaPublicar = solicitudEtiquetaPublicar($s['seccion']);
                        if ($s['seccion'] === 'directorio') {
                            // Correcciones y bajas se hacen sobre un contacto que ya existe, no con el formulario de alta.
                            $mov = solicitudDatosEncabezado($s)['movimiento'] ?? '';
                            if ($mov !== '' && stripos($mov, 'Agregar') !== 0) { $etiquetaPublicar = 'Abrir el directorio (' . mb_strtolower($mov, 'UTF-8') . ')'; }
                        }
                        ?>
                        <a href="<?= $paginasPublicar[$s['seccion']] ?>?desde_solicitud=<?= $s['id'] ?>" class="btn-accion" style="text-decoration:none;">➕ <?= htmlspecialchars($etiquetaPublicar) ?></a>
                    <?php elseif ($s['estatus'] === 'publicada'): ?>
                        <strong style="color:var(--guinda);">📢 Ya publicada</strong>
                    <?php endif; ?>
                    <?php if ($pendienteOAceptada): ?>
                        <button type="button" class="btn-secundario btn-copiar" style="border:none; cursor:pointer;" data-copiar="<?= htmlspecialchars($s['titulo'] . "

" . $textoCopia, ENT_QUOTES) ?>">📋 Copiar todo</button>
                    <?php endif; ?>
                    <a href="admin-solicitud-imprimir.php?id=<?= $s['id'] ?>" target="_blank" class="btn-secundario" style="text-decoration:none;">🖨️ Imprimir</a>
                </div>

                <?php if (!$pendiente): ?>
                    <div class="aviso-importante" style="margin-top:12px;">
                        <strong><?= $s['estatus'] === 'aceptada' ? '✅ Aceptada' : ($s['estatus'] === 'publicada' ? '📢 Publicada' : '❌ Rechazada') ?></strong>
                        <?= $s['revisor_nombre'] ? 'por ' . htmlspecialchars($s['revisor_nombre']) : '' ?>
                        <?= $s['revisado_en'] ? ' el ' . date('d/m/Y H:i', strtotime($s['revisado_en'])) : '' ?>
                        <?php if (!empty($s['respuesta_admin'])): ?><br><?= nl2br(htmlspecialchars($s['respuesta_admin'])) ?><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php ob_start(); ?>
                <form method="post" style="margin-top:18px;">
                    <input type="hidden" name="accion" value="revisar">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <div class="campo">
                        <label>Motivo del rechazo (obligatorio si rechazas) o nota para la persona (opcional si aceptas)</label>
                        <textarea name="respuesta" rows="2" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);" placeholder="Ej. Falta la fecha del evento / Ya se publicó el día 3."></textarea>
                    </div>
                    <?php if ($s['estatus'] === 'publicada'):
                        $tiposRev = solicitudTiposRevertibles();
                        $tablaPub = $s['publicado_tabla'] ?? null; ?>
                        <?php if ($tablaPub && isset($tiposRev[$tablaPub])): ?>
                            <label style="display:flex; gap:8px; align-items:flex-start; font-size:13px; margin:4px 0 8px; cursor:pointer;">
                                <input type="checkbox" name="quitar_publicado" value="1" checked style="margin-top:2px;">
                                <span>Si la rechazas, quitar también de la intranet <strong><?= htmlspecialchars($tiposRev[$tablaPub]['nombre']) ?></strong> que se publicó con esta solicitud.</span>
                            </label>
                        <?php else: ?>
                            <p style="font-size:13px; color:var(--text-muted); margin:4px 0 8px;">⚠️ Esta solicitud se publicó antes de que se guardara el vínculo con lo publicado: si la rechazas, quita la publicación a mano desde su sección.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:6px;">
                        <button type="submit" name="decision" value="aceptar" class="btn-primario" style="width:auto; padding:10px 18px;">✅ Aceptar</button>
                        <button type="submit" name="decision" value="rechazar" class="btn-secundario" style="border:none; cursor:pointer;" onclick="if(!this.form.respuesta.value.trim()){alert('Escribe el motivo del rechazo para que la persona sepa por qué.'); this.form.respuesta.focus(); return false;} return true;">❌ Rechazar</button>
                    </div>
                </form>
                <?php $formRevision = ob_get_clean(); ?>
                <?php if ($pendiente): ?>
                    <?= $formRevision ?>
                <?php else: ?>
                    <details style="margin-top:10px;">
                        <summary style="cursor:pointer; font-size:13px; color:var(--text-muted);">Cambiar la decisión</summary>
                        <?= $formRevision ?>
                    </details>
                <?php endif; ?>

                <form method="post" onsubmit="return confirm('¿Eliminar esta solicitud para siempre? Se borran también sus archivos y la persona deja de verla.');" style="margin-top:22px; padding-top:14px; border-top:1px solid var(--border);">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar esta solicitud</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <p id="filtro-solicitudes-vacio" style="display:none; color:var(--text-muted);">No hay solicitudes con ese estatus.</p>
</div>

<script>
    const filtroSolicitudes = document.getElementById('filtro-solicitudes');
    const contadorSolicitudes = document.getElementById('filtro-solicitudes-contador');
    const vacioSolicitudes = document.getElementById('filtro-solicitudes-vacio');

    // El filtro elegido se conserva al recargar (p. ej. después de aceptar una
    // solicitud), mientras la pestaña siga abierta.
    try {
        const guardado = sessionStorage.getItem('filtroSolicitudes');
        if (guardado !== null && filtroSolicitudes.querySelector('option[value="' + guardado + '"]')) filtroSolicitudes.value = guardado;
    } catch (e) {}

    function filtrarSolicitudes() {
        const tarjetas = Array.from(document.querySelectorAll('.tarjeta-solicitud'));
        // Cuántas hay de cada estatus, para mostrarlo en el filtro.
        const conteo = {};
        tarjetas.forEach(t => { conteo[t.dataset.estatus] = (conteo[t.dataset.estatus] || 0) + 1; });
        Array.from(filtroSolicitudes.options).forEach(o => {
            if (!o.dataset.texto) o.dataset.texto = o.textContent;
            o.textContent = o.dataset.texto + ' (' + (o.value ? (conteo[o.value] || 0) : tarjetas.length) + ')';
        });
        let visibles = 0;
        tarjetas.forEach(t => {
            const mostrar = !filtroSolicitudes.value || t.dataset.estatus === filtroSolicitudes.value;
            t.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });
        contadorSolicitudes.textContent = tarjetas.length ? 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' solicitudes' : '';
        vacioSolicitudes.style.display = tarjetas.length && visibles === 0 ? '' : 'none';
    }
    filtroSolicitudes.addEventListener('change', function () {
        try { sessionStorage.setItem('filtroSolicitudes', filtroSolicitudes.value); } catch (e) {}
        filtrarSolicitudes();
    });
    filtrarSolicitudes();

    // --- Actualización en vivo (cada 10 s), sin recargar la página ---
    // Si otro administrador acepta, rechaza o elimina una solicitud, aquí se
    // ve de inmediato quién lo hizo, para no repetir ni contradecir la
    // decisión. Se vuelve a pedir esta misma página y se reemplazan solo las
    // partes que cambian; lo que alguien esté escribiendo en una nota se
    // conserva tal cual.
    const REGIONES_VIVAS = ['contenedor-solicitudes', 'aviso-pendientes-wrap', 'borrar-todas-wrap'];
    // Huella de lo que se ve ahora; si el servidor trae lo mismo, no se toca la
    // pantalla (así nunca se pierde un clic justo cuando se refresca).
    let ultimaHuella = REGIONES_VIVAS.map(id => document.getElementById(id).innerHTML).join('|');

    function actualizarSolicitudes() {
        fetch('admin-solicitudes.php' + location.search, { credentials: 'same-origin' })
            .then(r => r.text())
            .then(html => {
                const nuevo = new DOMParser().parseFromString(html, 'text/html');
                const contenedorNuevo = nuevo.getElementById('contenedor-solicitudes');
                if (!contenedorNuevo) return;

                const huella = REGIONES_VIVAS.map(id => { const e = nuevo.getElementById(id); return e ? e.innerHTML : ''; }).join('|');
                if (huella === ultimaHuella) return;
                ultimaHuella = huella;

                // Guarda lo escrito, los "Cambiar decisión" abiertos y el foco.
                const escrito = {};
                const abiertos = [];
                document.querySelectorAll('.tarjeta-solicitud').forEach(t => {
                    const ta = t.querySelector('textarea[name="respuesta"]');
                    if (ta && ta.value !== '') escrito[t.id] = ta.value;
                    const det = t.querySelector('details');
                    if (det && det.open) abiertos.push(t.id);
                });
                const activo = document.activeElement;
                const tarjetaActiva = activo && activo.closest ? activo.closest('.tarjeta-solicitud') : null;
                const foco = (activo && activo.tagName === 'TEXTAREA' && tarjetaActiva)
                    ? { id: tarjetaActiva.id, ini: activo.selectionStart, fin: activo.selectionEnd } : null;

                REGIONES_VIVAS.forEach(id => {
                    const viejo = document.getElementById(id);
                    const fresco = nuevo.getElementById(id);
                    if (viejo && fresco) viejo.innerHTML = fresco.innerHTML;
                });

                Object.keys(escrito).forEach(id => {
                    const ta = document.querySelector('#' + id + ' textarea[name="respuesta"]');
                    if (ta) ta.value = escrito[id];
                });
                abiertos.forEach(id => {
                    const det = document.querySelector('#' + id + ' details');
                    if (det) det.open = true;
                });
                if (foco) {
                    const ta = document.querySelector('#' + foco.id + ' textarea[name="respuesta"]');
                    if (ta) { ta.focus(); ta.setSelectionRange(foco.ini, foco.fin); }
                }
                filtrarSolicitudes();
            })
            .catch(() => {});
    }
    setInterval(actualizarSolicitudes, 10000);

    // --- Botones "Copiar": funcionan también en http:// (sin HTTPS), donde
    // navigator.clipboard no existe, usando el método clásico como respaldo.
    function copiarAlPortapapeles(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(texto);
        }
        return new Promise((resolver, rechazar) => {
            const ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            ok ? resolver() : rechazar();
        });
    }
    document.addEventListener('click', function (e) {
        const boton = e.target.closest('.btn-copiar');
        if (!boton) return;
        const original = boton.dataset.textoOriginal || boton.textContent;
        boton.dataset.textoOriginal = original;
        copiarAlPortapapeles(boton.dataset.copiar).then(() => {
            boton.textContent = '✅ Copiado';
        }).catch(() => {
            boton.textContent = '⚠️ No se pudo copiar';
        }).finally(() => {
            setTimeout(() => { boton.textContent = original; }, 1800);
        });
    });
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
