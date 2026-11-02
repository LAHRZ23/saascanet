<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();

$cursos = [];
$resultado = mysqli_query($conexion, "SELECT * FROM cursos WHERE vigente = 1 ORDER BY creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $cursos[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Cursos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

    <div id="nav-placeholder"></div>

<div class="main-content">
  <h1>📚 Cursos de Capacitación</h1>
  <h2>🟢 Accede a los cursos disponibles en línea para fortalecer tus conocimientos.</h2>

  <?php if (esAdmin()): ?>
      <p><a href="admin-cursos.php" class="btn-accion" style="display:inline-block; margin:10px 0 20px;">➕ Agregar curso</a></p>
  <?php else: ?>
      <p style="font-size:13px; color:var(--text-muted); margin:10px 0 18px;">📌 ¿Quieres agregar un curso? <a href="solicitar-publicacion.php?seccion=curso">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI).</p>
  <?php endif; ?>

  <div class="cursos-grid">

    <?php if (empty($cursos)): ?>
        <div class="curso-card bloqueado">
          <h3>🔒 Próximamente</h3>
          <p>Nuevos cursos se publicarán próximamente.</p>
          <button disabled class="btn-curso">No disponible</button>
        </div>
    <?php endif; ?>

    <?php foreach ($cursos as $c):
        $etiquetaVigencia = null;
        if (!empty($c['fecha_vigencia'])) {
            $diasRestantes = (int) floor((strtotime($c['fecha_vigencia']) - strtotime(date('Y-m-d'))) / 86400);
            if ($diasRestantes < 0) {
                $etiquetaVigencia = ['texto' => '⚠️ Vencido', 'color' => '#B23A3A'];
            } elseif ($diasRestantes === 0) {
                $etiquetaVigencia = ['texto' => '🔴 Vence hoy', 'color' => '#B23A3A'];
            } elseif ($diasRestantes <= 7) {
                $etiquetaVigencia = ['texto' => '🟠 Vence pronto', 'color' => '#C77700'];
            }
        }
    ?>
        <div class="curso-card" id="curso-<?= (int) $c['id'] ?>" style="cursor:pointer;" onclick="abrirCurso(<?= (int) $c['id'] ?>)">
          <?php if ($etiquetaVigencia): ?>
              <span style="display:inline-block; background:<?= $etiquetaVigencia['color'] ?>; color:#fff; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px; margin-bottom:8px;"><?= $etiquetaVigencia['texto'] ?></span>
          <?php endif; ?>
          <h3><?= htmlspecialchars($c['titulo']) ?></h3>
          <?php if (!empty($c['institucion'])): ?>
              <p><strong style="color: var(--guinda)">Institución:</strong> <?= htmlspecialchars($c['institucion']) ?></p>
          <?php endif; ?>
          <?php if (!empty($c['modalidad'])): ?>
              <p><strong style="color: rgb(204, 123, 30)">Modalidad:</strong> <?= htmlspecialchars($c['modalidad']) ?></p>
          <?php endif; ?>
          <?php if (!empty($c['estado_texto'])): ?>
              <p><strong style="color: #C2185B">Estado:</strong> <?= htmlspecialchars($c['estado_texto']) ?></p>
          <?php endif; ?>
          <?php if (!empty($c['enlace'])): ?>
              <a href="<?= htmlspecialchars($c['enlace']) ?>" target="_blank" class="btn-curso" onclick="event.stopPropagation()">Ir al curso</a>
          <?php endif; ?>
          <p style="font-size:12px; color:var(--text-muted); text-align:center; margin:10px 0 0;">Ver detalle →</p>
        </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Pop-up con el detalle de cada curso -->
<div class="popup-overlay" id="modal-curso" onclick="if(event.target===this) cerrarCurso()">
  <div class="popup-box" style="text-align:left; max-width:560px;" onclick="event.stopPropagation()">
    <button class="popup-close" onclick="cerrarCurso()">✖</button>
    <span id="curso-etiqueta" style="display:none; color:#fff; font-size:12px; font-weight:800; padding:2px 10px; border-radius:999px;"></span>
    <h2 id="curso-titulo" style="margin:8px 0 10px;"></h2>
    <p id="curso-institucion"></p>
    <p id="curso-modalidad"></p>
    <p id="curso-estado"></p>
    <p id="curso-vigencia"></p>
    <a id="curso-enlace" href="#" target="_blank" rel="noopener" class="btn-curso" style="display:none; margin-top:10px;">Ir al curso</a>
  </div>
</div>

<script>
  const cursos = <?= json_encode(array_map(function ($c) {
      $etiqueta = null;
      if (!empty($c['fecha_vigencia'])) {
          $dias = (int) floor((strtotime($c['fecha_vigencia']) - strtotime(date('Y-m-d'))) / 86400);
          if ($dias < 0) { $etiqueta = ['⚠️ Vencido', '#B23A3A']; }
          elseif ($dias === 0) { $etiqueta = ['🔴 Vence hoy', '#B23A3A']; }
          elseif ($dias <= 7) { $etiqueta = ['🟠 Vence pronto', '#C77700']; }
      }
      return [
          'id' => (int) $c['id'],
          'titulo' => $c['titulo'],
          'institucion' => $c['institucion'] ?: null,
          'modalidad' => $c['modalidad'] ?: null,
          'estado' => $c['estado_texto'] ?: null,
          'vigencia' => !empty($c['fecha_vigencia']) ? date('d/m/Y', strtotime($c['fecha_vigencia'])) : null,
          'enlace' => $c['enlace'] ?: null,
          'etiqueta' => $etiqueta,
      ];
  }, $cursos), JSON_UNESCAPED_UNICODE) ?>;

  function abrirCurso(id) {
    const c = cursos.find(x => x.id === Number(id));
    if (!c) return;
    document.getElementById('curso-titulo').textContent = c.titulo;
    const etiqueta = document.getElementById('curso-etiqueta');
    if (c.etiqueta) {
      etiqueta.textContent = c.etiqueta[0];
      etiqueta.style.background = c.etiqueta[1];
      etiqueta.style.display = 'inline-block';
    } else {
      etiqueta.style.display = 'none';
    }
    const poner = (idElemento, rotulo, valor) => {
      const el = document.getElementById(idElemento);
      el.textContent = '';
      if (!valor) { el.style.display = 'none'; return; }
      const fuerte = document.createElement('strong');
      fuerte.textContent = rotulo + ' ';
      el.appendChild(fuerte);
      el.appendChild(document.createTextNode(valor));
      el.style.display = '';
    };
    poner('curso-institucion', 'Institución:', c.institucion);
    poner('curso-modalidad', 'Modalidad:', c.modalidad);
    poner('curso-estado', 'Estado:', c.estado);
    poner('curso-vigencia', 'Vigente hasta:', c.vigencia);
    const enlace = document.getElementById('curso-enlace');
    if (c.enlace) { enlace.href = c.enlace; enlace.style.display = 'inline-block'; } else { enlace.style.display = 'none'; }
    document.getElementById('modal-curso').style.display = 'flex';
  }

  function cerrarCurso() {
    document.getElementById('modal-curso').style.display = 'none';
    if (location.hash.indexOf('#curso-') === 0) history.replaceState(null, '', location.pathname + location.search);
  }

  // Si se llegó desde una notificación (enlace con #curso-<id>), abre ese curso.
  function abrirCursoDesdeEnlace() {
    const m = location.hash.match(/^#curso-(\d+)$/);
    if (!m) return;
    abrirCurso(m[1]);
    const tarjeta = document.getElementById('curso-' + m[1]);
    if (tarjeta) tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  abrirCursoDesdeEnlace();
  window.addEventListener('hashchange', abrirCursoDesdeEnlace);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarCurso(); });
</script>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
