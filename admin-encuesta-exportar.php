<?php
// Descarga todas las respuestas de la encuesta beta como CSV (se abre en Excel).
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();

$nombres = ['sugerencia' => 'Sugerencia', 'cambio' => 'Cambio', 'opinion' => 'Opinión', 'error' => 'Falla'];

$resultado = mysqli_query($conexion, "SELECT e.*, u.nombre AS usuario_nombre, u.area AS usuario_area
    FROM encuesta_respuestas e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    ORDER BY e.creado_en DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="encuesta_beta_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Fecha', 'Tipo', 'Sección', 'Calificación', 'Comentario', 'Nombre', 'Área', 'Estado']);

while ($r = mysqli_fetch_assoc($resultado)) {
    fputcsv($salida, [
        $r['creado_en'],
        $nombres[$r['tipo']] ?? $r['tipo'],
        $r['seccion'],
        $r['valoracion'],
        $r['mensaje'],
        $r['usuario_id'] ? $r['usuario_nombre'] : 'Anónimo',
        $r['usuario_id'] ? $r['usuario_area'] : '',
        $r['estado'] === 'nueva' ? 'Sin revisar' : 'Revisada',
    ]);
}

fclose($salida);
exit;
