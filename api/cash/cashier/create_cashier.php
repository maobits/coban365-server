<?php
/**
 * Archivo: create_cashier.php
 * Descripción: Permite registrar un nuevo cajero con sus corresponsales asignados y notifica por correo.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 01-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../db.php";
require_once "../../config/server.php";
require_once "../../../libs/src/PHPMailer.php";
require_once "../../../libs/src/SMTP.php";
require_once "../../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notifyEmails($toEmails, $subject, $htmlBody)
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
        foreach ($toEmails as $email) {
            $mail->addAddress($email);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data["email"], $data["fullname"], $data["password"], $data["correspondents"]) ||
    !is_array($data["correspondents"]) || empty($data["correspondents"])
) {
    echo json_encode(["success" => false, "message" => "Faltan campos obligatorios o correspondents no válido."]);
    exit;
}

$email = trim($data["email"]);
$fullname = trim($data["fullname"]);
$raw_password = $data["password"];
$password = password_hash($raw_password, PASSWORD_BCRYPT);
$phone = isset($data["phone"]) ? trim($data["phone"]) : null;
$role = isset($data["role"]) ? trim($data["role"]) : "cajero";
$status = 1;
$permissions = json_encode(["manageCash"], JSON_UNESCAPED_UNICODE);
$correspondents = json_encode($data["correspondents"], JSON_UNESCAPED_UNICODE);

try {
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->bindParam(":email", $email, PDO::PARAM_STR);
    $stmtCheck->execute();

    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "El correo ya está registrado."]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (
        email, fullname, phone, password, status, role, permissions, correspondents
    ) VALUES (
        :email, :fullname, :phone, :password, :status, :role, :permissions, :correspondents
    )");

    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
    $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
    $stmt->bindParam(":password", $password, PDO::PARAM_STR);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":role", $role, PDO::PARAM_STR);
    $stmt->bindParam(":permissions", $permissions, PDO::PARAM_STR);
    $stmt->bindParam(":correspondents", $correspondents, PDO::PARAM_STR);

    if ($stmt->execute()) {
        // 1. Notificar al nuevo cajero
        $body = "
    <div style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color: #0066cc;'>👋 Bienvenido a COBAN365</h2>
        <p>Tu cuenta de acceso ha sido creada exitosamente.</p>
        <table style='margin-top: 10px;'>
            <tr><td><strong>📧 Correo:</strong></td><td>$email</td></tr>
            <tr><td><strong>🙍‍♂️ Nombre:</strong></td><td>$fullname</td></tr>
            <tr><td><strong>🔐 Contraseña:</strong></td><td>$raw_password</td></tr>
        </table>
        <p style='margin-top: 20px;'>Puedes iniciar sesión en: 
        <a href='" . BASE_URL_FRONT . "' style='color: #0066cc; font-weight: bold;'>" . BASE_URL_FRONT . "</a></p>
        <p style='margin-top: 30px;'>Si tienes alguna duda, contacta al administrador del sistema.</p>
        <hr style='margin-top: 40px;'>
        <small style='color: #777;'>COBAN365 · Plataforma de gestión de corresponsales</small>
    </div>
";

        notifyEmails([$email], "👋 Bienvenido a COBAN365", $body);

        // 2. Buscar operadores de los corresponsales
        $correspondentIds = array_column($data["correspondents"], "id");
        $in = str_repeat('?,', count($correspondentIds) - 1) . '?';

        $query = "SELECT u.email FROM users u 
                  INNER JOIN correspondents c ON c.operator_id = u.id
                  WHERE c.id IN ($in) AND u.status = 1";

        $stmtOps = $pdo->prepare($query);
        $stmtOps->execute($correspondentIds);
        $adminEmails = $stmtOps->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($adminEmails)) {
            $adminBody = "
    <div style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color: #cc0000;'>👤 Nuevo cajero asignado a tu corresponsal</h2>
        <p>Se ha registrado un nuevo cajero en el sistema COBAN365 con los siguientes datos:</p>
        <table style='margin-top: 10px;'>
            <tr><td><strong>🙍‍♂️ Nombre:</strong></td><td>$fullname</td></tr>
            <tr><td><strong>📧 Email:</strong></td><td>$email</td></tr>
            <tr><td><strong>📱 Teléfono:</strong></td><td>$phone</td></tr>
        </table>
        <p style='margin-top: 20px;'>Puedes verificar esta información desde tu panel de gestión en COBAN365.</p>
        <hr style='margin-top: 40px;'>
        <small style='color: #777;'>COBAN365 · Notificación automática del sistema</small>
    </div>
";

            notifyEmails($adminEmails, "👤 Se ha creado un nuevo cajero", $adminBody);
        }

        echo json_encode(["success" => true, "message" => "Cajero registrado exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar el cajero."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
?>