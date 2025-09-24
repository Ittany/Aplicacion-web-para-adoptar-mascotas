<?php
// register.php (actualizado para usar procedimientos almacenados)

ob_start();
session_start();
require 'includes/conexion.php';
require 'includes/header.php';

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$etapa = isset($_GET['etapa']) ? (int)$_GET['etapa'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($etapa === 2 && empty($_SESSION['registro']['correo'])) {
        header('Location: register.php?etapa=1');
        exit;
    }
    if ($etapa === 3 && (empty($_SESSION['registro']['contrasena']) || empty($_SESSION['registro']['codigo_recuperacion']))) {
        header('Location: register.php?etapa=2');
        exit;
    }
    if ($etapa === 4 && empty($_SESSION['registro']['id_usuario'])) {
        header('Location: register.php?etapa=3');
        exit;
    }
}

function sanitizeInput($data, $type = 'string') {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;
        case 'int':
            return ctype_digit($data) ? $data : false;
        case 'phone':
             return preg_match('/^\d{9}$/', $data) ? $data : false;
        case 'password':
            return strlen($data) >= 8 ? $data : false;
    }
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } elseif ($etapa === 1) {
        $correo = sanitizeInput($_POST['correo'] ?? '', 'email');
        if (!$correo) {
            $error = "Correo electrónico inválido";
        } else {
            $stmt = $conn->prepare("EXEC VerificarCorreoExiste @correo = ?");
            $stmt->execute([$correo]);
            $existe = $stmt->fetchColumn();
            if ($existe > 0) {
            // Verifica si el registro está incompleto
            $stmt2 = $conn->prepare("SELECT id_usuario, estado_registro FROM Usuario WHERE correo = ?");
            $stmt2->execute([$correo]);
            $usuarioPendiente = $stmt2->fetch(PDO::FETCH_ASSOC);
            } else {

                $_SESSION['registro']['correo'] = $correo;
                header('Location: register.php?etapa=2');
                exit;

            if ($usuarioPendiente && $usuarioPendiente['estado_registro'] === 'pendiente') {
                // Guardar datos en sesión y redirigir a la etapa que falta
                $_SESSION['registro']['correo'] = $correo;
                $_SESSION['registro']['id_usuario'] = $usuarioPendiente['id_usuario'];

                $_SESSION['alert'] = [
                    'title' => 'Registro incompleto',
                    'text' => 'Tu registro fue interrumpido. Serás redirigido para completarlo.',
                    'icon' => 'info',
                    'redirect' => 'register.php?etapa=4'
                ];
                header('Location: login.php');
                exit;
            } else {
                $error = "Este correo ya está registrado. Puedes iniciar sesión.";
            }
        }
        }
    } elseif ($etapa === 2) {
        $contrasena = sanitizeInput($_POST['contrasena'] ?? '', 'password');
        $confirmarContrasena = sanitizeInput($_POST['confirmarContrasena'] ?? '', 'password');
        $codigo_recuperacion = $_POST['codigo_recuperacion'] ?? '';

        if (strlen($contrasena) < 8 || strlen($confirmarContrasena) < 8) {
            $error = "La contraseña debe tener al menos 8 caracteres";
        } elseif ($contrasena !== $confirmarContrasena) {
            $error = "Las contraseñas no coinciden";
        } elseif (!preg_match('/^\d{6}$/', $codigo_recuperacion)) {
            $error = "El código de recuperación debe tener exactamente 6 dígitos numéricos";
        } else {
            $_SESSION['registro']['contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
            $_SESSION['registro']['codigo_recuperacion'] = $codigo_recuperacion;
            header('Location: register.php?etapa=3');
            exit;
        }
    } elseif ($etapa === 3) {
            $campos = [
            'nombres' => sanitizeInput($_POST['nombres'] ?? ''),
            'apellidos' => sanitizeInput($_POST['apellidos'] ?? ''),
            'telefono' => sanitizeInput($_POST['telefono'] ?? '', 'phone')
        ];

        if (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ\s]{2,50}$/', $campos['nombres'])) {
            $error = "Los nombres solo deben contener letras y espacios (2 a 50 caracteres)";
        } elseif (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ\s]{2,50}$/', $campos['apellidos'])) {
            $error = "Los apellidos solo deben contener letras y espacios (2 a 50 caracteres)";
        } elseif (!preg_match('/^[0-9]{9}$/', $campos['telefono'])) {
            $error = "El teléfono debe tener exactamente 9 dígitos";
        }

        if (empty($error)) {
            try {
                $stmt = $conn->prepare("EXEC InsertarUsuarioYRol ?, ?, ?, ?, ?, ?, ?");
                $idUsuario = 0;
                $stmt->bindParam(1, $campos['nombres']);
                $stmt->bindParam(2, $campos['apellidos']);
                $stmt->bindParam(3, $_SESSION['registro']['correo']);
                $stmt->bindParam(4, $_SESSION['registro']['contrasena']);
                $stmt->bindParam(5, $campos['telefono']);
                $stmt->bindParam(6, $_SESSION['registro']['codigo_recuperacion']);
                $stmt->bindParam(7, $idUsuario, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 10);
                $stmt->execute();
                $_SESSION['registro']['id_usuario'] = $idUsuario;
                header('Location: register.php?etapa=4');
                exit;
            } catch (PDOException $e) {
                $error = "Error en la etapa 3: " . $e->getMessage();
            }
        }
    } elseif ($etapa === 4) {
    $campos = [
        'dni_cedula' => sanitizeInput($_POST['dni_cedula'] ?? ''),
        'ciudad' => sanitizeInput($_POST['ciudad'] ?? ''),
        'direccion_especifica' => sanitizeInput($_POST['direccion_especifica'] ?? ''),
        'ocupacion' => sanitizeInput($_POST['ocupacion'] ?? '')
    ];

    // Validación de DNI: exactamente 8 dígitos numéricos
    if (!preg_match('/^\d{8}$/', $campos['dni_cedula'])) {
        $error = "El DNI debe tener exactamente 8 dígitos numéricos.";
    }

    // Validación campos obligatorios (excepto dni)
    foreach ($campos as $k => $v) {
        if (empty($v) && $k !== 'dni_cedula') {
            $error = "El campo $k es obligatorio.";
            break;
        }
    }

    // Validación ciudad: solo letras y espacios
    if (empty($error) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $campos['ciudad'])) {
        $error = "La ciudad solo debe contener letras y espacios.";
    }

    // Validación ocupación: solo letras y espacios
    if (empty($error) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $campos['ocupacion'])) {
        $error = "La ocupación solo debe contener letras y espacios.";
    }

    // Validación dirección específica: puedes permitir letras, números y signos básicos
    if (empty($error) && !preg_match('/^[\w\s\.\,\-#°]+$/u', $campos['direccion_especifica'])) {
        $error = "La dirección contiene caracteres no permitidos.";
    }

    // Continuar si no hay errores
    if (empty($error)) {
        // Aquí continuarías con tu inserción o lógica correspondiente
    }
}


    if (empty($error)) {
        try {
            // Insertar en Adoptante
            $stmt = $conn->prepare("EXEC InsertarAdoptante ?, ?, ?, ?, ?");
            $stmt->execute([
                $_SESSION['registro']['id_usuario'],
                $campos['dni_cedula'],
                $campos['ciudad'],
                $campos['direccion_especifica'],
                $campos['ocupacion']
            ]);

            // ✅ Actualizar estado_registro a 'completo'
            $stmtEstado = $conn->prepare("EXEC ActualizarEstadoRegistro ?, ?");
            $stmtEstado->execute([
                $_SESSION['registro']['id_usuario'],
                'completo'
            ]);

            // Login automático
            $_SESSION['user_id'] = $_SESSION['registro']['id_usuario'];
            $_SESSION['user_role'] = 'Adoptante';
            $_SESSION['user_email'] = $_SESSION['registro']['correo'];

            unset($_SESSION['registro']);
            unset($_SESSION['csrf_token']);
            session_regenerate_id(true);

            header('Location: adoptante/home_adoptante.php');
            exit;

        } catch (PDOException $e) {
            $error = "Error en la etapa 4: " . $e->getMessage();
        }
    }

}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - MichiHouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/registrarse.css">
    <link rel="stylesheet" href="/node_modules/sweetalert2/dist/sweetalert2.min.css">
</head>
<body>
<main class="register-container">
    <div class="register-image">
        <img src="/assets/img/gatito.gif" alt="Imagen decorativa" loading="lazy" />
    </div>

    <div class="register-form-box">
        <img src="/assets/img/logo2.png" alt="Logo MichiHouse" class="register-logo" />
        <h1 class="form-title">MichiHouse</h1>
        <p class="form-subtitle">Official - Refugio de gatos y perros</p>

        <div class="step-indicator">
            <div class="step-circle <?= $etapa >= 1 ? 'active' : '' ?>">1</div>
            <div class="step-circle <?= $etapa >= 2 ? 'active' : '' ?>">2</div>
            <div class="step-circle <?= $etapa >= 3 ? 'active' : '' ?>">3</div>
            <div class="step-circle <?= $etapa >= 4 ? 'active' : '' ?>">4</div>
        </div>

        <!-- Paso 1 -->
        <?php if ($etapa === 1): ?>
        <div class="step">
            <form method="POST" action="register.php?etapa=1" id="registroForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group">
                    <label for="correo">Correo electrónico:</label>
                    <input type="email"
                        id="correo"
                        name="correo"
                        required
                        pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                        value="<?= htmlspecialchars($_SESSION['registro']['correo'] ?? '') ?>"
                        placeholder="ejemplo@correo.com"
                        title="Ingrese un correo válido. Ejemplo: usuario@dominio.com">
                </div>
                <p class="account">
                    ¿Ya tienes una cuenta? <a href="/login.php">Inicia sesión</a>
                </p>
                <button type="submit" class="register-button">Verificar</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Paso 2 -->
        <?php if ($etapa === 2): ?>
        <div class="step">
            <form method="POST" action="register.php?etapa=2" id="credencialesForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group password-group">
                    <label for="contrasena">Contraseña:</label>
                    <div class="password-input-container">
                        <input type="password" id="contrasena" name="contrasena" required minlength="8"
                            placeholder="Mínimo 8 caracteres" />
                        <img src="/assets/img/eye.svg" class="toggle-password" alt="Ver"
                            onclick="togglePassword('contrasena', this)" />
                    </div>
                </div>

                <div class="form-group password-group">
                    <label for="confirmarContrasena">Confirmar contraseña:</label>
                    <div class="password-input-container">
                        <input type="password" id="confirmarContrasena" name="confirmarContrasena" required minlength="8"
                            placeholder="Confirma tu contraseña" />
                        <img src="/assets/img/eye.svg" class="toggle-password" alt="Ver"
                            onclick="togglePassword('confirmarContrasena', this)" />
                    </div>
                </div>

                <div class="form-group">
                <label for="codigo_recuperacion">Código de recuperación (6 dígitos):</label>
                <input type="text"
                    id="codigo_recuperacion"
                    name="codigo_recuperacion"
                    maxlength="6"
                    required
                    placeholder="Ej: 123456"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    inputmode="numeric"
                    title="Debe contener exactamente 6 dígitos numéricos." />
                <small class="hint">Este código servirá para recuperar tu cuenta si olvidas tu contraseña.</small>
            </div>


                <button type="submit" class="register-button">Siguiente</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Paso 3 -->
        <?php if ($etapa === 3): ?>
        <div class="step">
            <form method="POST" action="register.php?etapa=3" id="datosUsuarioForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="grid-datos-personales">
                    <div class="form-group">
                        <label for="nombres">Nombres:</label>
                        <input type="text" id="nombres" name="nombres" required pattern="[A-Za-zÁÉÍÓÚÑáéíóúñ\s]{2,50}" title="Solo letras y espacios (mínimo 2, máximo 50)" maxlength="50">
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" id="apellidos" name="apellidos" required pattern="[A-Za-zÁÉÍÓÚÑáéíóúñ\s]{2,50}" title="Solo letras y espacios (mínimo 2, máximo 50)" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="tel" id="telefono" name="telefono" required pattern="[0-9]{9}" maxlength="9" title="Debe contener exactamente 9 números">
                    </div>
                </div>
                <button type="submit" class="register-button">Guardar y continuar</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Paso 4 -->
        <?php if ($etapa === 4): ?>
        <div class="step">
            <form method="POST" action="register.php?etapa=4" id="datosAdoptanteForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="grid-datos-personales">
                   <div class="form-group">
                        <label for="dni_cedula">DNI:</label>
                        <input  inputmode="numeric"  id="dni_cedula" name="dni_cedula" required pattern="[0-9]{8}" maxlength="8" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Debe contener exactamente 8 números">
                        <small class="hint">Debe contener exactamente 8 dígitos.</small>
                    </div>
            <div class="form-group">
                <label for="ciudad">Ciudad:</label>
                <input type="text"
                    id="ciudad"
                    name="ciudad"
                    required
                    pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                    title="Solo se permiten letras y espacios."
                    placeholder="Ej: Lima" />
            </div>

                <div class="form-group">
                    <label for="direccion_especifica">Dirección específica:</label>
                    <input type="text"
                        id="direccion_especifica"
                        name="direccion_especifica"
                        required
                        placeholder="Ej: Av. Los gatos 123" />
                </div>
                <div class="form-group full-width">
                    <label for="ocupacion">Ocupación:</label>
                    <input type="text"
                        id="ocupacion"
                        name="ocupacion"
                        required
                        pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                        title="Solo se permiten letras y espacios."
                        placeholder="Ej: Estudiante" />
                </div>
                </div>
                <button type="submit" class="register-button">Completar registro</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="/node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>
<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.src = isHidden ? '/assets/img/eye-slash.svg' : '/assets/img/eye.svg';
}

<?php if (!empty($error)): ?>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        title: 'Error',
        text: <?= json_encode($error) ?>,
        icon: 'error',
        confirmButtonText: 'Entendido'
    });
});
document.addEventListener("DOMContentLoaded", function () {
    const codigoInput = document.getElementById("codigo_recuperacion");

    codigoInput.addEventListener("input", function () {
        // Remueve cualquier carácter que no sea un número
        this.value = this.value.replace(/[^0-9]/g, '');

        // Limita a 6 dígitos
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
    });
});
document.addEventListener('DOMContentLoaded', function () {
    const formularioEtapa3 = document.getElementById('datosUsuarioForm');
    if (formularioEtapa3) {
        formularioEtapa3.addEventListener('submit', function (e) {
            const telefono = document.getElementById('telefono').value.trim();
            if (!/^\d{9,15}$/.test(telefono)) {
                e.preventDefault();
                Swal.fire({
                    title: 'Teléfono inválido',
                    text: 'El número debe contener entre 9 y 15 dígitos numéricos.',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            }
        });
    }
});
document.addEventListener('DOMContentLoaded', function () {
    const dniInput = document.getElementById('dni_cedula');
    if (dniInput) {
        dniInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d]/g, '').slice(0, 8);
        });
    }
});
// Solo letras y espacios en nombres y apellidos
['nombres', 'apellidos'].forEach(id => {
    document.getElementById(id).addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚÑáéíóúñ\s]/g, '');
    });
});

// Solo números en teléfono
document.getElementById('telefono').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);
});

// Solo números en DNI
document.getElementById('dni_cedula').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8);
});


// Solo letras y espacios para ciudad
document.getElementById('ciudad').addEventListener('input', function () {
    this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, '');
});

// Solo letras y espacios para ocupación
document.getElementById('ocupacion').addEventListener('input', function () {
    this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, '');
});

<?php endif; ?>
</script>