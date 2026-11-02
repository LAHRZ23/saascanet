<?php
// Descarga el resumen del reporte de Mesa de Servicios (no los tickets uno
// por uno, para eso ya existe admin-tickets-exportar.php). Acepta
// ?desde=AAAA-MM-DD&hasta=AAAA-MM-DD para un periodo (bimestre, trimestre...).
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/reporte_tickets.php';
requiereLogin();
requiereAdmin();

list($desde, $hasta) = rangoDesdeGet();
$rep = reporteTickets($conexion, $desde, $hasta);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_mesa_servicios' . rangoSufijoArchivo($desde, $hasta) . '_' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM para acentos en Excel

fputcsv($salida, ['Reporte de Mesa de Servicios', 'Generado el ' . date('d/m/Y H:i')]);
fputcsv($salida, ['Periodo', rangoEtiqueta($desde, $hasta)]);
fputcsv($salida, ['Criterio', 'Tickets registrados dentro del periodo']);
fputcsv($salida, []);

fputcsv($salida, ['Resumen general']);
fputcsv($salida, ['Total de tickets', $rep['total']]);
fputcsv($salida, ['Nuevos', $rep['porEstatus']['nuevo']]);
fputcsv($salida, ['En proceso', $rep['porEstatus']['en_proceso']]);
fputcsv($salida, ['Resueltos', $rep['porEstatus']['resuelto']]);
fputcsv($salida, ['No procede', $rep['porEstatus']['no_procede']]);
fputcsv($salida, ['Días considerados', $rep['dias']]);
fputcsv($salida, ['Promedio diario', $rep['promedioDiario']]);
fputcsv($salida, ['Promedio semanal', $rep['promedioSemanal']]);
fputcsv($salida, []);

fputcsv($salida, ['Tickets por tipo']);
fputcsv($salida, ['Tipo', 'Total']);
foreach ($rep['porTipo'] as $t) {
    fputcsv($salida, [$t['tipo'], $t['total']]);
}
fputcsv($salida, []);

fputcsv($salida, ['Tickets por área o unidad']);
fputcsv($salida, ['Área', 'Total']);
foreach ($rep['porArea'] as $a) {
    fputcsv($salida, [$a['area'], $a['total']]);
}
fputcsv($salida, []);

fputcsv($salida, ['Tickets resueltos por administrador']);
if (!empty($rep['compartidos'])) {
    fputcsv($salida, ['Nota', $rep['compartidos'] . ' ticket(s) fueron trabajados por más de una persona: cada ticket cuenta una vez en el total y una vez para cada participante.']);
}
fputcsv($salida, ['Administrador', 'Total']);
foreach ($rep['porAdmin'] as $a) {
    fputcsv($salida, [$a['nombre'], $a['total']]);
}
fputcsv($salida, []);

fputcsv($salida, ['Rechazados (No procede) con nota']);
fputcsv($salida, ['Folio', 'Nombre', 'Área', 'Nota', 'Fecha']);
foreach ($rep['rechazados'] as $r) {
    fputcsv($salida, [$r['folio'], $r['nombre'], $r['area'], $r['nota_admin'] ?? '', $r['resuelto_en']]);
}

fclose($salida);
exit;
