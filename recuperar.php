<?php
ob_start();
session_start();
require 'includes/conexion.php';
require 'includes/header.php';
unset($_SESSION['alert']);
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

$paso = isset($_POST['paso']) ? (int)$_POST['paso'] : (isset($_GET['paso']) ? (int)$_GET['paso'] : 1);


// Validación de etapas
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($paso === 2 && empty($_SESSION['reset_email'])) {
        $_SESSION['alert'] = [
            'title' => '¡Etapa incompleta!',
            'text' => 'Debes completar la verificación de correo primero',
            'icon' => 'error',
            'redirect' => 'recuperar.php?paso=1'
        ];
        header('Location: handle_alert.php');
        exit;
    }

    if ($paso === 3 && empty($_SESSION['codigo_valido'])) {
        $_SESSION['alert'] = [
            'title' => '¡Etapa incompleta!',
            'text' => 'Debes verificar tu código de seguridad primero',
            'icon' => 'error',
            'redirect' => 'recuperar.php?paso=2'
        ];
        header('Location: handle_alert.php');
        exit;
    }
}

// Manejo de pasos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Paso 1: Validar correo
    if ($paso === 1) {
        $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$correo) {
            $_SESSION['alert'] = [
                'title' => 'Correo inválido',
                'text' => 'Por favor ingresa un correo válido',
                'icon' => 'error',
                'redirect' => 'recuperar.php?paso=1'
            ];
            header('Location: handle_alert.php');
            exit;
        }

        $stmt = $conn->prepare("EXEC VerificarCorreoExiste @correo = ?");
        $stmt->execute([$correo]);
        $resultado = $stmt->fetch();

        if (!$resultado || $resultado['existe'] == 0) {
            $_SESSION['alert'] = [
                'title' => 'Correo no encontrado',
                'text' => 'No hay cuenta registrada con ese correo',
                'icon' => 'error',
                'redirect' => 'login.php'
            ];
            header('Location: handle_alert.php');
            exit;
        }

        $_SESSION['reset_email'] = $correo;
        header('Location: recuperar.php?paso=2');
        exit;
    }

    elseif ($paso === 2) {
        $codigo = $_POST['codigo'] ?? '';

        // Validación: solo números y longitud exacta de 6
        if (!preg_match('/^\d{6}$/', $codigo)) {
            $_SESSION['alert'] = [
                'title' => 'Código inválido',
                'text' => 'El código debe contener exactamente 6 dígitos numéricos',
                'icon' => 'error',
                'redirect' => 'recuperar.php?paso=2'
            ];
            header('Location: handle_alert.php');
            exit;
        }

        $es_valido = 0;
        $stmt = $conn->prepare("EXEC ValidarCodigoRecuperacion @codigo = ?, @es_valido = ?");
        $stmt->bindParam(1, $codigo, PDO::PARAM_STR);
        $stmt->bindParam(2, $es_valido, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 1);
        $stmt->execute();

        if ($es_valido == 1) {
            $verifica = $conn->prepare("SELECT id_usuario FROM Usuario WHERE correo = ? AND codigo_recuperacion = ?");
            $verifica->execute([$_SESSION['reset_email'], $codigo]);

            if ($verifica->fetch()) {
                $_SESSION['codigo_valido'] = true;
                header('Location: recuperar.php?paso=3');
                exit;
            }
        }

        $_SESSION['alert'] = [
            'title' => 'Código inválido',
            'text' => 'El código ingresado no es válido',
            'icon' => 'error',
            'redirect' => 'recuperar.php?paso=2'
        ];
        header('Location: handle_alert.php');
        exit;
    }

    // Paso 3: Cambiar contraseña
    elseif ($paso === 3) {
        $nueva = $_POST['nuevaContrasena'] ?? '';
        $repetir = $_POST['repetirContrasena'] ?? '';

        if (strlen($nueva) < 8) {
            $_SESSION['alert'] = [
                'title' => 'Contraseña muy corta',
                'text' => 'Debe tener al menos 8 caracteres',
                'icon' => 'error',
                'redirect' => 'recuperar.php?paso=3'
            ];
            header('Location: handle_alert.php');
            exit;
        }

        if ($nueva !== $repetir) {
            $_SESSION['alert'] = [
                'title' => 'No coinciden',
                'text' => 'Las contraseñas no son iguales',
                'icon' => 'error',
                'redirect' => 'recuperar.php?paso=3'
            ];
            header('Location: handle_alert.php');
            exit;
        }

        try {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("EXEC ActualizarContrasenaUsuario @correo = ?, @contrasena = ?");
            $stmt->execute([$_SESSION['reset_email'], $hash]);

            unset($_SESSION['reset_email'], $_SESSION['codigo_valido']);

            $_SESSION['alert'] = [
                'title' => '¡Listo!',
                'text' => 'Contraseña actualizada correctamente',
                'icon' => 'success',
                'redirect' => 'login.php'
            ];
            header('Location: handle_alert.php');
            exit;

        } catch (Exception $e) {
            $_SESSION['alert'] = [
                'title' => 'Error',
                'text' => 'Ocurrió un error: ' . $e->getMessage(),
                'icon' => 'error',
                'redirect' => 'recuperar.php?paso=3'
            ];
            header('Location: handle_alert.php');
            exit;
        }
    }
}

// Navegación
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'back' && $paso > 1) {
        header("Location: recuperar.php?paso=" . ($paso - 1));
        exit;
    } elseif ($_GET['action'] === 'cancel') {
        header('Location: login.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - MichiHouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/recuperar.css">
</head>
<body>
<main class="recuperarpassword-container">
    <div class="recuperarpassword-image">
        <img src="/assets/img/gatito3.gif" alt="Imagen decorativa" />
    </div>

    <div class="recuperarpassword-form-container">
        <div class="recuperarpassword-form-box">
            <img src="/assets/img/logo2.png" alt="Logo" class="logo" />
            <h2 class="form-title">Recuperar Contraseña</h2>
            <p class="form-subtitle">Sigue los pasos para recuperar el acceso a tu cuenta</p>

            <form method="POST" action="recuperar.php" id="recoveryForm">
              <input type="hidden" name="paso" value="<?= $paso ?>">

                <!-- Paso 1 -->
                <?php if ($paso === 1): ?>
                <div class="step">
                    <div class="form-group full-width">
                        <label for="correo">Correo electrónico:</label>
                        <div class="input-container">
                            <input
                                type="email"
                                id="correo"
                                name="correo"
                                required
                                placeholder="Ingresa tu correo"
                            />
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Paso 2 -->
                <?php if ($paso === 2): ?>
                <div class="step">
                    <div class="form-group full-width">
                        <label for="codigo">Ingresa tu código de seguridad:</label>
                        <div class="input-container">
                            <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            required
                            placeholder="El código debe ser un número de 6 dígitos"
                            title="Debe contener 6 números"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        />
                        </div>
                    </div>
                </div>
                <?php endif; ?>

               <!-- Paso 3 -->
                <?php if ($paso === 3): ?>
                <div class="step">
                    <div class="form-group password-group">
                        <label for="nuevaContrasena">Nueva contraseña:</label>
                        <div class="input-container password-input-container">
                            <input
                                type="password"
                                id="nuevaContrasena"
                                name="nuevaContrasena"
                                required
                                minlength="8"
                            />
                            <img 
                                id="toggleNuevaContrasena" 
                                src="/assets/img/eye.svg" 
                                alt="Mostrar/Ocultar nueva contraseña" 
                                class="toggle-password" 
                                onclick="togglePassword('nuevaContrasena', this)"
                            />
                        </div>
                    </div>

                    <div class="form-group password-group">
                        <label for="repetirContrasena">Repetir contraseña:</label>
                        <div class="input-container password-input-container">
                            <input
                                type="password"
                                id="repetirContrasena"
                                name="repetirContrasena"
                                required
                                minlength="8"
                            />
                            <img 
                                id="toggleRepetirContrasena" 
                                src="/assets/img/eye.svg" 
                                alt="Mostrar/Ocultar repetir contraseña" 
                                class="toggle-password" 
                                onclick="togglePassword('repetirContrasena', this)"
                            />
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="buttons-container">
                    <?php if ($paso === 1): ?>
                        <a href="recuperar.php?action=cancel" class="action-button cancel-button">Cancelar</a>
                    <?php else: ?>
                        <a href="recuperar.php?action=back&paso=<?= $paso ?>" class="action-button back-button">Atrás</a>
                    <?php endif; ?>
                    <button type="submit" class="action-button submit-button">
                        <?= $paso === 3 ? 'Actualizar contraseña' : 'Siguiente' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function togglePassword(inputId, iconElement) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === "password";

    input.type = isHidden ? "text" : "password";
    iconElement.src = isHidden ? "/assets/img/eye-slash.svg" : "/assets/img/eye.svg";
}

document.getElementById('recoveryForm').addEventListener('submit', function(e) {
    if (<?= $paso ?> === 3) {
        const nuevaContrasena = document.getElementById('nuevaContrasena').value;
        const repetirContrasena = document.getElementById('repetirContrasena').value;
        
        if (nuevaContrasena.length < 8) {
            e.preventDefault();
            Swal.fire({
                title: 'Contraseña muy corta',
                text: 'La contraseña debe tener al menos 8 caracteres',
                icon: 'error'
            });
            return;
        }
        
        if (nuevaContrasena !== repetirContrasena) {
            e.preventDefault();
            Swal.fire({
                title: 'Contraseñas no coinciden',
                text: 'Las contraseñas ingresadas no son iguales',
                icon: 'error'
            });
            return;
        }
    }
});
</script>
</body>
</html>