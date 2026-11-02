<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();

$totalComunicados = 0;
$resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM comunicados WHERE vigente = 1");
if ($resultado) {
    $totalComunicados = (int) mysqli_fetch_assoc($resultado)['total'];
}

$ticketsNuevos = 0;
if (esAdmin()) {
    $resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus = 'nuevo' AND archivado = 0");
    if ($resultado) {
        $ticketsNuevos = (int) mysqli_fetch_assoc($resultado)['total'];
    }
}

$avisoPopup = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM popup_aviso WHERE id = 1"));
$mostrarPopup = $avisoPopup && $avisoPopup['activo'];

// El aviso se muestra una vez por inicio de sesión. Se recuerda en la sesión
// de PHP (no en el navegador), así que al cerrar sesión y volver a entrar
// aparece de nuevo. Si el administrador actualiza el aviso, cambia la clave
// y también vuelve a salir.
if ($mostrarPopup) {
    $clavePopup = 'popup_visto_' . $avisoPopup['actualizado_en'];
    if (!empty($_SESSION[$clavePopup])) {
        $mostrarPopup = false;
    } else {
        $_SESSION[$clavePopup] = true;
    }
}

$totalCursos = 0;
$resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM cursos WHERE vigente = 1");
if ($resultado) {
    $totalCursos = (int) mysqli_fetch_assoc($resultado)['total'];
}

$paginaExtra = null;
$resultado = mysqli_query($conexion, "SELECT activa, titulo FROM pagina_extra WHERE id = 1");
if ($resultado) {
    $fila = mysqli_fetch_assoc($resultado);
    if ($fila && $fila['activa']) {
        $paginaExtra = $fila;
    }
}

$totalEventos = 0;
$resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM eventos_programados WHERE vigente = 1");
if ($resultado) {
    $totalEventos = (int) mysqli_fetch_assoc($resultado)['total'];
}

$encuestasPendientes = encuestasPendientes($conexion, usuarioActual()['id']);
$cursosPorVencer = cursosPorVencer($conexion);
$eventosDeHoy = eventosDeHoy($conexion);
$comunicadosUrgentes = comunicadosUrgentes($conexion, usuarioActual()['id']);

// Los avisos de eventos de hoy / cursos por vencer / comunicados urgentes se
// ocultan en cuanto la persona le da clic a su botón (se recuerda en la
// sesión). Las encuestas (beta y calidad) no pasan por aquí: se quedan hasta
// que se contesten.
$avisosInicio = array_filter(
    array_merge($comunicadosUrgentes, $eventosDeHoy, $encuestasPendientes, $cursosPorVencer),
    function ($aviso) {
        return empty($aviso['tipo']) || empty($_SESSION['aviso_visto_' . $aviso['tipo']]);
    }
);

$cumpleanerosHoy = [];
$stmt = mysqli_prepare($conexion, "SELECT nombre, area, imagen FROM cumpleanos WHERE activo = 1 AND dia = ? AND mes = ?");
if ($stmt) {
    $diaHoy = (int) date('j');
    $mesHoy = (int) date('n');
    mysqli_stmt_bind_param($stmt, "ii", $diaHoy, $mesHoy);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $cumpleanerosHoy[] = $fila;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Bienvenida</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>
    <div id="nav-placeholder"></div>

    <div class="main-content">
<center><h1>Bienvenidas y Bienvenidos a SAASCANET</h1></center>

<div id="avisos-inicio">
<?php foreach ($avisosInicio as $enc): ?>
<div class="aviso-encuesta <?= htmlspecialchars($enc['clase'] ?? '') ?>">
    <div class="aviso-encuesta-icono"><?= $enc['icono'] ?></div>
    <div class="aviso-encuesta-texto">
        <strong><?= htmlspecialchars($enc['titulo']) ?></strong>
        <p><?= htmlspecialchars($enc['texto']) ?></p>
    </div>
    <a href="<?= htmlspecialchars($enc['url']) ?>" class="aviso-encuesta-boton"<?= !empty($enc['tipo']) ? ' onclick="marcarAvisoVisto(\'' . htmlspecialchars($enc['tipo']) . '\')"' : '' ?>><?= htmlspecialchars($enc['boton']) ?> →</a>
</div>
<?php endforeach; ?>
</div>

<?php if (!empty($cumpleanerosHoy)): ?>
<style>
    .aviso-cumpleanos.clicable { cursor: pointer; transition: transform .15s ease; }
    .aviso-cumpleanos.clicable:hover { transform: scale(1.02); }
    .aviso-cumpleanos .ver-grande { font-size: 12px; opacity: .85; margin-top: 6px; }
    .aviso-cumpleanos .btn-sonido { background: rgba(255,255,255,.2); color: white; border: 1px solid rgba(255,255,255,.5); border-radius: 999px; padding: 3px 10px; font-size: 12px; cursor: pointer; margin-top: 8px; }
    #popup-cumple .popup-box { overflow: hidden; }
    #popup-cumple .cumple-grande { margin: 14px 0 22px; }
    #popup-cumple .cumple-grande img { width: 100%; max-width: 460px; max-height: 60vh; object-fit: contain; border-radius: 14px; border: 4px solid var(--dorado); box-shadow: var(--shadow-hover); }
    #popup-cumple .cumple-grande .sin-foto { font-size: 90px; line-height: 1; }
    #popup-cumple .cumple-grande h3 { margin: 12px 0 2px; font-size: 22px; }
    #popup-cumple .cumple-grande p { margin: 0; color: var(--text-muted); }
    #popup-cumple .confeti-cae { position: absolute; top: -30px; font-size: 22px; pointer-events: none; animation: confeti-cae linear forwards; }
    @keyframes confeti-cae { to { transform: translateY(110vh) rotate(540deg); opacity: .2; } }
</style>
<div class="aviso-cumpleanos clicable" id="aviso-cumpleanos" role="button" tabindex="0" title="Clic para ver en grande">
    <div class="confeti">🎉🎈🎂🎈🎉</div>
    <h3>¡Hoy es cumpleaños!</h3>
    <?php foreach ($cumpleanerosHoy as $c): ?>
        <div class="cumpleanero">
            <?php if (!empty($c['imagen'])): ?>
                <img src="uploads/cumpleanos/<?= htmlspecialchars($c['imagen']) ?>" alt="" class="cumpleanero-foto">
            <?php endif; ?>
            <p><strong><?= htmlspecialchars($c['nombre']) ?></strong><?= !empty($c['area']) ? ' — ' . htmlspecialchars($c['area']) : '' ?></p>
        </div>
    <?php endforeach; ?>
    <div class="confeti">🎈🎂🎉🎂🎈</div>
    <div class="ver-grande">🔍 Clic para ver en grande</div>
    <button type="button" class="btn-sonido" id="btn-sonido-cumple"></button>
</div>

<!-- Tarjeta de cumpleaños en grande -->
<div class="popup-overlay" id="popup-cumple">
    <div class="popup-box">
        <button class="popup-close" id="cerrar-popup-cumple" aria-label="Cerrar">✖</button>
        <div class="confeti" style="font-size:26px;">🎉🎈🎂🎈🎉</div>
        <h2>¡Feliz cumpleaños!</h2>
        <?php foreach ($cumpleanerosHoy as $c): ?>
            <div class="cumple-grande">
                <?php if (!empty($c['imagen'])): ?>
                    <img src="uploads/cumpleanos/<?= htmlspecialchars($c['imagen']) ?>" alt="Foto de <?= htmlspecialchars($c['nombre']) ?>">
                <?php else: ?>
                    <div class="sin-foto">🎂</div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($c['nombre']) ?></h3>
                <?php if (!empty($c['area'])): ?><p><?= htmlspecialchars($c['area']) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="confeti" style="font-size:26px;">🎈🎂🎉🎂🎈</div>
    </div>
</div>

<script>
    (function () {
        const tarjeta = document.getElementById('aviso-cumpleanos');
        const popup = document.getElementById('popup-cumple');
        const btnSonido = document.getElementById('btn-sonido-cumple');
        const hoy = '<?= date('Y-m-d') ?>';
        const leer = k => { try { return localStorage.getItem(k); } catch (e) { return null; } };
        const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };

        // --- Fanfarria de cumpleaños "¡ta-ra-rá!", generada con el navegador (sin archivo de audio) ---
        let ctx = null;
        let ultimaVez = 0;
        // Distorsión suave que le da cuerpo de trompeta.
        function rasposo(cantidad) {
            const ws = ctx.createWaveShaper();
            const n = 1024, curva = new Float32Array(n);
            for (let i = 0; i < n; i++) { const x = i * 2 / n - 1; curva[i] = Math.tanh(x * cantidad); }
            ws.curve = curva;
            return ws;
        }
        // Una nota: dos osciladores apenas desafinados, resonancias de metal y
        // un poco de soplido al inicio.
        function nota(t, tono, dur, vol) {
            const salida = ctx.createGain();
            salida.gain.setValueAtTime(0.0001, t);
            salida.gain.exponentialRampToValueAtTime(vol, t + 0.03);
            salida.gain.setValueAtTime(vol, t + dur - 0.06);
            salida.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            salida.connect(ctx.destination);

            [0, 7].forEach(cents => {
                const o = ctx.createOscillator();
                o.type = 'sawtooth';
                o.detune.value = cents;
                o.frequency.setValueAtTime(tono * 0.92, t);
                o.frequency.exponentialRampToValueAtTime(tono, t + 0.07);
                const f1 = ctx.createBiquadFilter(); f1.type = 'peaking'; f1.frequency.value = 1430; f1.Q.value = 2; f1.gain.value = 9;
                const f2 = ctx.createBiquadFilter(); f2.type = 'peaking'; f2.frequency.value = 3380; f2.Q.value = 3; f2.gain.value = 6;
                const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 5850;
                o.connect(rasposo(2.5)).connect(f1).connect(f2).connect(lp).connect(salida);
                o.start(t); o.stop(t + dur + 0.05);
            });

            const buf = ctx.createBuffer(1, Math.ceil(ctx.sampleRate * dur), ctx.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
            const aire = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), g = ctx.createGain();
            aire.buffer = buf;
            bp.type = 'bandpass'; bp.frequency.value = 2000; bp.Q.value = 0.8;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.015, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            aire.connect(bp).connect(g).connect(ctx.destination);
            aire.start(t); aire.stop(t + dur);
        }
        function sonarCornetas() {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return Promise.resolve(false);
            ctx = ctx || new AC();
            return ctx.resume().then(() => {
                if (ctx.state !== 'running') return false;
                // Evita que se encimen si se piden dos veces seguidas.
                if (Date.now() - ultimaVez < 2500) return true;
                ultimaVez = Date.now();
                const t = ctx.currentTime + 0.05;
                // Sol-do-mi y un sol largo con su acorde.
                nota(t, 392, 0.14, 0.15);
                nota(t + 0.16, 523, 0.14, 0.15);
                nota(t + 0.32, 659, 0.14, 0.15);
                nota(t + 0.5, 784, 0.9, 0.15);
                nota(t + 0.5, 523, 0.9, 0.09);
                return true;
            }).catch(() => false);
        }

        // Suena solo una vez al día por computadora, y se puede silenciar.
        const silenciado = () => leer('cumpleSilencio') === '1';
        function pintarBoton() { btnSonido.textContent = silenciado() ? '🔇 Sonido silenciado' : '🔊 Silenciar sonido'; }
        pintarBoton();
        btnSonido.addEventListener('click', function (e) {
            e.stopPropagation();
            guardar('cumpleSilencio', silenciado() ? '0' : '1');
            pintarBoton();
        });

        function sonarSiToca() {
            if (silenciado() || leer('cumpleSono') === hoy) return Promise.resolve(true);
            return sonarCornetas().then(ok => { if (ok) guardar('cumpleSono', hoy); return ok; });
        }
        // El navegador no deja sonar nada hasta que la persona interactúa con
        // la página: se intenta al abrir y, si se bloquea, con el primer clic.
        sonarSiToca().then(ok => {
            if (ok) return;
            const alPrimerClic = (e) => {
                if (e.target && e.target.closest && e.target.closest('#btn-sonido-cumple')) return;
                document.removeEventListener('pointerdown', alPrimerClic);
                document.removeEventListener('keydown', alPrimerClic);
                sonarSiToca();
            };
            document.addEventListener('pointerdown', alPrimerClic);
            document.addEventListener('keydown', alPrimerClic);
        });

        // --- Ventana en grande ---
        function lluviaConfeti() {
            const caja = popup.querySelector('.popup-box');
            const piezas = ['🎉', '🎈', '🎊', '🎂', '✨'];
            for (let i = 0; i < 24; i++) {
                const p = document.createElement('span');
                p.className = 'confeti-cae';
                p.textContent = piezas[i % piezas.length];
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDuration = (2 + Math.random() * 2) + 's';
                p.style.animationDelay = (Math.random() * 0.8) + 's';
                caja.appendChild(p);
                setTimeout(() => p.remove(), 5000);
            }
        }
        function abrir() {
            popup.style.display = 'flex';
            lluviaConfeti();
            if (!silenciado()) sonarCornetas();
        }
        function cerrar() { popup.style.display = 'none'; }
        tarjeta.addEventListener('click', abrir);
        tarjeta.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(); } });
        document.getElementById('cerrar-popup-cumple').addEventListener('click', cerrar);
        popup.addEventListener('click', e => { if (e.target === popup) cerrar(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && popup.style.display === 'flex') cerrar(); });
    })();
</script>
<?php endif; ?>

<div class="stats">
    <a href="comunicados.php" class="stat" id="stat-comunicados" style="text-decoration:none; color:inherit; display:block;">
        <div class="cabeza">
            <span class="etq">Comunicados vigentes<span class="etiqueta-nuevo" style="display:none;">nuevo</span></span>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4a1 1 0 0 0 1 1h2l3 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6"/></svg></span>
        </div>
        <div class="valor mono"><?= $totalComunicados ?></div>
        <div class="detalle">Consulta las últimas noticias</div>
    </a>
    <a href="cursos.php" class="stat" id="stat-cursos" style="text-decoration:none; color:inherit; display:block;">
        <div class="cabeza">
            <span class="etq">Cursos disponibles<span class="etiqueta-nuevo" style="display:none;">nuevo</span></span>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4 2 9l10 5 8-4.2V15"/><path d="M6 11.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.5"/></svg></span>
        </div>
        <div class="valor mono"><?= $totalCursos ?></div>
        <div class="detalle">Capacitación disponible</div>
    </a>
    <a href="eventos.php" class="stat" id="stat-eventos" style="text-decoration:none; color:inherit; display:block;">
        <div class="cabeza">
            <span class="etq">Eventos<span class="etiqueta-nuevo" style="display:none;">nuevo</span></span>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.1 7-11a7 7 0 0 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.3"/></svg></span>
        </div>
        <div class="valor mono"><?= $totalEventos ?></div>
        <div class="detalle">Próximos eventos programados</div>
    </a>
    <?php if (esAdmin()): ?>
    <a href="admin-tickets.php" class="stat <?= $ticketsNuevos > 0 ? 'stat-nuevo' : '' ?>" style="text-decoration:none; color:inherit; display:block;">
        <div class="cabeza">
            <span class="etq">🔔 Tickets nuevos<?= $ticketsNuevos > 0 ? '<span class="etiqueta-nuevo">nuevo</span>' : '' ?></span>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4h6l1 3h3v3l-3 1v9H6v-9L3 10V7h3z"/></svg></span>
        </div>
        <div class="valor mono"><?= $ticketsNuevos ?></div>
        <div class="detalle"><?= $ticketsNuevos > 0 ? 'Sin atender — dale clic para verlos' : 'Todo al día' ?></div>
    </a>
    <?php endif; ?>
</div>

<h2 class="seccion-titulo" style="color:var(--guinda); font-size:16px; margin:0 0 12px;">Accesos rápidos</h2>
<div class="panel-accesos">
    <a href="mesa-servicios.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4h6l1 3h3v3l-3 1v9H6v-9L3 10V7h3z"/></svg></span>
        <span class="etiqueta">Mesa de Servicios</span>
    </a>
    <a href="comunicados.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4a1 1 0 0 0 1 1h2l3 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6"/></svg></span>
        <span class="etiqueta">Comunicados</span>
    </a>
    <a href="calendario.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg></span>
        <span class="etiqueta">Calendario</span>
    </a>
    <a href="directorio.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="13" height="18" rx="2"/><circle cx="10.5" cy="9" r="2"/></svg></span>
        <span class="etiqueta">Directorio</span>
    </a>
    <a href="cursos.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4 2 9l10 5 8-4.2V15"/><path d="M6 11.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.5"/></svg></span>
        <span class="etiqueta">Cursos</span>
    </a>
    <a href="tutoriales.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z" fill="currentColor" stroke="none"/></svg></span>
        <span class="etiqueta">Tutoriales</span>
    </a>
    <a href="mision.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7-4.4-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 5c-2.5 4.6-9.5 9-9.5 9Z"/></svg></span>
        <span class="etiqueta">Misión</span>
    </a>
    <?php if ($paginaExtra): ?>
    <a href="pagina-extra.php" class="acceso-card">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
        <span class="etiqueta"><?= htmlspecialchars($paginaExtra['titulo']) ?></span>
    </a>
    <?php endif; ?>
    <?php if (esAdmin()): ?>
    <a href="admin-panel.php" class="acceso-card" style="border-top-color: var(--guinda);">
        <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c1.2-3.5 4-5 7-5s5.8 1.5 7 5"/></svg></span>
        <span class="etiqueta">Panel de Administración</span>
    </a>
    <?php endif; ?>
</div>


        <div class="seccion-acerca">
            <p style="text-align: justify; margin-top:0;"><strong>SAASCANET</strong> es la plataforma interna del SAASCAEM desarrollada por la Unidad de Apoyo Técnico e Informática (UATI) para fortalecer la comunicación y coordinación entre todas las áreas del organismo. Esta herramienta facilita el acceso ágil y ordenado a los servicios y recursos que apoyan nuestras tareas diarias, contribuyendo a una operación más eficiente y alineada con los objetivos institucionales.</p>

            <h4 style="margin-bottom:4px;">A través de SAASCANET, el personal puede:</h4>
            <div class="beneficios-lista">
                <div class="beneficio-item">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4h6l1 3h3v3l-3 1v9H6v-9L3 10V7h3z"/></svg></span>
                    <div>
                        <strong>Levantar tickets de asistencia técnica</strong>
                        <p>Para garantizar una atención oportuna por parte de la UATI.</p>
                    </div>
                </div>
                <div class="beneficio-item">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4a1 1 0 0 0 1 1h2l3 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6"/></svg></span>
                    <div>
                        <strong>Consultar comunicados oficiales</strong>
                        <p>Relacionados con nuestras autopistas, proyectos de infraestructura y actividades institucionales.</p>
                    </div>
                </div>
                <div class="beneficio-item">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg></span>
                    <div>
                        <strong>Consultar el calendario institucional</strong>
                        <p>Eventos, reuniones y días no laborables, siempre actualizado.</p>
                    </div>
                </div>
                <div class="beneficio-item">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="13" height="18" rx="2"/><circle cx="10.5" cy="9" r="2"/></svg></span>
                    <div>
                        <strong>Acceder al directorio institucional</strong>
                        <p>Facilita la coordinación y comunicación entre unidades.</p>
                    </div>
                </div>
                <div class="beneficio-item">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4 2 9l10 5 8-4.2V15"/><path d="M6 11.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.5"/></svg></span>
                    <div>
                        <strong>Tomar cursos en línea</strong>
                        <p>Capacitación disponible para fortalecer nuestras habilidades.</p>
                    </div>
                </div>
                <div class="beneficio-item" style="border-bottom:none;">
                    <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z" fill="currentColor" stroke="none"/></svg></span>
                    <div>
                        <strong>Revisar tutoriales y herramientas de trabajo</strong>
                        <p>Apoyan el uso eficiente de los sistemas y procesos operativos del organismo.</p>
                    </div>
                </div>
            </div>

            <p style="text-align: justify;">Esta plataforma forma parte del compromiso del SAASCAEM por fortalecer la gestión interna, promover la transparencia operativa y facilitar el desarrollo de nuestras labores diarias. Su uso contribuye directamente a las acciones que impulsan las autopistas estatales y los proyectos de infraestructura que, bajo la guía del Gobierno del Estado de México, buscan mejorar la conectividad y el desarrollo en nuestra entidad.</p>
            <p style="color: var(--dorado-dark); text-align: center; margin-bottom:0;"><strong>Sigamos trabajando juntos para consolidar procesos más eficientes, coordinados y alineados con la transformación del Estado de México.</strong></p>
        </div>
    </div>
    <script src="js/nav.js?v=3"></script>
    <script src="js/nav-loader.js?v=30"></script>
    <script>
        // Avisa al servidor que este aviso (evento de hoy / curso por vencer)
        // ya se atendió, para que no vuelva a aparecer esta sesión. keepalive
        // asegura que la petición se complete aunque el clic ya esté navegando
        // a otra página.
        function marcarAvisoVisto(tipo) {
            fetch('home_aviso_visto.php', {
                method: 'POST',
                body: new URLSearchParams({ tipo }),
                keepalive: true,
            }).catch(() => {});
        }

        function escaparHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto;
            return div.innerHTML;
        }

        // Vuelve a dibujar los avisos de arriba (comunicado urgente, evento de
        // hoy, encuestas, cursos por vencer) con lo que venga del servidor, sin
        // recargar la página — así aparecen solos los nuevos y desaparecen
        // solos los que ya no apliquen (p. ej. si el admin oculta el
        // comunicado urgente o cierra la encuesta).
        function pintarAvisosInicio(avisos) {
            const contenedor = document.getElementById('avisos-inicio');
            if (!contenedor) return;
            contenedor.innerHTML = avisos.map(a => `
                <div class="aviso-encuesta ${a.clase ? escaparHtml(a.clase) : ''}">
                    <div class="aviso-encuesta-icono">${a.icono}</div>
                    <div class="aviso-encuesta-texto">
                        <strong>${escaparHtml(a.titulo)}</strong>
                        <p>${escaparHtml(a.texto)}</p>
                    </div>
                    <a href="${escaparHtml(a.url)}" class="aviso-encuesta-boton"${a.tipo ? ` onclick="marcarAvisoVisto('${escaparHtml(a.tipo)}')"` : ''}>${escaparHtml(a.boton)} →</a>
                </div>
            `).join('');
        }

        function revisarAvisosInicio() {
            fetch('home_avisos.php')
                .then(r => r.json())
                .then(datos => pintarAvisosInicio(datos.avisos || []))
                .catch(() => {});
        }
        setInterval(revisarAvisosInicio, 45000);
    </script>

    <?php if ($mostrarPopup): ?>
    <!-- AVISO EMERGENTE: lo activa/edita el administrador desde admin-popup.php -->
    <div class="popup-overlay" id="popup">
      <div class="popup-box">
        <button class="popup-close" onclick="document.getElementById('popup').style.display='none'">✖</button>
        <h2><?= htmlspecialchars($avisoPopup['titulo']) ?></h2>
        <?php if (!empty($avisoPopup['imagen'])): ?>
            <img src="uploads/comunicados/<?= htmlspecialchars($avisoPopup['imagen']) ?>" alt="Aviso" class="popup-img">
        <?php endif; ?>
        <?php if (!empty($avisoPopup['enlace'])): ?>
            <a href="<?= htmlspecialchars($avisoPopup['enlace']) ?>" class="btn-accion"><?= htmlspecialchars($avisoPopup['texto_boton'] ?: '➭ MÁS INFORMACIÓN') ?></a>
        <?php endif; ?>
      </div>
    </div>
    <script>
        window.addEventListener('load', function () {
            // PHP ya decidió si toca mostrarlo (una vez por inicio de sesión).
            const popup = document.getElementById('popup');
            popup.style.display = 'flex';
            popup.addEventListener('click', function (e) {
                if (e.target === popup) popup.style.display = 'none';
            });
        });
    </script>
    <?php endif; ?>

</body>
</html>