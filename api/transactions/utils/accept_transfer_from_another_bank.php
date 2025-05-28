<?php
/**
 * Archivo: accept_transfer_from_another_bank.php
 * Descripción: Acepta una transferencia pendiente entre cajas actualizando su estado.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 27-May-2025 (actualizado)
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

// Leer datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar parámetro requerido
if (!isset($data["transaction_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Parámetro requerido: transaction_id"
    ]);
    exit;
}

$transactionId = intval($data["transaction_id"]);

try {
    // Validar que exista la transacción pendiente de aceptar
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE id = :id 
        AND is_transfer = 1 
        AND transfer_status = 0
    ");
    $stmt->execute([":id" => $transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode([
            "success" => false,
            "message" => "La transacción no existe o ya fue aceptada."
        ]);
        exit;
    }

    // Actualizar estado a aceptada y neutral = 0
    $update = $pdo->prepare("UPDATE transactions SET transfer_status = 1, neutral = 0 WHERE id = :id");
    $update->bindParam(":id", $transactionId, PDO::PARAM_INT);
    $update->execute();

    echo json_encode([
        "success" => true,
        "message" => "Transferencia aceptada correctamente.",
        "data" => [
            "id" => $transactionId,
            "cost" => $transaction["cost"],
            "from_cash" => $transaction["id_cash"],
            "to_cash" => $transaction["box_reference"]
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al aceptar la transferencia: " . $e->getMessage()
    ]);
}
