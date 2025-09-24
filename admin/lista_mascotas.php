<?php
require('../includes/conexion.php');
require '../includes/header_admin.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Administrador') {
    header('Location: ../login.php');
    exit;
}

// Procesar eliminación de mascota
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        $id_mascota = $_GET['eliminar'];
        
        // Primero obtenemos la información de la imagen para eliminarla del servidor
        $stmt = $conn->prepare("SELECT imagen_url FROM Mascota WHERE id_mascota = ?");
        $stmt->execute([$id_mascota]);
        $mascota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mascota && $mascota['imagen_url'] !== 'sin_imagen.png' && file_exists('../' . $mascota['imagen_url'])) {
            unlink('../' . $mascota['imagen_url']);
        }
        
        // Usamos el procedimiento almacenado para eliminar mascota con sus relaciones
        $stmt = $conn->prepare("EXEC sp_eliminar_mascota @id_mascota = ?");
        $stmt->execute([$id_mascota]);
        
        // Procesar eliminación de mascota
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        $id_mascota = $_GET['eliminar'];
        
        // Primero obtenemos la información de la imagen para eliminarla del servidor
        $stmt = $conn->prepare("SELECT imagen_url FROM Mascota WHERE id_mascota = ?");
        $stmt->execute([$id_mascota]);
        $mascota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mascota && $mascota['imagen_url'] !== 'sin_imagen.png' && file_exists('../' . $mascota['imagen_url'])) {
            unlink('../' . $mascota['imagen_url']);
        }

        // Usamos el procedimiento almacenado para eliminar mascota con sus relaciones
        $stmt = $conn->prepare("EXEC sp_eliminar_mascota @id_mascota = ?");
        $stmt->execute([$id_mascota]);
        
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => '¡Éxito!',
            'text' => 'Mascota eliminada correctamente',
            'button' => false,
            'timer' => 2000
        ];
        
        header('Location: lista_mascotas.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'No se pudo eliminar la mascota: ' . $e->getMessage(),
            'button' => true
        ];
        header('Location: lista_mascotas.php');
        exit;
    }
}
        
        header('Location: lista_mascotas.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'No se pudo eliminar la mascota: ' . $e->getMessage(),
            'button' => true
        ];
        header('Location: lista_mascotas.php');
        exit;
    }
}


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

$sql = "SELECT *, CONVERT(varchar, fecha_nacimiento, 23) as fecha_nacimiento FROM Mascota WHERE 1=1";
$params = [];
$filtros = [];

$especie = !empty($_GET['especie']) ? $_GET['especie'] : null;
$sexo = !empty($_GET['sexo']) ? $_GET['sexo'] : null;
$esterilizado = isset($_GET['esterilizado']) && $_GET['esterilizado'] !== '' ? (int)$_GET['esterilizado'] : null;
$rango_edad = !empty($_GET['rango_edad']) ? $_GET['rango_edad'] : null;

if ($especie !== null) $filtros['especie'] = $especie;
if ($sexo !== null) $filtros['sexo'] = $sexo;
if ($esterilizado !== null) $filtros['esterilizado'] = $esterilizado;
if ($rango_edad !== null) $filtros['rango_edad'] = $rango_edad;

$sql = "EXEC sp_filtrar_mascotas @especie = ?, @sexo = ?, @esterilizado = ?, @rango_edad = ?";
$params = [$especie, $sexo, $esterilizado, $rango_edad];

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
    $stmt = $conn->query("SELECT DISTINCT especie FROM Mascota ORDER BY especie");
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
    <title>Lista de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/lista_mascotas.css">
</head>
<body>
    <div class="responsive-container">
        <?php if (isset($_SESSION['swal'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: '<?= $_SESSION['swal']['icon'] ?>',
                        title: '<?= $_SESSION['swal']['title'] ?>',
                        text: '<?= $_SESSION['swal']['text'] ?>',
                        showConfirmButton: <?= $_SESSION['swal']['button'] ? 'true' : 'false' ?>,
                        timer: <?= $_SESSION['swal']['timer'] ?? 'null' ?>
                    });
                });
            </script>
            <?php unset($_SESSION['swal']); ?>
        <?php endif; ?>
        
        <div class="welcome-section text-center mb-4">
            <h1>Lista de Mascotas</h1>
            <p class="lead">Administra todas las mascotas registradas en el sistema</p>
        </div>
        
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="mascotas.php"><i class="fas fa-plus-circle me-2"></i>Añadir Nueva Mascota</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="lista_mascotas.php"><i class="fas fa-list me-2"></i>Lista Mascotas</a>
            </li>
        </ul>
        
        <!-- Formulario de Filtros -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h4>
            </div>
            <div class="card-body">
                <form method="get" action="lista_mascotas.php" class="row g-3">
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
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="lista_mascotas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-paw me-2"></i>Mascotas Registradas</h3>
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
                        <div class="alert alert-info">No hay mascotas registradas con los filtros aplicados.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Imagen</th>
                                        <th>Nombre</th>
                                        <th>Sexo</th>
                                        <th>Especie</th>
                                        <th>Raza</th>
                                        <th>Edad</th>
                                        <th>Esterilizado</th>
                                        <th>Fecha Ingreso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mascotas as $mascota): ?>
                                        <tr>
                                            <td><?= $mascota['id_mascota'] ?></td>
                                            <td>
                                                <?php if ($mascota['imagen_url'] !== 'sin_imagen.png'): ?>
                                                    <img src="../<?= $mascota['imagen_url'] ?>" alt="<?= $mascota['nombres'] ?>" class="img-thumbnail">
                                                <?php else: ?>
                                                    <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light">
                                                        <i class="fas fa-paw text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($mascota['nombres']) ?></td>
                                            <td>
                                                <span class="badge <?= $mascota['sexo'] === 'Hembra' ? 'sexo-hembra' : 'sexo-macho' ?>">
                                                    <?= $mascota['sexo'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($mascota['especie']) ?></td>
                                            <td><?= htmlspecialchars($mascota['raza']) ?></td>
                                            <td>
                                                <?= isset($mascota['fecha_nacimiento']) && $mascota['fecha_nacimiento'] 
                                                    ? calcularEdadMeses($mascota['fecha_nacimiento']) 
                                                    : 'Desconocida' ?>
                                            </td>
                                            <td>
                                                <?= $mascota['esterilizado'] ? 
                                                    '<span class="badge bg-success"><i class="fas fa-check"></i> Sí</span>' : 
                                                    '<span class="badge bg-secondary"><i class="fas fa-times"></i> No</span>' ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($mascota['fecha_ingreso'])) ?></td>
                                            <td class="acciones">
                                                <a href="editar_mascota.php?id=<?= $mascota['id_mascota'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-sm btn-danger btn-eliminar" 
                                                        data-id="<?= $mascota['id_mascota'] ?>" 
                                                        data-nombre="<?= htmlspecialchars($mascota['nombres']) ?>"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Confirmación para eliminar mascota
                document.querySelectorAll('.btn-eliminar').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const nombre = this.getAttribute('data-nombre');
                        
                        Swal.fire({
                            title: '¿Eliminar mascota?',
                            html: `¿Estás seguro de que deseas eliminar a <strong>${nombre}</strong>?<br><br>Esta acción no se puede deshacer.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Sí, eliminar',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = `lista_mascotas.php?eliminar=${id}`;
                            }
                        });
                    });
                });
            });
        </script>
    </body>
    </html>