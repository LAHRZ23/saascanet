<?php
// Marca un aviso de la pantalla de inicio (evento de hoy / curso por vencer)
// como ya visto para esta sesión, para que deje de aparecer una vez que la
// persona le dio clic a "Ir a evento(s)" o "Ver cursos". Los avisos de
// encuestas (beta y calidad) no pasan por aquí: se quedan hasta que se
// contesten, que es su comportamiento correcto.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$tipo = $_POST['tipo'] ?? '';
$permitidos = ['eventos_hoy', 'cursos_hoy', 'cursos_pronto', 'comunicados_urgentes'];

if (in_array($tipo, $permitidos, true)) {
    $_SESSION['aviso_visto_' . $tipo] = true;
}

echo json_encode(['ok' => true]);
