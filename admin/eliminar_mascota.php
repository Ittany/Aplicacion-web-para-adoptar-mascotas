<?php
require('../includes/conexion.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_mascota'])) {
    $id_mascota = $_POST['id_mascota'];

    try {
        $stmt = $conn->prepare("EXEC sp_eliminar_mascota @id_mascota = ?");
        $stmt->execute([$id_mascota]);

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => '¡Mascota eliminada!',
            'text' => 'La mascota se eliminó correctamente.',
            'button' => false,
            'timer' => 2000
        ];
    } catch (PDOException $e) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Error al eliminar mascota: ' . $e->getMessage(),
            'button' => true
        ];
    }

    header('Location: lista_mascotas.php');
    exit;
}
?>
