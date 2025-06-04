<?php
/**
 * Archivo: create_transaction_note.php
 * Descripción: Duplica una transacción como nota crédito o débito, ajustando el valor y polaridad según el tipo de nota.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.5
 * Fecha de actualización: 04-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../../libs/src/PHPMailer.php';
require_once '../../libs/src/SMTP.php';
require_once '../../libs/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendNotification($to, $subject, $body)
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
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar notificación: " . $mail->ErrorInfo);
    }
}

// Leer JSON
$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['original_transaction_id']) ||
    !isset($data['type']) ||
    !isset($data['new_value']) ||
    !isset($data['observation'])
) {
    echo json_encode(["success" => false, "message" => "Parámetros incompletos"]);
    exit();
}

$originalId = intval($data['original_transaction_id']);
$type = strtolower(trim($data['type']));
$newValue = floatval($data['new_value']);
$observation = trim($data['observation']);

if (!in_array($type, ['credit', 'debit'])) {
    echo json_encode(["success" => false, "message" => "Tipo de nota inválido. Use 'credit' o 'debit'."]);
    exit();
}

if ($newValue <= 0) {
    echo json_encode(["success" => false, "message" => "El valor debe ser mayor a cero."]);
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener transacción original
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id");
    $stmt->execute([":id" => $originalId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        echo json_encode(["success" => false, "message" => "Transacción original no encontrada"]);
        exit();
    }

    $newPolarity = ($type === 'credit') ? 0 : 1;
    $noteLabel = ($type === 'credit') ? 'Nota crédito' : 'Nota débito';
    $cancellationNote = "$noteLabel: $observation";

    // Insertar nueva transacción
    $insert = $pdo->prepare("
        INSERT INTO transactions (
            id_cashier, id_cash, id_correspondent, transaction_type_id,
            polarity, neutral, cost, state, note, cancellation_note,
            client_reference, third_party_note, created_at, utility,
            is_transfer, box_reference, transfer_status
        ) VALUES (
            :id_cashier, :id_cash, :id_correspondent, :transaction_type_id,
            :polarity, :neutral, :cost, 1, :note, :cancellation_note,
            :client_reference, :third_party_note, NOW(), :utility,
            :is_transfer, :box_reference, :transfer_status
        )
    ");

    $insert->execute([
        ":id_cashier" => $original["id_cashier"],
        ":id_cash" => $original["id_cash"],
        ":id_correspondent" => $original["id_correspondent"],
        ":transaction_type_id" => $original["transaction_type_id"],
        ":polarity" => $newPolarity,
        ":neutral" => $original["neutral"],
        ":cost" => $newValue,
        ":note" => $noteLabel,
        ":cancellation_note" => $cancellationNote,
        ":client_reference" => $original["client_reference"],
        ":third_party_note" => $original["third_party_note"],
        ":utility" => 0.00,
        ":is_transfer" => $original["is_transfer"],
        ":box_reference" => $original["box_reference"],
        ":transfer_status" => $original["transfer_status"],
    ]);

    // Obtener administrador del corresponsal
    $adminStmt = $pdo->prepare("
        SELECT u.email, u.fullname AS admin_name, c.name AS correspondent_name
        FROM users u
        INNER JOIN correspondents c ON u.id = c.operator_id
        WHERE c.id = :correspondent_id
    ");
    $adminStmt->execute([":correspondent_id" => $original["id_correspondent"]]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    // Obtener cajero
    $cashierStmt = $pdo->prepare("SELECT email, fullname FROM users WHERE id = :id");
    $cashierStmt->execute([":id" => $original["id_cashier"]]);
    $cashier = $cashierStmt->fetch(PDO::FETCH_ASSOC);

    // Obtener nombre de la caja
    $cashStmt = $pdo->prepare("SELECT name FROM cash WHERE id = :id");
    $cashStmt->execute([":id" => $original["id_cash"]]);
    $cash = $cashStmt->fetch(PDO::FETCH_ASSOC);

    $details = "
    <div style='font-family: Arial, sans-serif; padding: 16px;'>
        <h2>$noteLabel generada en COBAN365</h2>
        <p><strong>💰 Valor:</strong> $" . number_format($newValue, 0, ',', '.') . "</p>
        <p><strong>📝 Observación:</strong> $observation</p>
        <p><strong>🏪 Corresponsal:</strong> {$admin['correspondent_name']}</p>
        <p><strong>💼 Caja:</strong> {$cash['name']}</p>
        <p style='font-size: 13px; color: #888;'>Este mensaje fue generado automáticamente por COBAN365.</p>
    </div>
    ";

    // Notificar administrador
    if (!empty($admin['email'])) {
        sendNotification($admin['email'], "🧾 $noteLabel registrada en COBAN365", $details);
    }

    // Notificar cajero
    if (!empty($cashier['email'])) {
        sendNotification($cashier['email'], "🧾 Confirmación de $noteLabel", $details);
    }

    echo json_encode(["success" => true, "message" => "Nota $noteLabel generada correctamente."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
}
?>