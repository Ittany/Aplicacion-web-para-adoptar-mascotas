<?php
require('../includes/conexion.php');
require '../includes/header_admin.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_rol'] !== 'Administrador') {
    header('Location: ../login.php');
    exit;
}

$mensaje_exito = $mensaje_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombres = trim($_POST['nombres']);
        $sexo = $_POST['sexo'];
        $especie = trim($_POST['especie']);
        $raza = trim($_POST['raza']) ?: 'Sin raza';

        if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u', $nombres)) {
            throw new Exception("El nombre solo debe contener letras y espacios.");
        }

        if (!empty($raza) && !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u', $raza)) {
            throw new Exception("La raza solo debe contener letras y espacios.");
        }

        
        // Procesamiento de la fecha de nacimiento (solo mes y año)
        $meses = isset($_POST['meses']) ? (int)$_POST['meses'] : 0;
        
        // Si no se especifican meses, establecer fecha_nacimiento como NULL
        $fecha_nacimiento = null;
        if ($meses > 0) {
            // Calcular la fecha aproximada de nacimiento (restar meses a la fecha actual)
            $fecha_actual = new DateTime();
            $intervalo = new DateInterval("P{$meses}M");
            $fecha_nacimiento = $fecha_actual->sub($intervalo)->format('Y-m-d');
        }
        $especiesPermitidas = ['Perro', 'Gato', 'Conejo', 'Ave', 'Otro'];
        $razasPorEspecie = [
            'Perro' => ['Labrador', 'Pastor Alemán', 'Bulldog', 'Chihuahua', 'Otro'],
            'Gato' => ['Siamés', 'Persa', 'Bengalí', 'Mestizo', 'Otro'],
            'Conejo' => ['Holandés', 'Mini Rex', 'Angora', 'Otro'],
            'Ave' => ['Periquito', 'Canario', 'Cacatúa', 'Otro'],
            'Otro' => ['Otro']
        ];
        $esterilizado = isset($_POST['esterilizado']) ? 1 : 0;
        $descripcion = trim($_POST['descripcion']) ?: '';
        $fecha_ingreso = date('Y-m-d');
        $imagen_url = 'uploads/mascotas/sin_imagen.png';

        if (!in_array($especie, $especiesPermitidas)) {
            throw new Exception("Especie no válida");
        }

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/mascotas/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

               $allowedTypes = ['image/jpeg', 'image/png'];
                $fileType = mime_content_type($_FILES['imagen']['tmp_name']);

                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Solo se permiten imágenes JPG, PNG");
                }

                if ($_FILES['imagen']['size'] > 2097152) {
                    throw new Exception("La imagen no debe superar los 2MB");
                }

                $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $extension;
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destination)) {
                    $imagen_url = 'uploads/mascotas/' . $filename;
                } else {
                    throw new Exception("Error al mover el archivo subido");
                }
            } else {
                throw new Exception("Error al subir la imagen: código " . $_FILES['imagen']['error']);
            }
        }

        $conn->beginTransaction();
        $sql = "INSERT INTO Mascota (
            nombres, sexo, especie, raza, fecha_nacimiento, esterilizado, 
            descripcion, imagen_url, fecha_ingreso
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        // Si fecha_nacimiento es null, debemos manejarlo adecuadamente
        if ($fecha_nacimiento === null) {
            $stmt->execute([
                $nombres, $sexo, $especie, $raza, null, $esterilizado,
                $descripcion, $imagen_url, $fecha_ingreso
            ]);
        } else {
            $stmt->execute([
                $nombres, $sexo, $especie, $raza, $fecha_nacimiento, $esterilizado,
                $descripcion, $imagen_url, $fecha_ingreso
            ]);
        }
        
        $conn->commit();

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => '¡Éxito!',
            'text' => 'Mascota registrada correctamente',
            'button' => false,
            'timer' => 2000
        ];

        header('Location: mascotas.php');
        exit;


        
    } catch (PDOException $e) {
        $conn->rollBack();
        $mensaje_error = "Error al registrar la mascota: " . $e->getMessage();
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/mascotas.css">

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
        
        <?php if ($mensaje_error): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= addslashes($mensaje_error) ?>',
                        confirmButtonColor: '#4a6fa5'
                    });
                });
            </script>
        <?php endif; ?>
        
        <div class="welcome-section text-center mb-4">
            <h1>Administrar Mascotas</h1>
            <p> Agrega nuevas Mascotas</p>
        </div>
        
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" href="mascotas.php"><i class="fas fa-plus-circle me-2"></i>Añadir Nueva Mascota</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="lista_mascotas.php"><i class="fas fa-list me-2"></i>Lista Mascotas</a>
            </li>
        </ul>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="fas fa-paw me-2"></i>Registrar Nueva Mascota</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombres" class="form-label required-field">Nombres</label>
                                <input type="text"
                                    class="form-control"
                                    id="nombres"
                                    name="nombres"
                                    required
                                    pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                                    title="Solo se permiten letras y espacios.">
                            </div>
                            <div class="mb-3">
                                <label class="form-label required-field">Sexo</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sexo" id="sexo-hembra" value="Hembra" required>
                                        <label class="form-check-label" for="sexo-hembra">Hembra</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sexo" id="sexo-macho" value="Macho">
                                        <label class="form-check-label" for="sexo-macho">Macho</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="especie" class="form-label required-field">Especie</label>
                                <select class="form-select" id="especie" name="especie" required>
                                    <option value="">Seleccione una especie</option>
                                    <option value="Perro">Perro</option>
                                    <option value="Gato">Gato</option>
                                    <option value="Conejo">Conejo</option>
                                    <option value="Ave">Ave</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                           <div class="mb-3">
                                <label for="raza" class="form-label">Raza</label>
                                <input type="text"
                                    class="form-control"
                                    id="raza"
                                    name="raza"
                                    placeholder="Ingrese la raza o 'Sin raza'"
                                    required
                                    pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                                    title="Solo se permiten letras y espacios.">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Edad (meses)</label>
                                <input type="number" class="form-control" id="meses" name="meses" min="0" max="1200" placeholder="0">
                                <div class="edad-label">Meses de edad (ej. 3 meses, 24 meses = 2 años)</div>
                            </div>
                            
                            <div class="form-check form-switch mb-3 d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" id="esterilizado" name="esterilizado">
                                <label class="form-check-label ms-2" for="esterilizado">Esterilizado</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="imagen" class="form-label">Imagen de la mascota</label>
                        <input type="hidden" name="imagen_actual" id="imagen_actual" value="sin_imagen.png">
                        <div class="img-upload-container">
                            <label for="imagen" class="file-upload-label text-center">Subir Imagen de la Mascota</label>
                            <input type="file" class="form-control" id="imagen" name="imagen" accept="image/jpeg, image/png">
                            <div class="mt-2 position-relative">
                                <img id="imagen-preview" class="img-preview" style="display: none;">
                                <button id="btn-remove-img" class="btn-remove-img" title="Eliminar imagen" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="file-upload-info text-muted">Formatos aceptados: JPG, PNG (Máx. 2MB)</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Dejar vacío si no hay descripción"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-secondary">Limpiar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Mascota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imagenInput = document.getElementById('imagen');
            const imagenPreview = document.getElementById('imagen-preview');
            const btnRemoveImg = document.getElementById('btn-remove-img');
            const imagenActualInput = document.getElementById('imagen_actual');
            
            function mostrarVistaPrevia(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagenPreview.src = e.target.result;
                    imagenPreview.style.display = 'block';
                    btnRemoveImg.style.display = 'flex';
                    imagenActualInput.value = 'imagen_seleccionada'; 
                }
                reader.readAsDataURL(file);
            }
            
            if (imagenInput) {
                imagenInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    
                    if (imagenActualInput.value !== 'sin_imagen.png') {
                        Swal.fire({
                            title: '¿Reemplazar imagen?',
                            text: "Ya tienes una imagen seleccionada. ¿Deseas reemplazarla?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#4a6fa5',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Sí, reemplazar',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                mostrarVistaPrevia(file);
                            } else {
                                imagenInput.value = ''; 
                            }
                        });
                    } else {
                        mostrarVistaPrevia(file);
                    }
                });
            }

            if (btnRemoveImg) {
                btnRemoveImg.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: '¿Eliminar imagen?',
                        text: "¿Estás seguro de que deseas quitar esta imagen?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#4a6fa5',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            imagenInput.value = '';
                            imagenPreview.src = '';
                            imagenPreview.style.display = 'none';
                            btnRemoveImg.style.display = 'none';
                            imagenActualInput.value = 'sin_imagen.png';
                        }
                    });
                });
            }
        });

        document.querySelector('form').addEventListener('submit', function(e) {
        const nombres = document.getElementById('nombres').value.trim();
        const raza = document.getElementById('raza').value.trim();
        const soloLetrasRegex = /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/;

        if (!soloLetrasRegex.test(nombres)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error en el nombre',
                text: 'El nombre solo debe contener letras y espacios.',
            });
            return;
        }

        if (raza !== '' && !soloLetrasRegex.test(raza)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error en la raza',
                text: 'La raza solo debe contener letras y espacios.',
            });
            return;
        }
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