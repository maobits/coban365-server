<?php
/**
 * Archivo: reject_turn.php
 * Descripción: Marca un turno como expirado (rechazado).
 * Proyecto: COBAN365
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendRejectionEmail($email, $full_name, $transaction_type)
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
        $mail->Subject = "⛔ Tu solicitud de turno fue rechazada";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2>Turno Rechazado</h2>
                <p>Hola <strong>$full_name</strong>, lamentamos informarte que tu turno de tipo <strong>$transaction_type</strong> no ha sido registrado.</p>
                <p>Te invitamos a intentarlo nuevamente en otro momento.</p>
                <p>Gracias por tu comprensión.</p>
            </div>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo de rechazo: " . $mail->ErrorInfo);
    }
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data["shift_id"])) {
        echo json_encode(["success" => false, "message" => "Falta el ID del turno."]);
        exit;
    }

    $shift_id = intval($data["shift_id"]);

    // Obtener los datos del turno para enviar notificación
    $stmt = $pdo->prepare("SELECT full_name, email, transaction_type FROM shifts WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $shift_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        echo json_encode(["success" => false, "message" => "Turno no encontrado."]);
        exit;
    }

    // Actualizar estado del turno a 3 (rechazado/expirado)
    $update = $pdo->prepare("UPDATE shifts SET state = 3 WHERE id = :id");
    $update->execute([":id" => $shift_id]);

    // Notificar al cliente
    sendRejectionEmail($shift["email"], $shift["full_name"], $shift["transaction_type"]);

    echo json_encode(["success" => true, "message" => "Turno rechazado correctamente."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en base de datos: " . $e->getMessage()]);
}
