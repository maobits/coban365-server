<?php
/**
 * Archivo: create_transaction_type.php
 * Descripción: Permite registrar un nuevo tipo de transacción en la base de datos y notifica a los administradores.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.0
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
require_once "../config/server.php"; // Por si se necesita BASE_URL en el futuro
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

// Establecer zona horaria Colombia (Bogotá)
date_default_timezone_set("America/Bogota");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifyAdminsNewTransactionType($pdo, $category, $name, $polarity)
{
    $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($admins))
        return;

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

        foreach ($admins as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->Subject = "📌 Nuevo Tipo de Transacción Registrado";

        $polarityLabel = $polarity ? "Positiva" : "Negativa";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #2c3e50;'>Se ha creado un nuevo tipo de transacción en COBAN365</h2>
                <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                    <tr><th>Categoría</th><td>{$category}</td></tr>
                    <tr><th>Nombre</th><td>{$name}</td></tr>
                    <tr><th>Polaridad</th><td>{$polarityLabel}</td></tr>
                </table>
                <p style='font-size: 14px; color: #7f8c8d;'>Este mensaje fue generado automáticamente por el sistema COBAN365.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar notificación de tipo de transacción: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["category"], $data["name"], $data["polarity"])) {
        echo json_encode([
            "success" => false,
            "message" => "Los campos 'category', 'name' y 'polarity' son obligatorios."
        ]);
        exit;
    }

    $category = trim($data["category"]);
    $name = trim($data["name"]);
    $polarity = boolval($data["polarity"]);

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM transaction_types WHERE category = :category AND name = :name");
        $checkStmt->bindParam(":category", $category, PDO::PARAM_STR);
        $checkStmt->bindParam(":name", $name, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El tipo de transacción ya existe."]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO transaction_types (category, name, polarity)
            VALUES (:category, :name, :polarity)
        ");
        $stmt->bindParam(":category", $category, PDO::PARAM_STR);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":polarity", $polarity, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            notifyAdminsNewTransactionType($pdo, $category, $name, $polarity);
            echo json_encode(["success" => true, "message" => "Tipo de transacción registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el tipo de transacción."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
