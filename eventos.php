<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

$eventos = [];
$resultado = mysqli_query($conexion, "SELECT * FROM eventos_programados WHERE vigente = 1 ORDER BY (fecha IS NULL), fecha ASC, creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $eventos[] = $fila;
}

// Al entrar directo a Eventos, se marca todo lo que hay hasta ahora como
// visto para el número del menú (eventos_conteo.php); si luego se publica
// uno nuevo, el número vuelve a aparecer solo por ese.
$resMax = mysqli_query($conexion, "SELECT MAX(id) AS maximo FROM eventos_programados WHERE vigente = 1");
$maximoIdEvento = (int) (mysqli_fetch_assoc($resMax)['maximo'] ?? 0);
if ($maximoIdEvento > 0) {
    $stmtChk = mysqli_prepare($conexion, "INSERT INTO eventos_badge_checkpoint (usuario_id, visto_hasta_id) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE visto_hasta_id = GREATEST(visto_hasta_id, VALUES(visto_hasta_id))");
    mysqli_stmt_bind_param($stmtChk, "ii", $yo['id'], $maximoIdEvento);
    mysqli_stmt_execute($stmtChk);
}

// Comunicados y eventos que este usuario ya abrió con el popup (para la
// etiqueta "Visto" en cada tarjeta). Se marcan nuevos al abrir el popup.
$eventosVistos = [];
$stmtVistos = mysqli_prepare($conexion, "SELECT evento_id FROM eventos_vistos WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmtVistos, "i", $yo['id']);
mysqli_stmt_execute($stmtVistos);
$resVistos = mysqli_stmt_get_result($stmtVistos);
while ($fila = mysqli_fetch_assoc($resVistos)) {
    $eventosVistos[(int) $fila['evento_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eventos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=31">
  <link rel="stylesheet" href="tema.php">
  <link rel="stylesheet" href="css/comunicados.css">
</head>
<body>
<div id="nav-placeholder"></div>
  <div class="main-content">
    <h1>✨Eventos Programados</h1>
    <h2 style="font-size:15px; color:var(--text-muted); font-weight:600; margin-top:-6px;">🟢 Da clic en un evento para ver todos sus detalles</h2>

    <?php if (esAdmin()): ?>
        <p><a href="admin-eventos.php" class="btn-accion" style="display:inline-block; margin-bottom:20px;">➕ Publicar evento</a></p>
    <?php else: ?>
        <p style="font-size:13px; color:var(--text-muted); margin:0 0 18px;">📌 ¿Quieres agregar un evento? <a href="solicitar-publicacion.php?seccion=evento">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI).</p>
    <?php endif; ?>

    <?php if (empty($eventos)): ?>
        <div class="evento">
          <h3>No hay eventos programados por el momento</h3>
          <p><strong>Nota:</strong> <span style="color: rgb(221, 45, 45);">Puedes solicitar subir un evento al área informática.</span></p>
        </div>
    <?php else: ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:6px;">
            <select id="filtro-eventos" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Todos</option>
                <option value="hoy">📌 Hoy</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="vencidos">⏳ Vencidos</option>
            </select>
            <select id="orden-eventos" title="Ordenar por fecha" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="proximos">↕️ Ordenar: Más próximos primero</option>
                <option value="antiguos">↕️ Ordenar: Más antiguos primero</option>
            </select>
            <button type="button" id="filtro-eventos-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
        </div>
        <p id="filtro-eventos-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 14px;"></p>
        <p id="filtro-eventos-vacio" style="display:none; color:var(--text-muted);">No hay eventos que coincidan con el filtro.</p>

        <div id="lista-eventos">
        <?php foreach ($eventos as $i => $ev): $visto = isset($eventosVistos[(int) $ev['id']]); ?>
            <div class="evento tarjeta-evento" data-fecha="<?= htmlspecialchars($ev['fecha'] ?? '') ?>" id="evento-<?= $ev['id'] ?>" style="cursor:pointer;" onclick="abrirModal(<?= $i ?>)">
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                  <span class="etiqueta-vencido" style="display:none; background:#B23A3A; color:#fff; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px;">⚠️ Vencido</span>
                  <span class="etiqueta-hoy" style="display:none; background:#2E7D4F; color:#fff; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px;">Hoy</span>
                  <span class="etiqueta-visto" style="display:<?= $visto ? 'inline-block' : 'none' ?>; background:rgba(45,115,221,.15); color:#2d73dd; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px;">✅ Visto</span>
                  <h3 style="margin:0; color:var(--titulo, var(--guinda));"><?= htmlspecialchars($ev['titulo']) ?></h3>
              </div>
              <p class="fecha" style="margin:4px 0 0;"><?= $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : 'Fecha por definir' ?><?= $ev['lugar'] ? ' — ' . htmlspecialchars($ev['lugar']) : '' ?></p>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="modal" id="modal">
        <div class="modal-contenido">
            <span class="cerrar" onclick="cerrarModal()">&times;</span>
            <h2 id="modal-titulo"></h2>
            <p class="fecha" id="modal-fecha"></p>
            <p id="modal-hora"></p>
            <p id="modal-lugar"></p>
            <p id="modal-nota" style="white-space:pre-wrap; text-align:left;"></p>
            <div id="modal-extra"></div>
        </div>
    </div>
  </div>

  <script src="js/nav.js?v=3"></script>
  <script src="js/nav-loader.js?v=30"></script>
  <script>
        const eventos = <?= json_encode(array_map(function ($ev) {
            return [
                'id' => (int) $ev['id'],
                'titulo' => $ev['titulo'],
                'fecha' => $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : 'Fecha por definir',
                'hora' => $ev['hora'] ?: null,
                'lugar' => $ev['lugar'] ?: null,
                'nota' => $ev['nota'] ? htmlspecialchars($ev['nota']) : null,
                'imagen' => $ev['imagen'] ? 'uploads/eventos/' . $ev['imagen'] : null,
                'pdf' => $ev['pdf'] ? 'uploads/eventos/' . $ev['pdf'] : null,
                'enlace_url' => $ev['enlace_url'] ?: null,
                'enlace_texto' => $ev['enlace_texto'] ?: null,
            ];
        }, $eventos), JSON_UNESCAPED_UNICODE) ?>;

        function abrirModal(indice) {
            const modal = document.getElementById('modal');
            const ev = eventos[indice];
            document.getElementById('modal-titulo').textContent = ev.titulo;
            document.getElementById('modal-fecha').textContent = '📅 ' + ev.fecha;
            document.getElementById('modal-hora').textContent = ev.hora ? '🕒 ' + ev.hora : '';
            document.getElementById('modal-lugar').textContent = ev.lugar ? '📍 ' + ev.lugar : '';
            document.getElementById('modal-nota').innerHTML = ev.nota || '';

            const contenedor = document.getElementById('modal-extra');
            contenedor.innerHTML = '';
            if (ev.imagen) {
                const img = document.createElement('img');
                img.src = ev.imagen;
                contenedor.appendChild(img);
            }
            if (ev.pdf) {
                const linkPdf = document.createElement('a');
                linkPdf.href = ev.pdf;
                linkPdf.textContent = '📎 Descargar documento PDF';
                linkPdf.target = '_blank';
                linkPdf.rel = 'noopener';
                contenedor.appendChild(linkPdf);
            }
            if (ev.enlace_url) {
                const link = document.createElement('a');
                link.href = ev.enlace_url;
                link.textContent = '🔗 ' + (ev.enlace_texto || 'Unirse / Ver enlace');
                link.target = '_blank';
                link.rel = 'noopener';
                contenedor.appendChild(link);
            }
            modal.style.display = 'flex';
            marcarVisto('evento', ev.id);
        }

        function cerrarModal() {
            document.getElementById('modal').style.display = 'none';
            // Se quita el #evento-N para que la misma notificación vuelva a abrirlo.
            if (location.hash.indexOf('#evento-') === 0) history.replaceState(null, '', location.pathname + location.search);
        }

        // Marca un comunicado/evento como visto (para la etiqueta "Visto") y
        // muestra la etiqueta de inmediato en su tarjeta, sin recargar.
        function marcarVisto(tipo, id) {
            const tarjeta = document.getElementById((tipo === 'evento' ? 'evento-' : 'comunicado-') + id);
            const etiqueta = tarjeta ? tarjeta.querySelector('.etiqueta-visto') : null;
            if (etiqueta && etiqueta.style.display === 'inline-block') return; // ya estaba marcado
            fetch('marcar_visto.php', { method: 'POST', body: new URLSearchParams({ tipo, id }) })
                .then(r => r.json())
                .then(resp => {
                    if (resp.ok && etiqueta) etiqueta.style.display = 'inline-block';
                })
                .catch(() => {});
        }

        // Si se llegó aquí desde "Ir a Eventos" del Calendario (enlace con
        // #evento-<id>), abre directo el detalle de ese evento.
        // También funciona si ya estabas en esta página y das clic en la notificación.
        function abrirEventoDesdeEnlace() {
            const coincidencia = location.hash.match(/^#evento-(\d+)$/);
            if (!coincidencia) return;
            const idBuscado = Number(coincidencia[1]);
            const indice = eventos.findIndex(ev => ev.id === idBuscado);
            if (indice !== -1) {
                abrirModal(indice);
                const tarjeta = document.getElementById('evento-' + idBuscado);
                if (tarjeta) tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        abrirEventoDesdeEnlace();
        window.addEventListener('hashchange', abrirEventoDesdeEnlace);

        // Filtro por Hoy / Esta semana / Este mes, y la etiqueta verde "Hoy".
        (function () {
            const tarjetas = Array.from(document.querySelectorAll('.tarjeta-evento'));
            if (!tarjetas.length) return;
            const filtro = document.getElementById('filtro-eventos');
            const contador = document.getElementById('filtro-eventos-contador');
            const vacio = document.getElementById('filtro-eventos-vacio');

            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const inicioSemana = new Date(hoy);
            inicioSemana.setDate(hoy.getDate() - hoy.getDay());
            const finSemana = new Date(inicioSemana);
            finSemana.setDate(inicioSemana.getDate() + 6);

            function aFecha(texto) {
                if (!texto) return null;
                const f = new Date(texto + 'T00:00:00');
                return isNaN(f) ? null : f;
            }

            tarjetas.forEach(t => {
                const f = aFecha(t.dataset.fecha);
                if (f && f.getTime() === hoy.getTime()) {
                    const etiqueta = t.querySelector('.etiqueta-hoy');
                    if (etiqueta) etiqueta.style.display = 'inline-block';
                } else if (f && f.getTime() < hoy.getTime()) {
                    const etiquetaVencido = t.querySelector('.etiqueta-vencido');
                    if (etiquetaVencido) etiquetaVencido.style.display = 'inline-block';
                }
            });

            function filtrar() {
                let visibles = 0;
                tarjetas.forEach(t => {
                    const f = aFecha(t.dataset.fecha);
                    let mostrar = true;
                    if (filtro.value === 'hoy') {
                        mostrar = !!f && f.getTime() === hoy.getTime();
                    } else if (filtro.value === 'semana') {
                        mostrar = !!f && f >= inicioSemana && f <= finSemana;
                    } else if (filtro.value === 'mes') {
                        mostrar = !!f && f.getFullYear() === hoy.getFullYear() && f.getMonth() === hoy.getMonth();
                    } else if (filtro.value === 'vencidos') {
                        mostrar = !!f && f.getTime() < hoy.getTime();
                    }
                    t.style.display = mostrar ? '' : 'none';
                    if (mostrar) visibles++;
                });
                contador.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' eventos';
                vacio.style.display = visibles === 0 ? '' : 'none';
            }

            // --- Orden por fecha del evento ---
            const orden = document.getElementById('orden-eventos');
            const lista = document.getElementById('lista-eventos');
            try {
                const guardado = localStorage.getItem('ordenEventos');
                if (guardado && orden.querySelector('option[value="' + guardado + '"]')) orden.value = guardado;
            } catch (e) {}

            function ordenar() {
                const tiempo = t => { const f = aFecha(t.dataset.fecha); return f ? f.getTime() : null; };
                const lista_ = tarjetas.slice();
                lista_.sort((a, b) => {
                    const ta = tiempo(a), tb = tiempo(b);
                    if (ta === null || tb === null) return (ta === null) - (tb === null); // sin fecha, al final
                    if (orden.value === 'antiguos') return ta - tb; // la fecha más vieja arriba
                    // "Más próxima primero": hoy y futuros (de cerca a lejos), y los vencidos al final (el más reciente primero)
                    const vencidoA = ta < hoy.getTime(), vencidoB = tb < hoy.getTime();
                    if (vencidoA !== vencidoB) return vencidoA - vencidoB;
                    return vencidoA ? tb - ta : ta - tb;
                });
                // Los de "sin fecha" van antes que los vencidos en "más próxima".
                if (orden.value === 'proximos') {
                    const futuros = lista_.filter(t => tiempo(t) !== null && tiempo(t) >= hoy.getTime());
                    const sinFecha = lista_.filter(t => tiempo(t) === null);
                    const vencidos = lista_.filter(t => tiempo(t) !== null && tiempo(t) < hoy.getTime());
                    lista_.length = 0;
                    Array.prototype.push.apply(lista_, futuros.concat(sinFecha, vencidos));
                }
                lista_.forEach(t => lista.appendChild(t));
            }

            filtro.addEventListener('change', filtrar);
            orden.addEventListener('change', () => {
                try { localStorage.setItem('ordenEventos', orden.value); } catch (e) {}
                ordenar();
            });
            document.getElementById('filtro-eventos-limpiar').addEventListener('click', () => {
                filtro.value = '';
                orden.value = 'proximos';
                try { localStorage.removeItem('ordenEventos'); } catch (e) {}
                ordenar();
                filtrar();
            });
            ordenar();
            filtrar();
        })();
  </script>
</body>
</html>
