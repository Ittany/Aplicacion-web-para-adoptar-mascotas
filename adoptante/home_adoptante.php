<?php
session_start();
require '../includes/conexion.php';
require '../includes/header_adoptante.php';

// Verifica si el usuario ha iniciado sesión correctamente como Adoptante
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Adoptante') {
    header("Location: ../login.php");
    exit;
}

$id_usuario = $_SESSION['usuario']['id_usuario'] ?? null;

    // Cargar datos combinados desde Usuario y Adoptante (id_usuario = id_adoptante)
    $stmt = $conn->prepare("
        SELECT u.id_usuario, u.nombres, u.apellidos, u.correo, u.telefono, u.codigo_recuperacion,
            a.dni_cedula, a.ciudad, a.direccion_especifica, a.ocupacion
        FROM Usuario u
        INNER JOIN Adoptante a ON u.id_usuario = a.id_adoptante
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    $adoptante_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adoptante_data) {
        $_SESSION['usuario']['nombres'] = $adoptante_data['nombres'];
        $_SESSION['usuario']['apellidos'] = $adoptante_data['apellidos'];
    }

    // Mensajes opcionales (por ejemplo, después de editar el perfil)
    $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
    $mensaje_error = $_SESSION['mensaje_error'] ?? null;
    unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['verificar_codigo'])) {
                $codigo_ingresado = trim($_POST['codigo_recuperacion']);
                if ($codigo_ingresado === $adoptante_data['codigo_recuperacion']) {
                    $_SESSION['codigo_verificado'] = true;
                    $_SESSION['mensaje_exito'] = 'Código verificado correctamente';
                } else {
                    $_SESSION['codigo_verificado'] = false;
                    $_SESSION['mensaje_error'] = 'El código ingresado es incorrecto';
                }
                header('Location: home_adoptante.php');
                exit;
            }

            if (isset($_POST['actualizar_perfil']) && ($_SESSION['codigo_verificado'] ?? false) === true) {
                try {
            $datos_actuales = $adoptante_data;

            // Campos básicos con fallback
            $nombres = trim($_POST['nombres']) ?: $datos_actuales['nombres'];
            $apellidos = trim($_POST['apellidos']) ?: $datos_actuales['apellidos'];
            $correo = trim($_POST['correo']) ?: $datos_actuales['correo'];
            $telefono = trim($_POST['telefono']) ?: $datos_actuales['telefono'];
            $dni = trim($_POST['dni_cedula']) ?: $datos_actuales['dni_cedula'];
            $ciudad = trim($_POST['ciudad']) ?: $datos_actuales['ciudad'];
            $direccion = trim($_POST['direccion_especifica']) ?: $datos_actuales['direccion_especifica'];
            $ocupacion = trim($_POST['ocupacion']) ?: $datos_actuales['ocupacion'];
            $contrasena = isset($_POST['contrasena']) ? trim($_POST['contrasena']) : '';
            $nuevo_codigo = trim($_POST['nuevo_codigo_recuperacion'] ?? '');

            // Validaciones básicas
            if (!preg_match('/^\d{8}$/', $dni)) {
                throw new Exception('DNI/Cédula inválido. Debe tener 8 dígitos numéricos.');
            }

            if (!preg_match('/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]*$/', $ciudad)) {
                throw new Exception('Ciudad inválida. Solo se permiten letras y espacios.');
            }

            if (!preg_match('/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]*$/', $ocupacion)) {
                throw new Exception('Ocupación inválida. Solo se permiten letras y espacios.');
            }

            if (!preg_match('/^\d{9}$/', $telefono)) {
                throw new Exception('Teléfono inválido. Debe contener exactamente 9 dígitos.');
            }

            // Validar longitud de la contraseña (si se ha ingresado una nueva)
            if (!empty($contrasena) && strlen($contrasena) < 8) {
                throw new Exception('La contraseña debe tener al menos 8 caracteres');
            }

            // Preparar consulta para actualizar Usuario
            $sqlUsuario = "UPDATE Usuario SET nombres = ?, apellidos = ?, correo = ?, telefono = ?";
            $paramsUsuario = [$nombres, $apellidos, $correo, $telefono];

            if (!empty($contrasena)) {
                $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $sqlUsuario .= ", contrasena = ?";
                $paramsUsuario[] = $hash;
            }

            if ($nuevo_codigo !== '') {
                $sqlUsuario .= ", codigo_recuperacion = ?";
                $paramsUsuario[] = $nuevo_codigo;
            }

            $sqlUsuario .= " WHERE id_usuario = ?";
            $paramsUsuario[] = $id_usuario;

            $stmtUsuario = $conn->prepare($sqlUsuario);
            $stmtUsuario->execute($paramsUsuario);

            // Actualizar Adoptante
            $sqlAdoptante = "UPDATE Adoptante SET dni_cedula = ?, ciudad = ?, direccion_especifica = ?, ocupacion = ? WHERE id_adoptante = ?";
            $stmtAdoptante = $conn->prepare($sqlAdoptante);
            $stmtAdoptante->execute([$dni, $ciudad, $direccion, $ocupacion, $id_adoptante]);

            $_SESSION['mensaje_exito'] = 'Perfil actualizado correctamente';
            unset($_SESSION['codigo_verificado']);
        } catch (Exception $e) {
            $_SESSION['mensaje_error'] = $e->getMessage();
        } catch (PDOException $e) {
            $_SESSION['mensaje_error'] = 'Error al actualizar perfil: ' . $e->getMessage();
        }

        header('Location: home_adoptante.php');
        exit;
            }
    }
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="../assets/css/home_adoptante.css">
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-header">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
               <span>
                <?php 
                    echo htmlspecialchars(($_SESSION['usuario']['nombres'] ?? '') . ' ' . ($_SESSION['usuario']['apellidos'] ?? '')); 
                ?>
            </span>

            </div>
            
            <h2><i class="fas fa-cat"></i> Bienvenido a MichiHouse</h2>
           <p>
                Hola <strong>
                    <?php 
                        echo htmlspecialchars(($_SESSION['usuario']['nombres'] ?? '') . ' ' . ($_SESSION['usuario']['apellidos'] ?? '')); 
                    ?>
                </strong>, estamos felices de tenerte aquí
            </p>
        </div>
        
        <div class="action-cards">
            <div class="action-card">
                <i class="fas fa-paw"></i>
                <h3>Ver Mascotas</h3>
                <p>Explora nuestra lista de adorables animalitos que buscan un hogar lleno de amor</p>
                <a href="ver_mascotas.php" class="btn-michihouse">Ver disponibles</a>
            </div>
            
            <div class="action-card">
                <i class="fas fa-user-edit"></i>
                <h3>Mi Perfil</h3>
                <p>Actualiza tu información personal y preferencias de adopción</p>
                <button class="btn-michihouse" data-bs-toggle="modal" data-bs-target="#editarPerfilModal">
                    Editar perfil
                </button>
            </div>
        </div>
        
        <div class="text-center">
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
            </a>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div class="modal fade" id="editarPerfilModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Editar Perfil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php if (!isset($_SESSION['codigo_verificado']) || !$_SESSION['codigo_verificado']): ?>
                            <!-- Paso 1: Verificación -->
                            <div class="mb-3">
                                <label class="form-label">Ingrese su código de recuperación (6 dígitos)</label>
                                <input type="text" name="codigo_recuperacion" class="form-control" 
                                       pattern="\d{6}" maxlength="6" required>
                            </div>
                            <input type="hidden" name="verificar_codigo" value="1">
                        <?php else: ?>
                            <!-- Paso 2: Edición -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombres</label>
                                    <input type="text" name="nombres" class="form-control"
                                        value="<?= htmlspecialchars($adoptante_data['nombres'] ?? '') ?>"
                                        required pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" title="Solo letras y espacios"
                                        oninput="this.value = this.value.replace(/[^a-zA-ZÁÉÍÓÚáéíóúÑñ\s]/g, '')">
                                </div>
                                <div class="col-md-6 mb-3">
                                <label class="form-label">Apellidos</label>
                                <input type="text" name="apellidos" class="form-control"
                                    value="<?= htmlspecialchars($adoptante_data['apellidos'] ?? '') ?>"
                                    required pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" title="Solo letras y espacios"
                                    oninput="this.value = this.value.replace(/[^a-zA-ZÁÉÍÓÚáéíóúÑñ\s]/g, '')">
                            </div>

                            </div>
                           <div class="mb-3">
                                <label class="form-label">Correo</label>
                                <input type="email" name="correo" class="form-control"
                                    value="<?= htmlspecialchars($adoptante_data['correo']) ?>" 
                                    required
                                    pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                    title="Ingrese un correo válido, por ejemplo: ejemplo@dominio.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" name="telefono" class="form-control"
                                    value="<?= htmlspecialchars($adoptante_data['telefono']) ?>"
                                    pattern="\d{9}" maxlength="9" inputmode="numeric"
                                    oninput="this.value = this.value.replace(/\D/g, '')" required>
                                <small class="text-muted">Solo se permiten 9 dígitos numéricos.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña (opcional)</label>
                                <input type="password" name="contrasena" class="form-control">
                                <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nuevo Código Recuperación (opcional)</label>
                                <input type="text" name="nuevo_codigo_recuperacion" class="form-control" 
                                       pattern="\d{6}" maxlength="6">
                                <small class="text-muted">Debe contener exactamente 6 dígitos</small>
                            </div>
                            <!-- Datos del Adoptante -->
                            <div class="mb-3">
                                <label class="form-label">DNI</label>
                                <input type="text" name="dni_cedula" class="form-control"
                                    value="<?= htmlspecialchars($adoptante_data['dni_cedula']) ?>"
                                    pattern="\d{8}" maxlength="8" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '')">
                                <small class="text-muted">Opcional: puedes dejarlo en blanco si lo prefieres. Solo se permiten 8 dígitos numéricos.</small>
                            </div>

                           <div class="mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" name="ciudad" class="form-control" 
                                    value="<?= htmlspecialchars($adoptante_data['ciudad']) ?>"
                                    pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" 
                                    title="Solo letras y espacios">
                                    <small class="text-muted">Opcional: puedes dejarlo en blanco si lo prefieres.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dirección Específica</label>
                                <input type="text" name="direccion_especifica" class="form-control"
                                    value="<?= htmlspecialchars($adoptante_data['direccion_especifica']) ?>">
                                <small class="text-muted">Opcional: puedes dejarlo en blanco si lo prefieres.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ocupación</label>
                                <input type="text" name="ocupacion" class="form-control" 
                                    value="<?= htmlspecialchars($adoptante_data['ocupacion']) ?>"
                                    pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" 
                                    title="Solo letras y espacios">
                                    <small class="text-muted">Opcional: puedes dejarlo en blanco si lo prefieres.</small>
                            </div>

                            <input type="hidden" name="actualizar_perfil" value="1">
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <?= (!isset($_SESSION['codigo_verificado']) || !$_SESSION['codigo_verificado']) ? 'Verificar' : 'Guardar Cambios' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Validar código recuperación (solo números, máx. 6 dígitos)
    document.querySelector('input[name="codigo_recuperacion"]')?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });

    <?php if ($mensaje_exito): ?>
        Swal.fire({
            title: 'Éxito',
            text: '<?= $mensaje_exito ?>',
            icon: 'success',
            confirmButtonColor: '#28a745'
        });
    <?php elseif ($mensaje_error): ?>
        Swal.fire({
            title: 'Error',
            text: '<?= $mensaje_error ?>',
            icon: 'error',
            confirmButtonColor: '#d33'
        }).then(() => {
            const editarPerfilModal = new bootstrap.Modal(document.getElementById('editarPerfilModal'));
            editarPerfilModal.show();
        });
    <?php endif; ?>

    // Confirmación antes de enviar el formulario
    document.querySelector('form[name="form_editar_perfil"]')?.addEventListener('submit', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '¿Guardar cambios?',
            text: '¿Deseas actualizar tu perfil?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33'
        }).then(result => {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    });
</script>