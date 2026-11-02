<?php
// Descarga todos los tickets como un archivo CSV, para que el administrador
// tenga un respaldo antes de limpiar/eliminar tickets viejos de la página.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/rango_fechas.php';
require_once __DIR__ . '/includes/ticket_participantes.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$filtroEstatus = $_GET['estatus'] ?? '';
$soloValidos = ['nuevo', 'en_proceso', 'resuelto', 'no_procede'];
$soloMios = ($_GET['mios'] ?? '') === '1';

$condiciones = [];
$tipos = '';
$valores = [];

if (in_array($filtroEstatus, $soloValidos, true)) {
    $condiciones[] = 't.estatus = ?';
    $tipos .= 's';
    $valores[] = $filtroEstatus;
}
if ($soloMios) {
    // "Míos": los que tengo yo o en los que participo.
    $condiciones[] = ticketCondicionMios($conexion, $yo['id']);
}

// Periodo opcional: ?desde=AAAA-MM-DD&hasta=AAAA-MM-DD (fecha de registro del ticket).
list($desde, $hasta) = rangoDesdeGet();
list($condRango, $tiposRango, $valoresRango) = rangoCondiciones($desde, $hasta, 't.creado_en');
foreach ($condRango as $c) { $condiciones[] = $c; }
$tipos .= $tiposRango;
foreach ($valoresRango as $v) { $valores[] = $v; }

// "Descargar filtrados": el panel manda por POST los id de los tickets que se ven en pantalla.
$idsFiltrados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids'])) {
    foreach (array_slice(explode(',', (string) $_POST['ids']), 0, 5000) as $x) {
        $x = (int) $x;
        if ($x > 0) { $idsFiltrados[$x] = $x; }
    }
}
if ($idsFiltrados) {
    $condiciones[] = 't.id IN (' . implode(',', array_keys($idsFiltrados)) . ')';
}

$sql = "SELECT t.id, t.atendido_por, t.folio, t.nombre, t.area, t.equipo, t.tipo, t.comentarios, t.estatus, t.nota_admin, t.creado_en, t.resuelto_en, u.nombre AS atendio_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.atendido_por";
if ($condiciones) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}
$sql .= " ORDER BY t.creado_en DESC";

$tickets = [];
// Con parámetros ("?") se prepara la consulta; si las condiciones no llevan ninguno
// (por ejemplo, solo la lista de tickets filtrados), va como consulta normal.
if ($tipos !== '') {
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {
    $resultado = mysqli_query($conexion, $sql);
}
while ($fila = mysqli_fetch_assoc($resultado)) {
    $tickets[] = $fila;
}
$tickets = ticketsConParticipantes($conexion, $tickets);

$sufijoArchivo = (in_array($filtroEstatus, $soloValidos, true) ? "_$filtroEstatus" : '') . ($soloMios ? '_mios' : '') . rangoSufijoArchivo($desde, $hasta) . ($idsFiltrados ? '_filtrados' : '');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tickets' . $sufijoArchivo . '_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
// BOM para que Excel abra los acentos correctamente
fwrite($salida, "\xEF\xBB\xBF");

fputcsv($salida, ['Folio', 'Nombre', 'Área', 'Equipo', 'Tipo', 'Comentarios', 'Estatus', 'Nota', 'Registrado', 'Resuelto', 'Atendió', 'Participantes']);

$etiquetas = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];

foreach ($tickets as $t) {
    fputcsv($salida, [
        $t['folio'],
        $t['nombre'],
        $t['area'],
        $t['equipo'],
        $t['tipo'],
        $t['comentarios'],
        $etiquetas[$t['estatus']] ?? $t['estatus'],
        $t['nota_admin'] ?? '',
        $t['creado_en'],
        $t['resuelto_en'],
        $t['atendio_nombre'] ?? '',
        implode(', ', array_column($t['participantes'], 'nombre')),
    ]);
}

fclose($salida);
exit;
