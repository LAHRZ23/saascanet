<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/solicitudes.php';
requiereLogin();
$esAdmin = esAdmin();
$yo = usuarioActual();

// Si se abrió desde "Publicar esto" en Solicitudes, aquí viene la solicitud (solo administradores).
$solPublicar = solicitudCargarParaPublicar($conexion);
$datosSolCal = $solPublicar ? solicitudDatosEncabezado($solPublicar) : [];

$errorCalOficial = '';
$exitoCalOficial = '';

if ($esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_calendario_oficial') {
    $textoCalOficial = trim($_POST['texto'] ?? '');
    $tamañoMaxCal = 5 * 1024 * 1024; // 5 MB
    $nuevoPdfCal = null;

    if ($textoCalOficial === '') {
        $errorCalOficial = 'El texto del botón es obligatorio.';
    } elseif (!empty($_FILES['pdf_calendario']['name'])) {
        $extension = strtolower(pathinfo($_FILES['pdf_calendario']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $errorCalOficial = 'El archivo debe ser un PDF.';
        } elseif ($_FILES['pdf_calendario']['size'] > $tamañoMaxCal) {
            $errorCalOficial = 'El PDF no puede pesar más de 5 MB.';
        } elseif ($_FILES['pdf_calendario']['error'] !== UPLOAD_ERR_OK) {
            $errorCalOficial = 'Ocurrió un error al subir el PDF, intenta de nuevo.';
        } else {
            $nuevoPdfCal = uniqid('calof_', true) . '.pdf';
            if (!move_uploaded_file($_FILES['pdf_calendario']['tmp_name'], __DIR__ . '/uploads/calendario/' . $nuevoPdfCal)) {
                $errorCalOficial = 'No se pudo guardar el PDF.';
                $nuevoPdfCal = null;
            }
        }
    }

    if (!$errorCalOficial) {
        if ($nuevoPdfCal) {
            $anterior = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT pdf FROM calendario_oficial_config WHERE id = 1"));
            $stmt = mysqli_prepare($conexion, "UPDATE calendario_oficial_config SET texto = ?, pdf = ?, actualizado_por = ? WHERE id = 1");
            mysqli_stmt_bind_param($stmt, "ssi", $textoCalOficial, $nuevoPdfCal, $yo['id']);
            mysqli_stmt_execute($stmt);
            if (!empty($anterior['pdf'])) @unlink(__DIR__ . '/uploads/calendario/' . $anterior['pdf']);
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE calendario_oficial_config SET texto = ?, actualizado_por = ? WHERE id = 1");
            mysqli_stmt_bind_param($stmt, "si", $textoCalOficial, $yo['id']);
            mysqli_stmt_execute($stmt);
        }
        registrarBitacora($conexion, 'Actualizó el botón del calendario oficial', $textoCalOficial);
        $exitoCalOficial = 'Listo, el botón del calendario oficial quedó actualizado.';
    }
}

$calOficial = ['texto' => 'Consulta el calendario del Poder Ejecutivo del Gobierno del Estado de México', 'pdf' => null];
$filaCal = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT texto, pdf FROM calendario_oficial_config WHERE id = 1"));
if ($filaCal) {
    $calOficial = $filaCal;
}

// Calcula el n-ésimo día de la semana de un mes (ej. "primer lunes de febrero").
// $diaIso: 1=lunes ... 7=domingo (formato ISO de PHP).
function nEsimoDiaSemana($año, $mes, $diaIso, $n) {
    $primerDiaMes = (int) (new DateTime("$año-$mes-01"))->format('N');
    $diferencia = ($diaIso - $primerDiaMes + 7) % 7;
    $dia = 1 + $diferencia + ($n - 1) * 7;
    return sprintf('%04d-%02d-%02d', $año, $mes, $dia);
}

// Días de descanso obligatorio en México (Ley Federal del Trabajo, Art. 74),
// calculados automáticamente para que no haya que capturarlos cada año.
function diasAsuetoMexico($año) {
    return [
        sprintf('%04d-01-01', $año) => 'Año Nuevo',
        nEsimoDiaSemana($año, 2, 1, 1) => 'Día de la Constitución',
        nEsimoDiaSemana($año, 3, 1, 3) => 'Natalicio de Benito Juárez',
        sprintf('%04d-05-01', $año) => 'Día del Trabajo',
        sprintf('%04d-09-16', $año) => 'Día de la Independencia',
        nEsimoDiaSemana($año, 11, 1, 3) => 'Día de la Revolución Mexicana',
        sprintf('%04d-12-25', $año) => 'Navidad',
    ];
}

// Un mismo día puede tener varios eventos, así que cada día guarda un arreglo.
$eventos = [];
$resultado = mysqli_query($conexion, "SELECT c.fecha, c.titulo, c.tipo, e.id AS evento_id
    FROM calendario_eventos c
    LEFT JOIN eventos_programados e ON e.id = c.evento_id AND e.vigente = 1
    ORDER BY c.id ASC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    // Misma llave que usaba el calendario anterior: "año-mes(0-indexado)-día"
    $partes = explode('-', $fila['fecha']);
    $llave = ((int) $partes[0]) . '-' . ((int) $partes[1] - 1) . '-' . ((int) $partes[2]);
    $eventos[$llave][] = ['titulo' => $fila['titulo'], 'tipo' => $fila['tipo'], 'evento_id' => $fila['evento_id'] ? (int) $fila['evento_id'] : null];
}

// Agrega los días de asueto automáticos (sin pisar lo que el admin ya haya
// capturado manualmente ese mismo día).
for ($año = ((int) date('Y')) - 1; $año <= ((int) date('Y')) + 4; $año++) {
    foreach (diasAsuetoMexico($año) as $fecha => $nombre) {
        $partes = explode('-', $fecha);
        $llave = ((int) $partes[0]) . '-' . ((int) $partes[1] - 1) . '-' . ((int) $partes[2]);
        if (!isset($eventos[$llave])) {
            $eventos[$llave] = [['titulo' => $nombre, 'tipo' => 'nolaboral', 'evento_id' => null]];
        }
    }
}

// Cumpleaños (decorativos, sin ligar a cuentas): se repiten cada año, se
// suman a lo que ya haya ese día en vez de reemplazarlo.
$cumpleanos = [];
$resultado = mysqli_query($conexion, "SELECT nombre, area, dia, mes FROM cumpleanos WHERE activo = 1");
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $cumpleanos[] = $fila;
    }
}
for ($año = ((int) date('Y')) - 1; $año <= ((int) date('Y')) + 4; $año++) {
    foreach ($cumpleanos as $c) {
        $llave = $año . '-' . ((int) $c['mes'] - 1) . '-' . (int) $c['dia'];
        $titulo = $c['nombre'] . (!empty($c['area']) ? ' — ' . $c['area'] : '');
        $eventos[$llave][] = ['titulo' => $titulo, 'tipo' => 'cumpleanos', 'evento_id' => null];
    }
}

// Notas personales del usuario en sesión (agenda privada, separada por
// completo del calendario institucional de arriba). Solo se leen las suyas.
// Hasta 3 por día, cada una con su propio id para poder borrarlas por separado.
$notasPersonales = [];
$stmtNotas = mysqli_prepare($conexion, "SELECT id, fecha, texto, icono FROM calendario_notas_personales WHERE usuario_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($stmtNotas, "i", $yo['id']);
mysqli_stmt_execute($stmtNotas);
$resNotas = mysqli_stmt_get_result($stmtNotas);
while ($fila = mysqli_fetch_assoc($resNotas)) {
    $partes = explode('-', $fila['fecha']);
    $llave = ((int) $partes[0]) . '-' . ((int) $partes[1] - 1) . '-' . ((int) $partes[2]);
    $notasPersonales[$llave][] = ['id' => (int) $fila['id'], 'texto' => $fila['texto'], 'icono' => $fila['icono']];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SAASCAEM - Calendario</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
    <link rel="stylesheet" href="css/calendario.css?v=6">
</head>

<body>

    <div id="nav-placeholder"></div>

    <div class="main-content">
        <h1>📅 Calendario</h1>
        <p style="text-align:center; margin: 16px 0 24px;">
            <a href="<?= $calOficial['pdf'] ? 'uploads/calendario/' . htmlspecialchars($calOficial['pdf']) : 'https://legislacion.edomex.gob.mx/sites/legislacion.edomex.gob.mx/files/files/pdf/gct/2025/diciembre/dic091/dic091f.pdf' ?>" target="_blank" class="btn-secundario">
                🟢 <?= htmlspecialchars($calOficial['texto']) ?>
            </a>
        </p>

        <?php if ($esAdmin): ?>
        <div style="max-width:560px; margin:0 auto 20px;">
            <details>
                <summary style="cursor:pointer; text-align:center; color:var(--text-muted); font-size:13px; font-weight:600;">✏️ Editar botón del calendario oficial</summary>
                <div style="background:var(--card-bg); border-radius:14px; box-shadow:var(--shadow); padding:18px 20px; margin-top:10px;">
                    <?php if ($errorCalOficial): ?>
                        <div class="aviso-importante" style="margin-bottom:12px;"><?= htmlspecialchars($errorCalOficial) ?></div>
                    <?php endif; ?>
                    <?php if ($exitoCalOficial): ?>
                        <div class="aviso-importante" style="margin-bottom:12px;"><?= htmlspecialchars($exitoCalOficial) ?></div>
                    <?php endif; ?>
                    <?php if ($calOficial['pdf']): ?>
                        <p style="font-size:12.5px; color:var(--text-muted); margin-top:0;">📎 PDF actual: <a href="uploads/calendario/<?= htmlspecialchars($calOficial['pdf']) ?>" target="_blank"><?= htmlspecialchars($calOficial['pdf']) ?></a></p>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="actualizar_calendario_oficial">
                        <div class="campo">
                            <label for="cal-oficial-texto">Texto del botón</label>
                            <input id="cal-oficial-texto" type="text" name="texto" value="<?= htmlspecialchars($calOficial['texto']) ?>" required>
                        </div>
                        <div class="campo">
                            <label for="cal-oficial-pdf">Reemplazar PDF (déjalo vacío para conservar el actual)</label>
                            <input id="cal-oficial-pdf" type="file" name="pdf_calendario" accept=".pdf">
                        </div>
                        <button type="submit" class="btn-primario">Guardar</button>
                    </form>
                </div>
            </details>
        </div>
        <?php endif; ?>
        <h3>🟢Este calendario lo ve todo el personal.
            <?php if ($esAdmin): ?>
                <strong style="color: var(--guinda);">Como administrador puedes dar clic en cualquier día para agregar o quitar un evento.</strong>
            <?php else: ?>
                Solo el administrador puede agregar o editar los eventos oficiales, pero tú también puedes dar clic en cualquier día para agregar tus propias notas personales (hasta 3 por día, solo tú las ves).
            <?php endif; ?>
        </h3>

        <?php if (!$esAdmin): ?>
        <p style="font-size:13px; color:var(--text-muted); text-align:center; margin:0 0 14px;">📌 ¿Necesitas agregar un evento oficial? <a href="solicitar-publicacion.php?seccion=calendario">Solicítalo aquí</a> a la Unidad de Apoyo Técnico e Informática (UATI). ¿Quieres que aparezca un cumpleaños? <a href="solicitar-cumpleanos.php">Solicítalo aquí</a>.</p>
        <p style="text-align:center; margin:0 0 20px;">
            <button type="button" onclick="borrarNotasVencidas()" class="btn-secundario" style="border:none; cursor:pointer;">🗑️ Borrar mis notas personales vencidas</button>
        </p>
        <?php endif; ?>

        <?php if ($solPublicar): ?>
            <div id="aviso-solicitud-calendario" style="max-width:600px; margin:0 auto;">
                <?php solicitudMostrarBanner($solPublicar, !empty($datosSolCal['fecha_calendario'])
                    ? 'Se abrió el día que pidió con el texto ya escrito. Revísalo y da clic en <strong>Guardar</strong>: la solicitud queda como publicada. Si ese día ya tenía una nota, la nueva se agrega a continuación.'
                    : 'Esta solicitud no trae el día del calendario. Da clic en el día que corresponda, escribe la nota (puedes copiar el texto de arriba) y guarda; luego márcala como aceptada en Solicitudes.'); ?>
            </div>
        <?php endif; ?>

        <?php if ($esAdmin): ?>
        <div style="max-width:560px; margin:0 auto 24px; background:var(--card-bg); border-radius:16px; box-shadow:var(--shadow);">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px; cursor:pointer;" onclick="toggleCajaVacaciones()">
                <h3 style="margin:0; color:var(--guinda);">🏖️ Vacaciones por rango de días</h3>
                <span id="rango-vacaciones-flecha" style="color:var(--guinda); font-size:16px;">▸ Mostrar</span>
            </div>
            <div id="rango-vacaciones-contenido" style="padding:0 24px 20px; display:none;">
                <p style="font-size:13px; color:var(--text-muted); margin-top:-6px;">Puedes poner varios rangos a lo largo del año (por ejemplo vacaciones de verano y de invierno), uno tras otro.</p>

                <h4 style="margin:14px 0 6px; font-size:14px; color:var(--guinda);">➕ Agregar rango (o un solo día)</h4>
                <label style="font-size:12.5px; font-weight:600; color:var(--text-muted); display:flex; align-items:center; gap:6px; margin:0 0 10px;">
                    <input type="checkbox" id="rango-un-dia" onchange="toggleUnSoloDia()"> Es un solo día (no un rango)
                </label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="campo" style="flex:1; min-width:140px;">
                        <label for="rango-inicio" id="rango-inicio-label">Desde</label>
                        <input id="rango-inicio" type="date">
                    </div>
                    <div class="campo" style="flex:1; min-width:140px;" id="rango-fin-wrap">
                        <label for="rango-fin">Hasta</label>
                        <input id="rango-fin" type="date">
                    </div>
                </div>
                <div class="campo">
                    <label for="rango-titulo">Etiqueta (opcional)</label>
                    <input id="rango-titulo" type="text" placeholder="Vacaciones">
                </div>
                <button type="button" onclick="guardarRangoVacaciones()" class="btn-primario">Marcar como vacaciones</button>
                <div id="rango-mensaje" style="margin-top:10px; font-size:13px;"></div>

                <h4 style="margin:22px 0 6px; font-size:14px; color:var(--guinda);">📋 Vacaciones ya puestas</h4>
                <div id="lista-vacaciones"></div>

                <h4 style="margin:22px 0 6px; font-size:14px; color:var(--guinda);">🗑 Quitar un rango manualmente</h4>
                <p style="font-size:12px; color:var(--text-muted); margin-top:-6px;">Úsalo si necesitas quitar solo una parte de un rango de la lista de arriba.</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="campo" style="flex:1; min-width:140px;">
                        <label for="quitar-rango-inicio">Desde</label>
                        <input id="quitar-rango-inicio" type="date">
                    </div>
                    <div class="campo" style="flex:1; min-width:140px;">
                        <label for="quitar-rango-fin">Hasta</label>
                        <input id="quitar-rango-fin" type="date">
                    </div>
                </div>
                <button type="button" onclick="quitarRangoVacaciones()" class="btn-secundario" style="border:none; cursor:pointer;">Quitar vacaciones de ese rango</button>
                <div id="quitar-rango-mensaje" style="margin-top:10px; font-size:13px;"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="calendario-container" id="contenedor-calendario">
            <div class="mes-controles">
                <button onclick="cambiarMes(-1)">⬅</button>
                <h2 id="mes-anio"></h2>
                <button onclick="cambiarMes(1)">➡</button>
            </div>
            <div class="calendario" id="calendario"></div>
        </div>

        <?php if ($esAdmin): ?>
        <!-- Modal (solo lo usa el administrador) -->
        <div class="modal" id="modal">
            <div class="modal-contenido">
                <span class="cerrar" onclick="cerrarModal()">&times;</span>
                <h3>Agregar evento rápido</h3>
                <p id="modal-dia"></p>
                <div id="modal-lista-eventos"></div>
                <textarea id="evento-texto" placeholder="Escribe una nota para este día... (déjala vacía para borrarla)"></textarea>
                <select id="evento-tipo" style="width:100%; padding:8px; border-radius:8px; margin-bottom:10px;">
                    <option value="normal">Evento normal</option>
                    <option value="vacaciones">🏖️ Vacaciones</option>
                    <option value="nolaboral">⛔ Día no laboral</option>
                </select>
                <button onclick="guardarEvento()">Guardar</button>
                <a id="modal-link-eventos" href="admin-eventos.php" class="btn-secundario" style="display:block; margin-top:10px; text-decoration:none;">📋 Agregar evento completo desde Eventos</a>
                <a href="eventos.php" class="btn-secundario" style="display:block; margin-top:8px; text-decoration:none;">📅 Ir a Eventos</a>
            </div>
        </div>
        <?php else: ?>
        <!-- Popup de solo consulta para el personal estándar -->
        <div class="popup-overlay" id="modal-consulta" onclick="if(event.target===this) cerrarConsulta()">
            <div class="popup-box" onclick="event.stopPropagation()">
                <button class="popup-close" onclick="cerrarConsulta()">✖</button>
                <p id="consulta-dia" style="color:var(--text-muted); font-size:13px; margin-top:0;"></p>
                <div id="consulta-lista"></div>

                <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
                <h4 style="margin:0 0 4px; color:var(--guinda); font-size:14px;">🔒 Personal</h4>
                <p style="font-size:12px; color:var(--text-muted); margin:0 0 10px;">Tu agenda privada de este día (hasta 3) — solo tú la ves, no la ve nadie más.</p>
                <div id="lista-notas-personales" style="margin-bottom:10px;"></div>
                <div id="form-nota-personal">
                    <select id="nota-personal-icono" style="width:100%; padding:9px 10px; border-radius:8px; border:1px solid var(--border); background:var(--bg); color:var(--text); margin-bottom:8px;">
                        <option value="📌">📌 Otro</option>
                        <option value="🤝">🤝 Reunión</option>
                        <option value="📋">📋 Junta</option>
                        <option value="💼">💼 Trabajo</option>
                        <option value="🏠">🏠 Personal</option>
                    </select>
                    <input id="nota-personal-texto" type="text" maxlength="255" placeholder="Ej. Reunión con el área de..." style="width:100%; padding:9px 10px; border-radius:8px; border:1px solid var(--border); background:var(--bg); color:var(--text); margin-bottom:8px;">
                    <button type="button" onclick="guardarNotaPersonal()" class="btn-primario">Agregar nota</button>
                </div>
                <p id="nota-personal-limite" style="display:none; font-size:12.5px; color:var(--text-muted); margin:0;">Ya tienes el máximo de 3 notas este día. Borra alguna para agregar otra.</p>

                <a href="eventos.php" class="btn-secundario" style="display:block; margin-top:14px; text-decoration:none;">📅 Ir a Eventos</a>
            </div>
        </div>
        <?php endif; ?>

        <br>

        <script src="js/nav.js?v=3"></script>
        <script src="js/nav-loader.js?v=30"></script>
        <script>
            const eventosDB = <?= json_encode($eventos, JSON_UNESCAPED_UNICODE) ?>;
            const notasPersonalesDB = <?= json_encode($notasPersonales, JSON_UNESCAPED_UNICODE) ?>;
            const esAdminCalendario = <?= $esAdmin ? 'true' : 'false' ?>;
        </script>
        <script src="js/calendario.js?v=15"></script>
        <script>
            generarCalendario();
            <?php if ($solPublicar && !empty($datosSolCal['fecha_calendario'])): ?>
            // Desde "Publicar esto": se abre el día pedido con el texto de la solicitud ya escrito.
            (function () {
                window.solicitudCalendarioId = <?= (int) $solPublicar['id'] ?>;
                const partes = <?= json_encode($datosSolCal['fecha_calendario']) ?>.split('-').map(Number);
                fechaActual = new Date(partes[0], partes[1] - 1, 1);
                generarCalendario();
                abrirModal(partes[0] + '-' + (partes[1] - 1) + '-' + partes[2], partes[2], partes[0], partes[1] - 1);
                const caja = document.getElementById('evento-texto');
                const pedido = <?= json_encode($solPublicar['titulo'], JSON_UNESCAPED_UNICODE) ?>;
                caja.value = caja.value.trim() === '' ? pedido : caja.value.trim() + ' — ' + pedido;
            })();
            <?php elseif ($solPublicar): ?>
            window.solicitudCalendarioId = <?= (int) $solPublicar['id'] ?>;
            <?php endif; ?>
            actualizarListaVacaciones();
            iniciarValidacionRangos();
        </script>
</body>

</html>
