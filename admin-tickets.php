<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/rango_fechas.php';
require_once __DIR__ . '/includes/ticket_participantes.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmtFolio = mysqli_prepare($conexion, "SELECT folio FROM tickets WHERE id = ?");
    mysqli_stmt_bind_param($stmtFolio, "i", $id);
    mysqli_stmt_execute($stmtFolio);
    $folio = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFolio)) ?: [])['folio'] ?? "id $id";

    if ($accion === 'eliminar') {
        $stmt = mysqli_prepare($conexion, "DELETE FROM tickets WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Eliminó ticket', $folio);
    } elseif ($accion === 'archivar') {
        $stmt = mysqli_prepare($conexion, "UPDATE tickets SET archivado = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Archivó ticket', $folio);
    } elseif ($accion === 'desarchivar') {
        $stmt = mysqli_prepare($conexion, "UPDATE tickets SET archivado = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Desarchivó ticket', $folio);
    } elseif ($accion === 'tomar') {
        $stmt = mysqli_prepare($conexion, "UPDATE tickets SET atendido_por = ?, estatus = IF(estatus = 'nuevo', 'en_proceso', estatus), usuario_notificado = IF(estatus = 'nuevo', 0, usuario_notificado) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $yo['id'], $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Tomó ticket', $folio);
        ticketAgregarParticipante($conexion, $id, $yo['id'], $yo['id']);
    } elseif ($accion === 'liberar') {
        $liberarTodo = true;
        if (ticketParticipantesDisponible($conexion)) {
            if (ticketEsDeUsuario($conexion, $id, $yo['id'])) {
                // Sale solo esta persona; si todavía queda alguien trabajándolo, el ticket sigue con esa persona.
                ticketQuitarParticipante($conexion, $id, $yo['id']);
                $quedan = ticketParticipantesDe($conexion, [$id])[$id] ?? [];
                if ($quedan) {
                    $liberarTodo = false;
                    $nuevoResp = (int) $quedan[0]['id'];
                    $stmt = mysqli_prepare($conexion, "UPDATE tickets SET atendido_por = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ii", $nuevoResp, $id);
                    mysqli_stmt_execute($stmt);
                    registrarBitacora($conexion, 'Salió del ticket (sigue con otros participantes)', $folio);
                }
            } else {
                $liberarTodo = false; // no es suyo ni participa: no se toca
            }
        }
        if ($liberarTodo) {
            $stmt = mysqli_prepare($conexion, "UPDATE tickets SET atendido_por = NULL, estatus = IF(estatus = 'en_proceso', 'nuevo', estatus) WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, 'Liberó ticket', $folio);
        }
    } elseif ($accion === 'compartir' && ticketParticipantesDisponible($conexion)) {
        // Solo quien tiene el ticket o participa en él puede sumar a otras personas.
        if (ticketEsDeUsuario($conexion, $id, $yo['id'])) {
            $stmtDueno = mysqli_prepare($conexion, "SELECT atendido_por FROM tickets WHERE id = ?");
            mysqli_stmt_bind_param($stmtDueno, "i", $id);
            mysqli_stmt_execute($stmtDueno);
            $dueno = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDueno)) ?: [])['atendido_por'] ?? null;
            if ($dueno) { ticketAgregarParticipante($conexion, $id, $dueno, $yo['id']); }
            ticketAgregarParticipante($conexion, $id, $yo['id'], $yo['id']);

            $nombres = [];
            foreach ((array) ($_POST['usuarios'] ?? []) as $uid) {
                $uid = (int) $uid;
                $stmtU = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ? AND rol = 'admin' AND activo = 1");
                mysqli_stmt_bind_param($stmtU, "i", $uid);
                mysqli_stmt_execute($stmtU);
                $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtU));
                if ($u && ticketAgregarParticipante($conexion, $id, $uid, $yo['id'])) {
                    $nombres[] = $u['nombre'];
                }
            }
            if ($nombres) {
                registrarBitacora($conexion, 'Compartió ticket', $folio . ' con ' . implode(', ', $nombres));
            }
        }
    } elseif ($accion === 'unirse' && ticketParticipantesDisponible($conexion)) {
        // Cualquier administrador puede sumarse a un ticket que alguien más tiene y que sigue abierto.
        $stmtT = mysqli_prepare($conexion, "SELECT atendido_por, estatus FROM tickets WHERE id = ?");
        mysqli_stmt_bind_param($stmtT, "i", $id);
        mysqli_stmt_execute($stmtT);
        $tk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
        if ($tk && $tk['atendido_por'] && in_array($tk['estatus'], ['nuevo', 'en_proceso'], true)) {
            ticketAgregarParticipante($conexion, $id, $tk['atendido_por'], $tk['atendido_por']);
            ticketAgregarParticipante($conexion, $id, $yo['id'], $yo['id']);
            registrarBitacora($conexion, 'Se unió al ticket', $folio);
        }
    } elseif ($accion === 'quitar_participante' && ticketParticipantesDisponible($conexion)) {
        $quitarId = (int) ($_POST['quitar_id'] ?? 0);
        $stmtT = mysqli_prepare($conexion, "SELECT atendido_por FROM tickets WHERE id = ?");
        mysqli_stmt_bind_param($stmtT, "i", $id);
        mysqli_stmt_execute($stmtT);
        $tk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
        // Solo participantes; no a uno mismo (para eso está "Salirme") ni a quien tiene/cerró el ticket.
        if ($tk && $quitarId !== (int) $yo['id'] && $quitarId !== (int) $tk['atendido_por'] && ticketEsDeUsuario($conexion, $id, $yo['id'])) {
            $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
            mysqli_stmt_bind_param($stmtN, "i", $quitarId);
            mysqli_stmt_execute($stmtN);
            $nombreQuitado = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $quitarId";
            if (ticketQuitarParticipante($conexion, $id, $quitarId)) {
                registrarBitacora($conexion, 'Quitó a un participante del ticket', $folio . ': ' . $nombreQuitado);
            }
        }
    } elseif ($accion === 'actualizar') {
        $estatus = $_POST['estatus'] ?? '';
        $notaAdmin = trim($_POST['nota_admin'] ?? '') ?: null;
        $stmtPrev = mysqli_prepare($conexion, "SELECT atendido_por FROM tickets WHERE id = ?");
        mysqli_stmt_bind_param($stmtPrev, "i", $id);
        mysqli_stmt_execute($stmtPrev);
        $responsableAnterior = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPrev)) ?: [])['atendido_por'] ?? null;
        if (in_array($estatus, ['nuevo', 'en_proceso', 'resuelto', 'no_procede'], true)) {
            if ($estatus === 'resuelto' || $estatus === 'no_procede') {
                $stmt = mysqli_prepare($conexion, "UPDATE tickets SET estatus = ?, atendido_por = ?, nota_admin = ?, resuelto_en = NOW(), usuario_notificado = 0 WHERE id = ?");
            } else {
                $stmt = mysqli_prepare($conexion, "UPDATE tickets SET estatus = ?, atendido_por = ?, nota_admin = ?, resuelto_en = NULL, usuario_notificado = 0 WHERE id = ?");
            }
            mysqli_stmt_bind_param($stmt, "sisi", $estatus, $yo['id'], $notaAdmin, $id);
            mysqli_stmt_execute($stmt);
            registrarBitacora($conexion, "Actualizó ticket a estatus $estatus", $folio);
            // Quien lo tenía antes no pierde su parte; y quien guarda queda como participante.
            if ($responsableAnterior) { ticketAgregarParticipante($conexion, $id, $responsableAnterior, $responsableAnterior); }
            ticketAgregarParticipante($conexion, $id, $yo['id'], $yo['id']);
        }
    } elseif ($accion === 'vaciar_cerrados') {
        $resultadoConteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus IN ('resuelto', 'no_procede')");
        $cuantos = (int) mysqli_fetch_assoc($resultadoConteo)['total'];
        mysqli_query($conexion, "DELETE FROM tickets WHERE estatus IN ('resuelto', 'no_procede')");
        registrarBitacora($conexion, 'Vació tickets cerrados', "$cuantos tickets eliminados (resueltos y no procede), de todos los administradores");
    }
    $volver = '';
    if (($_GET['ver'] ?? '') === 'archivados') {
        $volver = '?ver=archivados';
    } elseif (($_GET['ver'] ?? '') === 'mios') {
        $volver = '?ver=mios';
    }
    header('Location: admin-tickets.php' . $volver);
    exit;
}

$vista = $_GET['ver'] ?? 'activos';
$verArchivados = $vista === 'archivados';
$verMios = $vista === 'mios';

$tickets = [];
if ($verMios) {
    $stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.atendido_por
        WHERE t.archivado = 0 AND " . ticketCondicionMios($conexion, $yo['id']) . "
        ORDER BY t.creado_en DESC");
} else {
    $stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.atendido_por
        WHERE t.archivado = ?
        ORDER BY t.creado_en DESC");
    $archivadoParam = $verArchivados ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "i", $archivadoParam);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tickets[] = $fila;
}

$tickets = ticketsConParticipantes($conexion, $tickets);
$participantesOk = ticketParticipantesDisponible($conexion);
$adminsLista = ticketAdmins($conexion);

$totalNuevos = count(array_filter($tickets, function ($t) { return $t['estatus'] === 'nuevo'; }));

$areasEnLista = [];
foreach ($tickets as $t) {
    if (!empty($t['area']) && !in_array($t['area'], $areasEnLista, true)) {
        $areasEnLista[] = $t['area'];
    }
}
sort($areasEnLista);

$totalActivos = 0;
$rActivos = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE archivado = 0");
if ($rActivos) $totalActivos = (int) mysqli_fetch_assoc($rActivos)['total'];

$totalMios = 0;
$rMios = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets t WHERE t.archivado = 0 AND " . ticketCondicionMios($conexion, $yo['id']));
if ($rMios) $totalMios = (int) mysqli_fetch_assoc($rMios)['total'];

$totalArchivados = 0;
$rArchivados = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE archivado = 1");
if ($rArchivados) $totalArchivados = (int) mysqli_fetch_assoc($rArchivados)['total'];

$totalCerrados = 0;
$rCerrados = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus IN ('resuelto', 'no_procede')");
if ($rCerrados) $totalCerrados = (int) mysqli_fetch_assoc($rCerrados)['total'];

$etiquetas = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];
$estilosPill = [
    'nuevo' => ['🔔 Nuevo', 'rgba(201,162,39,.18)', 'var(--dorado-dark)'],
    'en_proceso' => ['⏳ En proceso', 'rgba(45,115,221,.15)', '#2d73dd'],
    'resuelto' => ['✅ Resuelto', 'rgba(46,125,91,.15)', 'var(--success, #2E7D5B)'],
    'no_procede' => ['❌ No procede', 'rgba(178,58,58,.15)', 'var(--danger, #B23A3A)'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Panel de tickets</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🗂 Panel de tickets</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="mesa-servicios.php" class="enlace-ver">Ver Mesa de Servicios</a></p>

    <div id="aviso-nuevos-wrap">
    <?php if ($vista === 'activos' && $totalNuevos > 0): ?>
        <div class="aviso-importante">🔔 Tienes <strong><?= $totalNuevos ?></strong> ticket<?= $totalNuevos === 1 ? '' : 's' ?> nuevo<?= $totalNuevos === 1 ? '' : 's' ?> sin atender.</div>
    <?php endif; ?>
    </div>

    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="admin-tickets.php" class="btn-secundario" style="<?= $vista === 'activos' ? 'outline:2px solid var(--guinda);' : '' ?>">Todos <span class="contador-pill" id="contador-activos"><?= $totalActivos ?></span></a>
        <a href="admin-tickets.php?ver=mios" class="btn-secundario" style="<?= $verMios ? 'outline:2px solid var(--guinda);' : '' ?>">🙋 Los míos <span class="contador-pill" id="contador-mios"><?= $totalMios ?></span></a>
        <a href="admin-tickets.php?ver=archivados" class="btn-secundario" style="<?= $verArchivados ? 'outline:2px solid var(--guinda);' : '' ?>">📦 Archivados <span class="contador-pill" id="contador-archivados"><?= $totalArchivados ?></span></a>
        <a href="admin-tickets-exportar.php" class="btn-secundario enlace-rango" data-base="admin-tickets-exportar.php">⬇️ Descargar todos (CSV)</a>
        <a href="admin-tickets-exportar.php?estatus=resuelto" class="btn-secundario enlace-rango" data-base="admin-tickets-exportar.php?estatus=resuelto">⬇️ Solo resueltos (CSV)</a>
        <a href="admin-tickets-exportar.php?estatus=resuelto&mios=1" class="btn-secundario enlace-rango" data-base="admin-tickets-exportar.php?estatus=resuelto&mios=1">⬇️ Solo mis resueltos (CSV)</a>
        <a href="admin-reportes.php" class="btn-secundario enlace-rango" data-base="admin-reportes.php">📊 Reporte del periodo</a>
        <div id="boton-vaciar-cerrados-wrap">
        <?php if ($totalCerrados > 0): ?>
            <form method="post" onsubmit="return confirm('Vas a eliminar PARA SIEMPRE los <?= $totalCerrados ?> tickets cerrados (resueltos y no procede) de TODOS los administradores. Esto también borra ese historial de la vista \'Mis tickets\' de cada usuario.\n\n¿Ya descargaste el CSV de respaldo? Si no, cancela y descárgalo primero.\n\n¿Continuar de todas formas?');">
                <input type="hidden" name="accion" value="vaciar_cerrados">
                <button type="submit" class="btn-peligro">🗑 Vaciar cerrados (<?= $totalCerrados ?>)</button>
            </form>
        <?php endif; ?>
        </div>
    </div>

    <!-- El filtro se queda siempre visible, aunque no haya tickets en este momento. -->
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:18px 0 6px;">
        <input id="buscar-folio" type="search" placeholder="🔎 Buscar por folio (ej. 0023)" autocomplete="off" style="min-width:220px; padding:10px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
        <select id="filtro-ticket-grupo" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todos los estatus</option>
            <option value="libre">🔔 Libres (sin tomar)</option>
            <option value="tomado">⏳ Tomados (en proceso)</option>
            <option value="resuelto">✅ Resueltos</option>
            <option value="no_procede">❌ No procede</option>
        </select>
        <select id="filtro-ticket-area" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todas las áreas</option>
            <?php foreach ($areasEnLista as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="filtro-ticket-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
    </div>
    <!-- Rango de fechas (fecha en que se registró el ticket). Las descargas de arriba lo respetan. -->
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin:6px 0;">
        <div class="campo" style="margin:0;">
            <label for="filtro-fecha-desde" style="font-size:12px;">📅 Registrados desde</label>
            <input id="filtro-fecha-desde" type="date" style="padding:9px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
        </div>
        <div class="campo" style="margin:0;">
            <label for="filtro-fecha-hasta" style="font-size:12px;">hasta</label>
            <input id="filtro-fecha-hasta" type="date" style="padding:9px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
        </div>
        <select id="filtro-fecha-preset" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Periodo rápido…</option>
        </select>
        <button type="button" id="btn-descargar-filtrados" class="btn-secundario" style="border:none; cursor:pointer;" onclick="descargarFiltrados()" title="Descarga en CSV los tickets que ves ahora en pantalla, con todos los filtros aplicados">⬇️ Descargar filtrados (CSV)</button>
    </div>
    <p id="filtro-ticket-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 10px;"></p>

    <?php if (empty($tickets)): ?>
        <p>
            <?php if ($verArchivados): ?>No hay tickets archivados.
            <?php elseif ($verMios): ?>No tienes tickets tomados en este momento.
            <?php else: ?>Aún no hay tickets registrados.
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <p id="filtro-ticket-vacio" style="display:none; color:var(--text-muted);">No hay tickets que coincidan con el filtro.</p>

    <div class="contenedor-acciones" id="lista-tickets">
        <?php foreach ($tickets as $t): [$etq, $fondo, $color] = $estilosPill[$t['estatus']]; ?>
            <?php
                // "Libre" = nadie lo ha tomado todavía; "Tomado" = alguien lo está atendiendo ahora mismo;
                // "Resuelto" y "No procede" van separados, para poder filtrarlos por su cuenta.
                if ($t['estatus'] === 'resuelto' || $t['estatus'] === 'no_procede') {
                    $grupoFiltro = $t['estatus'];
                } elseif ($t['atendido_por']) {
                    $grupoFiltro = 'tomado';
                } else {
                    $grupoFiltro = 'libre';
                }
            ?>
            <div class="caja-accion tarjeta-ticket" data-id="<?= (int) $t['id'] ?>" data-grupo="<?= $grupoFiltro ?>" data-area="<?= htmlspecialchars($t['area']) ?>" data-fecha="<?= htmlspecialchars(substr($t['creado_en'], 0, 10)) ?>" style="text-align:left; cursor:pointer; <?= $t['estatus'] === 'nuevo' ? 'border-top-color: var(--dorado);' : '' ?>" onclick="document.getElementById('modal-ticket-<?= $t['id'] ?>').style.display='flex'">
                <span class="pill" style="background: <?= $fondo ?>; color: <?= $color ?>;"><?= $etq ?></span>
                <h3 class="mono" style="text-align:center;"><?= htmlspecialchars($t['folio']) ?></h3>
                <p><strong>De:</strong> <?= htmlspecialchars($t['nombre']) ?> (<?= htmlspecialchars($t['area']) ?>)</p>
                <p><strong>Tipo:</strong> <?= htmlspecialchars($t['tipo']) ?></p>
                <?php if ($t['atendio_nombre'] && $t['estatus'] === 'en_proceso'): ?>
                    <p style="font-size:12px; color:var(--dorado-dark); font-weight:700;">🔒 Atendiendo: <?= htmlspecialchars($t['atendio_nombre']) ?></p>
                <?php elseif ($t['atendio_nombre'] && in_array($t['estatus'], ['resuelto', 'no_procede'], true)): ?>
                    <p style="font-size:12px; color:var(--text-muted); font-weight:700;">🔒 Cerrado por: <?= htmlspecialchars($t['atendio_nombre']) ?></p>
                <?php endif; ?>
                <?php
                    $otrosParticipantes = array_values(array_filter($t['participantes'], function ($p) use ($t) { return (int) $p['id'] !== (int) $t['atendido_por']; }));
                ?>
                <?php if ($otrosParticipantes): ?>
                    <p style="font-size:12px; color:var(--text-muted);">👥 Con: <?= htmlspecialchars(implode(', ', array_column($otrosParticipantes, 'nombre'))) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['nota_admin'])): ?>
                    <p style="font-size:12px; color:var(--text-muted);">📝 Tiene una nota</p>
                <?php endif; ?>
                <?php if (!empty($t['imagen'])): ?>
                    <p style="font-size:12px; color:var(--text-muted);">📷 Tiene imagen adjunta</p>
                <?php endif; ?>
                <p style="font-size:12px; color:var(--text-muted); text-align:center; margin-top:8px;">Ver detalle →</p>
            </div>

            <!-- Modal de detalle del ticket -->
            <div class="popup-overlay" id="modal-ticket-<?= $t['id'] ?>" onclick="if(event.target===this) this.style.display='none'">
                <div class="popup-box" style="text-align:left; max-width:480px;" onclick="event.stopPropagation()">
                    <button class="popup-close" onclick="document.getElementById('modal-ticket-<?= $t['id'] ?>').style.display='none'">✖</button>
                    <h2 class="mono" style="margin-top:0;"><?= htmlspecialchars($t['folio']) ?></h2>
                    <p><strong>De:</strong> <?= htmlspecialchars($t['nombre']) ?> (<?= htmlspecialchars($t['area']) ?>)</p>
                    <p><strong>Equipo:</strong> <?= htmlspecialchars($t['equipo']) ?></p>
                    <p><strong>Tipo:</strong> <?= htmlspecialchars($t['tipo']) ?></p>
                    <p><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($t['comentarios'])) ?></p>
                    <?php if (!empty($t['imagen'])): ?>
                        <p style="margin-bottom:6px;"><strong>Imagen adjunta:</strong></p>
                        <img src="uploads/tickets/<?= htmlspecialchars($t['imagen']) ?>" alt="Imagen del ticket" style="max-width:100%; border-radius:10px; cursor:zoom-in; display:block; margin-bottom:10px;" onclick="abrirImagenTicket('uploads/tickets/<?= htmlspecialchars($t['imagen']) ?>')" title="Clic para ampliar">
                    <?php endif; ?>
                    <p style="font-size:12px; color:var(--text-muted);">Registrado: <?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></p>
                    <?php if ($t['resuelto_en']): ?>
                        <p style="font-size:12px; color:var(--text-muted);">Cerrado: <?= date('d/m/Y H:i', strtotime($t['resuelto_en'])) ?></p>
                    <?php endif; ?>

                    <?php if ($t['atendio_nombre']): ?>
                        <div class="aviso-importante" style="margin:12px 0;">🔒 <?= in_array($t['estatus'], ['resuelto', 'no_procede'], true) ? 'Lo cerró' : 'Lo está atendiendo' ?> <strong><?= htmlspecialchars($t['atendio_nombre']) ?></strong><?= $t['atendio_nombre'] !== $yo['nombre'] && $t['estatus'] === 'en_proceso' ? ' — coordínate con esa persona antes de tomarlo.' : '' ?></div>
                    <?php endif; ?>

                    <?php
                        $idsParticipantes = array_column($t['participantes'], 'id');
                        $yoParticipa = in_array((int) $yo['id'], $idsParticipantes, true);
                        $ticketCerrado = in_array($t['estatus'], ['resuelto', 'no_procede'], true);
                        $adminsDisponibles = array_values(array_filter($adminsLista, function ($a) use ($idsParticipantes) { return !in_array($a['id'], $idsParticipantes, true); }));
                    ?>
                    <?php if ($participantesOk): ?>
                    <div style="margin:12px 0; padding:10px 12px; border:1px solid var(--border); border-radius:10px;">
                        <p style="margin:0 0 6px; font-weight:700;">👥 Participantes</p>
                        <?php if (empty($t['participantes'])): ?>
                            <p style="font-size:12.5px; color:var(--text-muted); margin:0;">Nadie lo ha tomado todavía.</p>
                        <?php endif; ?>
                        <?php foreach ($t['participantes'] as $part): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:4px 0;">
                                <span><?= htmlspecialchars($part['nombre']) ?><?php if ((int) $part['id'] === (int) $t['atendido_por']): ?> <span style="font-size:11px; color:var(--text-muted);">(<?= $ticketCerrado ? 'lo cerró' : 'lo tiene' ?>)</span><?php endif; ?></span>
                                <?php if ($yoParticipa && (int) $part['id'] !== (int) $yo['id'] && (int) $part['id'] !== (int) $t['atendido_por']): ?>
                                    <form method="post" onsubmit="return confirm('¿Quitar a esta persona de los participantes del ticket?');" style="margin:0;">
                                        <input type="hidden" name="accion" value="quitar_participante"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="quitar_id" value="<?= (int) $part['id'] ?>">
                                        <button type="submit" title="Quitar" style="border:none; background:none; cursor:pointer; color:var(--danger, #B23A3A);">✖</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($yoParticipa && $adminsDisponibles): ?>
                            <details style="margin-top:8px;">
                                <summary style="cursor:pointer; font-weight:600;">➕ Compartir con otra persona</summary>
                                <form method="post" style="margin-top:8px;">
                                    <input type="hidden" name="accion" value="compartir"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <?php foreach ($adminsDisponibles as $a): ?>
                                        <label style="display:flex; align-items:center; gap:8px; padding:3px 0;"><input type="checkbox" name="usuarios[]" value="<?= $a['id'] ?>" style="width:auto;"> <?= htmlspecialchars($a['nombre']) ?></label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn-secundario" style="border:none; cursor:pointer; margin-top:6px;">Compartir</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <?php if (!$yoParticipa && $t['atendido_por'] && !$ticketCerrado): ?>
                            <form method="post" style="margin-top:8px;">
                                <input type="hidden" name="accion" value="unirse"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-accion" style="border:none; cursor:pointer;">🤝 Unirme a este ticket</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" style="margin-top:14px;">
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <div class="campo">
                            <label>Estatus</label>
                            <select name="estatus" style="width:100%; padding:8px; border-radius:8px;">
                                <?php foreach ($etiquetas as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= $t['estatus'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Nota para el usuario (opcional — por ejemplo, por qué no procede)</label>
                            <textarea name="nota_admin" rows="3" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);" placeholder="Ej. No hay piezas disponibles por el momento."><?= htmlspecialchars($t['nota_admin'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn-primario">Guardar</button>
                    </form>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                        <?php if (!$t['atendido_por']): ?>
                            <form method="post"><input type="hidden" name="accion" value="tomar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-accion" style="border:none; cursor:pointer;">🙋 Tomar este ticket</button>
                            </form>
                        <?php elseif ($yoParticipa || (int) $t['atendido_por'] === (int) $yo['id']): ?>
                            <form method="post"><input type="hidden" name="accion" value="liberar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;"><?= count($t['participantes']) > 1 ? 'Salirme del ticket' : 'Liberar ticket' ?></button>
                            </form>
                        <?php endif; ?>

                        <?php if (!$verArchivados): ?>
                            <form method="post" onsubmit="return confirm('¿Archivar este ticket? Ya no se verá en tu lista, pero el usuario lo sigue viendo en su historial.');">
                                <input type="hidden" name="accion" value="archivar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">📦 Archivar</button>
                            </form>
                        <?php else: ?>
                            <form method="post"><input type="hidden" name="accion" value="desarchivar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Desarchivar</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <a href="admin-ticket-imprimir.php?id=<?= $t['id'] ?>" target="_blank" class="btn-secundario" style="display:block; text-align:center; margin-top:10px; text-decoration:none;">🖨️ Imprimir este ticket</a>

                    <form method="post" onsubmit="return confirm('¿Eliminar este ticket para siempre? Desaparece también del historial del usuario. Si quieres conservarlo, descarga primero el respaldo en CSV.');" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" style="width:100%; border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar para siempre</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Visor para ampliar la imagen adjunta de un ticket -->
    <div class="popup-overlay" id="visor-imagen-ticket" onclick="cerrarImagenTicket()">
        <img id="visor-imagen-ticket-img" src="" alt="Imagen ampliada" style="max-width:92vw; max-height:92vh; border-radius:10px;">
    </div>
</div>

<script>
    function abrirImagenTicket(url) {
        document.getElementById('visor-imagen-ticket-img').src = url;
        document.getElementById('visor-imagen-ticket').style.display = 'flex';
    }
    function cerrarImagenTicket() {
        document.getElementById('visor-imagen-ticket').style.display = 'none';
    }
</script>

<script>window.RANGO_PRESETS = <?= json_encode(rangoPresets(), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="js/rango-fechas.js?v=1"></script>
<script>
    const grupoFiltro = document.getElementById('filtro-ticket-grupo');
    const areaFiltro = document.getElementById('filtro-ticket-area');
    const contadorFiltro = document.getElementById('filtro-ticket-contador');
    const vacioFiltro = document.getElementById('filtro-ticket-vacio');

    // Se puede volver a llamar después de cada actualización en vivo, porque
    // las tarjetas se vuelven a crear desde cero cada vez.
    // Quita guiones, espacios y mayúsculas para que "gtr-23", "GTR0023" o "23" encuentren el mismo folio.
    const normalizarFolio = texto => (texto || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const buscarFolio = document.getElementById('buscar-folio');

    function filtrarTickets() {
        const tarjetas = Array.from(document.querySelectorAll('.tarjeta-ticket'));
        const consulta = normalizarFolio(buscarFolio.value);
        let visibles = 0;
        tarjetas.forEach(t => {
            const coincideGrupo = !grupoFiltro.value || t.dataset.grupo === grupoFiltro.value;
            const coincideArea = !areaFiltro.value || t.dataset.area === areaFiltro.value;
            const folio = t.querySelector('h3') ? t.querySelector('h3').textContent : '';
            const coincideFolio = !consulta || normalizarFolio(folio).includes(consulta);
            const coincideFecha = RangoFechas.enRango(t.dataset.fecha, fechaDesde.value, fechaHasta.value);
            const mostrar = coincideGrupo && coincideArea && coincideFolio && coincideFecha;
            t.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });
        contadorFiltro.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' tickets';
        const botonFiltrados = document.getElementById('btn-descargar-filtrados');
        if (botonFiltrados) {
            botonFiltrados.textContent = '⬇️ Descargar filtrados (CSV) · ' + visibles;
            botonFiltrados.disabled = visibles === 0;
            botonFiltrados.style.opacity = visibles === 0 ? '.5' : '';
        }
        vacioFiltro.textContent = consulta
            ? 'No hay un ticket con ese folio en esta vista. Prueba en "Todos", "Los míos" o "Archivados", o quita los otros filtros.'
            : 'No hay tickets que coincidan con el filtro.';
        vacioFiltro.style.display = visibles === 0 && tarjetas.length > 0 ? '' : 'none';
    }

    // Descarga en CSV exactamente los tickets que se ven en pantalla (con todos los filtros puestos).
    function descargarFiltrados() {
        const ids = Array.from(document.querySelectorAll('.tarjeta-ticket'))
            .filter(t => t.style.display !== 'none')
            .map(t => t.dataset.id)
            .filter(Boolean);
        if (!ids.length) {
            alert('No hay tickets en pantalla para descargar. Cambia o limpia los filtros.');
            return;
        }
        const f = document.createElement('form');
        f.method = 'post';
        f.action = 'admin-tickets-exportar.php';
        f.style.display = 'none';
        const campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = 'ids';
        campo.value = ids.join(',');
        f.appendChild(campo);
        document.body.appendChild(f);
        f.submit();
        setTimeout(() => f.remove(), 2000);
    }

    // --- Rango de fechas ---
    const fechaDesde = document.getElementById('filtro-fecha-desde');
    const fechaHasta = document.getElementById('filtro-fecha-hasta');
    function actualizarEnlacesRango() {
        const partes = [];
        if (fechaDesde.value) partes.push('desde=' + encodeURIComponent(fechaDesde.value));
        if (fechaHasta.value) partes.push('hasta=' + encodeURIComponent(fechaHasta.value));
        document.querySelectorAll('.enlace-rango').forEach(a => {
            const base = a.dataset.base;
            a.setAttribute('href', partes.length ? base + (base.indexOf('?') === -1 ? '?' : '&') + partes.join('&') : base);
        });
    }
    RangoFechas.iniciar({
        desde: fechaDesde, hasta: fechaHasta, preset: document.getElementById('filtro-fecha-preset'),
        alCambiar: () => { actualizarEnlacesRango(); filtrarTickets(); },
    });

    grupoFiltro.addEventListener('change', filtrarTickets);
    areaFiltro.addEventListener('change', filtrarTickets);
    buscarFolio.addEventListener('input', filtrarTickets);
    // Enter con un solo resultado: abre directo ese ticket.
    buscarFolio.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        const visibles = Array.from(document.querySelectorAll('.tarjeta-ticket')).filter(t => t.style.display !== 'none');
        if (visibles.length === 1) visibles[0].click();
    });
    document.getElementById('filtro-ticket-limpiar').addEventListener('click', () => {
        grupoFiltro.value = ''; areaFiltro.value = ''; buscarFolio.value = '';
        fechaDesde.value = ''; fechaHasta.value = ''; document.getElementById('filtro-fecha-preset').value = '';
        actualizarEnlacesRango();
        filtrarTickets();
    });
    filtrarTickets();
</script>

<script>
    // --- Actualización en vivo del panel, sin recargar la página ---
    // Cada ~15s se vuelve a pedir el listado y se reconstruyen las tarjetas,
    // así que si otro administrador toma, cierra o actualiza un ticket, se ve
    // reflejado solo. Las acciones (tomar, liberar, archivar, guardar,
    // eliminar) siguen siendo las mismas de siempre (un POST normal que
    // recarga la página de quien las usa); esto solo mantiene actualizada la
    // vista de quien está mirando el panel sin tocar nada.
    const VISTA_ACTUAL = <?= json_encode($vista) ?>;
    // Se actualizan con cada refresco (vienen de admin_tickets_datos.php).
    let ADMINS_TICKETS = [];
    let PARTICIPANTES_OK = false;
    const ESTILOS_PILL = {
        nuevo: ['🔔 Nuevo', 'rgba(201,162,39,.18)', 'var(--dorado-dark)'],
        en_proceso: ['⏳ En proceso', 'rgba(45,115,221,.15)', '#2d73dd'],
        resuelto: ['✅ Resuelto', 'rgba(46,125,91,.15)', 'var(--success, #2E7D5B)'],
        no_procede: ['❌ No procede', 'rgba(178,58,58,.15)', 'var(--danger, #B23A3A)'],
    };
    const ETIQUETAS_ESTATUS_TICKET = { nuevo: 'Nuevo', en_proceso: 'En proceso', resuelto: 'Resuelto', no_procede: 'No procede' };

    function escaparHtmlTicket(texto) {
        const d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    function construirTarjetaTicket(t) {
        const [etq, fondo, color] = ESTILOS_PILL[t.estatus];
        let grupo;
        if (t.estatus === 'resuelto' || t.estatus === 'no_procede') grupo = t.estatus;
        else if (t.atendido_por) grupo = 'tomado';
        else grupo = 'libre';

        let extra = '';
        if (t.atendio_nombre && t.estatus === 'en_proceso') {
            extra += `<p style="font-size:12px; color:var(--dorado-dark); font-weight:700;">🔒 Atendiendo: ${escaparHtmlTicket(t.atendio_nombre)}</p>`;
        } else if (t.atendio_nombre && (t.estatus === 'resuelto' || t.estatus === 'no_procede')) {
            extra += `<p style="font-size:12px; color:var(--text-muted); font-weight:700;">🔒 Cerrado por: ${escaparHtmlTicket(t.atendio_nombre)}</p>`;
        }
        const otrosParticipantes = (t.participantes || []).filter(p => p.id !== t.atendido_por);
        if (otrosParticipantes.length) extra += `<p style="font-size:12px; color:var(--text-muted);">👥 Con: ${otrosParticipantes.map(p => escaparHtmlTicket(p.nombre)).join(', ')}</p>`;
        if (t.nota_admin) extra += `<p style="font-size:12px; color:var(--text-muted);">📝 Tiene una nota</p>`;
        if (t.imagen) extra += `<p style="font-size:12px; color:var(--text-muted);">📷 Tiene imagen adjunta</p>`;

        return `
            <div class="caja-accion tarjeta-ticket" data-id="${t.id}" data-grupo="${grupo}" data-area="${escaparHtmlTicket(t.area)}" data-fecha="${escaparHtmlTicket(String(t.creado_en).slice(0, 10))}" style="text-align:left; cursor:pointer; ${t.estatus === 'nuevo' ? 'border-top-color: var(--dorado);' : ''}" onclick="document.getElementById('modal-ticket-${t.id}').style.display='flex'">
                <span class="pill" style="background: ${fondo}; color: ${color};">${etq}</span>
                <h3 class="mono" style="text-align:center;">${escaparHtmlTicket(t.folio)}</h3>
                <p><strong>De:</strong> ${escaparHtmlTicket(t.nombre)} (${escaparHtmlTicket(t.area)})</p>
                <p><strong>Tipo:</strong> ${escaparHtmlTicket(t.tipo)}</p>
                ${extra}
                <p style="font-size:12px; color:var(--text-muted); text-align:center; margin-top:8px;">Ver detalle →</p>
            </div>`;
    }

    // Panel "Participantes" de la ventana de un ticket (igual al que arma PHP).
    function construirPanelParticipantes(t, yo) {
        if (!PARTICIPANTES_OK) return '';
        const lista = t.participantes || [];
        const participa = lista.some(p => p.id === yo.id);
        const cerrado = t.estatus === 'resuelto' || t.estatus === 'no_procede';
        const filas = lista.length ? lista.map(p => {
            const rol = (p.id === t.atendido_por) ? ` <span style="font-size:11px; color:var(--text-muted);">(${cerrado ? 'lo cerró' : 'lo tiene'})</span>` : '';
            const quitar = (participa && p.id !== yo.id && p.id !== t.atendido_por)
                ? `<form method="post" onsubmit="return confirm('¿Quitar a esta persona de los participantes del ticket?');" style="margin:0;">
                        <input type="hidden" name="accion" value="quitar_participante"><input type="hidden" name="id" value="${t.id}"><input type="hidden" name="quitar_id" value="${p.id}">
                        <button type="submit" title="Quitar" style="border:none; background:none; cursor:pointer; color:var(--danger, #B23A3A);">✖</button>
                   </form>` : '';
            return `<div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:4px 0;"><span>${escaparHtmlTicket(p.nombre)}${rol}</span>${quitar}</div>`;
        }).join('') : '<p style="font-size:12.5px; color:var(--text-muted); margin:0;">Nadie lo ha tomado todavía.</p>';

        const disponibles = (ADMINS_TICKETS || []).filter(a => !lista.some(p => p.id === a.id));
        let compartir = '';
        if (participa && disponibles.length) {
            compartir = `<details style="margin-top:8px;"><summary style="cursor:pointer; font-weight:600;">➕ Compartir con otra persona</summary>
                <form method="post" style="margin-top:8px;">
                    <input type="hidden" name="accion" value="compartir"><input type="hidden" name="id" value="${t.id}">
                    ${disponibles.map(a => `<label style="display:flex; align-items:center; gap:8px; padding:3px 0;"><input type="checkbox" name="usuarios[]" value="${a.id}" style="width:auto;"> ${escaparHtmlTicket(a.nombre)}</label>`).join('')}
                    <button type="submit" class="btn-secundario" style="border:none; cursor:pointer; margin-top:6px;">Compartir</button>
                </form></details>`;
        }
        let unirme = '';
        if (!participa && t.atendido_por && !cerrado) {
            unirme = `<form method="post" style="margin-top:8px;">
                <input type="hidden" name="accion" value="unirse"><input type="hidden" name="id" value="${t.id}">
                <button type="submit" class="btn-accion" style="border:none; cursor:pointer;">🤝 Unirme a este ticket</button>
            </form>`;
        }
        return `<div style="margin:12px 0; padding:10px 12px; border:1px solid var(--border); border-radius:10px;">
            <p style="margin:0 0 6px; font-weight:700;">👥 Participantes</p>${filas}${compartir}${unirme}</div>`;
    }

    function construirModalTicket(t, yo, verArchivados) {
        const opcionesEstatus = Object.entries(ETIQUETAS_ESTATUS_TICKET).map(([valor, etiqueta]) =>
            `<option value="${valor}" ${t.estatus === valor ? 'selected' : ''}>${etiqueta}</option>`).join('');

        let avisoImagen = '';
        if (t.imagen) {
            const rutaImagen = 'uploads/tickets/' + encodeURIComponent(t.imagen);
            avisoImagen = `
                <p style="margin-bottom:6px;"><strong>Imagen adjunta:</strong></p>
                <img src="${rutaImagen}" alt="Imagen del ticket" style="max-width:100%; border-radius:10px; cursor:zoom-in; display:block; margin-bottom:10px;" onclick="abrirImagenTicket('${rutaImagen}')" title="Clic para ampliar">`;
        }

        let avisoCerrado = '';
        if (t.resuelto_en_fmt) {
            avisoCerrado = `<p style="font-size:12px; color:var(--text-muted);">Cerrado: ${t.resuelto_en_fmt}</p>`;
        }

        let avisoAtendio = '';
        if (t.atendio_nombre) {
            const esCerrado = t.estatus === 'resuelto' || t.estatus === 'no_procede';
            const coordinate = (t.atendio_nombre !== yo.nombre && t.estatus === 'en_proceso') ? ' — coordínate con esa persona antes de tomarlo.' : '';
            avisoAtendio = `<div class="aviso-importante" style="margin:12px 0;">🔒 ${esCerrado ? 'Lo cerró' : 'Lo está atendiendo'} <strong>${escaparHtmlTicket(t.atendio_nombre)}</strong>${coordinate}</div>`;
        }

        let botonTomarLiberar = '';
        if (!t.atendido_por) {
            botonTomarLiberar = `<form method="post"><input type="hidden" name="accion" value="tomar"><input type="hidden" name="id" value="${t.id}">
                <button type="submit" class="btn-accion" style="border:none; cursor:pointer;">🙋 Tomar este ticket</button>
            </form>`;
        } else if (t.atendido_por === yo.id || (t.participantes || []).some(p => p.id === yo.id)) {
            const etiquetaSalir = (t.participantes || []).length > 1 ? 'Salirme del ticket' : 'Liberar ticket';
            botonTomarLiberar = `<form method="post"><input type="hidden" name="accion" value="liberar"><input type="hidden" name="id" value="${t.id}">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">${etiquetaSalir}</button>
            </form>`;
        }

        const botonArchivar = !verArchivados
            ? `<form method="post" onsubmit="return confirm('¿Archivar este ticket? Ya no se verá en tu lista, pero el usuario lo sigue viendo en su historial.');">
                <input type="hidden" name="accion" value="archivar"><input type="hidden" name="id" value="${t.id}">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">📦 Archivar</button>
            </form>`
            : `<form method="post"><input type="hidden" name="accion" value="desarchivar"><input type="hidden" name="id" value="${t.id}">
                <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">Desarchivar</button>
            </form>`;

        return `
            <div class="popup-overlay" id="modal-ticket-${t.id}" onclick="if(event.target===this) this.style.display='none'">
                <div class="popup-box" style="text-align:left; max-width:480px;" onclick="event.stopPropagation()">
                    <button class="popup-close" onclick="document.getElementById('modal-ticket-${t.id}').style.display='none'">✖</button>
                    <h2 class="mono" style="margin-top:0;">${escaparHtmlTicket(t.folio)}</h2>
                    <p><strong>De:</strong> ${escaparHtmlTicket(t.nombre)} (${escaparHtmlTicket(t.area)})</p>
                    <p><strong>Equipo:</strong> ${escaparHtmlTicket(t.equipo)}</p>
                    <p><strong>Tipo:</strong> ${escaparHtmlTicket(t.tipo)}</p>
                    <p><strong>Descripción:</strong><br>${escaparHtmlTicket(t.comentarios).replace(/\n/g, '<br>')}</p>
                    ${avisoImagen}
                    <p style="font-size:12px; color:var(--text-muted);">Registrado: ${t.creado_en_fmt}</p>
                    ${avisoCerrado}
                    ${avisoAtendio}
                    ${construirPanelParticipantes(t, yo)}
                    <form method="post" style="margin-top:14px;">
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id" value="${t.id}">
                        <div class="campo">
                            <label>Estatus</label>
                            <select name="estatus" style="width:100%; padding:8px; border-radius:8px;">${opcionesEstatus}</select>
                        </div>
                        <div class="campo">
                            <label>Nota para el usuario (opcional — por ejemplo, por qué no procede)</label>
                            <textarea name="nota_admin" rows="3" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body);" placeholder="Ej. No hay piezas disponibles por el momento.">${escaparHtmlTicket(t.nota_admin || '')}</textarea>
                        </div>
                        <button type="submit" class="btn-primario">Guardar</button>
                    </form>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                        ${botonTomarLiberar}
                        ${botonArchivar}
                    </div>
                    <a href="admin-ticket-imprimir.php?id=${t.id}" target="_blank" class="btn-secundario" style="display:block; text-align:center; margin-top:10px; text-decoration:none;">🖨️ Imprimir este ticket</a>
                    <form method="post" onsubmit="return confirm('¿Eliminar este ticket para siempre? Desaparece también del historial del usuario. Si quieres conservarlo, descarga primero el respaldo en CSV.');" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="${t.id}">
                        <button type="submit" style="width:100%; border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar para siempre</button>
                    </form>
                </div>
            </div>`;
    }

    function actualizarPanelTickets() {
        // Si hay un ticket abierto, no se reconstruye nada: así no se te
        // borra lo que estés escribiendo en la nota a medio camino. En
        // cuanto cierras ese ticket, la actualización automática continúa.
        const hayModalAbierto = Array.from(document.querySelectorAll('.popup-overlay[id^="modal-ticket-"]'))
            .some(m => m.style.display === 'flex');
        if (hayModalAbierto) return;

        fetch('admin_tickets_datos.php?ver=' + encodeURIComponent(VISTA_ACTUAL))
            .then(r => r.json())
            .then(datos => {
                const lista = document.getElementById('lista-tickets');
                ADMINS_TICKETS = datos.admins || [];
                PARTICIPANTES_OK = !!datos.participantesOk;
                lista.innerHTML = datos.tickets.map(t => construirTarjetaTicket(t)).join('')
                    + datos.tickets.map(t => construirModalTicket(t, datos.yo, datos.verArchivados)).join('');

                // Contadores y avisos de arriba.
                document.getElementById('contador-activos').textContent = datos.totales.activos;
                document.getElementById('contador-mios').textContent = datos.totales.mios;
                document.getElementById('contador-archivados').textContent = datos.totales.archivados;

                const avisoNuevosWrap = document.getElementById('aviso-nuevos-wrap');
                if (VISTA_ACTUAL === 'activos' && datos.totales.nuevos > 0) {
                    const s = datos.totales.nuevos === 1 ? '' : 's';
                    avisoNuevosWrap.innerHTML = `<div class="aviso-importante">🔔 Tienes <strong>${datos.totales.nuevos}</strong> ticket${s} nuevo${s} sin atender.</div>`;
                } else {
                    avisoNuevosWrap.innerHTML = '';
                }

                const vaciarWrap = document.getElementById('boton-vaciar-cerrados-wrap');
                if (datos.totales.cerrados > 0) {
                    vaciarWrap.innerHTML = `
                        <form method="post" onsubmit="return confirm('Vas a eliminar PARA SIEMPRE los ${datos.totales.cerrados} tickets cerrados (resueltos y no procede) de TODOS los administradores. Esto también borra ese historial de la vista \\'Mis tickets\\' de cada usuario.\\n\\n¿Ya descargaste el CSV de respaldo? Si no, cancela y descárgalo primero.\\n\\n¿Continuar de todas formas?');">
                            <input type="hidden" name="accion" value="vaciar_cerrados">
                            <button type="submit" class="btn-peligro">🗑 Vaciar cerrados (${datos.totales.cerrados})</button>
                        </form>`;
                } else {
                    vaciarWrap.innerHTML = '';
                }

                filtrarTickets();
            })
            .catch(() => {});
    }

    setInterval(actualizarPanelTickets, 15000);
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
