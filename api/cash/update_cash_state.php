<?php
// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../config/server.php';
require_once '../../libs/src/PHPMailer.php';
require_once '../../libs/src/SMTP.php';
require_once '../../libs/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Función para enviar notificación por correo
function notifyAdmin($email, $adminName, $cashName, $correspondentName, $newStateText)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'notifications@coban365.maobits.com';
        $mail->Password = 'Coban3652025@';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('notifications@coban365.maobits.com', 'COBAN365');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "🔔 Estado de caja actualizado";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #0066cc;'>Actualización de Caja</h2>
                <p>Hola <strong>$adminName</strong>,</p>
                <p>La caja <strong>$cashName</strong> del corresponsal <strong>$correspondentName</strong> ha cambiado de estado.</p>
                <p><strong>Nuevo estado:</strong> $newStateText</p>
                <hr>
                <p style='color: #888;'>COBAN365 - Gestión de Cajas</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar notificación: " . $mail->ErrorInfo);
    }
}

// Validación del método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['state'])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data['id']);
$state = intval($data['state']);
$newStateText = $state === 1 ? 'Activa' : 'Inactiva';

try {
    // Obtener datos de la caja, corresponsal y su administrador
    $stmtInfo = $pdo->prepare("
        SELECT ca.name AS cash_name, co.name AS correspondent_name, u.email AS admin_email, u.fullname AS admin_name
        FROM cash ca
        INNER JOIN correspondents co ON ca.correspondent_id = co.id
        INNER JOIN users u ON co.operator_id = u.id
        WHERE ca.id = :id
    ");
    $stmtInfo->execute([':id' => $id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo json_encode(["success" => false, "message" => "Caja no encontrada."]);
        exit();
    }

    // Actualizar estado
    $stmt = $pdo->prepare("UPDATE cash SET state = :state, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":state", $state, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Enviar notificación
        notifyAdmin(
            $info['admin_email'],
            $info['admin_name'],
            $info['cash_name'],
            $info['correspondent_name'],
            $newStateText
        );

        echo json_encode(["success" => true, "message" => "Estado actualizado y notificación enviada."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se encontró la caja o el estado ya estaba asignado"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
