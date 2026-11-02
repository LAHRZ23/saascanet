<?php
// Descarga toda la bitácora como un archivo CSV, para tener un respaldo
// antes de vaciarla.
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();

$registros = [];
$resultado = mysqli_query($conexion, "SELECT b.accion, b.detalle, b.creado_en, u.nombre AS usuario_nombre
    FROM bitacora b
    LEFT JOIN usuarios u ON u.id = b.usuario_id
    ORDER BY b.creado_en DESC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $registros[] = $fila;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bitacora_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Fecha', 'Usuario', 'Acción', 'Detalle']);

foreach ($registros as $r) {
    fputcsv($salida, [
        $r['creado_en'],
        $r['usuario_nombre'] ?? 'Usuario eliminado',
        $r['accion'],
        $r['detalle'],
    ]);
}

fclose($salida);
exit;
