<?php 
session_start();

// Configuración para evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Obtener y limpiar la alerta
$alert = $_SESSION['alert'] ?? null;
if ($alert) {
    // Eliminar antes de que cargue el HTML
    unset($_SESSION['alert']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Redireccionando...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const alertData = <?= json_encode($alert) ?>;

            if (alertData) {
                Swal.fire({
                    title: alertData.title,
                    text: alertData.text,
                    icon: alertData.icon,
                    confirmButtonText: 'OK',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: true
                }).then(() => {
                    window.location.href = alertData.redirect;
                });
            } else {
                // Redirección silenciosa por defecto
                window.location.href = 'recuperar.php';
            }
        });
    </script>
</body>
</html>
