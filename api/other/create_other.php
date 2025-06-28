<?php
/**
 * Archivo: create_other.php
 * Descripción: Permite registrar un nuevo tercero en la base de datos y notifica al administrador del corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.2
 * Fecha de actualización: 27-Jun-2025
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifyAdmin($adminEmail, $correspondentName, $other)
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
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);

        $mail->Subject = "🧾 Nuevo tercero registrado para el corresponsal $correspondentName";

        $negBalanceText = $other['negative_balance'] ? "Sí (se le debe al corresponsal)" : "No (el corresponsal debe)";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #0066cc;'>Nuevo tercero creado</h2>
                <p>Se ha registrado un nuevo tercero para el corresponsal <strong>$correspondentName</strong>.</p>
                <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; font-size: 14px;'>
                    <tr><th>Nombre</th><td>{$other['name']}</td></tr>
                    <tr><th>Tipo ID</th><td>{$other['id_type']}</td></tr>
                    <tr><th>Número ID</th><td>{$other['id_number']}</td></tr>
                    <tr><th>Correo</th><td>{$other['email']}</td></tr>
                    <tr><th>Teléfono</th><td>{$other['phone']}</td></tr>
                    <tr><th>Dirección</th><td>{$other['address']}</td></tr>
                    <tr><th>Crédito</th><td>$ {$other['credit']}</td></tr>
                    <tr><th>Balance</th><td>$ {$other['balance']}</td></tr>
                    <tr><th>Tipo de balance</th><td>$negBalanceText</td></tr>
                    <tr><th>Estado</th><td>" . ($other['state'] ? "Activo" : "Inactivo") . "</td></tr>
                </table>
                <p style='color: #888; font-size: 12px;'>COBAN365 - Sistema de gestión de corresponsales</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["correspondent_id"], $data["name"], $data["credit"], $data["balance"], $data["id_type"], $data["id_number"])) {
        echo json_encode(["success" => false, "message" => "Faltan campos obligatorios."]);
        exit;
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $name = trim($data["name"]);
    $credit = floatval($data["credit"]);
    $balance = floatval($data["balance"]);
    $negative_balance = isset($data["negative_balance"]) && $data["negative_balance"] ? 1 : 0;
    $state = isset($data["state"]) ? intval($data["state"]) : 1;
    $id_type = trim($data["id_type"]);
    $id_number = trim($data["id_number"]);
    $email = isset($data["email"]) ? trim($data["email"]) : null;
    $phone = isset($data["phone"]) ? trim($data["phone"]) : null;
    $address = isset($data["address"]) ? trim($data["address"]) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO others (
                correspondent_id, name, id_type, id_number, email, phone, address, credit, balance, negative_balance, state
            ) VALUES (
                :correspondent_id, :name, :id_type, :id_number, :email, :phone, :address, :credit, :balance, :negative_balance, :state
            )
        ");

        $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":id_type", $id_type, PDO::PARAM_STR);
        $stmt->bindParam(":id_number", $id_number, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
        $stmt->bindParam(":address", $address, PDO::PARAM_STR);
        $stmt->bindParam(":credit", $credit);
        $stmt->bindParam(":balance", $balance);
        $stmt->bindParam(":negative_balance", $negative_balance, PDO::PARAM_INT);
        $stmt->bindParam(":state", $state, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $adminQuery = $pdo->prepare("
                SELECT u.email, c.name AS correspondent_name
                FROM correspondents c
                INNER JOIN users u ON u.id = c.operator_id
                WHERE c.id = :correspondent_id
            ");
            $adminQuery->execute(["correspondent_id" => $correspondent_id]);
            $adminData = $adminQuery->fetch(PDO::FETCH_ASSOC);

            if ($adminData && $adminData["email"]) {
                notifyAdmin($adminData["email"], $adminData["correspondent_name"], [
                    "name" => $name,
                    "id_type" => $id_type,
                    "id_number" => $id_number,
                    "email" => $email ?? "No proporcionado",
                    "phone" => $phone ?? "No proporcionado",
                    "address" => $address ?? "No proporcionado",
                    "credit" => number_format($credit, 2),
                    "balance" => number_format($balance, 2),
                    "negative_balance" => $negative_balance,
                    "state" => $state
                ]);
            }

            echo json_encode(["success" => true, "message" => "Tercero registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el tercero."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
