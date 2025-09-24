<?php 

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../includes/conexion.php');
require '../includes/header_adoptante.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Adoptante') {
    header("Location: login.php");
    exit;
}

// Validar ID de mascota
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ver_mascotas.php');
    exit;
}

$id_mascota = $_GET['id'];
$id_adoptante = $_SESSION['usuario']['id_usuario'];

$stmt_verificar = $conn->prepare("SELECT estado FROM Adopcion WHERE id_mascota = ? AND id_adoptante = ?");
$stmt_verificar->execute([$id_mascota, $id_adoptante]);
$solicitud_existente = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

if ($solicitud_existente) {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Solicitud Existente</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    </head>
    <body>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
                icon: 'info',
                title: 'Solicitud Existente',
                html: '<p>Ya has solicitado esta mascota.</p><p><b>Estado actual:</b> <?= htmlspecialchars($solicitud_existente['estado']) ?></p>',
                confirmButtonText: 'Ver mis solicitudes',
                showCancelButton: true,
                cancelButtonText: 'Volver al listado',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'solicitudes_enviadas.php';
                } else {
                    window.location.href = 'ver_mascotas.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

$stmt_usuario = $conn->prepare("SELECT nombres, apellidos, correo, telefono FROM Usuario WHERE id_usuario = ?");

$stmt_usuario->execute([$id_adoptante]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

$stmt_adoptante = $conn->prepare("SELECT * FROM Adoptante WHERE id_adoptante = ?");
$stmt_adoptante->execute([$id_adoptante]);
$datos_adoptante = $stmt_adoptante->fetch(PDO::FETCH_ASSOC);

// Obtener datos completos de la mascota
$stmt_mascota = $conn->prepare("SELECT *, 
    CONVERT(VARCHAR, fecha_nacimiento, 103) AS fecha_nacimiento_formateada,
    CONVERT(VARCHAR, fecha_ingreso, 103) AS fecha_ingreso_formateada
    FROM Mascota WHERE id_mascota = ?");
$stmt_mascota->execute([$id_mascota]);
$mascota = $stmt_mascota->fetch(PDO::FETCH_ASSOC);

if (!$mascota) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La mascota no existe'
        }).then(() => {
            window.location.href = '../adoptante/ver_mascotas.php';
        });
    </script>";
    exit;
}

$compromiso_default = "Me comprometo a:\n\n"
    . "1. Proporcionar alimentación adecuada y agua fresca diariamente\n"
    . "2. Brindar atención veterinaria regular y en caso de enfermedad\n"
    . "3. Ofrecer un espacio seguro y cómodo en mi hogar\n"
    . "4. Proporcionar amor, cuidado y tiempo de calidad\n"
    . "5. Notificar cualquier cambio importante en mi situación\n\n"
    . "Además, específicamente para ".htmlspecialchars($mascota['nombres']).", planeo: ";

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $sql = "INSERT INTO Adopcion (
            id_mascota, 
            id_adoptante, 
            fecha_solicitud, 
            compromiso_adoptante,
            estado,
            comentarios
        ) VALUES (?, ?, GETDATE(), ?, 'Solicitada', ?)";
        
        $comentario_inicial = "Solicitud creada el " . date('d/m/Y H:i');
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $id_mascota,
            $id_adoptante,
            $_POST['compromiso'],
            $comentario_inicial
        ]);
        
        $conn->commit();

       echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '¡Solicitud Registrada!',
                    html: '<p>Tu solicitud para <b>".htmlspecialchars($mascota['nombres'])."</b> ha sido recibida.</p><p>Te notificaremos cuando haya novedades.</p>',
                    confirmButtonText: 'Ver mis solicitudes',
                    showCancelButton: true,
                    cancelButtonText: 'Volver al listado',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../adoptante/solicitudes_enviadas.php';
                    } else {
                        window.location.href = '../adoptante/ver_mascotas.php';
                    }
                });
            });
        </script>";
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al procesar la solicitud: ".addslashes($e->getMessage())."',
                    confirmButtonText: 'Entendido'
                });
            });
        </script>";
    }
}

if ($solicitud_existente) {
    die("<script>alert('Ya has solicitado esta mascota'); window.location.href='ver_mascotas.php';</script>");
}
function calcularEdad($fecha) {
    if (empty($fecha)) return 'Desconocida';
    $diff = date_diff(date_create($fecha), date_create());
    return $diff->format('%y años, %m meses');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Adopción | MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/proceso_solicitud.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>

    <div class="container py-4">
        <form method="POST" action="">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-paw me-2"></i> Solicitud de Adopción</h2>
                        <p class="lead">Completa tu compromiso para adoptar a <?= htmlspecialchars($mascota['nombres']) ?></p>
                    </div>

                <!-- Tus Datos -->
                <div class="card card-section mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i> Tus Datos</h5>
                    </div>
                    <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="info-label">Nombre Completo</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="info-label">Documento de Identidad</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($datos_adoptante['dni_cedula'] ?? 'No especificado') ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="info-label">Ciudad</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($datos_adoptante['ciudad'] ?? 'No especificada') ?>" readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="info-label">Dirección Completa</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($datos_adoptante['direccion_especifica'] ?? $usuario['direccion'] ?? 'No especificada') ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="info-label">Ocupación</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($datos_adoptante['ocupacion'] ?? 'No especificada') ?>" readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="info-label">Correo Electrónico</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($usuario['correo']) ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="info-label">Teléfono</label>
                        <input type="text" class="form-control readonly-field" 
                            value="<?= htmlspecialchars($usuario['telefono']) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos de la Mascota -->
            <div class="card card-section mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-paw me-2"></i> Datos de la Mascota</h5>
                </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="mascota-img-container">
                                    <?php if (!empty($mascota['imagen_url'])): ?>
                                        <img src="../<?= htmlspecialchars($mascota['imagen_url']) ?>" 
                                             class="mascota-img" alt="<?= htmlspecialchars($mascota['nombres']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-paw fa-5x text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="badge bg-<?= $mascota['estado'] == 1 ? 'success' : 'warning' ?>">
                                        <?= $mascota['estado'] == 1 ? 'Disponible' : 'En proceso' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="info-label">Nombre</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= htmlspecialchars($mascota['nombres']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="info-label">Especie/Raza</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= htmlspecialchars($mascota['especie'] . ' / ' . $mascota['raza']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="info-label">Sexo</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= htmlspecialchars($mascota['sexo']) ?>" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="info-label">Esterilizado</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= $mascota['esterilizado'] ? 'Sí' : 'No' ?>" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="info-label">Color</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= htmlspecialchars($mascota['color'] ?? 'No especificado') ?>" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="info-label">Edad</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= calcularEdad($mascota['fecha_nacimiento']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="info-label">Fecha Nacimiento</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= $mascota['fecha_nacimiento_formateada'] ?? 'Desconocida' ?>" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="info-label">Descripción</label>
                                        <textarea class="form-control readonly-field" rows="3" readonly><?= htmlspecialchars($mascota['descripcion'] ?? 'Sin descripción disponible') ?></textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="info-label">Fecha de Ingreso</label>
                                        <input type="text" class="form-control readonly-field" 
                                            value="<?= $mascota['fecha_ingreso_formateada'] ?? 'Desconocida' ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

             <!-- Sección de compromiso -->
                    <div class="card card-section mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-handshake me-2"></i> Tu Compromiso</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="compromiso" class="form-label">Describe cómo cuidarás a <?= htmlspecialchars($mascota['nombres']) ?>:</label>
                                <textarea class="form-control" id="compromiso" name="compromiso" rows="8" required><?= htmlspecialchars($compromiso_default) ?></textarea>
                                <div class="form-text">Por favor, sé lo más detallado posible (mínimo 200 caracteres).</div>
                                <small id="char-counter" class="form-text text-end d-block">0/200 caracteres</small>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terminos" name="terminos" required>
                                <label class="form-check-label" for="terminos">
                                    Acepto los <a href="#" data-bs-toggle="modal" data-bs-target="#modalTerminos">términos y condiciones</a> de adopción
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mb-5">
                        <a href="ver_mascotas.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i> Volver
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> Enviar Solicitud
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Términos -->
    <div class="modal fade" id="modalTerminos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Términos y Condiciones de Adopción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Compromisos del Adoptante:</h6>
                    <ol>
                        <li>Proporcionar alimentación adecuada y agua fresca diariamente.</li>
                        <li>Brindar atención veterinaria regular y en caso de enfermedad.</li>
                        <li>Mantener un ambiente seguro, limpio y enriquecido.</li>
                        <li>No utilizar al animal para experimentos, peleas o reproducción indiscriminada.</li>
                        <li>Notificar cualquier cambio de domicilio o situación que afecte al animal.</li>
                        <li>Permitir visitas de seguimiento por parte del refugio.</li>
                    </ol>
                    <h6 class="mt-4">Políticas de MichiHouse:</h6>
                    <ul>
                        <li>Nos reservamos el derecho de realizar seguimientos post-adopción.</li>
                        <li>Podemos rechazar solicitudes sin necesidad de explicación.</li>
                        <li>El incumplimiento de los términos puede resultar en la recuperación del animal.</li>
                        <li>El adoptante asume todos los costos asociados al cuidado del animal.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Validación del formulario antes de enviar
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const compromiso = document.getElementById('compromiso');
                const terminos = document.getElementById('terminos');
                
                if (compromiso.value.length < 200) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Compromiso incompleto',
                        text: 'Por favor, escribe al menos 200 caracteres en tu compromiso',
                        confirmButtonText: 'Entendido'
                    });
                    compromiso.focus();
                    return;
                }
                
                if (!terminos.checked) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Términos no aceptados',
                        text: 'Debes aceptar los términos y condiciones para continuar',
                        confirmButtonText: 'Entendido'
                    });
                    terminos.focus();
                    return;
                }
                
                // Mostrar carga mientras se procesa
                Swal.fire({
                    title: 'Procesando tu solicitud',
                    html: 'Por favor espera...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            });
        }
    });
    </script>
</body>
</html>