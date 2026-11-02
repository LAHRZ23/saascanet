<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Decl@raNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

    <div id="nav-placeholder"></div>

    <div class="main-content">
<center><h1>📜 Declaración por Modificación Patrimonial, Anualidad 2025</h1></center>
<div class="banner-container2">
    <div class="banner">

        <a href="https://declaranet.secogem.gob.mx/login" target="_blank">
            <img src="img/baner_home/3 PASOS.jpg" alt="Imagen 3">
        </a>

        <a href="https://declaranet.secogem.gob.mx/login" target="_blank">
            <img src="img/baner_home/0.jpg" alt="Imagen 1">
        </a>

        <a href="https://declaranet.secogem.gob.mx/login" target="_blank">
            <img src="img/baner_home/AVISO.jpg" alt="Imagen 2">
        </a>







    </div>

    <button class="prev" onclick="moverSlide(-1)">&#10094;</button>
    <button class="next" onclick="moverSlide(1)">&#10095;</button>
</div>

        <script>
    let indice = 0;
    const banner = document.querySelector(".banner");
    const slides = document.querySelectorAll(".banner a");
    const totalSlides = slides.length;

    function mostrarSlide() {
        banner.style.transform = `translateX(${-indice * 100}%)`;
    }

    function moverSlide(direccion) {
        indice = (indice + direccion + totalSlides) % totalSlides;
        mostrarSlide();
    }

    // ✅ Tiempo configurable (en milisegundos)
    const tiempoBanner = 6500; // 4 segundos

    setInterval(() => moverSlide(1), tiempoBanner);
</script>

          


        <br>
        <h1 style="text-align: justify;"> <center>Enlaces de accesos directos al sistema Decl@raNET:</center> </h1>
    </br>
 <div class="contenedor-acciones">

    <!-- TARJETA 1 -->
    <div class="caja-accion">
      <h3>📜 Decl@raNET</h3>
    </br>
      <a href="https://declaranet.secogem.gob.mx/login"
         class="btn-accion" target="_blank">
        Clic aquí
      </a>
    </div>

    <!-- TARJETA 2 -->
    <div class="caja-accion">
      <h3>📄 NORMAS E INSTRUCTIVO DE LLENADO</h3>
    </br>
      <a href="https://declaranet.secogem.gob.mx/formatos/NORMAS%20E%20INSTRUCTIVO%20DE%20LLENADO%20DeclaraNet.pdf"
         class="btn-accion" target="_blank">
        Clic aquí
      </a>
    </div>

    <!-- TARJETA 3 -->
    <div class="caja-accion">
      <h3>📄 FORMATO PARA LA UBICACIÓN DE SITIOS</h3>
    </br>
      <a href="https://declaranet.secogem.gob.mx/formatos/FormatoDeLaDeclaracionDeModificacion.pdf"
         class="btn-accion" target="_blank">
        Clic aquí
      </a>
    </div>
    <!-- TARJETA 4 -->
    <div class="caja-accion">
      <h3>📄 Oficio con número 220C0201000200S/227/2026</h3>
    </br>
      <a href="https://saascaem.edomex.gob.mx/sites/saascaem.edomex.gob.mx/files/files/OFICIO%20227-%20OBLIGACI%C3%93N%20A%20PRESENTAR%20DECLRACI%C3%93N%20POR%20MODIF.%20PATRIMONIAL%20-%20ANUALIDAD%202025%20(1).pdf"
         class="btn-accion" target="_blank">
        Descargar ⬇️
      </a>
    </div>
  </div>

<!-- POPUP BIENVENIDA RECUERDA PEGAR EL SCRIPT PARA QUE SALGA EL POP UP QUIEN SEA QUE LEA ESTO-->
<div class="popup-overlay" id="popup">
  <div class="popup-box">
    
    <button class="popup-close" onclick="cerrarPopup()">✖</button>


    <h3>Bienvenidos a SAASCANET</h3>
    <img src="img/img comunicados/mision.jpg" alt="Aviso" class="popup-img">

    <a href="mision.php"
    class="btn-accion" >
    ➭ MÁS INFORMACIÓN
 </a>
  </div>
</div>

<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>