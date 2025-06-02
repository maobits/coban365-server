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

function notifyEmails($recipients, $correspondent, $newStateText)
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

        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->Subject = "🔔 Estado del corresponsal actualizado";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #0066cc;'>Estado del corresponsal actualizado</h2>
                <p>Se ha actualizado el estado del corresponsal asignado.</p>
                <ul>
                    <li><strong>Nombre:</strong> {$correspondent['name']}</li>
                    <li><strong>Código:</strong> {$correspondent['code']}</li>
                    <li><strong>Ubicación:</strong> {$correspondent['ciudad']}</li>
                    <li><strong>Tipo:</strong> {$correspondent['type_name']}</li>
                    <li><strong>Nuevo estado:</strong> $newStateText</li>
                </ul>
                <hr>
                <p style='color: #888;'>COBAN365 - Sistema de gestión de corresponsales</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo: " . $mail->ErrorInfo);
    }
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['state'])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data['id']);
$state = intval($data['state']);

try {
    // Obtener info detallada del corresponsal
    $stmtInfo = $pdo->prepare("
        SELECT 
            c.name,
            c.code,
            c.location,
            t.name AS type_name,
            u.email AS operator_email
        FROM correspondents c
        INNER JOIN users u ON c.operator_id = u.id
        INNER JOIN types_correspondents t ON c.type_id = t.id
        WHERE c.id = :id
    ");
    $stmtInfo->execute([':id' => $id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo json_encode(["success" => false, "message" => "Corresponsal no encontrado."]);
        exit();
    }

    $location = json_decode($info['location'], true);
    $info['ciudad'] = $location['ciudad'] ?? 'No especificada';

    // Actualizar estado
    $stmt = $pdo->prepare("UPDATE correspondents SET state = :state, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":state", $state, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $newStateText = $state === 1 ? "Activo" : "Inactivo";

        // Correos a notificar
        $stmtAdmins = $pdo->query("SELECT email FROM users WHERE role = 'superadmin' AND status = 1");
        $superadminEmails = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
        $emailsToNotify = array_merge([$info['operator_email']], $superadminEmails);

        notifyEmails($emailsToNotify, $info, $newStateText);

        echo json_encode(["success" => true, "message" => "Estado actualizado y notificación enviada."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se encontró el corresponsal o el estado ya estaba asignado"]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
