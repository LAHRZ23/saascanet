<?php
// Registra un nuevo ticket de soporte, ligado al usuario que inició sesión.
// Responde en JSON porque el formulario de mesa-servicios.php lo llama
// por fetch() en vez de enviarlo como formulario tradicional.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuarioActual()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Debes iniciar sesión para levantar un ticket.']);
    exit;
}

$yo = usuarioActual();
$area = trim($_POST['area'] ?? '');
$equipo = trim($_POST['equipo'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');
$comentarios = trim($_POST['comentarios'] ?? '');

if ($area === '' || $equipo === '' || $tipo === '' || $comentarios === '') {
    echo json_encode(['ok' => false, 'error' => 'Por favor completa todos los campos.']);
    exit;
}

$nombreImagen = null;
if (!empty($_FILES['imagen']['name'])) {
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
    $tamañoMax = 5 * 1024 * 1024; // 5 MB

    if (!in_array($extension, $permitidas, true)) {
        echo json_encode(['ok' => false, 'error' => 'La imagen debe ser jpg, png, gif o webp.']);
        exit;
    }
    if ($_FILES['imagen']['size'] > $tamañoMax) {
        echo json_encode(['ok' => false, 'error' => 'La imagen no puede pesar más de 5 MB.']);
        exit;
    }
    if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al subir la imagen, intenta de nuevo.']);
        exit;
    }
    $nombreImagen = uniqid('tkt_', true) . '.' . $extension;
    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/uploads/tickets/' . $nombreImagen)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.']);
        exit;
    }
}

// Folio temporal único mientras obtenemos el id autoincremental real.
$folioTemporal = 'TEMP-' . bin2hex(random_bytes(6));

$stmt = mysqli_prepare($conexion, "INSERT INTO tickets (folio, usuario_id, nombre, area, equipo, tipo, comentarios, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sissssss", $folioTemporal, $yo['id'], $yo['nombre'], $area, $equipo, $tipo, $comentarios, $nombreImagen);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo registrar el ticket.']);
    exit;
}

$id = mysqli_insert_id($conexion);
$folio = 'GTR-' . str_pad($id, 4, '0', STR_PAD_LEFT);

$stmtFolio = mysqli_prepare($conexion, "UPDATE tickets SET folio = ? WHERE id = ?");
mysqli_stmt_bind_param($stmtFolio, "si", $folio, $id);
mysqli_stmt_execute($stmtFolio);

echo json_encode(['ok' => true, 'folio' => $folio]);
