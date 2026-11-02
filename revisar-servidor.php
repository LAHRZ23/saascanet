<?php
// Página temporal para revisar si un servidor cumple lo que necesita la intranet.
// IMPORTANTE: borrarla del servidor en cuanto se termine de revisar.
function enBytes($valor) {
    $valor = trim($valor);
    $n = (float) $valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

$version = PHP_VERSION;
$mysqlVersion = null;
if (function_exists('mysqli_connect') && file_exists(__DIR__ . '/con_db.php')) {
    require __DIR__ . '/con_db.php';
    if (isset($conexion) && $conexion) {
        $mysqlVersion = mysqli_get_server_info($conexion);
    }
}

$espacioLibre = @disk_free_space(__DIR__);
$revisiones = [
    ['PHP 7.4 o más nuevo', 'Versión encontrada: ' . $version, version_compare($version, '7.4.0', '>=')],
    ['Extensión mysqli (conexión a la base de datos)', extension_loaded('mysqli') ? 'Activa' : 'No está activa', extension_loaded('mysqli')],
    ['Extensión mbstring (acentos y ñ)', extension_loaded('mbstring') ? 'Activa' : 'No está activa', extension_loaded('mbstring')],
    ['Subida de archivos de 40 MB o más (upload_max_filesize)', 'Valor actual: ' . ini_get('upload_max_filesize'), enBytes(ini_get('upload_max_filesize')) >= 40 * 1048576],
    ['Envío de formularios de 40 MB o más (post_max_size)', 'Valor actual: ' . ini_get('post_max_size'), enBytes(ini_get('post_max_size')) >= 40 * 1048576],
    ['Carpeta uploads con permiso de escritura', is_writable(__DIR__ . '/uploads') ? 'Sí se puede escribir' : 'No se puede escribir (o no existe)', is_writable(__DIR__ . '/uploads')],
];
if ($espacioLibre !== false) {
    $revisiones[] = ['Espacio libre en disco (mínimo 1 GB recomendado)', 'Libre: ' . round($espacioLibre / 1073741824, 1) . ' GB', $espacioLibre >= 1073741824];
}
$revisiones[] = ['Base de datos MySQL 5.7+ / MariaDB 10.3+', $mysqlVersion ? 'Versión encontrada: ' . $mysqlVersion : 'No se pudo conectar todavía (normal si aún no se configura con_db.php)', $mysqlVersion === null ? null : true];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Revisión del servidor</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 720px; margin: 30px auto; padding: 0 16px; color: #222; }
        h1 { color: #7C1F3D; }
        .fila { display: flex; gap: 12px; align-items: center; padding: 12px 14px; margin: 8px 0; border-radius: 10px; border: 1px solid #ddd; }
        .ok { background: #e8f5ec; border-color: #7fbf94; }
        .mal { background: #fdeaea; border-color: #d98a8a; }
        .nd { background: #fff8e1; border-color: #e0c56b; }
        .icono { font-size: 22px; }
        small { color: #555; display: block; }
        .aviso { margin-top: 24px; padding: 14px; background: #fff3cd; border-radius: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Revisión del servidor</h1>
    <p>Esto es lo que necesita la intranet SAASCANET para funcionar:</p>
    <?php foreach ($revisiones as $rev): $titulo = $rev[0]; $detalle = $rev[1]; $ok = $rev[2]; ?>
        <div class="fila <?= $ok === null ? 'nd' : ($ok ? 'ok' : 'mal') ?>">
            <span class="icono"><?= $ok === null ? '❔' : ($ok ? '✅' : '❌') ?></span>
            <div><strong><?= htmlspecialchars($titulo) ?></strong><small><?= htmlspecialchars($detalle) ?></small></div>
        </div>
    <?php endforeach; ?>
    <div class="aviso">⚠️ Cuando termines de revisar, borra este archivo (revisar-servidor.php) del servidor: muestra datos internos.</div>
</body>
</html>
