<?php
/**
 * Archivo: update_premium.php
 * Descripción: Actualiza el estado premium de un corresponsal y notifica al operador y a los superadministradores.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.0
 * Fecha de actualización: 03-Jun-2025
 */

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Dependencias
require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verificar método POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
if (!isset($data["id"], $data["premium"])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit();
}

$id = intval($data["id"]);
$premium = intval($data["premium"]); // 0 o 1

try {
    // Actualizar estado premium
    $stmt = $pdo->prepare("UPDATE correspondents SET premium = :premium, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":premium", $premium, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "No se encontró el corresponsal o el estado ya estaba asignado."]);
        exit();
    }

    // Obtener información del corresponsal y operador
    $infoStmt = $pdo->prepare("
        SELECT c.name AS correspondent_name, u.email AS admin_email, u.fullname AS admin_name
        FROM correspondents c
        LEFT JOIN users u ON c.operator_id = u.id
        WHERE c.id = :id
    ");
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo json_encode(["success" => true, "message" => "Estado actualizado, pero no se encontró info del corresponsal."]);
        exit();
    }

    // Obtener correos de superadmins
    $superStmt = $pdo->query("SELECT email FROM users WHERE role = 'superadmin'");
    $superadmins = $superStmt->fetchAll(PDO::FETCH_COLUMN);

    $correspondentName = $info['correspondent_name'];
    $adminEmail = $info['admin_email'];
    $adminName = $info['admin_name'];
    $isPremium = $premium === 1;

    $subject = $isPremium
        ? "🎖 Corresponsal ascendido a PREMIUM"
        : "🔘 Corresponsal cambiado a modo BÁSICO";

    // Diseño del correo
    $body = "
    <div style='font-family:Arial, sans-serif; color:#333; max-width:600px; margin:20px auto;'>
      <h2 style='color:#2c3e50;'>🔔 Notificación de cambio de estado</h2>
      <p>El corresponsal <strong style='color:#2980b9;'>$correspondentName</strong> ha sido actualizado a estado:</p>
      <p style='font-size:18px; font-weight:bold; color:" . ($isPremium ? '#f1c40f' : '#7f8c8d') . ";'>
        " . ($isPremium ? "PREMIUM 🥇" : "BÁSICO ⚪") . "
      </p>
      <hr style='border:none; border-top:1px solid #ccc; margin:20px 0;' />
      <p style='font-size:14px;'>Este cambio fue realizado desde la plataforma <strong>COBAN365</strong> y notificado automáticamente a los responsables.</p>
      <p style='font-size:12px; color:#999;'>Fecha: " . date("d-m-Y H:i") . "</p>
    </div>
    ";

    // Enviar correo
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->CharSet = "UTF-8";
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'notifications@coban365.maobits.com';
    $mail->Password = 'Coban3652025@'; // Cambia esto por tu contraseña real
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('notifications@coban365.maobits.com', 'COBAN365 Notificaciones');

    foreach ($superadmins as $superEmail) {
        $mail->addBCC($superEmail);
    }

    if ($adminEmail) {
        $mail->addAddress($adminEmail, $adminName ?: 'Administrador');
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    echo json_encode([
        "success" => true,
        "message" => "Estado premium actualizado y notificación enviada."
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al enviar notificación: " . $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
