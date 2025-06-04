<?php
/**
 * Archivo: confirm_shift.php
 * Descripción: Confirma un turno (actualiza su estado a 1) y notifica al cajero y al cliente.
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

function sendNotification($to, $subject, $body, $cc = null)
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
        $mail->addAddress($to);
        if ($cc)
            $mail->addCC($cc);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo: " . $mail->ErrorInfo);
    }
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data["shift_id"])) {
        echo json_encode(["success" => false, "message" => "Falta el ID del turno."]);
        exit;
    }

    $shift_id = intval($data["shift_id"]);

    // Obtener datos del turno
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = :id");
    $stmt->execute([":id" => $shift_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        echo json_encode(["success" => false, "message" => "Turno no encontrado."]);
        exit;
    }

    // Actualizar estado del turno a 1 (confirmado)
    $update = $pdo->prepare("UPDATE shifts SET state = 1 WHERE id = :id");
    $update->execute([":id" => $shift_id]);

    // Obtener email del cajero
    $userStmt = $pdo->prepare("
        SELECT u.email 
        FROM users u 
        INNER JOIN cash c ON u.id = c.cashier_id 
        WHERE c.id = :cash_id 
        LIMIT 1
    ");
    $userStmt->execute([':cash_id' => $shift["cash_id"]]);
    $cashier = $userStmt->fetch(PDO::FETCH_ASSOC);
    $cashierEmail = $cashier['email'] ?? null;

    // Notificar al cajero
    if ($cashierEmail) {
        sendNotification(
            $cashierEmail,
            "✅ Turno confirmado",
            "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2>Un turno ha sido confirmado</h2>
                <p><strong>Cliente:</strong> {$shift['full_name']}</p>
                <p><strong>Documento:</strong> {$shift['document_id']}</p>
                <p><strong>Transacción:</strong> {$shift['transaction_type']}</p>
                <p><strong>Valor:</strong> $" . number_format($shift['amount'], 0, ',', '.') . "</p>
                <p><strong>Referencia:</strong> {$shift['reference']}</p>
                <p><strong>Correo:</strong> {$shift['email']}</p>
            </div>
            ",
            $shift['email']
        );
    }

    // Notificar al cliente
    sendNotification(
        $shift['email'],
        "✅ Tu turno ha sido confirmado",
        "
        <div style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2>Turno confirmado exitosamente</h2>
            <p><strong>Tipo:</strong> {$shift['transaction_type']}</p>
            <p><strong>Valor:</strong> $" . number_format($shift['amount'], 0, ',', '.') . "</p>
            <p><strong>Convenio:</strong> {$shift['agreement']}</p>
            <p><strong>Referencia:</strong> {$shift['reference']}</p>
            <p>Gracias por confiar en COBAN365.</p>
        </div>
        "
    );

    echo json_encode(["success" => true, "message" => "Turno confirmado y notificaciones enviadas."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en base de datos: " . $e->getMessage()]);
}
