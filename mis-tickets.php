<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/rango_fechas.php';
requiereLogin();
$yo = usuarioActual();

// Al entrar aquí, se dan por vistas las actualizaciones de estatus pendientes.
$stmtVisto = mysqli_prepare($conexion, "UPDATE tickets SET usuario_notificado = 1 WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmtVisto, "i", $yo['id']);
mysqli_stmt_execute($stmtVisto);

$tickets = [];
$stmt = mysqli_prepare($conexion, "SELECT t.folio, t.tipo, t.equipo, t.comentarios, t.imagen, t.estatus, t.nota_admin, t.creado_en, t.resuelto_en, u.nombre AS atendio_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.atendido_por
    WHERE t.usuario_id = ?
    ORDER BY t.creado_en DESC");
mysqli_stmt_bind_param($stmt, "i", $yo['id']);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tickets[] = $fila;
}

$etiquetas = [
    'nuevo' => ['texto' => 'Nuevo', 'color' => 'var(--dorado-dark)', 'fondo' => 'rgba(201,162,39,.15)'],
    'en_proceso' => ['texto' => 'En proceso', 'color' => '#2d73dd', 'fondo' => 'rgba(45,115,221,.12)'],
    'resuelto' => ['texto' => 'Resuelto', 'color' => 'var(--success, #2E7D5B)', 'fondo' => 'rgba(46,125,91,.15)'],
    'no_procede' => ['texto' => 'No procede', 'color' => 'var(--danger, #B23A3A)', 'fondo' => 'rgba(178,58,58,.15)'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Mis tickets</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📋 Mis tickets</h1>
    <p style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="mesa-servicios.php" class="enlace-volver">← Volver a Mesa de Servicios</a>
        <?php if ($tickets): ?>
            <a href="mis-tickets-imprimir.php" target="_blank" class="enlace-ver enlace-rango" data-base="mis-tickets-imprimir.php">🖨️ Imprimir / PDF de mi historial</a>
            <a href="mis-tickets-exportar.php" class="enlace-ver enlace-rango" data-base="mis-tickets-exportar.php">⬇️ Descargar mi historial (CSV)</a>
        <?php endif; ?>
    </p>

    <?php if (empty($tickets)): ?>
        <p>Todavía no has levantado ningún ticket.</p>
    <?php else: ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:18px 0 6px;">
            <input id="buscar-mi-folio" type="search" placeholder="🔎 Buscar por folio (ej. 0023)" autocomplete="off" style="min-width:220px; padding:10px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <select id="filtro-mis-tickets" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Todos los estatus</option>
                <option value="nuevo">🔔 Nuevo</option>
                <option value="en_proceso">⏳ En proceso</option>
                <option value="resuelto">✅ Resuelto</option>
                <option value="no_procede">❌ No procede</option>
            </select>
            <button type="button" id="filtro-mis-tickets-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
        </div>
        <!-- Rango de fechas (día en que se registró el ticket). La impresión y la descarga de arriba lo respetan. -->
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin:10px 0 6px;">
            <div class="campo" style="margin:0;">
                <label for="mis-fecha-desde" style="font-size:12px;">📅 Registrados desde</label>
                <input id="mis-fecha-desde" type="date" style="padding:9px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            </div>
            <div class="campo" style="margin:0;">
                <label for="mis-fecha-hasta" style="font-size:12px;">hasta</label>
                <input id="mis-fecha-hasta" type="date" style="padding:9px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            </div>
            <select id="mis-fecha-preset" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Periodo rápido…</option>
            </select>
        </div>
        <p id="filtro-mis-tickets-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 10px;"></p>
        <p id="filtro-mis-tickets-vacio" style="display:none; color:var(--text-muted);">No hay tickets que coincidan con el filtro.</p>
    <?php endif; ?>

    <div class="contenedor-acciones" id="lista-mis-tickets">
        <?php foreach ($tickets as $t): $e = $etiquetas[$t['estatus']] ?? $etiquetas['nuevo']; ?>
            <div class="caja-accion tarjeta-mi-ticket" data-estatus="<?= htmlspecialchars($t['estatus']) ?>" data-fecha="<?= htmlspecialchars(substr($t['creado_en'], 0, 10)) ?>" style="text-align:left;">
                <span class="pill" style="background: <?= $e['fondo'] ?>; color: <?= $e['color'] ?>;"><?= $e['texto'] ?></span>
                <h3 class="mono" style="text-align:center;"><?= htmlspecialchars($t['folio']) ?></h3>
                <p><strong>Tipo:</strong> <?= htmlspecialchars($t['tipo']) ?></p>
                <p><strong>Equipo:</strong> <?= htmlspecialchars($t['equipo']) ?></p>
                <p><?= nl2br(htmlspecialchars($t['comentarios'])) ?></p>
                <?php if (!empty($t['imagen'])): ?>
                    <p style="margin:8px 0 6px;"><strong>📷 Imagen que adjuntaste:</strong></p>
                    <img src="uploads/tickets/<?= htmlspecialchars($t['imagen']) ?>" alt="Imagen del ticket" style="max-width:100%; border-radius:10px; cursor:zoom-in; display:block; margin-bottom:10px;" onclick="abrirImagenTicket('uploads/tickets/<?= htmlspecialchars($t['imagen']) ?>')" title="Clic para ampliar">
                <?php endif; ?>
                <p style="font-size:12px; color:var(--text-muted);">Registrado: <?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></p>
                <?php if ($t['resuelto_en']): ?>
                    <p style="font-size:12px; color:var(--text-muted);">Cerrado: <?= date('d/m/Y H:i', strtotime($t['resuelto_en'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['atendio_nombre'])): ?>
                    <p style="font-size:12px; color:var(--text-muted);">
                        🔒 <?= in_array($t['estatus'], ['resuelto', 'no_procede'], true) ? 'Atendido por' : 'Lo está atendiendo' ?>: <strong><?= htmlspecialchars($t['atendio_nombre']) ?></strong>
                    </p>
                <?php endif; ?>
                <?php if (!empty($t['nota_admin'])): ?>
                    <div class="aviso-importante" style="margin-top:10px; text-align:left;">
                        <strong>📝 Nota de soporte:</strong><br><?= nl2br(htmlspecialchars($t['nota_admin'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Visor para ampliar la imagen adjunta de un ticket -->
<div class="popup-overlay" id="visor-imagen-ticket" onclick="cerrarImagenTicket()">
    <img id="visor-imagen-ticket-img" src="" alt="Imagen ampliada" style="max-width:92vw; max-height:92vh; border-radius:10px;">
</div>

<script>window.RANGO_PRESETS = <?= json_encode(rangoPresets(), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="js/rango-fechas.js?v=1"></script>
<script>
    function abrirImagenTicket(url) {
        document.getElementById('visor-imagen-ticket-img').src = url;
        document.getElementById('visor-imagen-ticket').style.display = 'flex';
    }
    function cerrarImagenTicket() {
        document.getElementById('visor-imagen-ticket').style.display = 'none';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarImagenTicket(); });
</script>

<script>
    const filtroMisTickets = document.getElementById('filtro-mis-tickets');
    const buscarMiFolio = document.getElementById('buscar-mi-folio');
    const contadorMisTickets = document.getElementById('filtro-mis-tickets-contador');
    const vacioMisTickets = document.getElementById('filtro-mis-tickets-vacio');
    const misFechaDesde = document.getElementById('mis-fecha-desde');
    const misFechaHasta = document.getElementById('mis-fecha-hasta');

    // Imprimir y descargar usan el mismo rango (y estatus) que se ve en pantalla.
    function actualizarEnlacesRango() {
        const partes = [];
        if (misFechaDesde && misFechaDesde.value) partes.push('desde=' + encodeURIComponent(misFechaDesde.value));
        if (misFechaHasta && misFechaHasta.value) partes.push('hasta=' + encodeURIComponent(misFechaHasta.value));
        if (filtroMisTickets && filtroMisTickets.value) partes.push('estatus=' + encodeURIComponent(filtroMisTickets.value));
        document.querySelectorAll('.enlace-rango').forEach(a => {
            const baseUrl = a.dataset.base;
            a.setAttribute('href', partes.length ? baseUrl + '?' + partes.join('&') : baseUrl);
        });
    }

    function filtrarMisTickets() {
        const tarjetas = Array.from(document.querySelectorAll('.tarjeta-mi-ticket'));
        if (!tarjetas.length || !filtroMisTickets) return;
        const consulta = (buscarMiFolio.value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        let visibles = 0;
        tarjetas.forEach(t => {
            const folio = (t.querySelector('h3') ? t.querySelector('h3').textContent : '').toLowerCase().replace(/[^a-z0-9]/g, '');
            const mostrar = (!filtroMisTickets.value || t.dataset.estatus === filtroMisTickets.value)
                && (!consulta || folio.includes(consulta))
                && RangoFechas.enRango(t.dataset.fecha, misFechaDesde.value, misFechaHasta.value);
            t.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });
        contadorMisTickets.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' tickets';
        vacioMisTickets.style.display = visibles === 0 ? '' : 'none';
    }

    if (filtroMisTickets) {
        filtroMisTickets.addEventListener('change', () => { actualizarEnlacesRango(); filtrarMisTickets(); });
        buscarMiFolio.addEventListener('input', filtrarMisTickets);
        RangoFechas.iniciar({
            desde: misFechaDesde, hasta: misFechaHasta, preset: document.getElementById('mis-fecha-preset'),
            alCambiar: () => { actualizarEnlacesRango(); filtrarMisTickets(); },
        });
        document.getElementById('filtro-mis-tickets-limpiar').addEventListener('click', () => {
            filtroMisTickets.value = ''; buscarMiFolio.value = '';
            misFechaDesde.value = ''; misFechaHasta.value = ''; document.getElementById('mis-fecha-preset').value = '';
            actualizarEnlacesRango();
            filtrarMisTickets();
        });
        filtrarMisTickets();
    }
</script>

<script>
    // --- Actualización en vivo, sin recargar la página ---
    // Cada ~15s se vuelve a pedir el listado, así que si el administrador
    // toma, actualiza o cierra alguno de tus tickets, lo ves reflejado solo.
    const ETIQUETAS_MIS_TICKETS = {
        nuevo: { texto: 'Nuevo', color: 'var(--dorado-dark)', fondo: 'rgba(201,162,39,.15)' },
        en_proceso: { texto: 'En proceso', color: '#2d73dd', fondo: 'rgba(45,115,221,.12)' },
        resuelto: { texto: 'Resuelto', color: 'var(--success, #2E7D5B)', fondo: 'rgba(46,125,91,.15)' },
        no_procede: { texto: 'No procede', color: 'var(--danger, #B23A3A)', fondo: 'rgba(178,58,58,.15)' },
    };

    function escaparHtmlMiTicket(texto) {
        const d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    function construirTarjetaMiTicket(t) {
        const e = ETIQUETAS_MIS_TICKETS[t.estatus] || ETIQUETAS_MIS_TICKETS.nuevo;

        let avisoCerrado = t.resuelto_en_fmt
            ? `<p style="font-size:12px; color:var(--text-muted);">Cerrado: ${t.resuelto_en_fmt}</p>` : '';

        let avisoAtendio = '';
        if (t.atendio_nombre) {
            const cerrado = (t.estatus === 'resuelto' || t.estatus === 'no_procede');
            avisoAtendio = `<p style="font-size:12px; color:var(--text-muted);">
                🔒 ${cerrado ? 'Atendido por' : 'Lo está atendiendo'}: <strong>${escaparHtmlMiTicket(t.atendio_nombre)}</strong>
            </p>`;
        }

        let avisoImagen = '';
        if (t.imagen) {
            const rutaImagen = 'uploads/tickets/' + encodeURIComponent(t.imagen);
            avisoImagen = `
                <p style="margin:8px 0 6px;"><strong>📷 Imagen que adjuntaste:</strong></p>
                <img src="${rutaImagen}" alt="Imagen del ticket" style="max-width:100%; border-radius:10px; cursor:zoom-in; display:block; margin-bottom:10px;" onclick="abrirImagenTicket('${rutaImagen}')" title="Clic para ampliar">`;
        }

        const avisoNota = t.nota_admin
            ? `<div class="aviso-importante" style="margin-top:10px; text-align:left;">
                <strong>📝 Nota de soporte:</strong><br>${escaparHtmlMiTicket(t.nota_admin).replace(/\n/g, '<br>')}
               </div>` : '';

        return `
            <div class="caja-accion tarjeta-mi-ticket" data-estatus="${t.estatus}" data-fecha="${escaparHtmlMiTicket(String(t.creado_en).slice(0, 10))}" style="text-align:left;">
                <span class="pill" style="background: ${e.fondo}; color: ${e.color};">${e.texto}</span>
                <h3 class="mono" style="text-align:center;">${escaparHtmlMiTicket(t.folio)}</h3>
                <p><strong>Tipo:</strong> ${escaparHtmlMiTicket(t.tipo)}</p>
                <p><strong>Equipo:</strong> ${escaparHtmlMiTicket(t.equipo)}</p>
                <p>${escaparHtmlMiTicket(t.comentarios).replace(/\n/g, '<br>')}</p>
                ${avisoImagen}
                <p style="font-size:12px; color:var(--text-muted);">Registrado: ${t.creado_en_fmt}</p>
                ${avisoCerrado}
                ${avisoAtendio}
                ${avisoNota}
            </div>`;
    }

    function actualizarMisTickets() {
        fetch('mis_tickets_datos.php')
            .then(r => r.json())
            .then(datos => {
                const lista = document.getElementById('lista-mis-tickets');
                if (!lista) return;
                lista.innerHTML = datos.tickets.map(t => construirTarjetaMiTicket(t)).join('');
                filtrarMisTickets();
            })
            .catch(() => {});
    }

    setInterval(actualizarMisTickets, 15000);
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
