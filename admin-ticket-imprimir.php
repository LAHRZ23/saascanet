<?php
// Vista imprimible de un solo ticket, para tener un respaldo en papel en
// caso de que alguien pida una aclaración.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/ticket_participantes.php';
requiereLogin();
requiereAdmin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.atendido_por
    WHERE t.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$t = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$t) {
    header('Location: admin-tickets.php');
    exit;
}

$etiquetas = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];
$conParticipantes = ticketsConParticipantes($conexion, [$t]);
$participantesNombres = array_column($conParticipantes[0]['participantes'], 'nombre');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ticket <?= htmlspecialchars($t['folio']) ?> - SAASCAEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>
<div class="main-content" style="margin-left:0; max-width:700px; margin:0 auto; padding:40px 30px;">
    <p class="no-imprimir" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="admin-tickets.php" class="enlace-volver">← Volver a Tickets</a>
        <button type="button" onclick="window.print()" class="btn-secundario" style="border:none; cursor:pointer;">🖨️ Imprimir</button>
    </p>

    <div style="text-align:center; margin-bottom:24px; border-bottom:3px solid var(--guinda); padding-bottom:16px;">
        <img src="img/logo.png" alt="Logo SAASCAEM" style="width:70px;">
        <h1 style="margin:8px 0 0; color:var(--guinda);">Ticket de Soporte Técnico</h1>
        <p style="color:var(--text-muted); margin:4px 0 0;">Mesa de Servicios — SAASCAEM</p>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <tr><td style="padding:8px 0; font-weight:700; width:180px; vertical-align:top;">Folio:</td><td class="mono" style="padding:8px 0;"><?= htmlspecialchars($t['folio']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Solicitante:</td><td style="padding:8px 0;"><?= htmlspecialchars($t['nombre']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Área / Unidad:</td><td style="padding:8px 0;"><?= htmlspecialchars($t['area']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Equipo:</td><td style="padding:8px 0;"><?= htmlspecialchars($t['equipo']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Tipo de solicitud:</td><td style="padding:8px 0;"><?= htmlspecialchars($t['tipo']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Descripción:</td><td style="padding:8px 0; white-space:pre-line;"><?= htmlspecialchars($t['comentarios']) ?></td></tr>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Estatus:</td><td style="padding:8px 0;"><?= htmlspecialchars($etiquetas[$t['estatus']] ?? $t['estatus']) ?></td></tr>
        <?php if (!empty($t['nota_admin'])): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Nota del administrador:</td><td style="padding:8px 0; white-space:pre-line;"><?= htmlspecialchars($t['nota_admin']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Atendido por:</td><td style="padding:8px 0;"><?= htmlspecialchars($t['atendio_nombre'] ?? 'Sin asignar') ?></td></tr>
        <?php if (count($participantesNombres) > 1): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Participaron:</td><td style="padding:8px 0;"><?= htmlspecialchars(implode(', ', $participantesNombres)) ?></td></tr>
        <?php endif; ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Registrado:</td><td style="padding:8px 0;"><?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></td></tr>
        <?php if ($t['resuelto_en']): ?>
        <tr><td style="padding:8px 0; font-weight:700; vertical-align:top;">Cerrado:</td><td style="padding:8px 0;"><?= date('d/m/Y H:i', strtotime($t['resuelto_en'])) ?></td></tr>
        <?php endif; ?>
    </table>

    <p style="margin-top:40px; font-size:12px; color:var(--text-muted); text-align:center;">Documento generado el <?= date('d/m/Y H:i') ?> desde SAASCANET.</p>
</div>
</body>
</html>
