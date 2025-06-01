<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";
require_once "../config/server.php";  // para que BASE_URL esté disponible

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data["email"])) {
        echo json_encode(["success" => false, "message" => "El campo 'email' es obligatorio."]);
        exit;
    }

    $email = trim($data["email"]);

    // Verificar si el email existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "No se encontró una cuenta con ese correo."]);
        exit;
    }

    // Generar token y guardar hash + expiración
    $token = bin2hex(random_bytes(16));
    $token_hash = password_hash($token, PASSWORD_BCRYPT);
    $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Guardar en base de datos
    $userId = $stmt->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token_hash, $expires_at]);

    // Link de recuperación
    $recovery_link = $baseUrlApi . "/public/reset_password.php?token=$token&email=" . urlencode($email);

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
        $mail->Subject = 'Recuperación de contraseña - COBAN365';
        $mail->Body = "
            <div style='font-family: \"Roboto\", sans-serif; background-color: #f2f2f2; padding: 30px;'>
              <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                <h2 style='font-family: \"Montserrat\", sans-serif; color: #1a1a1a; text-align: center;'>Restablecer tu contraseña</h2>
                <p style='font-size: 16px; color: #1a1a1a;'>Hola,</p>
                <p style='font-size: 16px; color: #1a1a1a;'>Hemos recibido una solicitud para restablecer tu contraseña en <strong>COBAN365</strong>.</p>
                <p style='text-align: center; margin: 30px 0;'>
                  <a href='$recovery_link' style='display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 14px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; font-size: 16px;'>Restablecer contraseña</a>
                </p>
                <p style='font-size: 14px; color: #4d4d4d;'>Este enlace estará activo durante 1 hora.</p>
                <p style='font-size: 14px; color: #4d4d4d;'>Si no solicitaste esta acción, simplemente ignora este correo.</p>
                <hr style='margin-top: 30px; border: none; border-top: 1px solid #dddddd;' />
                <p style='font-size: 12px; color: #aaaaaa; text-align: center;'>COBAN365 - Todos los derechos reservados</p>
              </div>
            </div>
        ";

        $mail->send();
        echo json_encode(["success" => true, "message" => "Correo de recuperación enviado."]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Error al enviar correo: {$mail->ErrorInfo}"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
