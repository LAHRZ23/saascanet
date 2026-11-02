<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

$tutoriales = [];
$resultado = mysqli_query($conexion, "SELECT * FROM tutoriales WHERE vigente = 1 ORDER BY creado_en DESC");
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $tutoriales[] = $fila;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Tutoriales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🎓 Tutoriales</h1>
    <?php if (esAdmin()): ?>
        <p><a href="admin-tutoriales.php">➕ Agregar tutorial</a></p>
    <?php else: ?>
        <p style="font-size:13px; color:var(--text-muted); margin:0 0 18px;">📌 ¿Quieres agregar un tutorial? <a href="solicitar-publicacion.php?seccion=tutorial">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI).</p>
    <?php endif; ?>

    <?php if (empty($tutoriales)): ?>
        <p style="text-align:center; margin-top:40px; color:var(--text-muted, #666);">En este momento no hay tutoriales disponibles.</p>
    <?php else: ?>
        <div style="display:flex; flex-wrap:wrap; gap:24px; margin-top:20px;">
            <?php foreach ($tutoriales as $t): ?>
                <div class="caja-accion" id="tutorial-<?= (int) $t['id'] ?>" style="width:380px; max-width:100%;">
                    <?php if (strtolower(pathinfo($t['video'], PATHINFO_EXTENSION)) === 'gif'): ?>
                        <img src="uploads/tutoriales/<?= htmlspecialchars($t['video']) ?>" alt="<?= htmlspecialchars($t['titulo']) ?>" style="width:100%; border-radius:10px; margin-bottom:10px; cursor:zoom-in;" onclick="abrirTutorial(<?= (int) $t['id'] ?>)">
                    <?php else: ?>
                        <video controls style="width:100%; border-radius:10px; margin-bottom:10px;" src="uploads/tutoriales/<?= htmlspecialchars($t['video']) ?>"></video>
                    <?php endif; ?>
                    <button type="button" class="btn-secundario" style="border:none; cursor:pointer; margin-bottom:10px;" onclick="abrirTutorial(<?= (int) $t['id'] ?>)">🔍 Ver más grande</button>
                    <h3><?= htmlspecialchars($t['titulo']) ?></h3>
                    <?php if (!empty($t['descripcion'])): ?>
                        <p><?= nl2br(htmlspecialchars($t['descripcion'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Pop-up grande de cada tutorial -->
<div class="popup-overlay" id="modal-tutorial" onclick="if(event.target===this) cerrarTutorial()">
    <div class="popup-box" style="text-align:left; max-width:980px;" onclick="event.stopPropagation()">
        <button class="popup-close" onclick="cerrarTutorial()">✖</button>
        <h2 id="tutorial-titulo" style="margin-top:0;"></h2>
        <div id="tutorial-medio" style="text-align:center;"></div>
        <p id="tutorial-descripcion" style="white-space:pre-line; margin-top:12px;"></p>
    </div>
</div>

<script>
    const tutoriales = <?= json_encode(array_map(function ($t) {
        return [
            'id' => (int) $t['id'],
            'titulo' => $t['titulo'],
            'descripcion' => $t['descripcion'] ?: null,
            'archivo' => 'uploads/tutoriales/' . $t['video'],
            'esGif' => strtolower(pathinfo($t['video'], PATHINFO_EXTENSION)) === 'gif',
        ];
    }, $tutoriales), JSON_UNESCAPED_UNICODE) ?>;

    function abrirTutorial(id) {
        const t = tutoriales.find(x => x.id === Number(id));
        if (!t) return;
        // Se pausan los videos de las tarjetas para que no suenen dos a la vez.
        document.querySelectorAll('.caja-accion video').forEach(v => v.pause());

        document.getElementById('tutorial-titulo').textContent = t.titulo;
        const descripcion = document.getElementById('tutorial-descripcion');
        descripcion.textContent = t.descripcion || '';
        descripcion.style.display = t.descripcion ? '' : 'none';

        const medio = document.getElementById('tutorial-medio');
        medio.innerHTML = '';
        if (t.esGif) {
            const img = document.createElement('img');
            img.src = t.archivo;
            img.alt = t.titulo;
            img.style.cssText = 'max-width:100%; max-height:70vh; border-radius:10px;';
            medio.appendChild(img);
        } else {
            const video = document.createElement('video');
            video.controls = true;
            video.src = t.archivo;
            video.style.cssText = 'width:100%; max-height:70vh; border-radius:10px; background:#000;';
            medio.appendChild(video);
        }
        document.getElementById('modal-tutorial').style.display = 'flex';
    }

    function cerrarTutorial() {
        document.getElementById('modal-tutorial').style.display = 'none';
        document.getElementById('tutorial-medio').innerHTML = ''; // detiene el video
        if (location.hash.indexOf('#tutorial-') === 0) history.replaceState(null, '', location.pathname + location.search);
    }

    // Si se llegó desde una notificación (enlace con #tutorial-<id>), abre ese tutorial.
    function abrirTutorialDesdeEnlace() {
        const m = location.hash.match(/^#tutorial-(\d+)$/);
        if (!m) return;
        abrirTutorial(m[1]);
        const tarjeta = document.getElementById('tutorial-' + m[1]);
        if (tarjeta) tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    abrirTutorialDesdeEnlace();
    window.addEventListener('hashchange', abrirTutorialDesdeEnlace);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarTutorial(); });
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
