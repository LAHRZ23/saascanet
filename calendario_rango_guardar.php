<?php
// Endpoint AJAX: marca un rango de fechas seguidas como vacaciones (o el tipo
// que se le pase) de un jalón, en vez de tener que capturar día por día.
// Solo el administrador puede llamarlo.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuarioActual()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}
if (!esAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo el administrador puede editar el calendario.']);
    exit;
}

$yo = usuarioActual();
$inicio = $_POST['inicio'] ?? '';
$fin = $_POST['fin'] ?? '';
$titulo = trim($_POST['titulo'] ?? '') ?: 'Vacaciones';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Selecciona ambas fechas.']);
    exit;
}

$fechaInicio = new DateTime($inicio);
$fechaFin = new DateTime($fin);

if ($fechaFin < $fechaInicio) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
    exit;
}

if ($fechaInicio->diff($fechaFin)->days > 90) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El rango no puede ser mayor a 90 días.']);
    exit;
}

$fechas = [];
$cursor = clone $fechaInicio;
while ($cursor <= $fechaFin) {
    $fechas[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}

// Cada día del rango se guarda como nota manual del calendario (igual que un
// día capturado a mano), reemplazando la nota manual que hubiera antes ese
// día, pero sin tocar los eventos ya publicados desde "Publicar evento".
foreach ($fechas as $fecha) {
    $stmtDel = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE fecha = ? AND evento_id IS NULL");
    mysqli_stmt_bind_param($stmtDel, "s", $fecha);
    mysqli_stmt_execute($stmtDel);

    $stmt = mysqli_prepare($conexion, "INSERT INTO calendario_eventos (titulo, fecha, tipo, creado_por, evento_id) VALUES (?, ?, 'vacaciones', ?, NULL)");
    mysqli_stmt_bind_param($stmt, "ssi", $titulo, $fecha, $yo['id']);
    mysqli_stmt_execute($stmt);
}

registrarBitacora($conexion, 'Marcó un rango de vacaciones', "$inicio a $fin ($titulo, " . count($fechas) . " días)");
echo json_encode(['ok' => true, 'fechas' => $fechas, 'titulo' => $titulo], JSON_UNESCAPED_UNICODE);
