<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
$yo = usuarioActual();

if (esAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_aviso_reunion') {
    $mensaje = trim($_POST['mensaje'] ?? '') ?: 'Por el momento, la Unidad de Apoyo Técnico e Informática (UATI) se encuentra en reunión. Esto puede afectar el tiempo de resolución de los tickets.';
    $activo = !empty($_POST['activo']) ? 1 : 0;
    $stmt = mysqli_prepare($conexion, "UPDATE aviso_reunion SET activo = ?, mensaje = ?, actualizado_por = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "isi", $activo, $mensaje, $yo['id']);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, $activo ? 'Activó el aviso de reunión en Mesa de Servicios' : 'Desactivó el aviso de reunión en Mesa de Servicios');
    header('Location: mesa-servicios.php');
    exit;
}

$ticketsNuevos = 0;
if (esAdmin()) {
    $resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus = 'nuevo' AND archivado = 0");
    if ($resultado) {
        $ticketsNuevos = (int) mysqli_fetch_assoc($resultado)['total'];
    }
}

$areas = obtenerAreas($conexion);

$avisoReunion = null;
$resultado = mysqli_query($conexion, "SELECT * FROM aviso_reunion WHERE id = 1");
if ($resultado) {
    $avisoReunion = mysqli_fetch_assoc($resultado);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>SAASCAEM - Mesa de Servicios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=31">
  <link rel="stylesheet" href="tema.php">
</head>

<body>

<div id="nav-placeholder"></div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">

  <h1>🛠 Mesa de Servicios</h1>

  <?php if ($avisoReunion && $avisoReunion['activo']): ?>
      <div class="aviso-reunion">
          <span class="aviso-reunion-icono">⚠️</span>
          <div class="aviso-reunion-texto"><?= nl2br(htmlspecialchars($avisoReunion['mensaje'])) ?></div>
      </div>
  <?php endif; ?>

  <?php if (esAdmin()): ?>
      <div style="max-width:560px; background:var(--card-bg); padding:18px 20px; border-radius:14px; box-shadow:var(--shadow); margin:16px 0;">
          <form method="post">
              <input type="hidden" name="accion" value="guardar_aviso_reunion">
              <div class="campo">
                  <label for="mensaje-reunion">Mensaje del aviso (ej. "estamos en reunión")</label>
                  <textarea id="mensaje-reunion" name="mensaje" rows="2" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body); font-size:14px; background:var(--bg); color:var(--text);"><?= htmlspecialchars($avisoReunion['mensaje'] ?? '') ?></textarea>
              </div>
              <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                  <?php if ($avisoReunion && $avisoReunion['activo']): ?>
                      <button type="submit" name="activo" value="" class="btn-secundario" style="border:none; cursor:pointer;">Desactivar aviso</button>
                      <span style="font-size:12px; color:var(--text-muted);">🟢 Activo — el personal lo está viendo</span>
                  <?php else: ?>
                      <button type="submit" name="activo" value="1" class="btn-primario" style="width:auto; padding:10px 18px;">Activar aviso</button>
                      <span style="font-size:12px; color:var(--text-muted);">🔴 Desactivado</span>
                  <?php endif; ?>
              </div>
          </form>
      </div>
  <?php endif; ?>

  <h2>
    🟢En esta sección podrás generar una <strong style="color: var(--guinda);">solicitud de servicio para tu equipo de cómputo</strong>
    mediante la creación de un <strong style="color: var(--guinda);">"Ticket de Soporte"</strong>.
  </h2>
<br>
  <!-- TARJETAS DE ACCIÓN -->
  <div class="contenedor-acciones">

    <div class="caja-accion">
      <h3>🎟 Generar Ticket</h3>
      <p>Registra fallas técnicas, solicitudes de soporte o requerimientos de equipo.</p>
      <a href="#form-ticket" class="btn-accion">Crear Ticket</a>
    </div>

    <div class="caja-accion">
      <h3>📋 Mis tickets</h3>
      <p>Consulta el estatus de los tickets que has levantado.</p>
      <a href="mis-tickets.php" class="btn-secundario">Ver mis tickets</a>
    </div>

    <?php if (esAdmin()): ?>
    <div class="caja-accion" <?= $ticketsNuevos > 0 ? 'style="border-top-color: var(--dorado);"' : '' ?>>
      <h3>🗂 Panel de tickets<?= $ticketsNuevos > 0 ? ' 🔔' : '' ?></h3>
      <p><?= $ticketsNuevos > 0 ? "Tienes <strong>$ticketsNuevos</strong> ticket" . ($ticketsNuevos === 1 ? '' : 's') . " nuevo" . ($ticketsNuevos === 1 ? '' : 's') . " sin atender." : 'Consulta y da seguimiento a los tickets del personal.' ?></p>
      <a href="admin-tickets.php" class="btn-accion">Ver tickets</a>
    </div>

    <div class="caja-accion">
      <h3>📊 Reportes</h3>
      <p>Desglose de tickets por tipo, quién ha resuelto más, promedio recibido y rechazados con nota.</p>
      <a href="admin-reportes.php" class="btn-accion">Ver reportes</a>
    </div>
    <?php endif; ?>

  </div>
<br>
  <!-- AVISO IMPORTANTE -->
  <div class="aviso-importante">
    <strong>📌 Importante:</strong>
Solo se atenderan las solicitudes realizadas por mesa de servicios, ayudanos registrando tu ticket correctamente!
  </div>

  <h2 id="form-ticket" style="margin-top:40px;">🎟 Nuevo ticket de soporte</h2>
  <div id="resultado-ticket"></div>
  <form id="ticket-form" enctype="multipart/form-data" style="max-width:560px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin-top:16px;">
    <div class="campo">
      <label for="area">Área / Unidad</label>
      <select id="area" name="area" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);">
        <option value="">Selecciona tu área…</option>
        <?php foreach ($areas as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
        <option value="Otra">Otra (no aparece en la lista)</option>
      </select>
    </div>
    <div class="campo">
      <label for="equipo">Equipo (por ejemplo: PC de escritorio, número de inventario)</label>
      <input id="equipo" name="equipo" type="text" required>
    </div>
    <div class="campo">
      <label for="tipo">Tipo de solicitud</label>
      <select id="tipo" name="tipo" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);">
        <option value="Falla técnica">Falla técnica</option>
        <option value="Solicitud de soporte">Solicitud de soporte</option>
        <option value="Requerimiento de periféricos o proyector">Requerimiento de periféricos o proyector</option>
        <option value="Sistemas de gestión y correos">Sistemas de gestión y correos</option>
        <option value="Impresiones a color">Impresiones a color</option>
        <option value="Otro">Otro</option>
      </select>
    </div>
    <div class="campo">
      <label for="comentarios">Describe el problema o solicitud</label>
      <textarea id="comentarios" name="comentarios" rows="4" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:var(--font-body); font-size:15px; background:var(--bg); color:var(--text);"></textarea>
    </div>
    <div class="campo">
      <label for="imagen">Adjuntar imagen (opcional — por ejemplo una foto de la falla)</label>
      <input id="imagen" name="imagen" type="file" accept="image/*">
    </div>
    <button type="submit" class="btn-primario">Enviar ticket</button>
  </form>

</div>

<!-- SCRIPTS -->
<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
<script>
document.getElementById('ticket-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const datos = new FormData(this);
    fetch('registro.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(resp => {
            const cont = document.getElementById('resultado-ticket');
            if (resp.ok) {
                cont.innerHTML = `<div class="aviso-importante" style="border-left-color: var(--guinda);">✅ Ticket registrado. Tu número de folio es <strong>${resp.folio}</strong>. Consérvalo para dar seguimiento.</div>`;
                this.reset();
            } else {
                cont.innerHTML = `<div class="login-error" style="display:block;">${resp.error || 'No se pudo registrar el ticket.'}</div>`;
            }
            cont.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(() => {
            document.getElementById('resultado-ticket').innerHTML = '<div class="login-error" style="display:block;">No se pudo conectar con el servidor.</div>';
        });
});
</script>

</body>
</html>
