<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();

$extra = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM pagina_extra WHERE id = 1"));
$activa = $extra && $extra['activa'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - <?= $activa ? htmlspecialchars($extra['titulo']) : 'Página adicional' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <?php if (!$activa): ?>
        <h1>📄 Página adicional</h1>
        <p style="color:var(--text-muted);">Esta página no está activa por el momento.</p>
    <?php else: ?>
        <h1>📄 <?= htmlspecialchars($extra['titulo']) ?></h1>

        <?php if (!empty($extra['texto'])): ?>
            <p style="max-width:700px; white-space:pre-line;"><?= htmlspecialchars($extra['texto']) ?></p>
        <?php endif; ?>

        <?php if (!empty($extra['video'])): ?>
            <video controls style="width:100%; max-width:700px; border-radius:10px; margin:16px 0; display:block;" src="uploads/pagina_extra/<?= htmlspecialchars($extra['video']) ?>"></video>
        <?php endif; ?>

        <?php if (!empty($extra['imagen1']) || !empty($extra['imagen2'])): ?>
            <div style="display:flex; gap:20px; flex-wrap:wrap; margin:16px 0;">
                <?php if (!empty($extra['imagen1'])): ?>
                    <img src="uploads/pagina_extra/<?= htmlspecialchars($extra['imagen1']) ?>" alt="" class="imagen-extra-clic" style="max-width:600px; width:100%; border-radius:10px; cursor:zoom-in;" onclick="abrirImagenGrande(this.src)">
                <?php endif; ?>
                <?php if (!empty($extra['imagen2'])): ?>
                    <img src="uploads/pagina_extra/<?= htmlspecialchars($extra['imagen2']) ?>" alt="" class="imagen-extra-clic" style="max-width:600px; width:100%; border-radius:10px; cursor:zoom-in;" onclick="abrirImagenGrande(this.src)">
                <?php endif; ?>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin-top:-10px;">🔍 Da clic en una imagen para verla más grande.</p>
        <?php endif; ?>

        <?php if (!empty($extra['enlace1_url']) || !empty($extra['enlace2_url'])): ?>
            <p style="display:flex; gap:10px; flex-wrap:wrap; margin-top:20px;">
                <?php if (!empty($extra['enlace1_url'])): ?>
                    <a href="<?= htmlspecialchars($extra['enlace1_url']) ?>" class="btn-accion" target="_blank"><?= htmlspecialchars($extra['enlace1_texto'] ?: 'Ver más') ?></a>
                <?php endif; ?>
                <?php if (!empty($extra['enlace2_url'])): ?>
                    <a href="<?= htmlspecialchars($extra['enlace2_url']) ?>" class="btn-accion" target="_blank"><?= htmlspecialchars($extra['enlace2_texto'] ?: 'Ver más') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (esAdmin()): ?>
        <p style="margin-top:30px;"><a href="admin-pagina-extra.php">✏️ Editar esta página</a></p>
    <?php endif; ?>
</div>

<?php if ($activa && (!empty($extra['imagen1']) || !empty($extra['imagen2']))): ?>
<div class="popup-overlay" id="lightbox-extra" onclick="cerrarImagenGrande()">
    <button class="popup-close" onclick="cerrarImagenGrande()">✖</button>
    <img id="lightbox-extra-img" src="" alt="" style="max-width:92vw; max-height:92vh; width:auto; border-radius:10px; object-fit:contain;">
</div>
<script>
    function abrirImagenGrande(src) {
        document.getElementById('lightbox-extra-img').src = src;
        document.getElementById('lightbox-extra').style.display = 'flex';
    }
    function cerrarImagenGrande() {
        document.getElementById('lightbox-extra').style.display = 'none';
    }
</script>
<?php endif; ?>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
