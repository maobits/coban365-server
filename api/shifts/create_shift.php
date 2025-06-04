<?php
/**
 * Archivo: create_shift.php
 * Descripción: Registra un nuevo turno en la base de datos y notifica al cajero y al solicitante por correo electrónico.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de actualización: 03-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

// Establecer zona horaria Colombia (Bogotá)
date_default_timezone_set("America/Bogota");

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
        error_log("❌ Error al enviar notificación: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $required = ["correspondent_id", "cash_id", "transaction_type", "amount", "full_name", "document_id", "email"];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === "") {
            echo json_encode(["success" => false, "message" => "El campo '$field' es obligatorio."]);
            exit;
        }
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $cash_id = intval($data["cash_id"]);
    $transaction_type = trim($data["transaction_type"]);
    $amount = floatval($data["amount"]);
    $agreement = trim($data["agreement"] ?? "");
    $reference = trim($data["reference"] ?? "");
    $full_name = trim($data["full_name"]);
    $document_id = trim($data["document_id"]);
    $phone = trim($data["phone"] ?? "");
    $email = trim($data["email"]);
    $state = 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO shifts 
            (correspondent_id, cash_id, transaction_type, amount, agreement, reference, full_name, document_id, phone, email, state) 
            VALUES (:correspondent_id, :cash_id, :transaction_type, :amount, :agreement, :reference, :full_name, :document_id, :phone, :email, :state)");

        $stmt->execute([
            ":correspondent_id" => $correspondent_id,
            ":cash_id" => $cash_id,
            ":transaction_type" => $transaction_type,
            ":amount" => $amount,
            ":agreement" => $agreement,
            ":reference" => $reference,
            ":full_name" => $full_name,
            ":document_id" => $document_id,
            ":phone" => $phone,
            ":email" => $email,
            ":state" => $state,
        ]);

        // Obtener el email del cajero asociado a la caja
        $userStmt = $pdo->prepare("SELECT u.email FROM users u INNER JOIN cash c ON u.id = c.cashier_id WHERE c.id = :cash_id LIMIT 1");
        $userStmt->execute([':cash_id' => $cash_id]);
        $cashier = $userStmt->fetch(PDO::FETCH_ASSOC);
        $cashierEmail = $cashier['email'] ?? null;

        // Notificar al cajero (si se encontró)
        if ($cashierEmail) {
            sendNotification(
                $cashierEmail,
                "🕒 Nueva solicitud de turno",
                "
                <div style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2>Se ha solicitado un nuevo turno</h2>
                    <p><strong>Cliente:</strong> $full_name</p>
                    <p><strong>Documento:</strong> $document_id</p>
                    <p><strong>Transacción:</strong> $transaction_type</p>
                    <p><strong>Valor:</strong> $" . number_format($amount, 0, ',', '.') . "</p>
                    <p><strong>Referencia:</strong> $reference</p>
                    <p><strong>Correo:</strong> $email</p>
                </div>
                ",
                $email // copia al solicitante
            );
        }

        // Notificar al solicitante
        sendNotification(
            $email,
            "✅ Solicitud de turno registrada",
            "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2>Tu solicitud ha sido registrada exitosamente</h2>
                <p><strong>Transacción:</strong> $transaction_type</p>
                <p><strong>Valor:</strong> $" . number_format($amount, 0, ',', '.') . "</p>
                <p><strong>Referencia:</strong> $reference</p>
                <p>Pronto serás atendido por un agente.</p>
                <p>Gracias por usar COBAN365.</p>
            </div>
            "
        );

        echo json_encode(["success" => true, "message" => "Turno registrado exitosamente."]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
