<?php
// Que los errores de MySQL regresen como "false" (para poder revisarlos con
// if/else en cada página) en vez de tumbar todo el sitio con un error fatal.
mysqli_report(MYSQLI_REPORT_OFF);

// Las credenciales viven en config.php (no se sube a GitHub).
// Copia config.example.php como config.php y pon tus datos.
$config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

$conexion = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if (!$conexion) {
    die("No se pudo conectar a la base de datos. Verifica que Apache y MySQL estén corriendo en XAMPP.");
}
mysqli_set_charset($conexion, "utf8mb4");
