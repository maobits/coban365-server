<?php
/**
 * Archivo: update_other_state.php
 * Descripción: Actualiza el estado lógico (activo/inactivo) de un tercero (others) y notifica al administrador del corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 01-Jun-2025
 */

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Dependencias
require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifyOperator($email, $correspondentName, $other, $newStateText)
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

        $mail->Subject = "🔄 Estado del tercero actualizado";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2>📌 Estado de Tercero Actualizado</h2>
                <p>El tercero del corresponsal <strong>$correspondentName</strong> ha sido actualizado:</p>
                <table style='border-collapse: collapse; width: 100%; margin-top: 10px;'>
                    <tr><td><strong>Nombre:</strong></td><td>{$other['name']}</td></tr>
                    <tr><td><strong>Identificación:</strong></td><td>{$other['id_type']} {$other['id_number']}</td></tr>
                    <tr><td><strong>Correo:</strong></td><td>{$other['email']}</td></tr>
                    <tr><td><strong>Celular:</strong></td><td>{$other['phone']}</td></tr>
                    <tr><td><strong>Dirección:</strong></td><td>{$other['address']}</td></tr>
                    <tr><td><strong>Crédito:</strong></td><td>{$other['credit']}</td></tr>
                    <tr><td><strong>Nuevo Estado:</strong></td><td>$newStateText</td></tr>
                </table>
                <hr>
                <p style='font-size: 12px; color: #888;'>Este mensaje fue generado automáticamente por COBAN365.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar notificación: " . $mail->ErrorInfo);
    }
}

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["id"]) || !isset($data["state"])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data["id"]);
$state = intval($data["state"]);
$newStateText = $state === 1 ? "Activo" : "Inactivo";

try {
    // Obtener datos del tercero y su corresponsal
    $stmtInfo = $pdo->prepare("
        SELECT o.*, c.name AS correspondent_name, u.email AS operator_email
        FROM others o
        JOIN correspondents c ON o.correspondent_id = c.id
        JOIN users u ON c.operator_id = u.id
        WHERE o.id = :id
    ");
    $stmtInfo->execute([':id' => $id]);
    $other = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$other) {
        echo json_encode(["success" => false, "message" => "Tercero no encontrado."]);
        exit();
    }

    // Actualizar estado
    $stmt = $pdo->prepare("UPDATE others SET state = :state, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":state", $state, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Enviar notificación al operador
        notifyOperator($other['operator_email'], $other['correspondent_name'], $other, $newStateText);

        echo json_encode(["success" => true, "message" => "Estado actualizado y notificación enviada."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se encontró el tercero o el estado ya estaba asignado."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
