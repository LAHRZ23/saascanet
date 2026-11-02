<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();

// Íconos conocidos por nombre de unidad (si agregas una unidad nueva desde
// el panel de administración y no está en esta lista, usa un ícono genérico).
$iconosUnidad = [
    'Dirección General' => '🏢',
    'Unidad de Apoyo Administrativo' => '📈',
    'Unidad Jurídica y de Igualdad e Género' => '👩‍⚖️',
    'Unidad de Apoyo Técnico e Informático' => '🖥️',
    'Contraloría' => '📋',
    'Dirección de Operación' => '📊',
    'Dirección de Proyectos y Control de Obras' => '👷',
    'Vigilancia' => '👮',
];

$unidades = [];
$resultado = mysqli_query($conexion, "SELECT * FROM directorio_unidades ORDER BY orden, nombre");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $fila['contactos'] = [];
    $unidades[$fila['id']] = $fila;
}

$resultado = mysqli_query($conexion, "SELECT * FROM directorio_contactos ORDER BY orden, id");
while ($fila = mysqli_fetch_assoc($resultado)) {
    if (isset($unidades[$fila['unidad_id']])) {
        $unidades[$fila['unidad_id']]['contactos'][] = $fila;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Directorio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
    <link rel="stylesheet" href="css/directorio.css?v=2">
</head>
<body>

    <div id="nav-placeholder"></div>
    <div class="main-content">
        <h1>☎️ Directorio</h1>
        <h3>🟢Usa los <strong>botones de navegación rápida</strong> o el buscador para encontrar a quién necesitas.</h3>
        <button id="btnArriba" onclick="subir()">🔝</button>

        <?php if (esAdmin()): ?>
            <p><a href="admin-directorio.php" class="btn-accion" style="display:inline-block; margin-bottom:16px;">✏️ Editar Directorio</a></p>
        <?php else: ?>
            <p style="font-size:13px; color:var(--text-muted); margin:0 0 16px;">📌 ¿Necesitas agregar o corregir un contacto del directorio? <a href="solicitar-publicacion.php?seccion=directorio">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI).</p>
        <?php endif; ?>

        <div class="campo" style="max-width:420px; margin: 16px auto;">
            <label for="buscar-directorio">🔎 Buscar por nombre, puesto, correo o extensión</label>
            <input id="buscar-directorio" type="text" placeholder="Ej. Informática, 122, jurídico…">
        </div>
        <p id="sin-resultados-directorio" style="display:none; text-align:center; color:var(--text-muted);">No se encontraron resultados.</p>

        <div class="accesos-rapidos">
            <?php foreach ($unidades as $u): ?>
                <button onclick="irA('unidad-<?= $u['id'] ?>')"><?= $iconosUnidad[$u['nombre']] ?? '🏛️' ?> <?= htmlspecialchars($u['nombre']) ?></button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($unidades as $u): ?>
            <section class="unidad" id="unidad-<?= $u['id'] ?>">
                <h2><?= $iconosUnidad[$u['nombre']] ?? '🏛️' ?> <?= htmlspecialchars($u['nombre']) ?></h2>
                <div class="tarjetas">
                    <?php foreach ($u['contactos'] as $c): ?>
                        <div class="tarjeta">
                            <h3><?= htmlspecialchars($c['puesto']) ?></h3>
                            <?php if (!empty($c['extension'])): ?>
                                <p>📞 <strong>Extensión:</strong> <?= htmlspecialchars($c['extension']) ?></p>
                            <?php endif; ?>
                            <?php
                            // Si una unidad tiene dos o más correos (separados por coma, punto y coma o espacio), cada uno lleva su propio botón de copiar.
                            $correosLista = array_values(array_filter(preg_split('/[\s,;]+/', (string) ($c['correo'] ?? ''))));
                            foreach ($correosLista as $i => $correoItem): ?>
                                <p class="correo-linea">
                                    ✉️
                                    <span class="correo-texto"><strong><?= $i === 0 ? 'Correo:' : 'Correo ' . ($i + 1) . ':' ?></strong> <?= htmlspecialchars($correoItem) ?></span>
                                    <button type="button" class="btn-copiar" data-correo="<?= htmlspecialchars($correoItem) ?>" title="Copiar correo">📋</button>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($u['contactos'])): ?>
                        <p style="color:var(--text-muted); font-size:13px;">Sin contactos registrados todavía.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (empty($unidades)): ?>
            <p>Aún no hay unidades registradas en el directorio.</p>
        <?php endif; ?>

    <script src="js/nav.js?v=3"></script>
    <script src="js/nav-loader.js?v=30"></script>
    <script>
        document.getElementById('buscar-directorio').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let algunaVisibleEnTotal = false;
            document.querySelectorAll('.unidad').forEach(unidad => {
                let algunaVisible = false;
                unidad.querySelectorAll('.tarjeta').forEach(tarjeta => {
                    const coincide = !q || tarjeta.textContent.toLowerCase().includes(q);
                    tarjeta.style.display = coincide ? '' : 'none';
                    if (coincide) algunaVisible = true;
                });
                const titulo = unidad.querySelector('h2');
                const tituloCoincide = titulo && titulo.textContent.toLowerCase().includes(q);
                unidad.style.display = (algunaVisible || tituloCoincide || !q) ? '' : 'none';
                if (algunaVisible || tituloCoincide || !q) algunaVisibleEnTotal = true;
            });
            document.getElementById('sin-resultados-directorio').style.display = (q && !algunaVisibleEnTotal) ? 'block' : 'none';
        });

        // navigator.clipboard solo existe en HTTPS o localhost; en la red interna (http://IP) se usa el método clásico.
        function copiarTexto(texto) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(texto);
            }
            return new Promise((resolver, rechazar) => {
                const campo = document.createElement('textarea');
                campo.value = texto;
                campo.setAttribute('readonly', '');
                campo.style.position = 'fixed';
                campo.style.opacity = '0';
                document.body.appendChild(campo);
                campo.select();
                let copiado = false;
                try { copiado = document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(campo);
                copiado ? resolver() : rechazar();
            });
        }

        document.querySelectorAll('.btn-copiar').forEach(boton => {
            boton.addEventListener('click', function () {
                const correo = this.dataset.correo;
                copiarTexto(correo).then(() => {
                    const original = this.textContent;
                    this.textContent = '✅';
                    setTimeout(() => { this.textContent = original; }, 1500);
                }).catch(() => alert('No se pudo copiar. Copia el correo manualmente: ' + correo));
            });
        });
    </script>
    </div>
</body>
</html>
