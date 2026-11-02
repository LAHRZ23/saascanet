<?php
// Descarga en CSV el historial de tickets de quien tiene la sesión abierta.
// Acepta ?desde=AAAA-MM-DD&hasta=AAAA-MM-DD y ?estatus=resuelto (todo opcional).
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/rango_fechas.php';
requiereLogin();
$yo = usuarioActual();

list($desde, $hasta) = rangoDesdeGet();
$estatusValidos = ['nuevo', 'en_proceso', 'resuelto', 'no_procede'];
$filtroEstatus = in_array($_GET['estatus'] ?? '', $estatusValidos, true) ? $_GET['estatus'] : '';

$condiciones = ['t.usuario_id = ?'];
$tipos = 'i';
$valores = [$yo['id']];
list($condRango, $tiposRango, $valoresRango) = rangoCondiciones($desde, $hasta, 't.creado_en');
foreach ($condRango as $c) { $condiciones[] = $c; }
$tipos .= $tiposRango;
foreach ($valoresRango as $v) { $valores[] = $v; }
if ($filtroEstatus !== '') {
    $condiciones[] = 't.estatus = ?';
    $tipos .= 's';
    $valores[] = $filtroEstatus;
}

$stmt = mysqli_prepare($conexion, "SELECT t.folio, t.equipo, t.tipo, t.comentarios, t.estatus, t.nota_admin, t.creado_en, t.resuelto_en, u.nombre AS atendio_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.atendido_por
    WHERE " . implode(' AND ', $condiciones) . "
    ORDER BY t.creado_en DESC");
mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$etiquetas = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="mis_tickets' . ($filtroEstatus !== '' ? "_$filtroEstatus" : '') . rangoSufijoArchivo($desde, $hasta) . '_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel abra los acentos

fputcsv($salida, ['Folio', 'Equipo', 'Tipo', 'Descripción', 'Estatus', 'Nota de soporte', 'Registrado', 'Cerrado', 'Atendió']);
while ($t = mysqli_fetch_assoc($resultado)) {
    fputcsv($salida, [
        $t['folio'],
        $t['equipo'],
        $t['tipo'],
        $t['comentarios'],
        $etiquetas[$t['estatus']] ?? $t['estatus'],
        $t['nota_admin'] ?? '',
        $t['creado_en'],
        $t['resuelto_en'] ?? '',
        $t['atendio_nombre'] ?? '',
    ]);
}
fclose($salida);
exit;
