<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
$yo = usuarioActual();

// Al entrar aquí, se dan por vistas las respuestas pendientes (apaga la campana).
$stmtVisto = mysqli_prepare($conexion, "UPDATE solicitudes_publicacion SET usuario_notificado = 1 WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmtVisto, "i", $yo['id']);
mysqli_stmt_execute($stmtVisto);

$SECCIONES = solicitudSecciones();
$ESTATUS = solicitudEstatus();

$solicitudes = [];
$stmt = mysqli_prepare($conexion, "SELECT s.*, r.nombre AS revisor_nombre
    FROM solicitudes_publicacion s
    LEFT JOIN usuarios r ON r.id = s.revisado_por
    WHERE s.usuario_id = ?
    ORDER BY s.creado_en DESC");
mysqli_stmt_bind_param($stmt, "i", $yo['id']);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $solicitudes[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Mis solicitudes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📋 Mis solicitudes</h1>
    <p style="display:flex; gap:14px; flex-wrap:wrap; align-items:center; margin-bottom:20px;">
        <a href="solicitar-publicacion.php" class="btn-accion" style="display:inline-block;">➕ Nueva solicitud</a>
    </p>

    <?php if (!empty($_GET['ok'])): ?>
        <div class="aviso-importante" style="max-width:760px; margin-bottom:18px;">✅ Tu solicitud se envió a la Unidad de Apoyo Técnico e Informática (UATI). Aquí podrás ver si se acepta o se rechaza.</div>
    <?php endif; ?>

    <?php if (empty($solicitudes)): ?>
        <p>Todavía no has hecho ninguna solicitud de publicación.</p>
    <?php endif; ?>

    <div style="max-width:760px; display:flex; flex-direction:column; gap:20px; margin-top:14px;">
        <?php foreach ($solicitudes as $s):
            $e = $ESTATUS[$s['estatus']] ?? $ESTATUS['pendiente']; ?>
            <div class="caja-accion" style="text-align:left;">
                <span class="pill" style="background: <?= $e['fondo'] ?>; color: <?= $e['color'] ?>;"><?= $e['texto'] ?></span>
                <?php if (!empty($s['categoria'])): ?>
                    <span class="pill" style="background: rgba(0,0,0,.06); color: var(--text); margin-left:6px;">Categoría: <?= htmlspecialchars(solicitudCategorias()[$s['categoria']] ?? $s['categoria']) ?></span>
                <?php endif; ?>
                <h3 style="margin:8px 0 4px; text-align:left;"><?= htmlspecialchars($s['titulo']) ?></h3>
                <p style="font-size:13px; color:var(--text-muted); margin:0 0 8px;">
                    <?= htmlspecialchars($SECCIONES[$s['seccion']] ?? $s['seccion']) ?>
                    · Para el <?= date('d/m/Y', strtotime($s['fecha_requerida'])) ?>
                    · Enviada el <?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?>
                </p>
                <p><?= nl2br(htmlspecialchars($s['descripcion'])) ?></p>

                <?php
                $adjuntos = [];
                if ($s['imagen']) { $adjuntos[] = '<a href="solicitud_archivo.php?id=' . $s['id'] . '&tipo=imagen" target="_blank">📷 Ver imagen' . ($s['imagen2'] ? ' 1' : ' adjunta') . '</a>'; }
                if ($s['imagen2']) { $adjuntos[] = '<a href="solicitud_archivo.php?id=' . $s['id'] . '&tipo=imagen2" target="_blank">📷 Ver imagen 2</a>'; }
                if ($s['pdf']) { $adjuntos[] = '<a href="solicitud_archivo.php?id=' . $s['id'] . '&tipo=pdf" target="_blank">📎 Ver PDF adjunto</a>'; }
                if ($s['video']) { $adjuntos[] = '<a href="solicitud_archivo.php?id=' . $s['id'] . '&tipo=video" target="_blank">🎬 Ver video</a>'; }
                ?>
                <?php if ($adjuntos): ?>
                    <p style="font-size:13px; margin:12px 0;"><?= implode(' &nbsp;·&nbsp; ', $adjuntos) ?></p>
                <?php endif; ?>

                <?php if ($s['enlace1_url']): ?>
                    <p style="font-size:13px; margin:2px 0;">🔗 <?= htmlspecialchars($s['enlace1_texto']) ?>: <?= htmlspecialchars($s['enlace1_url']) ?></p>
                <?php endif; ?>
                <?php if ($s['enlace2_url']): ?>
                    <p style="font-size:13px; margin:2px 0;">🔗 <?= htmlspecialchars($s['enlace2_texto']) ?>: <?= htmlspecialchars($s['enlace2_url']) ?></p>
                <?php endif; ?>

                <?php if ($s['estatus'] === 'rechazada'): ?>
                    <div class="aviso-importante" style="margin-top:12px; border-left-color: var(--danger, #B23A3A);">
                        <strong>❌ Motivo del rechazo:</strong><br><?= nl2br(htmlspecialchars($s['respuesta_admin'] ?? '')) ?>
                        <?php if ($s['revisor_nombre']): ?><br><span style="font-size:12px; color:var(--text-muted);">Revisó: <?= htmlspecialchars($s['revisor_nombre']) ?> · <?= date('d/m/Y H:i', strtotime($s['revisado_en'])) ?></span><?php endif; ?>
                    </div>
                <?php elseif ($s['estatus'] === 'publicada'): ?>
                    <div class="aviso-importante" style="margin-top:12px; border-left-color: var(--guinda);">
                        <strong>📢 Tu solicitud ya fue publicada en la intranet.</strong>
                        <?php if (!empty($s['respuesta_admin'])): ?><br><?= nl2br(htmlspecialchars($s['respuesta_admin'])) ?><?php endif; ?>
                        <?php if ($s['revisor_nombre']): ?><br><span style="font-size:12px; color:var(--text-muted);">Publicó: <?= htmlspecialchars($s['revisor_nombre']) ?> · <?= date('d/m/Y H:i', strtotime($s['revisado_en'])) ?></span><?php endif; ?>
                    </div>
                <?php elseif ($s['estatus'] === 'aceptada'): ?>
                    <div class="aviso-importante" style="margin-top:12px; border-left-color: var(--success, #2E7D5B);">
                        <strong>✅ Solicitud aceptada.</strong>
                        <?php if (!empty($s['respuesta_admin'])): ?><br><?= nl2br(htmlspecialchars($s['respuesta_admin'])) ?><?php endif; ?>
                        <?php if ($s['revisor_nombre']): ?><br><span style="font-size:12px; color:var(--text-muted);">Revisó: <?= htmlspecialchars($s['revisor_nombre']) ?> · <?= date('d/m/Y H:i', strtotime($s['revisado_en'])) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
