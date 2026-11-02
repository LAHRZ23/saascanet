<?php
// Hoja de estilo dinámica: sobreescribe los colores base (:root) según el
// tema activo. Se carga después de css/style.css en cada página, así que
// sus valores ganan. Ver includes/temas.php para editar o agregar temas.
require_once __DIR__ . '/includes/temas.php';
require_once __DIR__ . '/con_db.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-store');

$clave = 'institucional';
$resultado = mysqli_query($conexion, "SELECT tema FROM tema_config WHERE id = 1");
if ($resultado) {
    $fila = mysqli_fetch_assoc($resultado);
    if ($fila && isset($TEMAS[$fila['tema']])) {
        $clave = $fila['tema'];
    }
}

echo ":root {\n";
foreach ($TEMAS[$clave]['vars'] as $variable => $valor) {
    echo "  $variable: $valor;\n";
}
echo "}\n";
