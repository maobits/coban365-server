<?php
/**
 * Archivo: create_rate.php
 * Descripción: Registra una nueva tarifa (rate) en la base de datos y notifica al administrador del corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
 * Fecha de actualización: 01-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../db.php";
require_once "../../libs/src/PHPMailer.php";
require_once "../../libs/src/SMTP.php";
require_once "../../libs/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["transaction_type_id"], $data["price"], $data["correspondent_id"])) {
    echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
    exit;
}

$transaction_type_id = intval($data["transaction_type_id"]);
$price = floatval($data["price"]);
$correspondent_id = intval($data["correspondent_id"]);

try {
    // Conexión PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insertar nueva tarifa
    $stmt = $conn->prepare("INSERT INTO rates (transaction_type_id, price, correspondent_id) 
                            VALUES (:transaction_type_id, :price, :correspondent_id)");
    $stmt->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
    $stmt->bindParam(":price", $price);
    $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);

    if ($stmt->execute()) {

        // 🔔 Obtener datos del corresponsal y su administrador
        $adminQuery = $conn->prepare("
            SELECT u.email, u.fullname, c.name AS correspondent_name, tt.name AS transaction_name
            FROM correspondents c
            JOIN users u ON u.id = c.operator_id
            JOIN transaction_types tt ON tt.id = :transaction_type_id
            WHERE c.id = :correspondent_id
        ");
        $adminQuery->execute([
            ":correspondent_id" => $correspondent_id,
            ":transaction_type_id" => $transaction_type_id
        ]);

        $admin = $adminQuery->fetch(PDO::FETCH_ASSOC);

        if ($admin && filter_var($admin["email"], FILTER_VALIDATE_EMAIL)) {
            // 📨 Enviar correo
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.hostinger.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'notifications@coban365.maobits.com';
                $mail->Password = 'Coban3652025@'; // ← reemplazar con tu contraseña real
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;

                $mail->setFrom('notifications@coban365.maobits.com', 'COBAN365');
                $mail->addAddress($admin["email"], $admin["fullname"]);

                $mail->isHTML(true);
                $mail->Subject = 'Nueva tarifa registrada en tu corresponsal';
                $mail->Body = "
                    <p>Hola <strong>{$admin['fullname']}</strong>,</p>
                    <p>Se ha registrado una nueva <strong>tarifa</strong> en tu corresponsal <strong>{$admin['correspondent_name']}</strong>.</p>
                    <ul>
                        <li><strong>Transacción:</strong> {$admin['transaction_name']}</li>
                        <li><strong>Precio:</strong> " . number_format($price, 0, ',', '.') . " COP</li>
                    </ul>
                    <p>Puedes gestionarla desde tu panel en COBAN365.</p>
                ";

                $mail->send();
            } catch (Exception $e) {
                // Error silencioso, pero continúa con éxito
            }
        }

        echo json_encode(["success" => true, "message" => "Tarifa registrada exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar la tarifa."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
}
?>