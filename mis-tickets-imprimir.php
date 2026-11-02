<?php
// Vista imprimible con el historial completo de tickets de quien tiene la
// sesión abierta, útil como comprobante para algún trámite.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/rango_fechas.php';
requiereLogin();
$yo = usuarioActual();

// Periodo opcional (?desde=...&hasta=...) y estatus opcional (?estatus=resuelto).
list($desde, $hasta) = rangoDesdeGet();
$estatusValidos = ['nuevo', 'en_proceso', 'resuelto', 'no_procede'];
$filtroEstatus = in_array($_GET['estatus'] ?? '', $estatusValidos, true) ? $_GET['estatus'] : '';

$condiciones = ['t.usuario_id = ?'];
$tipos = 'i';
$valores = [$yo['id']];
list($condRango, $tiposRango, $valoresRango) = rangoCondiciones($desde, $hasta, 't.creado_en');
foreach ($condRango as $c) { $condiciones[] = $c; }
$tipos .= $tiposRango;
foreach ($valoresRango as $v) { $valores[] = $v; }
if ($filtroEstatus !== '') {
    $condiciones[] = 't.estatus = ?';
    $tipos .= 's';
    $valores[] = $filtroEstatus;
}

$tickets = [];
$stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.atendido_por
    WHERE " . implode(' AND ', $condiciones) . "
    ORDER BY t.creado_en DESC");
mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tickets[] = $fila;
}

$etiquetas = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Mi historial de tickets<?= ($desde !== '' || $hasta !== '') ? ' - ' . htmlspecialchars(rangoEtiqueta($desde, $hasta)) : '' ?> - SAASCAEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
    <style>
        @media print {
            .ticket-imp { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="main-content" style="margin-left:0; max-width:800px; margin:0 auto; padding:40px 30px;">
    <p class="no-imprimir" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="mis-tickets.php" class="enlace-volver">← Volver a Mis tickets</a>
        <?php if ($tickets): ?>
            <button type="button" onclick="window.print()" class="btn-secundario" style="border:none; cursor:pointer;">🖨️ Imprimir</button>
        <?php endif; ?>
    </p>

    <div style="text-align:center; margin-bottom:24px; border-bottom:3px solid var(--guinda); padding-bottom:16px;">
        <img src="img/logo.png" alt="Logo SAASCAEM" style="width:70px;">
        <h1 style="margin:8px 0 0; color:var(--guinda);">Historial de tickets</h1>
        <p style="color:var(--text-muted); margin:4px 0 0;"><?= htmlspecialchars($yo['nombre']) ?> — Mesa de Servicios, SAASCAEM</p>
        <?php if ($desde !== '' || $hasta !== '' || $filtroEstatus !== ''): ?>
            <p style="margin:6px 0 0; font-size:14px;"><strong>Periodo:</strong> <?= htmlspecialchars(rangoEtiqueta($desde, $hasta)) ?><?= $filtroEstatus !== '' ? ' · <strong>Estatus:</strong> ' . htmlspecialchars($etiquetas[$filtroEstatus]) : '' ?> · <?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?></p>
        <?php endif; ?>
    </div>

    <?php if (!$tickets): ?>
        <p style="text-align:center; color:var(--text-muted);"><?= ($desde !== '' || $hasta !== '' || $filtroEstatus !== '') ? 'No hay tickets tuyos con ese filtro.' : 'Todavía no has levantado ningún ticket.' ?></p>
    <?php endif; ?>

    <?php foreach ($tickets as $t): ?>
        <div class="ticket-imp" style="border:1px solid var(--border); border-radius:10px; padding:16px 18px; margin-bottom:14px;">
            <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
                <tr><td style="padding:4px 0; font-weight:700; width:160px; vertical-align:top;">Folio:</td><td class="mono" style="padding:4px 0;"><?= htmlspecialchars($t['folio']) ?></td></tr>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Equipo:</td><td style="padding:4px 0;"><?= htmlspecialchars($t['equipo']) ?></td></tr>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Tipo de solicitud:</td><td style="padding:4px 0;"><?= htmlspecialchars($t['tipo']) ?></td></tr>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Descripción:</td><td style="padding:4px 0; white-space:pre-line;"><?= htmlspecialchars($t['comentarios']) ?></td></tr>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Estatus:</td><td style="padding:4px 0;"><?= htmlspecialchars($etiquetas[$t['estatus']] ?? $t['estatus']) ?></td></tr>
                <?php if (!empty($t['imagen'])): ?>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Imagen adjunta:</td><td style="padding:4px 0;"><img src="uploads/tickets/<?= htmlspecialchars($t['imagen']) ?>" alt="Imagen del ticket" style="max-width:320px; max-height:240px; border-radius:6px; border:1px solid var(--border);"></td></tr>
                <?php endif; ?>
                <?php if (!empty($t['nota_admin'])): ?>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Nota de soporte:</td><td style="padding:4px 0; white-space:pre-line;"><?= htmlspecialchars($t['nota_admin']) ?></td></tr>
                <?php endif; ?>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Atendido por:</td><td style="padding:4px 0;"><?= htmlspecialchars($t['atendio_nombre'] ?? 'Sin asignar') ?></td></tr>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Registrado:</td><td style="padding:4px 0;"><?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></td></tr>
                <?php if ($t['resuelto_en']): ?>
                <tr><td style="padding:4px 0; font-weight:700; vertical-align:top;">Cerrado:</td><td style="padding:4px 0;"><?= date('d/m/Y H:i', strtotime($t['resuelto_en'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endforeach; ?>

    <?php if ($tickets): ?>
        <p style="margin-top:30px; font-size:12px; color:var(--text-muted); text-align:center;">Documento generado el <?= date('d/m/Y H:i') ?> desde SAASCANET. Total de tickets: <?= count($tickets) ?>.</p>
    <?php endif; ?>
</div>
</body>
</html>
