<?php
// Devuelve en JSON el mismo listado de tickets que admin-tickets.php, para
// que esa pantalla se pueda refrescar sola cada cierto tiempo sin que el
// administrador tenga que recargar la página — así ve de inmediato cuando
// alguien más toma, cierra o actualiza un ticket.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/ticket_participantes.php';
requiereLogin();
requiereAdmin();
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();

$vista = $_GET['ver'] ?? 'activos';
$verArchivados = $vista === 'archivados';
$verMios = $vista === 'mios';

$tickets = [];
if ($verMios) {
    $stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.atendido_por
        WHERE t.archivado = 0 AND " . ticketCondicionMios($conexion, $yo['id']) . "
        ORDER BY t.creado_en DESC");
} else {
    $stmt = mysqli_prepare($conexion, "SELECT t.*, u.nombre AS atendio_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.atendido_por
        WHERE t.archivado = ?
        ORDER BY t.creado_en DESC");
    $archivadoParam = $verArchivados ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "i", $archivadoParam);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $fila['id'] = (int) $fila['id'];
    $fila['atendido_por'] = $fila['atendido_por'] !== null ? (int) $fila['atendido_por'] : null;
    $fila['creado_en_fmt'] = date('d/m/Y H:i', strtotime($fila['creado_en']));
    $fila['resuelto_en_fmt'] = $fila['resuelto_en'] ? date('d/m/Y H:i', strtotime($fila['resuelto_en'])) : null;
    $tickets[] = $fila;
}

$tickets = ticketsConParticipantes($conexion, $tickets);

$totalNuevos = count(array_filter($tickets, function ($t) { return $t['estatus'] === 'nuevo'; }));

$totalActivos = 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE archivado = 0");
if ($r) $totalActivos = (int) mysqli_fetch_assoc($r)['total'];

$totalMios = 0;
$rM = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets t WHERE t.archivado = 0 AND " . ticketCondicionMios($conexion, $yo['id']));
if ($rM) $totalMios = (int) mysqli_fetch_assoc($rM)['total'];

$totalArchivados = 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE archivado = 1");
if ($r) $totalArchivados = (int) mysqli_fetch_assoc($r)['total'];

$totalCerrados = 0;
$r = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus IN ('resuelto', 'no_procede')");
if ($r) $totalCerrados = (int) mysqli_fetch_assoc($r)['total'];

echo json_encode([
    'tickets' => $tickets,
    'yo' => ['id' => (int) $yo['id'], 'nombre' => $yo['nombre']],
    'admins' => ticketAdmins($conexion),
    'participantesOk' => ticketParticipantesDisponible($conexion),
    'vista' => $vista,
    'verArchivados' => $verArchivados,
    'totales' => [
        'nuevos' => $totalNuevos,
        'activos' => $totalActivos,
        'mios' => $totalMios,
        'archivados' => $totalArchivados,
        'cerrados' => $totalCerrados,
    ],
], JSON_UNESCAPED_UNICODE);
