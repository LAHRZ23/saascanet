<?php
// Endpoint AJAX: quita las vacaciones marcadas manualmente en un rango de
// fechas (no toca notas normales, días festivos automáticos ni eventos
// publicados desde "Publicar evento"). Solo el administrador puede llamarlo.
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

$inicio = $_POST['inicio'] ?? '';
$fin = $_POST['fin'] ?? '';

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

$stmt = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE fecha BETWEEN ? AND ? AND tipo = 'vacaciones' AND evento_id IS NULL");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fin);
mysqli_stmt_execute($stmt);
$eliminados = mysqli_affected_rows($conexion);

registrarBitacora($conexion, 'Quitó un rango de vacaciones', "$inicio a $fin ($eliminados día(s))");

$fechas = [];
$cursor = clone $fechaInicio;
while ($cursor <= $fechaFin) {
    $fechas[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}

echo json_encode(['ok' => true, 'fechas' => $fechas, 'eliminados' => $eliminados], JSON_UNESCAPED_UNICODE);
