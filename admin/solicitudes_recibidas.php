<?php
require('../includes/conexion.php');
require '../includes/header_admin.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Administrador') {
    header('Location: ../login.php');
    exit;
}

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Validación de parámetros de filtro
$estados_validos_adopcion = ['Solicitada', 'En revision', 'Aprobada', 'Rechazada', 'Cancelada'];
$filtro_estado = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos_adopcion) 
    ? $_GET['estado'] : 'todos';

$filtro_admin = isset($_GET['admin']) ? $_GET['admin'] : 'todos';

// Consulta base con JOIN a estado de mascota
$sql = "SELECT 
    a.id_adopcion,
    a.id_mascota,
    m.nombres as nombre_mascota,
    m.especie,
    m.raza,
    m.estado as estado_mascota,
    CONCAT(u.nombres, ' ', u.apellidos) as adoptante,
    a.fecha_solicitud,
    a.estado as estado_adopcion,
    a.id_administrador,
    CONCAT(admin.nombres, ' ', admin.apellidos) as administrador
FROM Adopcion a
JOIN Mascota m ON a.id_mascota = m.id_mascota
JOIN Usuario u ON a.id_adoptante = u.id_usuario
LEFT JOIN Usuario admin ON a.id_administrador = admin.id_usuario
WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filtro_estado !== 'todos') {
    $sql .= " AND a.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_admin !== 'todos') {
    if ($filtro_admin === 'sin_asignar') {
        $sql .= " AND a.id_administrador IS NULL";
    } else {
        $sql .= " AND a.id_administrador = ?";
        $params[] = $filtro_admin;
    }
}

// Ordenación basada en estados de adopción y mascota
$sql .= " ORDER BY 
    CASE a.estado
        WHEN 'Solicitada' THEN 1
        WHEN 'En revision' THEN 2
        WHEN 'Aprobada' THEN 3
        WHEN 'Rechazada' THEN 4
        WHEN 'Cancelada' THEN 5
        ELSE 6
    END,
    a.fecha_solicitud DESC";

$stmt_count = $conn->prepare($sql);
$stmt_count->execute($params);
$todas_solicitudes = $stmt_count->fetchAll(PDO::FETCH_ASSOC);
$total_registros = count($todas_solicitudes);
$total_paginas = ceil($total_registros / $por_pagina);


$solicitudes_paginadas = array_slice($todas_solicitudes, ($pagina - 1) * $por_pagina, $por_pagina);
$solicitudes = $solicitudes_paginadas;

// Obtener administradores para filtro
$stmt_admins = $conn->prepare("SELECT id_usuario, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                              FROM Usuario WHERE 'tipo_rol' = 'Administrador' ORDER BY nombres");
$stmt_admins->execute();
$administradores = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

// Procesar acción de revisión - Versión mínima
if (isset($_GET['action']) && $_GET['action'] == 'start_review' && isset($_GET['id'])) {
    $id_adopcion = (int)$_GET['id'];
    
    try {
        // Solo asignar administrador y cambiar estado básico
        $sql = "UPDATE Adopcion 
                SET estado = 'En revision', 
                    id_administrador = ? 
                WHERE id_adopcion = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['usuario']['id_usuario'], $id_adopcion]);
        
        // Redirigir directamente a la página de revisión
        header("Location: revisar_solicitud.php?id=".$id_adopcion);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al asignar administrador: " . $e->getMessage();
        header("Location: solicitudes_recibidas.php");
        exit();
    }
}

if (isset($_POST['accion']) && isset($_POST['id_adopcion'])) {
    $id_adopcion = (int)$_POST['id_adopcion'];
    $decision = $_POST['accion']; // 'aprobar' o 'rechazar'

    // Obtener el id_mascota asociado a esta adopción
    $sql_get_mascota = "SELECT id_mascota FROM Adopcion WHERE id_adopcion = ?";
    $stmt_get_mascota = $conn->prepare($sql_get_mascota);
    $stmt_get_mascota->execute([$id_adopcion]);
    $id_mascota = $stmt_get_mascota->fetchColumn();

    if (!$id_mascota) {
        // Manejar error: no se encontró la mascota
        header('Location: solicitudes_recibidas.php');
        exit;
    }

    $conn->beginTransaction();
    try {
        if ($decision == 'aprobar') {
            // 1. Actualizar estado de la solicitud de adopción a 'Aprobada'
            $sql_update_adopcion = "UPDATE Adopcion SET estado = 'Aprobada' WHERE id_adopcion = ?";
            $conn->prepare($sql_update_adopcion)->execute([$id_adopcion]);

            // 2. Actualizar estado de la mascota a 'Adoptado'
            $sql_update_mascota = "UPDATE Mascota SET estado = 'Adoptado' WHERE id_mascota = ?";
            $conn->prepare($sql_update_mascota)->execute([$id_mascota]);

            // 3. Rechazar automáticamente otras solicitudes pendientes para la misma mascota
            $sql_rechazar_otras = "UPDATE Adopcion SET estado = 'Rechazada' 
                                 WHERE id_mascota = ? AND estado IN ('Solicitada', 'En revision') AND id_adopcion != ?";
            $conn->prepare($sql_rechazar_otras)->execute([$id_mascota, $id_adopcion]);

            $_SESSION['success'] = "¡Adopción aprobada! El estado de la mascota ha sido actualizado.";

        } elseif ($decision == 'rechazar') {
            // 1. Actualizar estado de la solicitud a 'Rechazada'
            $sql_update_adopcion = "UPDATE Adopcion SET estado = 'Rechazada' WHERE id_adopcion = ?";
            $conn->prepare($sql_update_adopcion)->execute([$id_adopcion]);

            // 2. Comprobar si existen OTRAS solicitudes 'En revision' para la misma mascota.
            $sql_check_otras = "SELECT COUNT(*) FROM Adopcion 
                                WHERE id_mascota = ? AND estado = 'En revision'";
            $stmt_check = $conn->prepare($sql_check_otras);
            $stmt_check->execute([$id_mascota]);
            $otras_revisiones = $stmt_check->fetchColumn();

            // 3. Si no hay otras revisiones activas, la mascota vuelve a estar 'Disponible'
            if ($otras_revisiones == 0) {
                $sql_update_mascota = "UPDATE Mascota SET estado = 'Disponible' WHERE id_mascota = ?";
                $conn->prepare($sql_update_mascota)->execute([$id_mascota]);
            }
            
            $_SESSION['success'] = "Solicitud rechazada. El estado de la mascota ha sido verificado.";
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al procesar la decisión: " . $e->getMessage();
    }

    header('Location: solicitudes_recibidas.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Adopción | MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/solicitudes_recibidas.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-paw me-2"></i> Solicitudes de Adopción</h2>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado de Adopción</label>
                        <select id="estado" name="estado" class="form-select">
                            <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                            <?php foreach ($estados_validos_adopcion as $estado): ?>
                                <option value="<?= $estado ?>" <?= $filtro_estado === $estado ? 'selected' : '' ?>>
                                    <?= $estado ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="admin" class="form-label">Administrador</label>
                        <select id="admin" name="admin" class="form-select">
                            <option value="todos" <?= $filtro_admin === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="sin_asignar" <?= $filtro_admin === 'sin_asignar' ? 'selected' : '' ?>>Sin asignar</option>
                            <?php foreach ($administradores as $admin): ?>
                                <option value="<?= $admin['id_usuario'] ?>" <?= $filtro_admin == $admin['id_usuario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="solicitudes_recibidas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($solicitudes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No hay solicitudes que coincidan con los filtros seleccionados.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Mascota (Estado)</th>
                            <th>Adoptante</th>
                            <th>Fecha Solicitud</th>
                            <th>Estado Adopción</th>
                            <th>Administrador</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($solicitud['id_adopcion']) ?></td>
                                <td>
                                    <?= htmlspecialchars($solicitud['nombre_mascota']) ?>
                                    <br>
                                    <span class="badge bg-<?= 
                                        $solicitud['estado_mascota'] == 'Disponible' ? 'success' : 
                                        ($solicitud['estado_mascota'] == 'Revisión' ? 'warning' : 'secondary') ?>">
                                        <?= htmlspecialchars($solicitud['estado_mascota']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($solicitud['adoptante']) ?></td>
                                <td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></td>
                                <td>
                                    <?php 
                                    $badge_class = [
                                        'Solicitada' => 'info',
                                        'En revision' => 'warning',
                                        'Aprobada' => 'success',
                                        'Rechazada' => 'danger',
                                        'Cancelada' => 'secondary'
                                    ][$solicitud['estado_adopcion']] ?? 'light';
                                    ?>
                                    <span class="badge bg-<?= $badge_class ?>">
                                        <?= htmlspecialchars($solicitud['estado_adopcion']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $solicitud['id_administrador'] ? 
                                        htmlspecialchars($solicitud['administrador']) : 
                                        '<span class="text-muted">Sin asignar</span>' ?>
                                </td>
                                <td>
                                    <?php if ($solicitud['estado_adopcion'] == 'Solicitada' || $solicitud['estado_adopcion'] == 'En revision'): ?>
                                        <a href="revisar_solicitud.php?id=<?= $solicitud['id_adopcion'] ?>" 
                                        class="btn btn-sm <?= $solicitud['estado_adopcion'] == 'Solicitada' ? 'btn-warning' : 'btn-primary' ?>">
                                            <i class="fas fa-search"></i> 
                                            <?= $solicitud['estado_adopcion'] == 'Solicitada' ? 'Revisar' : 'Continuar' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= 
                                    http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltips = [].slice.call(document.querySelectorAll('[title]'));
            tooltips.map(function(el) {
                return new bootstrap.Tooltip(el);
            });
        });
    </script>
</body>
</html>