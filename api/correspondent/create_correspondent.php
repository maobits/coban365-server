<?php
/**
 * Archivo: create_correspondent.php
 * Descripción: Permite registrar un nuevo corresponsal en la base de datos y notifica a los superadministradores.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.2
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
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifySuperadmins($pdo, $correspondent)
{
    $stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'superadmin' AND status = 1");
    $stmt->execute();
    $superadmins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($superadmins))
        return;

    $location = json_decode($correspondent['location'], true);
    $transactions = json_decode($correspondent['transactions'], true);

    // 🔹 Arreglado: se usa 'fullname AS name'
    $operatorStmt = $pdo->prepare("SELECT fullname AS name, email FROM users WHERE id = :id LIMIT 1");
    $operatorStmt->execute(['id' => $correspondent['operator_id']]);
    $operator = $operatorStmt->fetch(PDO::FETCH_ASSOC);
    $operatorName = $operator['name'] ?? 'Desconocido';
    $operatorEmail = $operator['email'] ?? null;

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

        foreach ($superadmins as $email) {
            $mail->addAddress($email);
        }

        if ($operatorEmail) {
            $mail->addCC($operatorEmail);
        }

        $mail->isHTML(true);
        $mail->Subject = "🔔 Nuevo Corresponsal Registrado";

        $transactionsList = '';
        foreach ($transactions as $t) {
            $transactionsList .= "<li>{$t['name']}</li>";
        }

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #2c3e50;'>Se ha registrado un nuevo corresponsal en COBAN365</h2>
                <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                    <tr><th>Código</th><td>{$correspondent['code']}</td></tr>
                    <tr><th>Nombre</th><td>{$correspondent['name']}</td></tr>
                    <tr><th>Departamento</th><td>{$location['departamento']}</td></tr>
                    <tr><th>Ciudad</th><td>{$location['ciudad']}</td></tr>
                    <tr><th>Cupo</th><td>" . number_format($correspondent['credit_limit'], 0, ',', '.') . " COP</td></tr>
                    <tr><th>Administrador</th><td>$operatorName</td></tr>
                </table>
                <p style='font-size: 14px; color: #7f8c8d;'>Este mensaje fue generado automáticamente por el sistema COBAN365.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar notificación de corresponsal: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["type_id"], $data["code"], $data["operator_id"], $data["name"], $data["location"])) {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
        exit;
    }

    $type_id = intval($data["type_id"]);
    $code = trim($data["code"]);
    $operator_id = intval($data["operator_id"]);
    $name = trim($data["name"]);
    $location = json_encode($data["location"], JSON_UNESCAPED_UNICODE);
    $transactions = isset($data["transactions"]) ? json_encode($data["transactions"], JSON_UNESCAPED_UNICODE) : json_encode([]);
    $credit_limit = isset($data["credit_limit"]) ? intval($data["credit_limit"]) : 0;

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM correspondents WHERE code = :code");
        $checkStmt->bindParam(":code", $code, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El código del corresponsal ya existe."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO correspondents (type_id, code, operator_id, name, location, transactions, credit_limit) 
                               VALUES (:type_id, :code, :operator_id, :name, :location, :transactions, :credit_limit)");
        $stmt->bindParam(":type_id", $type_id, PDO::PARAM_INT);
        $stmt->bindParam(":code", $code, PDO::PARAM_STR);
        $stmt->bindParam(":operator_id", $operator_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        $stmt->bindParam(":transactions", $transactions, PDO::PARAM_STR);
        $stmt->bindParam(":credit_limit", $credit_limit, PDO::PARAM_INT);

        if ($stmt->execute()) {
            notifySuperadmins($pdo, [
                "code" => $code,
                "name" => $name,
                "location" => $location,
                "transactions" => $transactions,
                "credit_limit" => $credit_limit,
                "operator_id" => $operator_id
            ]);

            echo json_encode(["success" => true, "message" => "Corresponsal registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el corresponsal."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
