<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require('../includes/conexion.php');
require '../admin/libreria/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['tipo_rol'] ?? '') !== 'Administrador') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: revisar_solicitud.php');
    exit;
}

$id_adopcion = $_GET['id'];
$id_administrador = $_SESSION['usuario']['id_usuario'];

$stmt_estado = $conn->prepare("SELECT estado FROM Adopcion WHERE id_adopcion = ?");
$stmt_estado->execute([$id_adopcion]);
$estado = $stmt_estado->fetchColumn();

if ($estado !== 'Aprobada') {
    die("Solo se pueden descargar solicitudes aprobadas");
}

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

$stmt_adopcion->execute([$id_adopcion]);
$solicitud = $stmt_adopcion->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    die("La solicitud no existe");
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Adopción #'.$solicitud['id_adopcion'].'</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(0,0,0,0.1);
            z-index: -1;
            pointer-events: none;
            font-weight: bold;
        }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 20px; font-weight: bold; margin: 10px 0; }
        .section { margin-bottom: 20px; position: relative; z-index: 1; }
        .section-title { background-color: #f2f2f2; padding: 5px 10px; font-weight: bold; border-left: 4px solid #333; }
        .info-label { font-weight: bold; display: inline-block; width: 180px; }
        .signature-area { margin-top: 50px; border-top: 1px dashed #333; padding-top: 10px; }
        .footer { margin-top: 50px; font-size: 12px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <!-- Marca de agua -->
    <div class="watermark">APROBADO MICHIHOUSE</div>

    <div class="header">
        <div class="title">Solicitud de Adopción #'.$solicitud['id_adopcion'].'</div>
        <div>Estado: <strong>'.$solicitud['estado'].'</strong></div>
    </div>

    <div class="section">
        <div class="section-title">Datos de la Solicitud</div>
        <p><span class="info-label">Fecha de solicitud:</span> '.$solicitud['fecha_solicitud_formateada'].'</p>
        '.($solicitud['fecha_adopcion_formateada'] ? '<p><span class="info-label">Fecha de adopción:</span> '.$solicitud['fecha_adopcion_formateada'].'</p>' : '').'
        <p><span class="info-label">Administrador asignado:</span> '.($solicitud['nombre_admin_completo'] ?: 'Sin asignar').'</p>
        <p><span class="info-label">Compromiso del adoptante:</span></p>
        <div style="border: 1px solid #eee; padding: 10px; margin: 10px 0;">'.nl2br(htmlspecialchars($solicitud['compromiso_adoptante'])).'</div>
    </div>

    <div class="section">
        <div class="section-title">Información de la Mascota</div>
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: top;">
                    <p><span class="info-label">Nombre:</span> '.htmlspecialchars($solicitud['nombres']).'</p>
                    <p><span class="info-label">Especie:</span> '.htmlspecialchars($solicitud['especie']).'</p>
                    <p><span class="info-label">Raza:</span> '.htmlspecialchars($solicitud['raza']).'</p>
                    <p><span class="info-label">Sexo:</span> '.htmlspecialchars($solicitud['sexo']).'</p>
                    <p><span class="info-label">Esterilizado:</span> '.($solicitud['esterilizado'] ? 'Sí' : 'No').'</p>
                    <p><span class="info-label">Fecha de nacimiento:</span> '.$solicitud['fecha_nacimiento_formateada'].'</p>
                    <p><span class="info-label">Fecha de ingreso:</span> '.$solicitud['fecha_ingreso_formateada'].'</p>
                </td>
            </tr>
        </table>
        <p><span class="info-label">Descripción:</span></p>
        <div style="border: 1px solid #eee; padding: 10px; margin: 10px 0;">'.nl2br(htmlspecialchars($solicitud['descripcion'])).'</div>
    </div>

    <div class="section">
        <div class="section-title">Información del Adoptante</div>
        <p><span class="info-label">Nombre completo:</span> '.htmlspecialchars($solicitud['nombre_adoptante_completo']).'</p>
        <p><span class="info-label">Documento de identidad:</span> '.htmlspecialchars($solicitud['dni_cedula']).'</p>
        <p><span class="info-label">Teléfono:</span> '.htmlspecialchars($solicitud['telefono_adoptante']).'</p>
        <p><span class="info-label">Correo electrónico:</span> '.htmlspecialchars($solicitud['correo_adoptante']).'</p>
        <p><span class="info-label">Dirección:</span> '.htmlspecialchars($solicitud['direccion_especifica']).'</p>
        <p><span class="info-label">Ciudad:</span> '.htmlspecialchars($solicitud['ciudad']).'</p>
        <p><span class="info-label">Ocupación:</span> '.htmlspecialchars($solicitud['ocupacion']).'</p>
    </div>

    <div class="signature-area">
        <p>Firma del Administrador: _________________________________________</p>
        <p>Firma del Adoptante: _________________________________________</p>
    </div>

    <div class="footer">
        Documento generado el '.date('d/m/Y H:i').' por MichiHouse - Todos los derechos reservados
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Solicitud_Adopcion_'.$solicitud['id_adopcion'].'_'.date('Ymd_His').'.pdf';
$filepath = $_SERVER['DOCUMENT_ROOT'].'/pdf/'.$filename; // Ajusta según tu estructura

// 1. Guardar el PDF en el servidor
file_put_contents($filepath, $dompdf->output());

// 2. Registrar en la base de datos
try {
    $conn->beginTransaction();
    
    $insert = $conn->prepare("
        INSERT INTO documentos_adopcion 
        (id_adopcion, ruta_archivo, generado_por) 
        VALUES (?, ?, ?)
    ");
    
    $insert->execute([
        $id_adopcion,
        '/pdf/'.$filename,
        $id_administrador
    ]);
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    // Puedes decidir si quieres continuar o mostrar error
    error_log("Error al registrar PDF: " . $e->getMessage());
}

// 3. Enviar el PDF al navegador para descarga
$dompdf->stream($filename, [
    'Attachment' => true
]);

exit;