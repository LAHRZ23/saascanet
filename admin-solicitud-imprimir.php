<?php
// Vista imprimible de una solicitud de publicación, para tenerla en papel o
// como respaldo mientras se prepara la publicación.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
requiereAdmin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conexion, "SELECT s.*, u.nombre AS solicitante, u.area AS solicitante_area, r.nombre AS revisor_nombre
    FROM solicitudes_publicacion s
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios r ON r.id = s.revisado_por
    WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$s = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$s) {
    header('Location: admin-solicitudes.php');
    exit;
}

$SECCIONES = solicitudSecciones();
$ESTATUS = solicitudEstatus();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Solicitud de publicación - SAASCAEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>
<div class="main-content" style="margin-left:0; max-width:700px; margin:0 auto; padding:40px 30px;">
    <p class="no-imprimir" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="admin-solicitudes.php" class="enlace-volver">← Volver a Solicitudes</a>
        <button type="button" onclick="window.print()" class="btn-secundario" style="border:none; cursor:pointer;">🖨️ Imprimir</button>
    </p>

    <div style="text-align:center; margin-bottom:24px; border-bottom:3px solid var(--guinda); padding-bottom:16px;">
        <img src="img/logo.png" alt="Logo SAASCAEM" style="width:70px;">
        <h1 style="margin:8px 0 0; color:var(--guinda);">Solicitud de publicación</h1>
        <p style="color:var(--text-muted); margin:4px 0 0;">Unidad de Apoyo Técnico e Informática — SAASCAEM</p>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <tr><td style="padding:8px 0; font-weight:700; width:180px; vertical-align:top;">Sección:</td><td style="padding:8px 0;"><?= htmlspecialchars($SECCIONES[$s['seccion']] ?? $s['seccion']) ?></td></tr>
        <?php if (!empty($s['categoria'])): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Categoría pedida:</td><td style="padding:8px 0;"><?= htmlspecialchars(solicitudCategorias()[$s['categoria']] ?? $s['categoria']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Título:</td><td style="padding:8px 0;"><?= htmlspecialchars($s['titulo']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Solicitante:</td><td style="padding:8px 0;"><?= htmlspecialchars($s['solicitante'] ?? 'Cuenta eliminada') ?><?= !empty($s['solicitante_area']) ? ' (' . htmlspecialchars($s['solicitante_area']) . ')' : '' ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Enviada:</td><td style="padding:8px 0;"><?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Se necesita para:</td><td style="padding:8px 0;"><?= date('d/m/Y', strtotime($s['fecha_requerida'])) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Contenido:</td><td style="padding:8px 0; white-space:pre-line;"><?= htmlspecialchars($s['descripcion']) ?></td></tr>
        <?php foreach ([1, 2] as $n): if ($s["enlace{$n}_url"]): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Botón <?= $n ?>:</td><td style="padding:8px 0;"><?= htmlspecialchars($s["enlace{$n}_texto"]) ?> → <?= htmlspecialchars($s["enlace{$n}_url"]) ?></td></tr>
        <?php endif; endforeach; ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Archivos:</td><td style="padding:8px 0;"><?= implode(', ', array_filter([$s['imagen'] ? ($s['imagen2'] ? 'Imagen 1' : 'Imagen') : '', $s['imagen2'] ? 'Imagen 2' : '', $s['pdf'] ? 'PDF' : '', $s['video'] ? 'Video' : ''])) ?: 'Sin archivos' ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Estatus:</td><td style="padding:8px 0;"><?= htmlspecialchars($ESTATUS[$s['estatus']]['texto'] ?? $s['estatus']) ?></td></tr>
        <?php if ($s['revisor_nombre']): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Revisó:</td><td style="padding:8px 0;"><?= htmlspecialchars($s['revisor_nombre']) ?><?= $s['revisado_en'] ? ' — ' . date('d/m/Y H:i', strtotime($s['revisado_en'])) : '' ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($s['respuesta_admin'])): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Nota / motivo:</td><td style="padding:8px 0; white-space:pre-line;"><?= htmlspecialchars($s['respuesta_admin']) ?></td></tr>
        <?php endif; ?>
    </table>

    <p style="margin-top:40px; font-size:12px; color:var(--text-muted); text-align:center;">Documento generado el <?= date('d/m/Y H:i') ?> desde SAASCANET.</p>
</div>
</body>
</html>
