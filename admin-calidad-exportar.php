<?php
// Descarga las respuestas (anónimas) de una ronda de la encuesta de calidad como CSV.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/calidad_preguntas.php';
requiereLogin();
requiereAdmin();

$rondaId = (int) ($_GET['ronda'] ?? 0);
$stmt = mysqli_prepare($conexion, "SELECT nombre FROM calidad_rondas WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $rondaId);
mysqli_stmt_execute($stmt);
$ronda = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$ronda) {
    header('Location: admin-calidad.php');
    exit;
}

$stmt = mysqli_prepare($conexion, "SELECT * FROM calidad_respuestas WHERE ronda_id = ? ORDER BY creado_en");
mysqli_stmt_bind_param($stmt, "i", $rondaId);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="calidad_atencion_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Encuesta', $ronda['nombre']]);
$claves = array_keys($CALIDAD_PREGUNTAS);
fputcsv($salida, array_merge(['Fecha'], array_map(function ($c) use ($CALIDAD_PREGUNTAS) { return $CALIDAD_PREGUNTAS[$c]['corto']; }, $claves)));

while ($r = mysqli_fetch_assoc($resultado)) {
    $fila = [$r['creado_en']];
    foreach ($claves as $c) {
        $v = $r[$c];
        if ($c === 'resolvio' && $v !== null) $v = $CALIDAD_RESOLVIO[$v] ?? $v;
        $fila[] = $v;
    }
    fputcsv($salida, $fila);
}

fclose($salida);
exit;
