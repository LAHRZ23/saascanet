<?php
// Devuelve los avisos actuales de la pantalla de Bienvenida (comunicados
// urgentes, eventos de hoy, encuestas pendientes, cursos por vencer) en
// JSON, para que home.php los revise cada cierto tiempo sin que la persona
// tenga que refrescar la página — tanto para que aparezcan avisos nuevos
// como para que desaparezcan los que ya no apliquen (p. ej. si el admin
// oculta el comunicado urgente o cierra la encuesta).
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    http_response_code(401);
    echo json_encode(['avisos' => []]);
    exit;
}

$encuestasPendientes = encuestasPendientes($conexion, $yo['id']);
$cursosPorVencer = cursosPorVencer($conexion);
$eventosDeHoy = eventosDeHoy($conexion);
$comunicadosUrgentes = comunicadosUrgentes($conexion, $yo['id']);

$avisos = array_values(array_filter(
    array_merge($comunicadosUrgentes, $eventosDeHoy, $encuestasPendientes, $cursosPorVencer),
    function ($aviso) {
        return empty($aviso['tipo']) || empty($_SESSION['aviso_visto_' . $aviso['tipo']]);
    }
));

echo json_encode(['avisos' => $avisos], JSON_UNESCAPED_UNICODE);
