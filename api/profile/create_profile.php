<?php
/**
 * Archivo: create_user.php
 * Descripción: Permite registrar un nuevo usuario en la base de datos y notifica por correo al usuario y a los administradores.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
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
require_once "../config/server.php"; // 👈 Para usar BASE_URL_FRONT
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendUserNotification($toEmails, $userData, $isAdmin = false)
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
        $mail->Subject = $isAdmin ? "👤 Nuevo Usuario Registrado en COBAN365" : "👋 Bienvenido a COBAN365";

        $loginButton = "<a href='" . BASE_URL_FRONT . "' style='display: inline-block; padding: 10px 20px; background-color: #2c3e50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>Iniciar sesión ahora</a>";

        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #2c3e50;'>" . ($isAdmin ? "Se ha registrado un nuevo usuario" : "Tu cuenta ha sido creada exitosamente") . "</h2>
                <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                    <tr><th>Email</th><td>{$userData['email']}</td></tr>
                    <tr><th>Nombre</th><td>{$userData['fullname']}</td></tr>
                    <tr><th>Rol</th><td>{$userData['role']}</td></tr>
                    <tr><th>Contraseña asignada</th><td>{$userData['raw_password']}</td></tr>
                </table>
                <p style='font-size: 14px; color: #7f8c8d;'>Puedes cambiar tu contraseña en cualquier momento desde la configuración de tu cuenta.</p>
                <div style='margin-top: 20px;'>$loginButton</div>
            </div>
        ";

        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo de usuario: " . $mail->ErrorInfo);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["email"], $data["fullname"], $data["password"], $data["role"])) {
        echo json_encode(["success" => false, "message" => "Todos los campos obligatorios deben ser proporcionados."]);
        exit;
    }

    $email = trim($data["email"]);
    $fullname = trim($data["fullname"]);
    $phone = isset($data["phone"]) ? trim($data["phone"]) : null;
    $raw_password = $data["password"];
    $password = password_hash($raw_password, PASSWORD_BCRYPT);
    $status = 1;
    $role = trim($data["role"]);
    $permissions = isset($data["permissions"]) ? json_encode($data["permissions"], JSON_UNESCAPED_UNICODE) : null;

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->bindParam(":email", $email, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El correo electrónico ya está registrado."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (email, fullname, phone, password, status, role, permissions) 
                               VALUES (:email, :fullname, :phone, :password, :status, :role, :permissions)");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
        $stmt->bindParam(":password", $password, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
        $stmt->bindParam(":role", $role, PDO::PARAM_STR);
        $stmt->bindParam(":permissions", $permissions, PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Obtener emails de administradores
            $adminStmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1");
            $adminEmails = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            $userData = [
                "email" => $email,
                "fullname" => $fullname,
                "role" => $role,
                "raw_password" => $raw_password
            ];

            // Enviar notificación al nuevo usuario
            sendUserNotification([$email], $userData, false);

            // Notificar a los administradores
            if (!empty($adminEmails)) {
                sendUserNotification($adminEmails, $userData, true);
            }

            echo json_encode(["success" => true, "message" => "Usuario registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el usuario."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>