<?php
// Entrega la imagen o el PDF de una solicitud de publicación. Solo la puede
// abrir un administrador o la persona que la hizo. Con &descargar=1 se baja
// como archivo (para que el administrador lo suba a la publicación).
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
$yo = usuarioActual();

$id = (int) ($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['imagen', 'imagen2', 'pdf', 'video'], true)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$stmt = mysqli_prepare($conexion, "SELECT usuario_id, imagen, imagen2, pdf, video FROM solicitudes_publicacion WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$fila || empty($fila[$tipo]) || (!esAdmin() && (int) $fila['usuario_id'] !== (int) $yo['id'])) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$ruta = solicitudCarpeta() . '/' . basename($fila[$tipo]);
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
$tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'ogg' => 'video/ogg'];
$descargar = !empty($_GET['descargar']);

header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="solicitud-' . $id . '-' . $tipo . '.' . $ext . '"');
readfile($ruta);
