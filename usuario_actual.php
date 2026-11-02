<?php
// Endpoint que consulta nav-loader.js para mostrar en la barra lateral
// el nombre real de quien tiene la sesión abierta en ese equipo, y para
// saber si la página adicional (pagina-extra.php) está activa ahora mismo.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/temas.php';
header('Content-Type: application/json; charset=utf-8');

$yo = usuarioActual();
if (!$yo) {
    echo json_encode(['nombre' => null, 'paginaExtra' => ['activa' => false]]);
    exit;
}

$area = null;
$stmtArea = mysqli_prepare($conexion, "SELECT area FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmtArea, "i", $yo['id']);
mysqli_stmt_execute($stmtArea);
$filaArea = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtArea));
if ($filaArea && !empty($filaArea['area'])) {
    $area = $filaArea['area'];
}

$paginaExtra = ['activa' => false, 'titulo' => null];
$resultadoExtra = mysqli_query($conexion, "SELECT activa, titulo FROM pagina_extra WHERE id = 1");
if ($resultadoExtra) {
    $extra = mysqli_fetch_assoc($resultadoExtra);
    if ($extra && $extra['activa']) {
        $paginaExtra = ['activa' => true, 'titulo' => $extra['titulo']];
    }
}

$nextcloud = ['activo' => false, 'url' => null];
$resultadoNc = mysqli_query($conexion, "SELECT activo, url FROM nextcloud_config WHERE id = 1");
if ($resultadoNc) {
    $filaNc = mysqli_fetch_assoc($resultadoNc);
    if ($filaNc && $filaNc['activo']) {
        $nextcloud = ['activo' => true, 'url' => $filaNc['url']];
    }
}

$encuestaActiva = false;
$resultadoEnc = mysqli_query($conexion, "SELECT activa FROM encuesta_config WHERE id = 1");
if ($resultadoEnc) {
    $filaEnc = mysqli_fetch_assoc($resultadoEnc);
    $encuestaActiva = $filaEnc && $filaEnc['activa'];
}

// Encuesta de calidad de atención: el botón solo se muestra si hay una ronda
// abierta y esta persona todavía no la ha contestado.
$calidadPendiente = false;
$resultadoRonda = mysqli_query($conexion, "SELECT id FROM calidad_rondas WHERE activa = 1 ORDER BY id DESC LIMIT 1");
if ($resultadoRonda && ($filaRonda = mysqli_fetch_assoc($resultadoRonda))) {
    $stmtCal = mysqli_prepare($conexion, "SELECT 1 FROM calidad_participaciones WHERE ronda_id = ? AND usuario_id = ?");
    mysqli_stmt_bind_param($stmtCal, "ii", $filaRonda['id'], $yo['id']);
    mysqli_stmt_execute($stmtCal);
    $calidadPendiente = !mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCal));
}

// Aviso del tema activo (icono + mensaje), solo en fiestas y conmemoraciones
// de verdad (Independencia, Día de Muertos, Navidad, Mes rosa, Día Naranja).
// En el tema institucional y en los neutros (azul, verde, morado, gris) no
// se manda nada, para no meter ruido en el día a día normal.
$temaAviso = null;
$resultadoTema = mysqli_query($conexion, "SELECT tema FROM tema_config WHERE id = 1");
if ($resultadoTema) {
    $filaTema = mysqli_fetch_assoc($resultadoTema);
    $claveTema = $filaTema['tema'] ?? 'institucional';
    if (isset($TEMAS[$claveTema]) && $TEMAS[$claveTema]['saludo']) {
        $temaAviso = ['icono' => $TEMAS[$claveTema]['icono'], 'texto' => $TEMAS[$claveTema]['saludo']];
    }
}

echo json_encode(['nombre' => $yo['nombre'], 'rol' => $yo['rol'], 'area' => $area, 'paginaExtra' => $paginaExtra, 'nextcloud' => $nextcloud, 'encuesta' => (bool) $encuestaActiva, 'calidad' => $calidadPendiente, 'temaAviso' => $temaAviso], JSON_UNESCAPED_UNICODE);
