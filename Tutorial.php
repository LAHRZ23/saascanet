<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Tutorial Mesa de Servicios</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

    <div id="nav-placeholder"></div>

    <div class="main-content">
        <h1>💾TUTORIAL INTERACTIVO / MESA DE SERVICIO</h1>
<div class="caja">
    <a href="mesa-servicios.php" class="btn-accion" >Regresar</a>
</div>
        <center><H3>UTILIZA LAS FLECHAS "⬅️ ➡️" PARA INTERACTUAR CON EL TUTRIAL</strong></H3></center>

  <div class="slider-container">
 <center><button class="nav left" onclick="moveSlider(-1)">⬅️</button>
  <button class="nav right" onclick="moveSlider(1)">➡️</button></center>
  <div class="slider" id="videoSlider">
    <video autoplay loop controls src="img/img mds/videos/paso 1.mp4"></video>
    <video autoplay loop controls src="img/img mds/videos/paso 2.mp4"></video>
    <video autoplay loop controls src="img/img mds/videos/paso 3.mp4"></video>
    <video autoplay loop controls src="img/img mds/videos/paso 4.mp4"></video>
    <video autoplay loop controls src="img/img mds/videos/paso 5.mp4"></video>
  </div>
  <center><button class="nav left" onclick="moveSlider(-1)">⬅️</button>
  <button class="nav right" onclick="moveSlider(1)">➡️</button></center>
</div>



        </div>
    </div>
    <script src="js/nav.js?v=3"></script>
    <script src="js/nav-loader.js?v=30"></script>
</body>
</html>
