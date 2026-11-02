<?php
// Endpoint que consulta nav-loader.js para la campana de "Novedades":
// avisa cuando se publica un comunicado, evento, curso o tutorial, cuando
// hay algún cambio en el Directorio, cuando se abre una encuesta, y cuando alguien cumple años hoy.
// Se apoya en la Bitácora (que ya registra estas acciones) en vez de
// consultar cada tabla por separado.
require_once __DIR__ . '/includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuarioActual()) {
    echo json_encode(['items' => [], 'cumpleanosHoy' => []]);
    exit;
}

$accionesRelevantes = [
    'Publicó comunicado',
    'Publicó evento',
    'Agregó curso',
    'Publicó tutorial',
    'Activó la encuesta beta',
    'Abrió encuesta de calidad de atención',
    'Creó unidad del directorio',
    'Eliminó unidad del directorio',
    'Agregó contacto al directorio',
    'Editó contacto del directorio',
    'Eliminó contacto del directorio',
];

$items = [];
$marcadores = implode(',', array_fill(0, count($accionesRelevantes), '?'));
$tipos = str_repeat('s', count($accionesRelevantes));
// Se piden de más porque luego se descartan los avisos de publicaciones que ya
// no existen. Si la bitácora aún no tiene la columna referencia_id (falta el
// SQL 35), se usa la consulta de siempre y se compara por título.
$stmt = @mysqli_prepare($conexion, "SELECT id, accion, detalle, referencia_id, creado_en FROM bitacora WHERE accion IN ($marcadores) ORDER BY id DESC LIMIT 60");
if (!$stmt) {
    $stmt = mysqli_prepare($conexion, "SELECT id, accion, detalle, NULL AS referencia_id, creado_en FROM bitacora WHERE accion IN ($marcadores) ORDER BY id DESC LIMIT 60");
}
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $tipos, ...$accionesRelevantes);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    // Acción de la bitácora => tabla donde vive lo publicado.
    $tablaPorAccion = [
        'Publicó comunicado' => 'comunicados',
        'Publicó evento' => 'eventos_programados',
        'Agregó curso' => 'cursos',
        'Publicó tutorial' => 'tutoriales',
    ];
    while ($fila = mysqli_fetch_assoc($resultado)) {
        if (isset($tablaPorAccion[$fila['accion']])) {
            $tabla = $tablaPorAccion[$fila['accion']];
            if ($fila['referencia_id'] !== null) {
                $q = mysqli_prepare($conexion, "SELECT 1 FROM $tabla WHERE id = ? AND vigente = 1");
                $ref = (int) $fila['referencia_id'];
                mysqli_stmt_bind_param($q, "i", $ref);
            } else {
                // Aviso anterior a que la bitácora guardara el id: se busca por título
                // (el más reciente) para que también lleve directo a su pop-up.
                $q = mysqli_prepare($conexion, "SELECT id FROM $tabla WHERE titulo = ? AND vigente = 1 ORDER BY id DESC LIMIT 1");
                mysqli_stmt_bind_param($q, "s", $fila['detalle']);
            }
            mysqli_stmt_execute($q);
            $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
            if (!$existe) {
                continue; // ya se borró u ocultó: no avisar de algo que no existe
            }
            if ($fila['referencia_id'] === null && isset($existe['id'])) {
                $fila['referencia_id'] = (int) $existe['id'];
            }
        }
        // referencia_id viaja al menú para que la notificación lleve directo a esa publicación.
        $items[] = $fila;
        if (count($items) >= 20) {
            break;
        }
    }
}

$cumpleanosHoy = [];
$stmt2 = mysqli_prepare($conexion, "SELECT nombre, area FROM cumpleanos WHERE activo = 1 AND dia = ? AND mes = ?");
if ($stmt2) {
    $diaHoy = (int) date('j');
    $mesHoy = (int) date('n');
    mysqli_stmt_bind_param($stmt2, "ii", $diaHoy, $mesHoy);
    mysqli_stmt_execute($stmt2);
    $resultado2 = mysqli_stmt_get_result($stmt2);
    while ($fila = mysqli_fetch_assoc($resultado2)) {
        $cumpleanosHoy[] = $fila;
    }
}

echo json_encode(['items' => $items, 'cumpleanosHoy' => $cumpleanosHoy], JSON_UNESCAPED_UNICODE);
