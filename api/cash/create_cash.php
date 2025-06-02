<?php
/**
 * Archivo: create_cash.php
 * Descripción: Permite registrar una nueva caja (cash) en la base de datos y notifica al administrador del corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.0
 * Fecha de actualización: 01-Jun-2025
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
require_once "../config/server.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifyAdminCashCreated($email, $correspondentName, $cashData)
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
        $mail->Subject = "🧾 Nueva caja creada para el corresponsal $correspondentName";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #0066cc;'>Nueva caja registrada</h2>
                <p>Se ha creado una nueva caja asociada al corresponsal <strong>$correspondentName</strong>.</p>
                <ul>
                    <li><strong>Nombre de la caja:</strong> {$cashData['name']}</li>
                    <li><strong>Cupo:</strong> " . number_format($cashData['capacity'], 0, ',', '.') . " COP</li>
                    <li><strong>Estado:</strong> " . ($cashData['state'] ? "Activa" : "Inactiva") . "</li>
                    <li><strong>Abierta:</strong> " . ($cashData['open'] ? "Sí" : "No") . "</li>
                    <li><strong>Observación:</strong> " . ($cashData['last_note'] ?: "Ninguna") . "</li>
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["correspondent_id"], $data["cashier_id"], $data["capacity"], $data["name"])) {
        echo json_encode(["success" => false, "message" => "Faltan campos obligatorios."]);
        exit;
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $cashier_id = intval($data["cashier_id"]);
    $capacity = intval($data["capacity"]);
    $name = trim($data["name"]);
    $state = isset($data["state"]) ? (bool) $data["state"] : true;
    $open = isset($data["open"]) ? (int) $data["open"] : 1;
    $last_note = isset($data["last_note"]) ? trim($data["last_note"]) : null;

    try {
        $check = $pdo->prepare("SELECT id FROM cash WHERE correspondent_id = :correspondent_id AND cashier_id = :cashier_id");
        $check->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $check->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $check->execute();

        if ($check->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Ya existe una caja para este corresponsal y cajero."]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO cash (correspondent_id, cashier_id, name, capacity, state, open, last_note)
            VALUES (:correspondent_id, :cashier_id, :name, :capacity, :state, :open, :last_note)
        ");
        $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $stmt->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":capacity", $capacity, PDO::PARAM_INT);
        $stmt->bindParam(":state", $state, PDO::PARAM_BOOL);
        $stmt->bindParam(":open", $open, PDO::PARAM_INT);
        $stmt->bindParam(":last_note", $last_note);

        if ($stmt->execute()) {
            // Obtener datos del corresponsal y su operador
            $stmtInfo = $pdo->prepare("
                SELECT c.name AS correspondent_name, u.email AS operator_email
                FROM correspondents c
                INNER JOIN users u ON c.operator_id = u.id
                WHERE c.id = :id
            ");
            $stmtInfo->execute([':id' => $correspondent_id]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($info && !empty($info['operator_email'])) {
                notifyAdminCashCreated($info['operator_email'], $info['correspondent_name'], [
                    "name" => $name,
                    "capacity" => $capacity,
                    "state" => $state,
                    "open" => $open,
                    "last_note" => $last_note
                ]);
            }

            echo json_encode(["success" => true, "message" => "Caja registrada exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar la caja."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error BD: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
