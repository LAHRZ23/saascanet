<?php
// Endpoint que consulta el menú (nav-loader.js) para llenar la campana de
// notificaciones: a los administradores les avisa de tickets nuevos y de
// solicitudes de publicación pendientes, y a cada usuario le avisa cuando
// sus propios tickets o solicitudes cambian de estatus.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    echo json_encode(['tipo' => 'ninguno']);
    exit;
}

$SECCIONES = solicitudSecciones();

if (esAdmin()) {
    $tickets = [];
    $resultado = mysqli_query($conexion, "SELECT folio, nombre, tipo, creado_en FROM tickets WHERE estatus = 'nuevo' AND archivado = 0 ORDER BY creado_en DESC LIMIT 6");
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $tickets[] = $fila;
    }

    $totalResultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus = 'nuevo' AND archivado = 0");
    $totalTickets = (int) mysqli_fetch_assoc($totalResultado)['total'];

    // Solicitudes de publicación pendientes de revisar. Si la tabla aún no
    // existe (falta importar el SQL), simplemente no hay solicitudes.
    $solicitudes = [];
    $totalSolicitudes = 0;
    $rs = mysqli_query($conexion, "SELECT s.id, s.seccion, s.titulo, u.nombre FROM solicitudes_publicacion s LEFT JOIN usuarios u ON u.id = s.usuario_id WHERE s.estatus = 'pendiente' ORDER BY s.creado_en DESC LIMIT 6");
    while ($rs && ($fila = mysqli_fetch_assoc($rs))) {
        $fila['seccion_label'] = $SECCIONES[$fila['seccion']] ?? $fila['seccion'];
        $solicitudes[] = $fila;
    }
    $rt = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM solicitudes_publicacion WHERE estatus = 'pendiente'");
    if ($rt) {
        $totalSolicitudes = (int) mysqli_fetch_assoc($rt)['total'];
    }

    echo json_encode([
        'tipo' => 'admin',
        'total' => $totalTickets + $totalSolicitudes,
        'tickets' => $tickets,
        'solicitudes' => $solicitudes,
        'totalSolicitudes' => $totalSolicitudes,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Usuario estándar: tickets propios con un cambio de estatus que aún no ha visto
$tickets = [];
$stmt = mysqli_prepare($conexion, "SELECT folio, tipo, estatus FROM tickets WHERE usuario_id = ? AND usuario_notificado = 0 ORDER BY creado_en DESC LIMIT 6");
mysqli_stmt_bind_param($stmt, "i", $yo['id']);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tickets[] = $fila;
}

$stmtTotal = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE usuario_id = ? AND usuario_notificado = 0");
mysqli_stmt_bind_param($stmtTotal, "i", $yo['id']);
mysqli_stmt_execute($stmtTotal);
$totalTickets = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTotal))['total'];

// Solicitudes de publicación propias que ya fueron aceptadas o rechazadas y
// que todavía no ha consultado en "Mis solicitudes".
$uid = (int) $yo['id'];
$solicitudes = [];
$totalSolicitudes = 0;
$rs = mysqli_query($conexion, "SELECT id, seccion, titulo, estatus FROM solicitudes_publicacion WHERE usuario_id = $uid AND usuario_notificado = 0 AND estatus <> 'pendiente' ORDER BY revisado_en DESC LIMIT 6");
while ($rs && ($fila = mysqli_fetch_assoc($rs))) {
    $fila['seccion_label'] = $SECCIONES[$fila['seccion']] ?? $fila['seccion'];
    $solicitudes[] = $fila;
}
$rt = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM solicitudes_publicacion WHERE usuario_id = $uid AND usuario_notificado = 0 AND estatus <> 'pendiente'");
if ($rt) {
    $totalSolicitudes = (int) mysqli_fetch_assoc($rt)['total'];
}

echo json_encode([
    'tipo' => 'usuario',
    'total' => $totalTickets + $totalSolicitudes,
    'tickets' => $tickets,
    'solicitudes' => $solicitudes,
    'totalSolicitudes' => $totalSolicitudes,
], JSON_UNESCAPED_UNICODE);
