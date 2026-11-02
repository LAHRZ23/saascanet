<?php
// Marca un comunicado o evento como visto por el usuario en sesión, para
// mostrarle la etiqueta "Visto" en su tarjeta. No afecta a nadie más ni
// cambia el contenido del comunicado/evento.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$tipo = $_POST['tipo'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if ($tipo === 'comunicado') {
    $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO comunicados_vistos (usuario_id, comunicado_id) VALUES (?, ?)");
} elseif ($tipo === 'evento') {
    $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO eventos_vistos (usuario_id, evento_id) VALUES (?, ?)");
} else {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ii", $yo['id'], $id);
mysqli_stmt_execute($stmt);

echo json_encode(['ok' => true]);
