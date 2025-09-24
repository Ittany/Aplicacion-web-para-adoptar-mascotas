<?php
session_start(); 

require('../includes/conexion.php');
require('../includes/header_adoptante.php');

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Adoptante') {
    header('Location: ../login.php');
    exit;
}

// Función para calcular edad en meses
function calcularEdadMeses($fecha_nacimiento) {
    if (empty($fecha_nacimiento)) return 'Desconocida';
    
    $fecha = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $diferencia = $hoy->diff($fecha);
    
    $meses = ($diferencia->y * 12) + $diferencia->m;
    
    if ($meses == 0) {
        return $diferencia->d . ' días';
    } elseif ($meses < 12) {
        return $meses . ' meses';
    } else {
        $anos = floor($meses / 12);
        $meses_restantes = $meses % 12;
        return $anos . ' año' . ($anos > 1 ? 's' : '') . 
               ($meses_restantes > 0 ? ' y ' . $meses_restantes . ' mes' . ($meses_restantes > 1 ? 'es' : '') : '');
    }
}

$sql = "SELECT *, CONVERT(varchar, fecha_nacimiento, 23) as fecha_nacimiento FROM Mascota WHERE estado = 'Disponible'";
$params = [];
$filtros = [];

if (!empty($_GET['especie'])) {
    $sql .= " AND especie = ?";
    $params[] = $_GET['especie'];
    $filtros['especie'] = $_GET['especie'];
}

if (!empty($_GET['sexo'])) {
    $sql .= " AND sexo = ?";
    $params[] = $_GET['sexo'];
    $filtros['sexo'] = $_GET['sexo'];
}

if (isset($_GET['esterilizado']) && $_GET['esterilizado'] !== '') {
    $sql .= " AND esterilizado = ?";
    $params[] = (int)$_GET['esterilizado'];
    $filtros['esterilizado'] = (int)$_GET['esterilizado'];
}

if (!empty($_GET['rango_edad'])) {
    $rango = $_GET['rango_edad'];
    $hoy = new DateTime();
    
    switch ($rango) {
        case 'cachorro':
            $limite = (clone $hoy)->sub(new DateInterval('P6M'));
            $sql .= " AND fecha_nacimiento >= ?";
            $params[] = $limite->format('Y-m-d');
            break;
            
        case 'joven':
             $limite_sup = (clone $hoy)->sub(new DateInterval('P6M'));
            $limite_inf = (clone $hoy)->sub(new DateInterval('P2Y'));
            $sql .= " AND fecha_nacimiento BETWEEN ? AND ?";
            $params[] = $limite_inf->format('Y-m-d');
            $params[] = $limite_sup->format('Y-m-d');
            break;
            
        case 'adulto':
            $limite_sup = (clone $hoy)->sub(new DateInterval('P2Y'));
            $limite_inf = (clone $hoy)->sub(new DateInterval('P8Y'));
            $sql .= " AND fecha_nacimiento BETWEEN ? AND ?";
            $params[] = $limite_inf->format('Y-m-d');
            $params[] = $limite_sup->format('Y-m-d');
            break;
            
        case 'senior':
            $limite = (clone $hoy)->sub(new DateInterval('P8Y'));
            $sql .= " AND fecha_nacimiento <= ?";
            $params[] = $limite->format('Y-m-d');
            break;
    }
    
    $filtros['rango_edad'] = $rango;
}

$sql .= " ORDER BY fecha_ingreso DESC";

$mascotas = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al obtener las mascotas: " . $e->getMessage();
}

$especies = [];
try {
    $stmt = $conn->query("SELECT DISTINCT especie FROM Mascota WHERE estado = 'Disponible' ORDER BY especie");
    $especies = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    $mensaje_error = $mensaje_error ?? "Error al obtener especies: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mascotas Disponibles para Adopción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ver_mascotas.css">
</head>
<body>
    <div class="responsive-container">
        <div class="welcome-section text-center mb-4">
            <h1>Mascotas Disponibles para Adopción</h1>
            <p class="lead">Encuentra a tu compañero perfecto</p>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h4>
            </div>
            <div class="card-body">
                <form method="get" action="ver_mascotas.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="especie" class="form-label">Especie</label>
                        <select id="especie" name="especie" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($especies as $esp): ?>
                                <option value="<?= htmlspecialchars($esp) ?>" <?= isset($filtros['especie']) && $filtros['especie'] === $esp ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($esp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="sexo" class="form-label">Sexo</label>
                        <select id="sexo" name="sexo" class="form-select">
                            <option value="">Todos</option>
                            <option value="Macho" <?= isset($filtros['sexo']) && $filtros['sexo'] === 'Macho' ? 'selected' : '' ?>>Macho</option>
                            <option value="Hembra" <?= isset($filtros['sexo']) && $filtros['sexo'] === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="esterilizado" class="form-label">Esterilizado</label>
                        <select id="esterilizado" name="esterilizado" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" <?= isset($filtros['esterilizado']) && $filtros['esterilizado'] === 1 ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= isset($filtros['esterilizado']) && $filtros['esterilizado'] === 0 ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="rango_edad" class="form-label">Rango de Edad</label>
                        <select id="rango_edad" name="rango_edad" class="form-select">
                            <option value="">Todos</option>
                            <option value="cachorro" <?= isset($filtros['rango_edad']) && $filtros['rango_edad'] === 'cachorro' ? 'selected' : '' ?>>Cachorro (0-6 meses)</option>
                            <option value="joven" <?= isset($filtros['rango_edad']) && $filtros['rango_edad'] === 'joven' ? 'selected' : '' ?>>Joven (6 meses - 2 años)</option>
                            <option value="adulto" <?= isset($filtros['rango_edad']) && $filtros['rango_edad'] === 'adulto' ? 'selected' : '' ?>>Adulto (2-8 años)</option>
                            <option value="senior" <?= isset($filtros['rango_edad']) && $filtros['rango_edad'] === 'senior' ? 'selected' : '' ?>>Senior (+8 años)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> SÍ
                        </button>
                        <a href="ver_mascotas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> NO
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-paw me-2"></i>Mascotas Disponibles</h3>
                <div>
                    <span class="badge bg-light text-dark fs-6">
                        Total: <?= count($mascotas) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php 
                    $mensaje_error = $mensaje_error ?? false;
                    if ($mensaje_error): ?>
                        <div class="alert alert-danger"><?= $mensaje_error ?></div>
                    <?php elseif (empty($mascotas)): ?>
                        <div class="alert alert-info">No hay mascotas disponibles con los filtros aplicados.</div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($mascotas as $mascota): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <div class="position-relative">
                                            <?php if ($mascota['imagen_url'] !== 'sin_imagen.png'): ?>
                                                <img src="../<?= $mascota['imagen_url'] ?>" class="card-img-top" alt="<?= $mascota['nombres'] ?>">
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                    <i class="fas fa-paw fa-5x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge <?= $mascota['sexo'] === 'Hembra' ? 'sexo-hembra' : 'sexo-macho' ?>">
                                                    <?= $mascota['sexo'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($mascota['nombres']) ?></h5>
                                            <ul class="list-group list-group-flush mb-3">
                                                <li class="list-group-item">
                                                    <strong>Especie:</strong> <?= htmlspecialchars($mascota['especie']) ?>
                                                </li>
                                                <li class="list-group-item">
                                                    <strong>Raza:</strong> <?= htmlspecialchars($mascota['raza']) ?>
                                                </li>
                                                <li class="list-group-item">
                                                    <strong>Edad:</strong> 
                                                    <?= isset($mascota['fecha_nacimiento']) && $mascota['fecha_nacimiento'] 
                                                        ? calcularEdadMeses($mascota['fecha_nacimiento']) 
                                                        : 'Desconocida' ?>
                                                </li>
                                                <li class="list-group-item">
                                                    <strong>Esterilizado:</strong> 
                                                    <?= $mascota['esterilizado'] ? 'Sí' : 'No' ?>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="proceso_solicitud.php?id=<?= $mascota['id_mascota'] ?>" class="btn btn-success w-100">
                                                <i class="fas fa-heart me-2"></i>Solicitar Adopción
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </body>
</html>