<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$exito = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar') {
    mysqli_query($conexion, "DELETE FROM bitacora");
    registrarBitacora($conexion, 'Vació la bitácora completa');
    $exito = 'Se vació la bitácora. Este registro es lo único que queda, para dejar constancia de quién y cuándo la vació.';
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 50;
$desde = ($pagina - 1) * $porPagina;

$totalResultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM bitacora");
$total = (int) mysqli_fetch_assoc($totalResultado)['total'];
$totalPaginas = max(1, (int) ceil($total / $porPagina));

$registros = [];
$stmt = mysqli_prepare($conexion, "SELECT b.*, u.nombre AS usuario_nombre
    FROM bitacora b
    LEFT JOIN usuarios u ON u.id = b.usuario_id
    ORDER BY b.creado_en DESC
    LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, "ii", $porPagina, $desde);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) {
    $registros[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Bitácora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>📖 Bitácora de actividad</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a></p>
    <p style="color:var(--text-muted); max-width:600px;">Registro de las acciones de administración: quién publicó, editó o eliminó algo, y cuándo. Solo lo pueden ver los administradores.</p>

    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <p style="display:flex; gap:14px; flex-wrap:wrap; align-items:center; margin-bottom:20px;">
        <a href="admin-bitacora-exportar.php" class="btn-secundario">⬇️ Descargar (CSV)</a>
        <?php if ($total > 0): ?>
            <form method="post" onsubmit="return confirm('¿Vaciar TODA la bitácora? No se puede deshacer. Si quieres conservarla, descarga primero el CSV.');">
                <input type="hidden" name="accion" value="vaciar">
                <button type="submit" class="btn-peligro">🗑 Vaciar bitácora</button>
            </form>
        <?php endif; ?>
    </p>

    <?php if (empty($registros)): ?>
        <p>Todavía no hay actividad registrada.</p>
    <?php endif; ?>

    <div style="max-width:800px;">
        <?php foreach ($registros as $r): ?>
            <div class="tarjeta" style="width:100%; margin-bottom:10px; display:flex; justify-content:space-between; gap:12px; align-items:baseline; flex-wrap:wrap;">
                <div>
                    <strong style="color:var(--guinda);"><?= htmlspecialchars($r['usuario_nombre'] ?? 'Usuario eliminado') ?></strong>
                    — <?= htmlspecialchars($r['accion']) ?>
                    <?php if (!empty($r['detalle'])): ?>
                        <span style="color:var(--text-muted);">(<?= htmlspecialchars($r['detalle']) ?>)</span>
                    <?php endif; ?>
                </div>
                <span class="mono" style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($r['creado_en'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <div style="display:flex; gap:10px; margin-top:20px; align-items:center;">
            <?php if ($pagina > 1): ?>
                <a href="admin-bitacora.php?pagina=<?= $pagina - 1 ?>" class="btn-secundario">← Más recientes</a>
            <?php endif; ?>
            <span style="font-size:13px; color:var(--text-muted);">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="admin-bitacora.php?pagina=<?= $pagina + 1 ?>" class="btn-secundario">Más antiguas →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
