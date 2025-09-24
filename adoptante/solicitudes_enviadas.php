<?php
session_start();
require('../includes/conexion.php');
require '../includes/header_adoptante.php';

// Verifica si el usuario ha iniciado sesión correctamente como Adoptante
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Adoptante') {
    header("Location: ../login.php");
    exit;
}

$id_adoptante = $_SESSION['usuario']['id_usuario'];

// Manejar filtro de estado
$estado_filtro = $_GET['estado'] ?? 'todos';

// Llamar al procedimiento almacenado
$stmt = $conn->prepare("EXEC ObtenerSolicitudesPorAdoptante :id_adoptante, :estado");
$stmt->bindValue(':id_adoptante', $id_adoptante, PDO::PARAM_INT);
$stmt->bindValue(':estado', $estado_filtro !== 'todos' ? $estado_filtro : null, PDO::PARAM_STR);
$stmt->execute();
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes Recibidas | MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/solicitudes_enviadas.css">
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-inbox me-2"></i> Solicitudes de Adopción Enviadas</h2>
                <p class="lead">Administra las solicitudes de adopción enviadas</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filtro por estado -->
        <div class="row mb-4">
            <div class="col-md-4">
                <form method="get" class="row g-2">
                    <div class="col-md-8">
                        <select name="estado" class="form-select" onchange="this.form.submit()">
                            <option value="todos" <?= $estado_filtro === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                            <option value="Solicitada" <?= $estado_filtro === 'Solicitada' ? 'selected' : '' ?>>Solicitada</option>
                            <option value="En revision" <?= $estado_filtro === 'En revision' ? 'selected' : '' ?>>En revision</option>
                            <option value="Aprobada" <?= $estado_filtro === 'Aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                            <option value="Rechazada" <?= $estado_filtro === 'Rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <a href="solicitudes_enviadas.php" class="btn btn-outline-secondary">Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($solicitudes)): ?>
            <div class="alert alert-info">
                No hay solicitudes de adopción <?= $estado_filtro !== 'todos' ? 'con estado ' . $estado_filtro : '' ?>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Mascota</th>
                            <th>Especie/Raza</th>
                            <th>Adoptante</th>
                            <th>Fecha Solicitud</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td><?= htmlspecialchars($solicitud['id_adopcion']) ?></td>
                                <td>
                                    <a href="../adoptante/ver_mascotas.php?id=<?= $solicitud['id_mascota'] ?>">
                                        <?= htmlspecialchars($solicitud['nombre_mascota']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($solicitud['especie'] . ' / ' . $solicitud['raza']) ?></td>
                                <td>
                                    <?= htmlspecialchars($solicitud['nombre_adoptante'] . ' ' . $solicitud['apellido_adoptante']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($solicitud['correo_adoptante']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    switch($solicitud['estado']) {
                                        case 'Pendiente': $badge_class = 'bg-warning'; break;
                                        case 'Aprobada': $badge_class = 'bg-success'; break;
                                        case 'Rechazada': $badge_class = 'bg-danger'; break;
                                        case 'En revision': $badge_class = 'bg-info'; break;
                                        default: $badge_class = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($solicitud['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($solicitud['comentarios'] ?? 'Sin comentarios') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>