<?php
// Reporte de Mesa de Servicios: se calcula al momento con los datos actuales
// de la tabla de tickets (no se guarda nada), porque en un sistema de este
// tamaño la consulta es instantánea y así el reporte siempre está al día.
// Se puede acotar a un periodo (ej. un bimestre o un trimestre): entonces se
// cuentan los tickets registrados entre esas dos fechas.
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/reporte_tickets.php';
requiereLogin();
requiereAdmin();

list($desde, $hasta) = rangoDesdeGet();
$conDetalle = ($_GET['detalle'] ?? '') === '1';
$rep = reporteTickets($conexion, $desde, $hasta, $conDetalle);
$periodo = rangoEtiqueta($desde, $hasta);
$presets = rangoPresets();

// Parámetros que se arrastran a las descargas y a los atajos.
$qRango = ($desde !== '' ? 'desde=' . urlencode($desde) : '') . ($hasta !== '' ? '&hasta=' . urlencode($hasta) : '');
$qRango = ltrim($qRango, '&');
$conRango = function ($base, $extra = '') use ($qRango) {
    $partes = array_filter([$extra, $qRango], function ($p) { return $p !== ''; });
    return $base . ($partes ? '?' . implode('&', $partes) : '');
};

$etiquetasEstatus = ['nuevo' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto', 'no_procede' => 'No procede'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reporte Mesa de Servicios - <?= htmlspecialchars($periodo) ?> - SAASCAEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
    <style>
        .solo-imprimir { display: none; }
        @media print {
            .solo-imprimir { display: block; }
            .ticket-bloque { break-inside: avoid; }
        }
        .ticket-bloque { border:1px solid var(--border); border-left:4px solid var(--guinda); border-radius:10px; padding:12px 16px; margin-bottom:12px; background:var(--card-bg); font-size:13.5px; }
        .ticket-bloque p { margin:5px 0; }
        .ticket-bloque .cabeza-ticket { display:flex; gap:12px; flex-wrap:wrap; align-items:baseline; }
        .ticket-bloque .nota-admin { background:rgba(122,31,51,.06); border-radius:8px; padding:8px 10px; margin-top:8px; white-space:pre-line; }
    </style>
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <!-- Encabezado que solo aparece al imprimir / guardar como PDF -->
    <div class="solo-imprimir" style="text-align:center; margin-bottom:18px; border-bottom:3px solid var(--guinda); padding-bottom:12px;">
        <img src="img/logo.png" alt="Logo SAASCAEM" style="width:64px;">
        <h1 style="margin:6px 0 0; color:var(--guinda);">Reporte de Mesa de Servicios</h1>
        <p style="margin:4px 0 0; font-size:15px;"><strong>Periodo:</strong> <?= htmlspecialchars($periodo) ?></p>
        <p style="margin:2px 0 0; font-size:12px; color:var(--text-muted);">Tickets registrados en el periodo · Generado el <?= date('d/m/Y H:i') ?> desde SAASCANET</p>
    </div>

    <h1 class="no-imprimir">📊 Reportes de Mesa de Servicios</h1>
    <p class="no-imprimir"><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a> &nbsp;·&nbsp; <a href="admin-tickets.php" class="enlace-ver">Ver Tickets</a></p>

    <!-- Selector de periodo -->
    <form method="get" class="no-imprimir" style="background:var(--card-bg); border-radius:16px; box-shadow:var(--shadow); padding:18px 20px; max-width:760px; margin:14px 0 18px;">
        <h3 style="margin:0 0 10px; color:var(--guinda);">📅 Periodo del reporte</h3>
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="campo" style="margin:0;">
                <label for="desde">Desde</label>
                <input id="desde" name="desde" type="date" value="<?= htmlspecialchars($desde) ?>">
            </div>
            <div class="campo" style="margin:0;">
                <label for="hasta">Hasta</label>
                <input id="hasta" name="hasta" type="date" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:10px;">
                <input type="checkbox" name="detalle" value="1" <?= $conDetalle ? 'checked' : '' ?> style="width:auto;"> Incluir el listado de tickets en el reporte
            </label>
            <button type="submit" class="btn-primario" style="width:auto; padding:10px 18px;">Ver reporte</button>
            <a href="admin-reportes.php<?= $conDetalle ? '?detalle=1' : '' ?>" class="btn-secundario">Todo el historial</a>
        </div>
        <p style="font-size:12.5px; color:var(--text-muted); margin:12px 0 6px;">Atajos:</p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php foreach ($presets as $nombre => $r): ?>
                <a class="btn-secundario" style="padding:6px 12px; font-size:13px; <?= ($desde === $r[0] && $hasta === $r[1]) ? 'outline:2px solid var(--guinda);' : '' ?>"
                   href="admin-reportes.php?desde=<?= $r[0] ?>&hasta=<?= $r[1] ?><?= $conDetalle ? '&detalle=1' : '' ?>"
                   title="<?= htmlspecialchars(rangoEtiqueta($r[0], $r[1])) ?>"><?= htmlspecialchars($nombre) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <p style="font-size:15px; margin:0 0 4px;" class="no-imprimir"><strong>Periodo:</strong> <?= htmlspecialchars($periodo) ?></p>
    <p style="color:var(--text-muted); font-size:13px;" class="no-imprimir">Se cuentan los tickets <strong>registrados</strong> dentro del periodo. Los números se calculan al momento.</p>

    <p class="no-imprimir" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?= htmlspecialchars($conRango('admin-reportes-exportar.php')) ?>" class="btn-secundario">⬇️ Descargar resumen (CSV)</a>
        <a href="<?= htmlspecialchars($conRango('admin-tickets-exportar.php')) ?>" class="btn-secundario">⬇️ Descargar tickets del periodo (CSV)</a>
        <button type="button" onclick="window.print()" class="btn-secundario" style="border:none; cursor:pointer;">🖨️ Imprimir / Guardar como PDF</button>
    </p>

    <div class="stats">
        <div class="stat">
            <div class="cabeza"><span class="etq">Total de tickets</span></div>
            <div class="valor mono"><?= $rep['total'] ?></div>
            <div class="detalle"><?= ($desde === '' && $hasta === '') ? 'Desde que se usa el sistema' : 'Registrados en el periodo' ?></div>
        </div>
        <div class="stat">
            <div class="cabeza"><span class="etq">🔔 Pendientes</span></div>
            <div class="valor mono"><?= $rep['pendientes'] ?></div>
            <div class="detalle"><?= $rep['porEstatus']['nuevo'] ?> nuevos, <?= $rep['porEstatus']['en_proceso'] ?> en proceso</div>
        </div>
        <div class="stat">
            <div class="cabeza"><span class="etq">✅ Resueltos</span></div>
            <div class="valor mono"><?= $rep['porEstatus']['resuelto'] ?></div>
            <div class="detalle">Atendidos correctamente</div>
        </div>
        <div class="stat">
            <div class="cabeza"><span class="etq">❌ No procede</span></div>
            <div class="valor mono"><?= $rep['porEstatus']['no_procede'] ?></div>
            <div class="detalle">Rechazados con nota</div>
        </div>
        <div class="stat">
            <div class="cabeza"><span class="etq">📈 Promedio</span></div>
            <div class="valor mono"><?= $rep['promedioDiario'] ?></div>
            <div class="detalle">Por día (≈ <?= $rep['promedioSemanal'] ?> por semana, <?= $rep['dias'] ?> días)</div>
        </div>
    </div>

    <h2 style="margin-top:36px;">Tickets por tipo (los más enviados)</h2>
    <?php if (empty($rep['porTipo'])): ?>
        <p style="color:var(--text-muted);">No hay tickets registrados en este periodo.</p>
    <?php else: ?>
        <div style="max-width:560px; background:var(--card-bg); border-radius:16px; box-shadow:var(--shadow); padding:10px 20px;">
            <?php foreach ($rep['porTipo'] as $t): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
                    <span><?= htmlspecialchars($t['tipo']) ?></span>
                    <strong class="mono" style="color:var(--guinda);"><?= $t['total'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:36px;">Tickets por área o unidad</h2>
    <?php if (empty($rep['porArea'])): ?>
        <p style="color:var(--text-muted);">No hay tickets registrados en este periodo.</p>
    <?php else: ?>
        <div style="max-width:560px; background:var(--card-bg); border-radius:16px; box-shadow:var(--shadow); padding:10px 20px;">
            <?php foreach ($rep['porArea'] as $a): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
                    <span><?= htmlspecialchars($a['area']) ?></span>
                    <strong class="mono" style="color:var(--guinda);"><?= $a['total'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:36px;">Tickets resueltos por administrador</h2>
    <?php if (!empty($rep['compartidos'])): ?>
        <p style="font-size:13px; color:var(--text-muted); max-width:560px;">En este periodo, <strong><?= (int) $rep['compartidos'] ?></strong> ticket<?= $rep['compartidos'] === 1 ? ' fue trabajado' : 's fueron trabajados' ?> por más de una persona. Cada ticket cuenta una sola vez en el total del reporte y una vez para cada persona que participó, por eso la suma de esta lista puede ser mayor que el número de tickets cerrados.</p>
    <?php endif; ?>
    <?php if (empty($rep['porAdmin'])): ?>
        <p style="color:var(--text-muted);">No se resolvió ningún ticket de este periodo.</p>
    <?php else: ?>
        <div style="max-width:560px; background:var(--card-bg); border-radius:16px; box-shadow:var(--shadow); padding:10px 20px;">
            <?php foreach ($rep['porAdmin'] as $a): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
                    <span><?= htmlspecialchars($a['nombre']) ?></span>
                    <strong class="mono" style="color:var(--guinda);"><?= $a['total'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:36px;">Rechazados con nota (No procede)</h2>
    <?php if (empty($rep['rechazados'])): ?>
        <p style="color:var(--text-muted);">No hay tickets rechazados en este periodo.</p>
    <?php else: ?>
        <div class="contenedor-acciones">
            <?php foreach ($rep['rechazados'] as $r): ?>
                <div class="caja-accion" style="text-align:left;">
                    <h3 class="mono" style="text-align:center;"><?= htmlspecialchars($r['folio']) ?></h3>
                    <p><strong>De:</strong> <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['area']) ?>)</p>
                    <p><strong>Nota:</strong> <?= nl2br(htmlspecialchars($r['nota_admin'] ?? 'Sin nota')) ?></p>
                    <p style="font-size:12px; color:var(--text-muted);"><?= $r['resuelto_en'] ? date('d/m/Y', strtotime($r['resuelto_en'])) : '' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($conDetalle): ?>
        <h2 style="margin-top:36px;">Listado de tickets del periodo (<?= count($rep['detalle']) ?>)</h2>
        <?php if (empty($rep['detalle'])): ?>
            <p style="color:var(--text-muted);">No hay tickets registrados en este periodo.</p>
        <?php else: ?>
            <?php foreach ($rep['detalle'] as $d): ?>
                <div class="ticket-bloque">
                    <div class="cabeza-ticket">
                        <strong class="mono" style="color:var(--guinda); font-size:15px;"><?= htmlspecialchars($d['folio']) ?></strong>
                        <span><strong><?= htmlspecialchars($etiquetasEstatus[$d['estatus']] ?? $d['estatus']) ?></strong></span>
                        <span style="color:var(--text-muted);">
                            Registrado: <?= date('d/m/Y', strtotime($d['creado_en'])) ?>
                            <?= $d['resuelto_en'] ? ' · Cerrado: ' . date('d/m/Y', strtotime($d['resuelto_en'])) : '' ?>
                        </span>
                    </div>
                    <p><strong>Solicitó:</strong> <?= htmlspecialchars($d['nombre']) ?> (<?= htmlspecialchars($d['area']) ?>)
                        &nbsp;·&nbsp; <strong>Tipo:</strong> <?= htmlspecialchars($d['tipo']) ?>
                        &nbsp;·&nbsp; <strong><?= count($d['participantes']) > 1 ? 'Atendieron' : 'Atendió' ?>:</strong>
                        <?= htmlspecialchars($d['participantes'] ? implode(', ', array_column($d['participantes'], 'nombre')) : 'Sin asignar') ?></p>
                    <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($d['comentarios'])) ?></p>
                    <?php if (!empty($d['nota_admin'])): ?>
                        <div class="nota-admin"><strong>Nota del administrador:</strong> <?= htmlspecialchars($d['nota_admin']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="no-imprimir" style="margin-top:36px;">Descargar tickets en CSV (detalle)</h2>
    <p class="no-imprimir" style="font-size:13px; color:var(--text-muted); margin-top:-6px;">Respetan el periodo elegido arriba<?= ($desde === '' && $hasta === '') ? ' (ahora mismo: todo el historial)' : ' (' . htmlspecialchars($periodo) . ')' ?>.</p>
    <p class="no-imprimir" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?= htmlspecialchars($conRango('admin-tickets-exportar.php')) ?>" class="btn-secundario">⬇️ Todos los tickets</a>
        <a href="<?= htmlspecialchars($conRango('admin-tickets-exportar.php', 'estatus=resuelto')) ?>" class="btn-secundario">⬇️ Solo resueltos</a>
        <a href="<?= htmlspecialchars($conRango('admin-tickets-exportar.php', 'estatus=no_procede')) ?>" class="btn-secundario">⬇️ Solo rechazados (no procede)</a>
        <a href="<?= htmlspecialchars($conRango('admin-tickets-exportar.php', 'estatus=resuelto&mios=1')) ?>" class="btn-secundario">⬇️ Mis resueltos</a>
    </p>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
