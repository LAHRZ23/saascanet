<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();

$ticketsNuevos = 0;
$resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM tickets WHERE estatus = 'nuevo' AND archivado = 0");
if ($resultado) {
    $ticketsNuevos = (int) mysqli_fetch_assoc($resultado)['total'];
}

$solicitudesPendientes = 0;
$resultadoSol = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM solicitudes_publicacion WHERE estatus = 'pendiente'");
if ($resultadoSol) {
    $solicitudesPendientes = (int) mysqli_fetch_assoc($resultadoSol)['total'];
}

$encuestasNuevas = 0;
$resultadoEnc = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM encuesta_respuestas WHERE estado = 'nueva'");
if ($resultadoEnc) {
    $encuestasNuevas = (int) mysqli_fetch_assoc($resultadoEnc)['total'];
}

// Total de respuestas de la ronda de calidad de atención que esté abierta ahora mismo.
$calidadRespuestas = 0;
$resultadoCal = mysqli_query($conexion, "SELECT COUNT(x.id) AS total FROM calidad_rondas r LEFT JOIN calidad_respuestas x ON x.ronda_id = r.id WHERE r.activa = 1");
if ($resultadoCal) {
    $calidadRespuestas = (int) mysqli_fetch_assoc($resultadoCal)['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Panel de Administración</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>🛡 Panel de Administración</h1>
    <p>Desde aquí manejas todo lo que ve el personal: comunicados, tickets, eventos, cursos, el aviso del inicio y las cuentas de usuario.</p>

    <?php if ($solicitudesPendientes > 0): ?>
        <div class="aviso-importante" style="margin-bottom:24px;">📨 Se pidió <?= $solicitudesPendientes === 1 ? 'una solicitud' : $solicitudesPendientes . ' solicitudes' ?> de publicación pendiente<?= $solicitudesPendientes === 1 ? '' : 's' ?>. <a href="admin-solicitudes.php"><strong>Revisar ahora →</strong></a></div>
    <?php endif; ?>

    <div class="panel-accesos">
        <a href="admin-tickets.php" class="acceso-card" <?= $ticketsNuevos > 0 ? 'style="border-top-color: var(--dorado);"' : '' ?>>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4h6l1 3h3v3l-3 1v9H6v-9L3 10V7h3z"/></svg></span>
            <span class="etiqueta">Tickets<?= $ticketsNuevos > 0 ? '<br><span class="contador-destacado">' . $ticketsNuevos . ' nuevos</span>' : '' ?></span>
        </a>
        <a href="admin-solicitudes.php" class="acceso-card" <?= $solicitudesPendientes > 0 ? 'style="border-top-color: var(--dorado);"' : '' ?>>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 3 3 10.5l7 2.5 2.5 7L21 3Z"/><path d="m10 13 4-4"/></svg></span>
            <span class="etiqueta">Solicitudes de publicación<?= $solicitudesPendientes > 0 ? '<br><span class="contador-destacado">' . $solicitudesPendientes . ($solicitudesPendientes === 1 ? ' pendiente' : ' pendientes') . '</span>' : '' ?></span>
        </a>
        <a href="admin-reportes.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span>
            <span class="etiqueta">Reportes</span>
        </a>
        <a href="admin-comunicados.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4a1 1 0 0 0 1 1h2l3 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6"/></svg></span>
            <span class="etiqueta">Comunicados</span>
        </a>
        <a href="admin-eventos.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.1 7-11a7 7 0 0 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.3"/></svg></span>
            <span class="etiqueta">Eventos</span>
        </a>
        <a href="admin-cursos.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4 2 9l10 5 8-4.2V15"/><path d="M6 11.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.5"/></svg></span>
            <span class="etiqueta">Cursos</span>
        </a>
        <a href="admin-tutoriales.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z" fill="currentColor" stroke="none"/></svg></span>
            <span class="etiqueta">Tutoriales</span>
        </a>
        <a href="admin-popup.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="12" rx="2"/><path d="M4 7l8 6 8-6"/></svg></span>
            <span class="etiqueta">Aviso de inicio</span>
        </a>
        <a href="admin-pagina-extra.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
            <span class="etiqueta">Página adicional</span>
        </a>
        <a href="admin-cumpleanos.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7-4.4-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 5c-2.5 4.6-9.5 9-9.5 9Z"/></svg></span>
            <span class="etiqueta">Cumpleaños</span>
        </a>
        <a href="admin-directorio.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="13" height="18" rx="2"/><circle cx="10.5" cy="9" r="2"/></svg></span>
            <span class="etiqueta">Directorio</span>
        </a>
        <a href="admin-usuarios.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c1.2-3.5 4-5 7-5s5.8 1.5 7 5"/></svg></span>
            <span class="etiqueta">Usuarios</span>
        </a>
        <a href="admin-encuesta.php" class="acceso-card" <?= $encuestasNuevas > 0 ? 'style="border-top-color: var(--dorado);"' : '' ?>>
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H10l-4 4v-4H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/><path d="M8 9h8M8 12h5"/></svg></span>
            <span class="etiqueta">Encuesta beta<?= $encuestasNuevas > 0 ? '<br><span class="contador-destacado">' . $encuestasNuevas . ' nuevas</span>' : '' ?></span>
        </a>
        <a href="admin-calidad.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9Z"/></svg></span>
            <span class="etiqueta">Calidad de atención<?= $calidadRespuestas > 0 ? '<br><span class="contador-destacado">' . $calidadRespuestas . ' respuestas</span>' : '' ?></span>
        </a>
        <a href="admin-tema.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 3a6 6 0 0 0 0 12 3 3 0 0 1 0 6"/><circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="7" r="1" fill="currentColor" stroke="none"/><circle cx="16" cy="10" r="1" fill="currentColor" stroke="none"/></svg></span>
            <span class="etiqueta">Tema de color</span>
        </a>
        <a href="admin-bitacora.php" class="acceso-card">
            <span class="icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h9l3 3v13H6z"/><path d="M9 9h6M9 13h6M9 17h4"/></svg></span>
            <span class="etiqueta">Bitácora</span>
        </a>
    </div>

    <p style="margin-top:30px;"><a href="home.php" class="enlace-volver">← Volver al inicio</a></p>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
