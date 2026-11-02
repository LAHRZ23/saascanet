<?php
// Endpoint AJAX: crea, actualiza o borra un evento del calendario compartido.
// Solo el administrador puede llamarlo (el calendario es de solo lectura
// para el resto del personal).
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
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
$fecha = $_POST['fecha'] ?? '';
$titulo = trim($_POST['titulo'] ?? '');
$tipo = $_POST['tipo'] ?? 'normal';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
if (!in_array($tipo, ['normal', 'vacaciones', 'nolaboral'], true)) {
    $tipo = 'normal';
}

// Un día puede tener varios eventos (los publicados desde "Publicar evento",
// cada uno su propia fila con evento_id). Esta pantalla del Calendario solo
// crea/edita/borra la nota manual de ese día (evento_id IS NULL), sin tocar
// los eventos ya publicados.
if ($titulo === '') {
    // Texto vacío = borrar la nota manual de ese día
    $stmt = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE fecha = ? AND evento_id IS NULL");
    mysqli_stmt_bind_param($stmt, "s", $fecha);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Borró nota del calendario', $fecha);
    echo json_encode(['ok' => true, 'accion' => 'borrado']);
    exit;
}

$stmtDel = mysqli_prepare($conexion, "DELETE FROM calendario_eventos WHERE fecha = ? AND evento_id IS NULL");
mysqli_stmt_bind_param($stmtDel, "s", $fecha);
mysqli_stmt_execute($stmtDel);

$stmt = mysqli_prepare($conexion, "INSERT INTO calendario_eventos (titulo, fecha, tipo, creado_por, evento_id) VALUES (?, ?, ?, ?, NULL)");
mysqli_stmt_bind_param($stmt, "sssi", $titulo, $fecha, $tipo, $yo['id']);

if (mysqli_stmt_execute($stmt)) {
    $idNota = mysqli_insert_id($conexion);
    registrarBitacora($conexion, 'Guardó evento del calendario', "$fecha — $titulo");
    // Si venía de "Publicar esto", la solicitud queda marcada como publicada.
    $publicada = false;
    $solPublicar = solicitudCargarParaPublicar($conexion);
    if ($solPublicar && $solPublicar['seccion'] === 'calendario' && in_array($solPublicar['estatus'], ['pendiente', 'aceptada'], true)) {
        solicitudMarcarPublicada($conexion, (int) $solPublicar['id'], $yo['id'], 'calendario_eventos', $idNota);
        $publicada = true;
    }
    echo json_encode(['ok' => true, 'accion' => 'guardado', 'solicitudPublicada' => $publicada]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el evento.']);
}
