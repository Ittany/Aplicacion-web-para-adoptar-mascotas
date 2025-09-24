<?php
require('../includes/conexion.php');
require '../includes/header_admin.php';

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Administrador') {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['usuario']['id_usuario'];

// Obtener datos del admin
$stmt_admin = $conn->prepare("SELECT * FROM Usuario WHERE id_usuario = ?");
$stmt_admin->execute([$admin_id]);
$admin_data = $stmt_admin->fetch(PDO::FETCH_ASSOC);

// Procesar verificación y actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verificar_codigo'])) {
        // Verificación del código
        if ($_POST['codigo_recuperacion'] === $admin_data['codigo_recuperacion']) {
            $_SESSION['codigo_verificado'] = true;
            $mensaje_exito = "Código verificado correctamente. Puede editar su perfil.";
        } else {
            $mensaje_error = "Código de recuperación incorrecto";
        }
    } elseif (isset($_POST['actualizar_perfil']) && $_SESSION['codigo_verificado']) {
        try {
            $contrasena_raw = trim($_POST['contrasena']);

            if (!empty($contrasena_raw)) {
                if (strlen($contrasena_raw) < 8) {
                    throw new PDOException("La contraseña debe tener al menos 8 caracteres");
                }
                $contrasena_hashed = password_hash($contrasena_raw, PASSWORD_DEFAULT);
            } else {
                $contrasena_hashed = $admin_data['contrasena']; // No se cambia
            }

            $stmt = $conn->prepare("EXEC ActualizarPerfilUsuario 
                @id_usuario = :id_usuario,
                @nombres = :nombres,
                @apellidos = :apellidos,
                @correo = :correo,
                @contrasena = :contrasena,
                @telefono = :telefono,
                @codigo_recuperacion = :codigo_recuperacion");

            $stmt->bindParam(':id_usuario', $admin_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombres', $_POST['nombres'], PDO::PARAM_STR);
            $stmt->bindParam(':apellidos', $_POST['apellidos'], PDO::PARAM_STR);
            $stmt->bindParam(':correo', $_POST['correo'], PDO::PARAM_STR);
            $stmt->bindParam(':contrasena', $contrasena_hashed, PDO::PARAM_STR);
            $stmt->bindParam(':telefono', $_POST['telefono'], PDO::PARAM_STR);
            $stmt->bindParam(':codigo_recuperacion', $_POST['nuevo_codigo_recuperacion'], PDO::PARAM_STR);

            $stmt->execute();


            // Refrescar datos
            $stmt_admin->execute([$admin_id]);
            $admin_data = $stmt_admin->fetch(PDO::FETCH_ASSOC);
            $_SESSION['codigo_verificado'] = false;

            $mensaje_exito = "Perfil actualizado correctamente";
        } catch (PDOException $e) {
            $mensaje_error = "Error al actualizar: " . $e->getMessage();
        }
    }

    
}

$total_adoptantes = $conn->query("SELECT COUNT(*) FROM Rol WHERE tipo_rol = 'Adoptante'")->fetchColumn();
$total_mascotas_adoptadas = $conn->query("SELECT COUNT(*) FROM Mascota WHERE estado = 'Adoptado'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="user-info">
                <div class="user-name">
                    <i class="fas fa-user-circle me-1"></i>
                    <?= htmlspecialchars($admin_data['nombres']) ?>
                </div>
                <a href="../logout.php" class="btn-logout" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            
            <h1>Bienvenido, <?= htmlspecialchars($admin_data['nombres'] . ' ' . $admin_data['apellidos']) ?></h1>
            <p>Panel de control administrativo</p>
            <div class="date-time" id="fechaHoraCliente"></div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_adoptantes ?></h3>
                    <p>Adoptantes registrados</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_mascotas_adoptadas ?></h3>
                    <p>Mascotas adoptadas</p>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <button class="btn-edit-profile" data-bs-toggle="modal" data-bs-target="#editarPerfilModal">
                <i class="fas fa-user-edit me-1"></i> Editar Perfil
            </button>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div class="modal fade" id="editarPerfilModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
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
                                        value="<?= htmlspecialchars($admin_data['nombres'] ?? '') ?>"
                                        required pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" title="Solo letras y espacios"
                                        oninput="this.value = this.value.replace(/[^a-zA-ZÁÉÍÓÚáéíóúÑñ\s]/g, '')">
                                </div>
                                <div class="col-md-6 mb-3">
                                <label class="form-label">Apellidos</label>
                                <input type="text" name="apellidos" class="form-control"
                                    value="<?= htmlspecialchars($admin_data['apellidos'] ?? '') ?>"
                                    required pattern="[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+" title="Solo letras y espacios"
                                    oninput="this.value = this.value.replace(/[^a-zA-ZÁÉÍÓÚáéíóúÑñ\s]/g, '')">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Correo</label>
                                <input type="email" name="correo" class="form-control" 
                                    value="<?= htmlspecialchars($admin_data['correo']) ?>" 
                                    pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                    title="Formato válido: usuario@dominio.com" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" name="telefono" class="form-control"
                                    value="<?= htmlspecialchars($admin_data['telefono']) ?>"
                                    pattern="\d{9}" maxlength="9" inputmode="numeric"
                                    oninput="this.value = this.value.replace(/\D/g, '')" required>
                                <small class="text-muted">Solo se permiten 9 dígitos numéricos.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña (opcional)</label>
                                <input type="password" name="contrasena" class="form-control" minlength="8">
                                <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nuevo Código Recuperación (opcional)</label>
                                <input type="text" name="nuevo_codigo_recuperacion" class="form-control" 
                                       pattern="\d{6}" maxlength="6">
                                <small class="text-muted">Debe contener exactamente 6 dígitos</small>
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
    // Validación de código de recuperación (solo números, máximo 6)
    document.querySelectorAll('input[name="codigo_recuperacion"], input[name="nuevo_codigo_recuperacion"]').forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) this.value = this.value.slice(0, 6);
        });
    });

    // Mostrar mensajes
    <?php if (isset($mensaje_exito)): ?>
        Swal.fire({
            title: 'Éxito',
            text: '<?= $mensaje_exito ?>',
            icon: 'success',
            confirmButtonColor: '#4361ee'
        }).then(() => {
            <?php if (isset($_SESSION['codigo_verificado']) && $_SESSION['codigo_verificado']): ?>
                $('#editarPerfilModal').modal('show');
            <?php endif; ?>
        });
    <?php elseif (isset($mensaje_error)): ?>
        Swal.fire({
            title: 'Error',
            text: '<?= $mensaje_error ?>',
            icon: 'error',
            confirmButtonColor: '#4361ee'
        }).then(() => {
            $('#editarPerfilModal').modal('show');
        });
    <?php endif; ?>

    // Confirmación antes de actualizar perfil
    const formEditar = document.querySelector('form[name="form_editar_perfil"]');
    if (formEditar) {
        formEditar.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validación: campos obligatorios
            const nombres = formEditar.querySelector('input[name="nombres"]');
            const apellidos = formEditar.querySelector('input[name="apellidos"]');

            if (!nombres.value.trim() || !apellidos.value.trim()) {
                Swal.fire({
                    title: 'Campos obligatorios',
                    text: 'Por favor, completa todos los campos requeridos.',
                    icon: 'warning',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            // Validación: nombres y apellidos solo letras
            const soloLetras = /^[A-Za-zÁÉÍÓÚÑáéíóúñ\s]+$/;
            if (!soloLetras.test(nombres.value) || !soloLetras.test(apellidos.value)) {
                Swal.fire({
                    title: 'Nombre inválido',
                    text: 'Nombres y apellidos deben contener solo letras.',
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            // Confirmación
            Swal.fire({
                title: '¿Guardar cambios?',
                text: 'Estás a punto de actualizar tu perfil',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    formEditar.submit();
                } else {
                    Swal.fire({
                        title: 'Cancelado',
                        text: 'No se realizaron cambios',
                        icon: 'info',
                        confirmButtonColor: '#4361ee'
                    });
                }
            });
        });
    }

    // Validar código de recuperación antes de enviar formulario
    const formCodigo = document.querySelector('form[name="form_codigo_recuperacion"]');
    if (formCodigo) {
        formCodigo.addEventListener('submit', function (e) {
            const codigo = formCodigo.querySelector('input[name="codigo_recuperacion"]');
            if (!codigo.value || codigo.value.length !== 6) {
                e.preventDefault();
                Swal.fire({
                    title: 'Código inválido',
                    text: 'El código de recuperación debe tener 6 dígitos numéricos.',
                    icon: 'warning',
                    confirmButtonColor: '#4361ee'
                });
            }
        });
    }

    // Actualizar fecha y hora en tiempo real
    function actualizarFechaHora() {
        const ahora = new Date();
        const opcionesFecha = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const opcionesHora = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const fecha = ahora.toLocaleDateString('es-ES', opcionesFecha);
        const hora = ahora.toLocaleTimeString('es-ES', opcionesHora);
        document.getElementById('fechaHoraCliente').textContent = `${fecha} - ${hora}`;
    }

    setInterval(actualizarFechaHora, 1000);
    actualizarFechaHora();
</script>
</body>
