<?php
// Endpoint que consulta nav-loader.js para poner un número sobre el botón
// "Eventos" del menú, igual que la campana de notificaciones.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    echo json_encode(['total' => 0]);
    exit;
}

// Solo cuenta eventos vigentes, no vencidos, y que el usuario no haya visto
// ya al entrar directo a Eventos (ver eventos_badge_checkpoint, que se
// actualiza en eventos.php).
$total = 0;
$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM eventos_programados e
    LEFT JOIN eventos_badge_checkpoint c ON c.usuario_id = ?
    WHERE e.vigente = 1
      AND (e.fecha IS NULL OR e.fecha >= CURDATE())
      AND e.id > COALESCE(c.visto_hasta_id, 0)");
mysqli_stmt_bind_param($stmt, "i", $yo['id']);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
if ($resultado) {
    $total = (int) mysqli_fetch_assoc($resultado)['total'];
}

echo json_encode(['total' => $total]);
