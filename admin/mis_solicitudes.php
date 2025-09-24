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

$id_administrador = $_SESSION['usuario']['id_usuario'];

// Obtener las solicitudes asignadas a este administrador
$sql = "
    SELECT 
        a.id_adopcion,
        a.estado,
        CONVERT(VARCHAR, a.fecha_solicitud, 103) AS fecha_solicitud_formateada,
        m.nombres as nombre_mascota,
        m.especie,
        m.raza,
        CONCAT(u.nombres, ' ', u.apellidos) AS nombre_adoptante,
        u.correo as correo_adoptante,
        u.telefono as telefono_adoptante
    FROM Adopcion a
    JOIN Mascota m ON a.id_mascota = m.id_mascota
    JOIN Usuario u ON a.id_adoptante = u.id_usuario
    WHERE a.id_administrador = ?
    ORDER BY a.fecha_solicitud DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([$id_administrador]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar solicitudes por estado para las estadísticas
$sql_estados = "
    SELECT 
        estado, 
        COUNT(*) as cantidad 
    FROM Adopcion 
    WHERE id_administrador = ?
    GROUP BY estado
";
$stmt_estados = $conn->prepare($sql_estados);
$stmt_estados->execute([$id_administrador]);
$estadisticas = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Preparar datos para el gráfico
$datos_grafico = [];
foreach ($estadisticas as $estadistica) {
    $datos_grafico[] = [
        'estado' => $estadistica['estado'],
        'cantidad' => $estadistica['cantidad'],
        'color' => $estadistica['estado'] == 'Aprobada' ? '#28a745' : 
                 ($estadistica['estado'] == 'Rechazada' ? '#dc3545' : 
                 ($estadistica['estado'] == 'Cancelada' ? '#6c757d' : '#ffc107'))
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Solicitudes | MichiHouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/mis_solicitudes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>Mis Solicitudes de Adopción
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="solicitudes_recibidas.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list me-1"></i> Todas las solicitudes
                        </a>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Distribución de mis solicitudes
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="estadosChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Resumen
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="card-subtitle mb-2 text-muted">Total de solicitudes</h6>
                                    <h3 class="card-text"><?= count($solicitudes) ?></h3>
                                </div>
                                <div class="row">
                                    <?php foreach ($estadisticas as $estadistica): ?>
                                        <div class="col-6 mb-2">
                                            <h6 class="card-subtitle mb-1 text-muted"><?= $estadistica['estado'] ?></h6>
                                            <h4 class="card-text">
                                                <?= $estadistica['cantidad'] ?>
                                                <small class="text-muted" style="font-size: 0.6em;">
                                                    (<?= round(($estadistica['cantidad'] / count($solicitudes)) * 100, 1) ?>%)
                                                </small>
                                            </h4>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Solicitudes -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list-ul me-2"></i>Solicitudes asignadas a mí
                            </h5>
                            <div>
                                <span class="badge bg-light text-dark">
                                    Mostrando <?= count($solicitudes) ?> solicitudes
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($solicitudes)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No tienes solicitudes asignadas actualmente.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha Solicitud</th>
                                            <th>Mascota</th>
                                            <th>Adoptante</th>
                                            <th>Contacto</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitudes as $solicitud): ?>
                                            <tr>
                                                <td>#<?= $solicitud['id_adopcion'] ?></td>
                                                <td><?= $solicitud['fecha_solicitud_formateada'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($solicitud['nombre_mascota']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($solicitud['especie']) ?> - <?= htmlspecialchars($solicitud['raza']) ?>
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($solicitud['nombre_adoptante']) ?></td>
                                                <td>
                                                    <small>
                                                        <?= htmlspecialchars($solicitud['correo_adoptante']) ?><br>
                                                        <?= htmlspecialchars($solicitud['telefono_adoptante']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                <span class="badge rounded-pill bg-<?= 
                                                    $solicitud['estado'] == 'Aprobada' ? 'success' : 
                                                    ($solicitud['estado'] == 'Rechazada' || $solicitud['estado'] == 'Cancelada' ? 'danger' : 'warning') 
                                                ?>">
                                                    <?= htmlspecialchars($solicitud['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($solicitud['estado'] == 'Aprobada'): ?>
                                                    <a href="descargar.php?id=<?= $solicitud['id_adopcion'] ?>" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    title="Descargar solicitud">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de estados
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('estadosChart').getContext('2d');
            const datos = <?= json_encode($datos_grafico) ?>;
            
            const labels = datos.map(item => item.estado);
            const data = datos.map(item => item.cantidad);
            const backgroundColors = datos.map(item => item.color);
            
            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>