<?php 
$page_title = "MichiHouse - Adopción de mascotas";
include('includes/header.php');
?>

<!-- Sección Hero -->
<section class="hero">
  <img src="/assets/img/fondocat.jpg" alt="Fondo gato" class="background-image" />
  
  <div class="hero-text">
    <p>Adoptando vidas</p>
    <button class="cta-button">Ver Adopciones</button>
  </div>
</section>

<!-- Sobre Nosotros -->
<section id="nosotros" class="about">
  <div class="about-container">
    <div class="about-text">
      <h2>SOBRE NOSOTROS</h2>
      <p>
        En <strong>MichiHouse</strong>, creemos que cada animal rescatado merece una segunda oportunidad. <br />
        Somos una iniciativa dedicada a promover la adopción responsable de perros y gatos rescatados del abandono, maltrato o situaciones de riesgo. <br />
        Nuestro propósito es ser el puente entre personas con gran corazón y aquellos animales que esperan una familia que los ame, los cuide y les devuelva la confianza.
      </p>
      <p class="highlight">💖 ADOPTAR ES UN ACTO DE AMOR. ¡TÚ PUEDES HACER EL CAMBIO! 💖</p>
    </div>
    <div class="about-image">
      <img src="/assets/img/CATnosotros.jpg" alt="Adopción de animales" />
    </div>
  </div>
</section>

<!-- Ubicación -->
<section id="ubicacion" class="location">
  <h2>UBICACIÓN</h2>
  <p><strong>Dirección:</strong> 12001, Huancayo, Junín – Perú</p>
  <div class="map-container">
    <iframe
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3901.6347079343777!2d-75.21029759999999!3d-12.068635699999998!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x910e9764afc546ed%3A0x50316d81b9b79382!2sMichi%20house!5e0!3m2!1ses!2spe!4v1749483205532!5m2!1ses!2spe"
      width="100%"
      height="300"
      style="border:0; border-radius: 10px;"
      allowfullscreen=""
      loading="lazy"
    ></iframe>
  </div>
</section>

<!-- Donaciones -->
<section id="donaciones" class="donations">
  <div class="donation-content">
    <div class="text">
      <h2>DONACIONES</h2>
      <p>
        Puedes seguir apoyándonos para llegar a más animalitos que necesitan tu ayuda. <br />
        Tu colaboración con comida, productos de limpieza, medicamentos o incluso una pequeña donación económica puede transformar vidas. <br />
        <strong>¡Cada granito de arena cuenta!</strong>
      </p>
      <button class="donate-button">💖 Click Aquí para Donar</button>
    </div>
    <div class="image">
      <img src="/assets/img/cat2.png" alt="Gatito" class="donation-cat" />
    </div>
  </div>
</section>

<?php include('includes/footer.php'); ?>