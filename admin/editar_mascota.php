<?php
require('../includes/conexion.php');
require '../includes/header_admin.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Administrador') {
    header('Location: ../login.php');
    exit;
}

// Verificar si se recibió un ID de mascota
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['swal'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'ID de mascota no válido',
        'button' => true
    ];
    header('Location: lista_mascotas.php');
    exit;
}

$id_mascota = $_GET['id'];

// Obtener datos de la mascota
$mascota = [];
try {
    $stmt = $conn->prepare("SELECT * FROM Mascota WHERE id_mascota = ?");
    $stmt->execute([$id_mascota]);
    $mascota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mascota) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Mascota no encontrada',
            'button' => true
        ];
        header('Location: lista_mascotas.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['swal'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'Error al obtener datos de la mascota: ' . $e->getMessage(),
        'button' => true
    ];
    header('Location: lista_mascotas.php');
    exit;
}

// Definir especies y razas permitidas
$especiesPermitidas = ['Perro', 'Gato', 'Conejo', 'Ave', 'Otro'];
$razasPorEspecie = [
    'Perro' => ['Labrador', 'Pastor Alemán', 'Bulldog', 'Chihuahua', 'Otro'],
    'Gato' => ['Siamés', 'Persa', 'Bengalí', 'Mestizo', 'Otro'],
    'Conejo' => ['Holandés', 'Mini Rex', 'Angora', 'Otro'],
    'Ave' => ['Periquito', 'Canario', 'Cacatúa', 'Otro'],
    'Otro' => ['Otro']
];

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger y validar datos del formulario
        $nombres = trim($_POST['nombres']);
        $especie = trim($_POST['especie']);
        $raza = trim($_POST['raza']);
        $sexo = trim($_POST['sexo']);
        $meses = isset($_POST['meses_edad']) ? intval($_POST['meses_edad']) : null;
        if ($meses < 0 || $meses > 240) {
            throw new Exception("La edad ingresada no es válida");
        }
        $fecha_nacimiento = null;
        if ($meses !== null) {
            $fecha_nacimiento = date('Y-m-d', strtotime("-$meses months"));
        }
        $esterilizado = isset($_POST['esterilizado']) ? 1 : 0;
        $descripcion = trim($_POST['descripcion']);
        
        // Validaciones básicas
        if (empty($nombres)) {
            throw new Exception("El nombre es obligatorio");
        }
        
        if (!in_array($especie, $especiesPermitidas)) {
            throw new Exception("Especie no válida");
        }
        
        // Procesar imagen si se subió una nueva
        $imagen_url = $mascota['imagen_url'];
        if (!empty($_FILES['imagen']['name'])) {
            $directorio = "../uploads/mascotas/";
            
            // Crear directorio si no existe
            if (!file_exists($directorio)) {
                mkdir($directorio, 0777, true);
            }
            
            // Eliminar imagen anterior si no es la predeterminada
            if ($imagen_url !== 'uploads/mascotas/sin_imagen.png' && file_exists('../' . $imagen_url)) {
                unlink('../' . $imagen_url);
            }
            
            // Validar tipo y tamaño de imagen
            $allowedTypes = ['image/jpeg', 'image/png'];
            $fileType = mime_content_type($_FILES['imagen']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Solo se permiten imágenes JPG o PNG");
            }
            
            if ($_FILES['imagen']['size'] > 2097152) { // 2MB
                throw new Exception("La imagen no debe superar los 2MB");
            }
            
            // Procesar nueva imagen
            $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $nombre_archivo = uniqid() . '.' . $extension;
            $ruta_completa = $directorio . $nombre_archivo;
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_completa)) {
                $imagen_url = 'uploads/mascotas/' . $nombre_archivo;
            } else {
                throw new Exception("Error al subir la imagen");
            }
        }
        
        // Actualizar en la base de datos
        $sql = "UPDATE Mascota SET 
                nombres = ?, 
                especie = ?, 
                raza = ?, 
                sexo = ?, 
                fecha_nacimiento = ?, 
                esterilizado = ?, 
                descripcion = ?, 
                imagen_url = ? 
                WHERE id_mascota = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $nombres,
            $especie,
            $raza,
            $sexo,
            $fecha_nacimiento,
            $esterilizado,
            $descripcion,
            $imagen_url,
            $id_mascota
        ]);
        
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => '¡Éxito!',
            'text' => 'Mascota actualizada correctamente',
            'button' => false,
            'timer' => 2000
        ];
        
        header('Location: lista_mascotas.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Mascota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/editar_mascotas.css">
</head>
<body>
    <div class="responsive-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="fas fa-paw me-2"></i>Editar Mascota</h1>
            <a href="lista_mascotas.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="card shadow">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="text-center mb-3">
                                <?php if ($mascota['imagen_url'] !== 'uploads/mascotas/sin_imagen.png'): ?>
                                    <img src="../<?= htmlspecialchars($mascota['imagen_url']) ?>" 
                                         alt="<?= htmlspecialchars($mascota['nombres']) ?>" 
                                         class="img-thumbnail mb-2" 
                                         style="max-height: 200px;">
                                <?php else: ?>
                                    <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light mb-2" 
                                         style="height: 200px; width: 200px;">
                                        <i class="fas fa-paw fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                                <small class="text-muted">Formatos: JPG, PNG (Máx. 2MB)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombres" class="form-label">Nombre *</label>
                                    <input type="text"
                                        class="form-control"
                                        id="nombres"
                                        name="nombres"
                                        value="<?= htmlspecialchars($mascota['nombres']) ?>"
                                        required
                                        pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                                        title="Solo se permiten letras y espacios.">
                                </div>

                                
                                <div class="col-md-6 mb-3">
                                    <label for="especie" class="form-label">Especie *</label>
                                    <select class="form-select" id="especie" name="especie" required>
                                        <?php foreach ($especiesPermitidas as $esp): ?>
                                            <option value="<?= $esp ?>" <?= $mascota['especie'] === $esp ? 'selected' : '' ?>>
                                                <?= $esp ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="raza" class="form-label">Raza</label>
                                    <input type="text"
                                        class="form-control"
                                        id="raza"
                                        name="raza"
                                        value="<?= htmlspecialchars($mascota['raza']) ?>"
                                        pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                                        title="Solo se permiten letras y espacios.">
                                    <small class="text-muted">Ejemplo: Labrador, Persa, etc.</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sexo" class="form-label">Sexo *</label>
                                    <select class="form-select" id="sexo" name="sexo" required>
                                        <option value="Macho" <?= $mascota['sexo'] === 'Macho' ? 'selected' : '' ?>>Macho</option>
                                        <option value="Hembra" <?= $mascota['sexo'] === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="meses_edad" class="form-label">Edad (en meses) *</label>
                                    <input type="number" class="form-control" id="meses_edad" name="meses_edad" 
                                        min="0" max="240" 
                                        value="<?= !empty($mascota['fecha_nacimiento']) ? floor((time() - strtotime($mascota['fecha_nacimiento'])) / (30*24*60*60)) : '' ?>" 
                                        required>
                                    <small class="text-muted">Ingrese solo números. Ejemplo: 12 para 1 año.</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Esterilizado</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="esterilizado" name="esterilizado" 
                                               <?= $mascota['esterilizado'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="esterilizado">Sí</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($mascota['descripcion']) ?></textarea>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-save me-1"></i> Guardar Cambios
                                    </button>
                                    <a href="lista_mascotas.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombres').value.trim();
            if (!nombre) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El nombre es obligatorio',
                    confirmButtonText: 'Entendido'
                });
            }
        });

        // Actualizar opciones de raza según especie seleccionada
        document.getElementById('especie').addEventListener('change', function() {
            const razas = {
                'Perro': ['Labrador', 'Pastor Alemán', 'Bulldog', 'Chihuahua', 'Otro'],
                'Gato': ['Siamés', 'Persa', 'Bengalí', 'Mestizo', 'Otro'],
                'Conejo': ['Holandés', 'Mini Rex', 'Angora', 'Otro'],
                'Ave': ['Periquito', 'Canario', 'Cacatúa', 'Otro'],
                'Otro': ['Otro']
            };
            
            const especie = this.value;
            const razaInput = document.getElementById('raza');

            razaInput.placeholder = 'Ejemplo: ' + razas[especie][0];
        });

         document.querySelector('form').addEventListener('submit', function(e) {
        const nombre = document.getElementById('nombres').value.trim();
        const raza = document.getElementById('raza').value.trim();
        const soloLetras = /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/;

        if (!nombre) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El nombre es obligatorio',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        if (!soloLetras.test(nombre)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Nombre inválido',
                text: 'El nombre solo debe contener letras y espacios.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        if (raza && !soloLetras.test(raza)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Raza inválida',
                text: 'La raza solo debe contener letras y espacios.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
    });
    document.getElementById('meses_edad')?.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value > 240) this.value = 240;
   });
       document.getElementById('nombres').addEventListener('input', function () {
    this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, '');
    });
    document.getElementById('raza').addEventListener('input', function () {
    this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, ''); 
   });

    </script>
</body>
</html>