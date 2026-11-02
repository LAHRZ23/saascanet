<?php
// Endpoint AJAX: agenda personal privada del usuario en sesión (hasta 3 notas
// por día). Cualquier persona con sesión puede usarlo, no solo el administrador,
// porque es su propia agenda — nunca toca calendario_eventos ni eventos_programados.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

$accion = $_POST['accion'] ?? 'agregar';
$iconosValidos = ['🤝', '📋', '💼', '🏠', '📌'];

if ($accion === 'borrar') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conexion, "DELETE FROM calendario_notas_personales WHERE id = ? AND usuario_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $yo['id']);
    mysqli_stmt_execute($stmt);
    echo json_encode(['ok' => true, 'accion' => 'borrado']);
    exit;
}

if ($accion === 'borrar_vencidas') {
    $stmt = mysqli_prepare($conexion, "DELETE FROM calendario_notas_personales WHERE usuario_id = ? AND fecha < CURDATE()");
    mysqli_stmt_bind_param($stmt, "i", $yo['id']);
    mysqli_stmt_execute($stmt);
    $borradas = mysqli_stmt_affected_rows($stmt);
    echo json_encode(['ok' => true, 'accion' => 'borradas_vencidas', 'total' => $borradas]);
    exit;
}

// accion === 'agregar' (por defecto)
$fecha = $_POST['fecha'] ?? '';
$texto = trim($_POST['texto'] ?? '');
$icono = trim($_POST['icono'] ?? '') ?: '📌';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
if ($texto === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Escribe algo en tu nota.']);
    exit;
}
if (!in_array($icono, $iconosValidos, true)) {
    $icono = '📌';
}

$stmtCont = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM calendario_notas_personales WHERE usuario_id = ? AND fecha = ?");
mysqli_stmt_bind_param($stmtCont, "is", $yo['id'], $fecha);
mysqli_stmt_execute($stmtCont);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCont))['total'];

if ($total >= 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ya tienes el máximo de 3 notas personales ese día. Borra alguna para agregar otra.']);
    exit;
}

$stmt = mysqli_prepare($conexion, "INSERT INTO calendario_notas_personales (usuario_id, fecha, texto, icono) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isss", $yo['id'], $fecha, $texto, $icono);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true, 'accion' => 'guardado', 'id' => mysqli_insert_id($conexion)]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la nota.']);
}
