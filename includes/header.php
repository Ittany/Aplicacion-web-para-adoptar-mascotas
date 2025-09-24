<?php
$is_logged_in = false;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'MichiHouse'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/index.css">
    
</head>
<body>
<header class="header">
    <img src="/assets/img/logo.png" alt="Logo" class="logo" />

    <button class="menu-toggle" id="menuToggle">
        ☰
    </button>

    <nav class="navigation" id="mainNav">
        <ul>
            <li><a href="/#Home">Inicio</a></li>
            <li><a href="/#nosotros">Nosotros</a></li>
            <li><a href="/#ubicacion">Ubicación</a></li>
            <li><a href="/#donaciones">Donaciones</a></li>
            <li><a href="/#contacto">Contacto</a></li>
        </ul>
        <div class="auth-buttons">
            <?php if($is_logged_in): ?>
                <a href="/logout.php"><button class="logout">Cerrar Sesión</button></a>
            <?php else: ?>
                <a href="/login.php"><button class="login">Iniciar Sesión</button></a>
                <a href="/register.php"><button class="register">Registrarse</button></a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');
    
    menuToggle.addEventListener('click', function() {
        mainNav.classList.toggle('open');
    });
});
</script>