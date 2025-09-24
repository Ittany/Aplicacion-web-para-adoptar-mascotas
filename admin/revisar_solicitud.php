<?php  
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../includes/conexion.php');
require '../includes/header_admin.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['tipo_rol'] ?? '') !== 'Administrador') {
    header("Location: login.php");
    exit;
}

// Validar ID de adopción
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: gestion_solicitudes.php');
    exit;
}

$id_adopcion = $_GET['id'];
$id_administrador = $_SESSION['usuario']['id_usuario'];

// Obtener datos completos de la solicitud
$stmt_adopcion = $conn->prepare("
    SELECT 
        a.*,
        m.*,
        u.nombres as nombres_adoptante,
        u.apellidos as apellidos_adoptante,
        u.correo as correo_adoptante,
        u.telefono as telefono_adoptante,
        ad.*,
        FORMAT(a.fecha_solicitud, 'dd/MM/yyyy') AS fecha_solicitud_formateada,
        FORMAT(a.fecha_adopcion, 'dd/MM/yyyy') AS fecha_adopcion_formateada,
        FORMAT(m.fecha_nacimiento, 'dd/MM/yyyy') AS fecha_nacimiento_formateada,
        FORMAT(m.fecha_ingreso, 'dd/MM/yyyy') AS fecha_ingreso_formateada,
        CONCAT(u.nombres, ' ', u.apellidos) AS nombre_adoptante_completo,
        CONCAT(admin.nombres, ' ', admin.apellidos) AS nombre_admin_completo
    FROM Adopcion a
    JOIN Mascota m ON a.id_mascota = m.id_mascota
    JOIN Usuario u ON a.id_adoptante = u.id_usuario
    JOIN Adoptante ad ON a.id_adoptante = ad.id_adoptante
    LEFT JOIN Usuario admin ON a.id_administrador = admin.id_usuario
    WHERE a.id_adopcion = ?
");

if (!$stmt_adopcion->execute([$id_adopcion])) {
    $error = $stmt_adopcion->errorInfo();
    die("Error al ejecutar consulta: " . $error[2]);
}

$solicitud = $stmt_adopcion->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La solicitud no existe'
        }).then(() => {
            window.location.href = 'gestion_solicitudes.php';
        });
    </script>";
    exit;
}

// Asignar administrador si no está asignado
if (empty($solicitud['id_administrador'])) {
    try {
        $conn->beginTransaction();
        
        $sql_asignar = "UPDATE Adopcion SET 
                        estado = 'En revision',
                        id_administrador = ?
                        WHERE id_adopcion = ?";
        
        $stmt_asignar = $conn->prepare($sql_asignar);
        if (!$stmt_asignar->execute([$id_administrador, $id_adopcion])) {
            $error = $stmt_asignar->errorInfo();
            throw new Exception("Error al asignar administrador: " . $error[2]);
        }
        
        $stmt_revision = $conn->prepare("UPDATE Mascota SET estado = 'Revisión' WHERE id_mascota = ?");
        if (!$stmt_revision->execute([$solicitud['id_mascota']])) {
            $error = $stmt_revision->errorInfo();
            throw new Exception("Error al actualizar mascota: " . $error[2]);
        }
        
        $conn->commit();

        // Refrescar datos
        $stmt_adopcion->execute([$id_adopcion]);
        $solicitud = $stmt_adopcion->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al asignar administrador: ".addslashes($e->getMessage())."'
            }).then(() => {
                window.location.href = 'gestion_solicitudes.php';
            });
        </script>";
        exit;
    }
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estado'])) {
    try {
        $conn->beginTransaction();
        
        $nuevo_estado = $_POST['estado'];
        $comentarios = trim($_POST['comentarios'] ?? '');

        $estados_permitidos = ['En revision', 'Aprobada', 'Rechazada', 'Cancelada'];
        if (!in_array($nuevo_estado, $estados_permitidos)) {
            throw new Exception("Estado no válido");
        }

        // Comentario con validación segura
        $comentario_actualizacion = "[".date('d/m/Y H:i')."] - Administrador: ";
        $comentario_actualizacion .= ($_SESSION['usuario']['nombres'] ?? 'Nombre no disponible') . " " .
                                     ($_SESSION['usuario']['apellidos'] ?? 'Apellido no disponible');
        $comentario_actualizacion .= "\nAcción: Cambio de estado a ".$nuevo_estado;
        if (!empty($comentarios)) {
            $comentario_actualizacion .= "\nComentario: ".$comentarios;
        }
        $comentario_actualizacion .= "\n----------------------------------------\n";

        // Actualizar Adopción
        $sql_update = "UPDATE Adopcion SET 
                      estado = ?,
                      comentarios = CONCAT(COALESCE(comentarios, ''), ?),
                      id_administrador = ?";
        
        $params = [$nuevo_estado, $comentario_actualizacion, $id_administrador];

        if ($nuevo_estado === 'Aprobada') {
            $sql_update .= ", fecha_adopcion = GETDATE()";

            $stmt_mascota = $conn->prepare("UPDATE Mascota SET 
                                          estado = 'Adoptado', 
                                          id_adoptante_usuario = ?,
                                          fecha_adopcion = GETDATE()
                                          WHERE id_mascota = ?");
            if (!$stmt_mascota->execute([$solicitud['id_adoptante'], $solicitud['id_mascota']])) {
                $error = $stmt_mascota->errorInfo();
                throw new Exception("Error al actualizar mascota: " . $error[2]);
            }

            $stmt_rechazar_otras = $conn->prepare("UPDATE Adopcion SET 
                                                 estado = 'Rechazada',
                                                 comentarios = CONCAT(COALESCE(comentarios, ''), 
                                                 CHAR(13)+CHAR(10), 'Rechazada automáticamente - Mascota aprobada en otra solicitud')
                                                 WHERE id_mascota = ? AND id_adopcion != ? AND estado = 'En revision'");
            $stmt_rechazar_otras->execute([$solicitud['id_mascota'], $id_adopcion]);
        } 
        elseif (in_array($nuevo_estado, ['Rechazada', 'Cancelada'])) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Adopcion 
                                        WHERE id_mascota = ? AND estado = 'En revision' AND id_adopcion != ?");
            $stmt_check->execute([$solicitud['id_mascota'], $id_adopcion]);
            $otras_revisiones = $stmt_check->fetchColumn();

            if ($otras_revisiones == 0) {
                $stmt_mascota = $conn->prepare("UPDATE Mascota SET 
                                              estado = 'Disponible',
                                              id_adoptante_usuario = NULL,
                                              fecha_adopcion = NULL
                                              WHERE id_mascota = ?");
                $stmt_mascota->execute([$solicitud['id_mascota']]);
            }
        }
        
        $sql_update .= " WHERE id_adopcion = ?";
        $params[] = $id_adopcion;
        
        $stmt = $conn->prepare($sql_update);
        if (!$stmt->execute($params)) {
            $error = $stmt->errorInfo();
            throw new Exception("Error al actualizar adopción: " . $error[2]);
        }
        
        $conn->commit();

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: '¡Actualizado!',
                text: 'Los cambios se han guardado correctamente',
                showConfirmButton: true
            }).then(() => {
                window.location.href = 'revisar_solicitud.php?id=".$id_adopcion."';
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al actualizar: ".addslashes($e->getMessage())."'
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión de Solicitud | MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/revisar_solicitud.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>Revisión de Solicitud de Adopción
            </h1>
            <a href="../admin/mis_solicitudes.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
        </div>

        <!-- Resumen de Solicitud -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-info-circle me-2"></i>Resumen de la Solicitud</h2>
                <span class="badge rounded-pill bg-<?= 
                    $solicitud['estado'] == 'Aprobada' ? 'success' : 
                    ($solicitud['estado'] == 'Rechazada' || $solicitud['estado'] == 'Cancelada' ? 'danger' : 'warning') 
                ?> badge-estado">
                    <?= htmlspecialchars($solicitud['estado']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="info-label">Fecha de solicitud:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['fecha_solicitud_formateada']) ?></span>
                        </p>
                        <?php if ($solicitud['fecha_adopcion_formateada']): ?>
                        <p><span class="info-label">Fecha de adopción:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['fecha_adopcion_formateada']) ?></span>
                        </p>
                        <?php endif; ?>
                        <p><span class="info-label">Administrador asignado:</span> 
                            <span class="info-value"><?= $solicitud['nombre_admin_completo'] ? htmlspecialchars($solicitud['nombre_admin_completo']) : 'Sin asignar' ?></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><span class="info-label">Compromiso del adoptante:</span></p>
                        <div class="bg-light p-3 rounded">
                            <pre class="mb-0"><?= htmlspecialchars($solicitud['compromiso_adoptante']) ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de la Mascota -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="mb-0"><i class="fas fa-paw me-2"></i>Información de la Mascota</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <?php if (!empty($solicitud['imagen_url'])): ?>
                            <img src="../<?= htmlspecialchars($solicitud['imagen_url']) ?>" 
                                alt="<?= htmlspecialchars($solicitud['nombres']) ?>" 
                                class="img-fluid pet-img mb-3">
                        <?php else: ?>
                            <img src="../img/default-pet.jpg" 
                                alt="Mascota sin imagen" 
                                class="img-fluid pet-img mb-3">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6">
                                <p><span class="info-label">Nombre de la mascota:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['nombres']) ?></span>
                                </p>
                                <p><span class="info-label">Especie:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['especie']) ?></span>
                                </p>
                                <p><span class="info-label">Raza:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['raza']) ?></span>
                                </p>
                                <p><span class="info-label">Sexo:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['sexo']) ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">Estado actual:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['estado']) ?></span>
                                </p>
                                <p><span class="info-label">Esterilizado:</span> 
                                    <span class="info-value"><?= $solicitud['esterilizado'] ? 'Sí' : 'No' ?></span>
                                </p>
                                <p><span class="info-label">Fecha de Ingreso:</span> 
                                    <span class="info-value"><?= htmlspecialchars($solicitud['fecha_ingreso_formateada']) ?></span>
                                </p>
                            </div>
                        </div>
                        <p><span class="info-label">Descripción:</span></p>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-0"><?= htmlspecialchars($solicitud['descripcion']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Adoptante -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="mb-0"><i class="fas fa-user me-2"></i>Información del Adoptante</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4><i class="fas fa-id-card me-2"></i>Datos Personales</h4>
                        <p><span class="info-label">Nombre completo:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['nombre_adoptante_completo']) ?></span>
                        </p>
                        <p><span class="info-label">Documento de identidad:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['dni_cedula']) ?></span>
                        </p>
                        <p><span class="info-label">Teléfono de contacto:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['telefono_adoptante']) ?></span>
                        </p>
                        <p><span class="info-label">Correo electrónico:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['correo_adoptante']) ?></span>
                        </p>
                        <p><span class="info-label">Fecha de Ingreso:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['fecha_ingreso_formateada']) ?></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h4><i class="fas fa-home me-2"></i>Datos de Vivienda</h4>
                        <p><span class="info-label">Dirección completa:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['direccion_especifica']) ?></span>
                        </p>
                        <p><span class="info-label">Ubicación:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['ciudad']) ?></span>
                        </p>
                        <p><span class="info-label">Ocupación:</span> 
                            <span class="info-value"><?= htmlspecialchars($solicitud['ocupacion']) ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de Revisión -->
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Revisión Administrativa</h2>
            </div>
            <div class="card-body">
                <form method="POST" id="formRevision">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="estado" class="form-label info-label">Estado de la Solicitud:</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="En revision" <?= $solicitud['estado'] === 'En revision' ? 'selected' : '' ?>>En Revisión</option>
                                <option value="Aprobada" <?= $solicitud['estado'] === 'Aprobada' ? 'selected' : '' ?>>Aprobar</option>
                                <option value="Rechazada" <?= $solicitud['estado'] === 'Rechazada' ? 'selected' : '' ?>>Rechazar</option>
                                <option value="Cancelada" <?= $solicitud['estado'] === 'Cancelada' ? 'selected' : '' ?>>Cancelar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="comentarios" class="form-label info-label">Comentarios:</label>
                            <textarea class="form-control" id="comentarios" name="comentarios" rows="4" 
                                      placeholder="Agregar comentarios sobre la revisión..."></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard_admin.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <h4 class="mb-3"><i class="fas fa-history me-2"></i>Historial de Comentarios:</h4>
                <div class="comentarios-historial">
                    <?php if (!empty($solicitud['comentarios'])): ?>
                        <pre><?= htmlspecialchars($solicitud['comentarios']) ?></pre>
                    <?php else: ?>
                        <p class="text-muted">No hay comentarios registrados</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('formRevision').addEventListener('submit', function(e) {
            const estado = document.getElementById('estado').value;
            
            if (estado === 'Aprobada') {
                e.preventDefault();
                Swal.fire({
                    title: '¿Confirmar aprobación?',
                    html: '<p>Al aprobar esta solicitud:</p>' +
                          '<ul>' +
                          '<li>El estado de la mascota cambiará a <b>Adoptado</b></li>' +
                          '<li>Se registrará la fecha actual como fecha de adopción</li>' +
                          '<li>Todas las demás solicitudes para esta mascota serán rechazadas</li>' +
                          '<li>Esta acción no se puede deshacer</li>' +
                          '</ul>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aprobar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('formRevision').submit();
                    }
                });
            }
            
            if (estado === 'Rechazada' || estado === 'Cancelada') {
                e.preventDefault();
                Swal.fire({
                    title: '¿Confirmar acción?',
                    html: '<p>Al cambiar el estado a <b>' + estado + '</b>:</p>' +
                          '<ul>' +
                          '<li>La solicitud será marcada como ' + estado + '</li>' +
                          '<li>Si no hay otras solicitudes en revisión, la mascota volverá a estar disponible</li>' +
                          '</ul>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('formRevision').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>