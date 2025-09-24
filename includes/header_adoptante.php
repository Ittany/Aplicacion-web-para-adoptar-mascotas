
<html lang="es">
<head>

    <link rel="stylesheet" href="/assets/css/header_adoptante.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<header class="header2">
    <div class="header2-container">
        <nav class="navigation2">
            <ul>
                <li class="logo-container">
                    <div class="logo2">
                        <img src="/assets/img/logo3.png" alt="Logo MichiHouse">
                    </div>
                </li>
                <li><a href="/adoptante/home_adoptante.php">HOME</a></li>
                <li><a href="/adoptante/ver_mascotas.php">MASCOTAS</a></li>
                <li><a href="/adoptante/solicitudes_enviadas.php">SOLICITUDES</a></li>
            </ul>
        </nav>
        
        <button class="menu-toggle2" aria-label="Menú">
            
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
</body>
<script>
// Menú responsive
document.querySelector('.menu-toggle2').addEventListener('click', function() {
    document.querySelector('.navigation2').classList.toggle('active');
    this.classList.toggle('active');
});
</script>