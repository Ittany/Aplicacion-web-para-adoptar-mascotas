<?php
ob_start();
session_start();
require 'includes/conexion.php'; 
require 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $correo = trim($_POST['correo'] ?? '');
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['alert'] = [
        'title' => 'Correo inválido',
        'text' => 'El correo no tiene un formato válido.',
        'icon' => 'error',
        'redirect' => 'login.php'
    ];
    header('Location: handle_alert.php');
    exit;
}

    $contrasena = $_POST['contrasena'] ?? '';

    if (!empty($correo) && !empty($contrasena)) {
        try {
            // Obtener usuario por correo
            $sql = "EXEC ObtenerUsuarioPorCorreo @correo = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && isset($usuario['contrasena']) && password_verify($contrasena, $usuario['contrasena'])) {
                $estado = strtolower(trim($usuario['estado_registro'] ?? ''));

                if ($estado !== 'completo') {
                    $_SESSION['registro'] = [
                        'correo' => $usuario['correo'] ?? '',
                        'id_usuario' => $usuario['id_usuario'] ?? null
                    ];

                    $_SESSION['alert'] = [
                        'title' => 'Registro incompleto',
                        'text' => 'Tu cuenta aún no ha sido completada. Serás redirigido para finalizar el registro.',
                        'icon' => 'info',
                        'redirect' => 'register.php?etapa=4'
                    ];
                } else {
                    unset($usuario['contrasena']);
                    $_SESSION['usuario'] = $usuario;

                    $rol = $usuario['tipo_rol'] ?? '';

                    if ($rol === 'Administrador') {
                        $_SESSION['alert'] = [
                            'title' => '¡Bienvenido Administrador!',
                            'text' => 'Has iniciado sesión correctamente',
                            'icon' => 'success',
                            'redirect' => 'admin/dashboard_admin.php'
                        ];
                    } elseif ($rol === 'Adoptante') {
                        $_SESSION['alert'] = [
                            'title' => '¡Bienvenido Adoptante!',
                            'text' => 'Has iniciado sesión correctamente',
                            'icon' => 'success',
                            'redirect' => 'adoptante/home_adoptante.php'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'title' => 'Error de rol',
                            'text' => '⚠️ Rol no reconocido.',
                            'icon' => 'error',
                            'redirect' => 'login.php'
                        ];
                    }
                }
            } else {
                $_SESSION['alert'] = [
                    'title' => 'Error de inicio de sesión',
                    'text' => '❌ Usuario o contraseña incorrectos.',
                    'icon' => 'error',
                    'redirect' => 'login.php'
                ];
            }
        } catch (PDOException $e) {
            $_SESSION['alert'] = [
                'title' => 'Error del servidor',
                'text' => 'Ocurrió un problema al procesar tu solicitud. Intenta nuevamente.',
                'icon' => 'error',
                'redirect' => 'login.php'
            ];
            error_log("Error en login: " . $e->getMessage());
        }
    } else {
        $_SESSION['alert'] = [
            'title' => 'Campos requeridos',
            'text' => 'Por favor, completa todos los campos para continuar.',
            'icon' => 'warning',
            'redirect' => 'login.php'
        ];
    }

    // ✅ Redirigir si hay una alerta
    if (isset($_SESSION['alert'])) {
        header('Location: handle_alert.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - MichiHouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/iniciarsesion.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <img src="/assets/img/gatito2.gif" alt="Imagen decorativa" />
        </div>

        <div class="login-form-box">
            <img src="/assets/img/logo2.png" alt="Logo MichiHouse" class="login-logo" />

            <!-- Formulario de login -->
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="email">Correo electrónico:</label>
                    <input type="email" id="email" name="correo" required />
                </div>

                <div class="form-group password-group">
                    <label for="password">Contraseña:</label>
                    <div class="password-input-container">
                        <input 
                            type="password" 
                            id="password" 
                            name="contrasena" 
                            required 
                            class="password-input"
                        />
                        <button type="button" class="toggle-password-button">
                            <img src="/assets/img/eye.svg" alt="Mostrar contraseña" class="toggle-password-icon" />
                        </button>
                    </div>
                </div>

                <a href="/recuperar.php" class="forgot-password">¿Olvidaste tu contraseña?</a>

                <button type="submit" class="login-button">Iniciar Sesión</button>

                <p class="no-account">
                    ¿Aún no tienes una cuenta?
                    <a href="/register.php">Crear Cuenta</a>
                </p>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.toggle-password-button');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.password-input');
            const icon = this.querySelector('img');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.src = isPassword ? '/assets/img/eye-slash.svg' : '/assets/img/eye.svg';
            icon.alt = isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña';
            this.classList.toggle('active', !isPassword);
        });
    });

    <?php if (isset($_SESSION['alert'])): ?>
    Swal.fire({
        title: '<?php echo $_SESSION['alert']['title']; ?>',
        text: '<?php echo $_SESSION['alert']['text']; ?>',
        icon: '<?php echo $_SESSION['alert']['icon']; ?>',
        confirmButtonText: 'OK',
        allowOutsideClick: false
    }).then(() => {
        <?php if (isset($_SESSION['alert']['redirect'])): ?>
        window.location.href = '<?php echo $_SESSION['alert']['redirect']; ?>';
        <?php endif; ?>
    });
    <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>

    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        if (!email || !password) {
            e.preventDefault();
            Swal.fire({
                title: 'Campos incompletos',
                text: 'Por favor completa todos los campos',
                icon: 'warning',
                confirmButtonText: 'Entendido'
            });
        }
    });
    
});
function validarCorreo(correo) {
    const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return regex.test(correo);
}

document.getElementById('loginForm').addEventListener('submit', function (e) {
    const email = document.getElementById('email').value;

    if (!validarCorreo(email)) {
        e.preventDefault();
        Swal.fire({
            title: 'Correo inválido',
            text: 'Por favor ingresa un correo con formato válido.',
            icon: 'error',
            confirmButtonText: 'Corregir'
        });
    }
});
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        const valorCorreo = emailInput.value;
        const tieneProhibidos = caracteresProhibidos.some(c => valorCorreo.includes(c));

        if (tieneProhibidos) {
            e.preventDefault();
            Swal.fire({
                title: 'Correo inválido',
                text: 'Tu correo contiene caracteres no permitidos.',
                icon: 'error',
                confirmButtonText: 'Corregir'
            });
        }
    });

</script>

</body>
</html>
<?php ob_end_flush(); ?>