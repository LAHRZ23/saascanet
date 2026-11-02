<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

$CATEGORIAS = [
    'general' => ['texto' => 'General', 'color' => '#2d73dd', 'fondo' => 'rgba(45,115,221,.12)'],
    'urgente' => ['texto' => '🚨 Urgente', 'color' => 'var(--danger, #B23A3A)', 'fondo' => 'rgba(178,58,58,.15)'],
    'informativo' => ['texto' => 'Informativo', 'color' => 'var(--dorado-dark)', 'fondo' => 'rgba(201,162,39,.15)'],
];

$comunicados = [];
// Orden inicial ("importancia"): los urgentes primero y luego todos los demás
// del más nuevo al más viejo. El selector "Ordenar por" lo cambia en pantalla.
$resultado = mysqli_query($conexion, "SELECT id, titulo, texto, categoria, imagen, pdf, creado_en FROM comunicados WHERE vigente = 1 ORDER BY (categoria = 'urgente') DESC, creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $comunicados[] = $fila;
}

// Comunicados que este usuario ya abrió con el popup (para la etiqueta "Visto").
$comunicadosVistos = [];
$stmtVistos = mysqli_prepare($conexion, "SELECT comunicado_id FROM comunicados_vistos WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmtVistos, "i", $yo['id']);
mysqli_stmt_execute($stmtVistos);
$resVistos = mysqli_stmt_get_result($stmtVistos);
while ($fila = mysqli_fetch_assoc($resVistos)) {
    $comunicadosVistos[(int) $fila['comunicado_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SAASCAEM - Comunicados</title>
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

        <h1>📢 Comunicados</h1>
        <h2>🟢Da clic en un comunicado para desplegar mayor información</h2>

        <?php if (esAdmin()): ?>
            <a href="admin-comunicados.php" class="btn-accion" style="display:inline-block; margin-bottom:20px;">➕ Publicar comunicado</a>
        <?php else: ?>
            <p style="font-size:13px; color:var(--text-muted); margin:0 0 18px;">📌 ¿Necesitas publicar un comunicado? <a href="solicitar-publicacion.php?seccion=comunicado">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI).</p>
        <?php endif; ?>

        <?php if (empty($comunicados)): ?>
            <p>No hay comunicados vigentes por el momento.</p>
        <?php else: ?>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:6px;">
                <select id="filtro-comunicados" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($CATEGORIAS as $clave => $catOpcion): ?>
                        <option value="<?= $clave ?>"><?= htmlspecialchars($catOpcion['texto']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="orden-comunicados" title="Ordenar por" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                    <option value="importancia">↕️ Ordenar: Importancia (urgentes primero)</option>
                    <option value="nuevo">↕️ Ordenar: Más nuevos primero</option>
                    <option value="viejo">↕️ Ordenar: Más viejos primero</option>
                </select>
                <button type="button" id="filtro-comunicados-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
            </div>
            <p id="filtro-comunicados-contador" style="font-size:13px; color:var(--text-muted); margin:0 0 14px;"></p>
            <p id="filtro-comunicados-vacio" style="display:none; color:var(--text-muted);">No hay comunicados que coincidan con el filtro.</p>
        <?php endif; ?>

        <div id="lista-comunicados">
        <?php foreach ($comunicados as $i => $c): $cat = $CATEGORIAS[$c['categoria']] ?? $CATEGORIAS['general']; $visto = isset($comunicadosVistos[(int) $c['id']]); ?>
            <div class="comunicado tarjeta-comunicado" id="comunicado-<?= $c['id'] ?>" data-categoria="<?= htmlspecialchars($c['categoria']) ?>" data-ts="<?= (int) strtotime($c['creado_en']) ?>" onclick="abrirModal(<?= $i ?>)" <?= $c['categoria'] === 'urgente' ? 'style="border:2px solid var(--danger, #B23A3A);"' : '' ?>>
                <span class="pill" style="background: <?= $cat['fondo'] ?>; color: <?= $cat['color'] ?>;"><?= $cat['texto'] ?></span>
                <span class="etiqueta-visto" style="display:<?= $visto ? 'inline-block' : 'none' ?>; background:rgba(45,115,221,.15); color:#2d73dd; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px; margin-left:6px;">✅ Visto</span>
                <h2><?= htmlspecialchars($c['titulo']) ?></h2>
                <p class="fecha"><?= date('d/m/Y', strtotime($c['creado_en'])) ?></p>
                <p><?= htmlspecialchars(mb_strimwidth($c['texto'], 0, 110, '…')) ?></p>
            </div>
        <?php endforeach; ?>
        </div>

        <div class="modal" id="modal">
            <div class="modal-contenido">
                <span class="cerrar" onclick="cerrarModal()">&times;</span>
                <h2 id="modal-titulo"></h2>
                <p class="fecha" id="modal-fecha"></p>
                <p id="modal-texto"></p>
                <div id="modal-extra"></div>
            </div>
        </div>
    </div>

    <script src="js/nav.js?v=3"></script>
    <script src="js/nav-loader.js?v=30"></script>
    <script>
        const comunicados = <?= json_encode(array_map(function ($c) {
            return [
                'id' => (int) $c['id'],
                'titulo' => $c['titulo'],
                'fecha' => date('d/m/Y', strtotime($c['creado_en'])),
                'texto' => nl2br(htmlspecialchars($c['texto'])),
                'imagen' => $c['imagen'] ? 'uploads/comunicados/' . $c['imagen'] : null,
                'pdf' => $c['pdf'] ? 'uploads/comunicados/' . $c['pdf'] : null,
            ];
        }, $comunicados), JSON_UNESCAPED_UNICODE) ?>;

        function abrirModal(indice) {
            const modal = document.getElementById('modal');
            const c = comunicados[indice];
            document.getElementById('modal-titulo').textContent = c.titulo;
            document.getElementById('modal-fecha').textContent = c.fecha;
            document.getElementById('modal-texto').innerHTML = c.texto;

            const contenedor = document.getElementById('modal-extra');
            contenedor.innerHTML = '';
            if (c.imagen) {
                const img = document.createElement('img');
                img.src = c.imagen;
                img.style.maxWidth = '100%';
                img.style.marginTop = '10px';
                img.style.borderRadius = '10px';
                contenedor.appendChild(img);
            }
            if (c.pdf) {
                const link = document.createElement('a');
                link.href = c.pdf;
                link.textContent = '📎 Descargar documento PDF';
                link.target = '_blank';
                link.style.display = 'block';
                link.style.marginTop = '10px';
                link.style.fontWeight = 'bold';
                contenedor.appendChild(link);
            }
            modal.style.display = 'flex';
            marcarVisto('comunicado', c.id);
        }

        function cerrarModal() {
            document.getElementById('modal').style.display = 'none';
            // Se quita el #comunicado-N para que la misma notificación vuelva a abrirlo.
            if (location.hash.indexOf('#comunicado-') === 0) history.replaceState(null, '', location.pathname + location.search);
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

        // Si se llegó aquí desde el aviso de "Comunicado urgente" en
        // Bienvenida (enlace con #comunicado-<id>), abre directo su detalle.
        // También funciona si ya estabas en esta página y das clic en la notificación.
        function abrirComunicadoDesdeEnlace() {
            const coincidenciaComunicado = location.hash.match(/^#comunicado-(\d+)$/);
            if (!coincidenciaComunicado) return;
            const idBuscado = Number(coincidenciaComunicado[1]);
            const indice = comunicados.findIndex(c => c.id === idBuscado);
            if (indice !== -1) {
                abrirModal(indice);
                const tarjeta = document.getElementById('comunicado-' + idBuscado);
                if (tarjeta) tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        abrirComunicadoDesdeEnlace();
        window.addEventListener('hashchange', abrirComunicadoDesdeEnlace);

        (function () {
            const tarjetas = Array.from(document.querySelectorAll('.tarjeta-comunicado'));
            if (!tarjetas.length) return;
            const filtro = document.getElementById('filtro-comunicados');
            const orden = document.getElementById('orden-comunicados');
            const lista = document.getElementById('lista-comunicados');
            const contador = document.getElementById('filtro-comunicados-contador');
            const vacio = document.getElementById('filtro-comunicados-vacio');

            // Línea que separa los urgentes del resto (solo en "Importancia").
            const separador = document.createElement('div');
            separador.id = 'separador-urgentes';
            separador.style.cssText = 'display:none; align-items:center; gap:12px; margin:18px 0 14px; color:var(--text-muted); font-size:13px; font-weight:700;';
            separador.innerHTML = '<span style="flex:1; border-top:2px solid var(--border);"></span><span>Otros comunicados</span><span style="flex:1; border-top:2px solid var(--border);"></span>';

            // Se recuerda en este navegador (solo comodidad; si no se puede, no pasa nada).
            try {
                const guardado = localStorage.getItem('ordenComunicados');
                if (guardado && orden.querySelector('option[value="' + guardado + '"]')) orden.value = guardado;
            } catch (e) {}

            function ordenar() {
                const porFecha = (a, b) => Number(b.dataset.ts) - Number(a.dataset.ts);
                const lista_ = tarjetas.slice();
                if (orden.value === 'viejo') {
                    lista_.sort((a, b) => -porFecha(a, b));
                } else if (orden.value === 'nuevo') {
                    lista_.sort(porFecha);
                } else {
                    const esUrg = t => t.dataset.categoria === 'urgente' ? 0 : 1;
                    lista_.sort((a, b) => esUrg(a) - esUrg(b) || porFecha(a, b));
                }
                lista_.forEach(t => lista.appendChild(t));
                if (orden.value === 'importancia') {
                    const primerNoUrgente = lista_.find(t => t.dataset.categoria !== 'urgente');
                    if (primerNoUrgente) lista.insertBefore(separador, primerNoUrgente); else lista.appendChild(separador);
                } else if (separador.parentNode) {
                    separador.parentNode.removeChild(separador);
                }
            }

            function filtrar() {
                let visibles = 0, urgVisibles = 0, otrosVisibles = 0;
                tarjetas.forEach(t => {
                    const mostrar = !filtro.value || t.dataset.categoria === filtro.value;
                    t.style.display = mostrar ? '' : 'none';
                    if (mostrar) {
                        visibles++;
                        if (t.dataset.categoria === 'urgente') urgVisibles++; else otrosVisibles++;
                    }
                });
                // La línea solo tiene sentido si hay urgentes y otros a la vista a la vez.
                separador.style.display = (orden.value === 'importancia' && urgVisibles > 0 && otrosVisibles > 0) ? 'flex' : 'none';
                contador.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' comunicados';
                vacio.style.display = visibles === 0 ? '' : 'none';
            }

            filtro.addEventListener('change', filtrar);
            orden.addEventListener('change', () => {
                try { localStorage.setItem('ordenComunicados', orden.value); } catch (e) {}
                ordenar();
                filtrar();
            });
            document.getElementById('filtro-comunicados-limpiar').addEventListener('click', () => {
                filtro.value = '';
                orden.value = 'importancia';
                try { localStorage.removeItem('ordenComunicados'); } catch (e) {}
                ordenar();
                filtrar();
            });
            ordenar();
            filtrar();
        })();
    </script>
</body>

</html>
