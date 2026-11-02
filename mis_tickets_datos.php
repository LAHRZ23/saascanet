<?php
// Devuelve en JSON los tickets del usuario en sesión, para que
// mis-tickets.php se pueda refrescar solo cada cierto tiempo sin recargar —
// así la persona ve en el momento cuando un administrador toma, actualiza o
// cierra su ticket.
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();

// Igual que hace la carga normal de la página: al consultar, se dan por
// vistas las actualizaciones pendientes (apaga la campana para estos tickets).
$stmtVisto = mysqli_prepare($conexion, "UPDATE tickets SET usuario_notificado = 1 WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmtVisto, "i", $yo['id']);
mysqli_stmt_execute($stmtVisto);

$tickets = [];
$stmt = mysqli_prepare($conexion, "SELECT t.folio, t.tipo, t.equipo, t.comentarios, t.imagen, t.estatus, t.nota_admin, t.creado_en, t.resuelto_en, u.nombre AS atendio_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.atendido_por
    WHERE t.usuario_id = ?
    ORDER BY t.creado_en DESC");
mysqli_stmt_bind_param($stmt, "i", $yo['id']);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $fila['creado_en_fmt'] = date('d/m/Y H:i', strtotime($fila['creado_en']));
    $fila['resuelto_en_fmt'] = $fila['resuelto_en'] ? date('d/m/Y H:i', strtotime($fila['resuelto_en'])) : null;
    $tickets[] = $fila;
}

echo json_encode(['tickets' => $tickets], JSON_UNESCAPED_UNICODE);
