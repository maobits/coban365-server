<?php
/**
 * Archivo: cancel_transfer.php
 * Descripción: Cancela una transacción activa sin eliminarla, agregando una nota personalizada.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 28-May-2025
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

// Validar parámetros requeridos
if (!isset($data["transaction_id"]) || !isset($data["cancellation_note"])) {
    echo json_encode([
        "success" => false,
        "message" => "Parámetros requeridos: transaction_id y cancellation_note"
    ]);
    exit;
}

$transactionId = intval($data["transaction_id"]);
$cancellationNote = trim($data["cancellation_note"]);

try {
    // Verificar que la transacción esté activa
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE id = :id 
        AND state = 1
    ");
    $stmt->execute([":id" => $transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode([
            "success" => false,
            "message" => "La transacción no existe o ya está cancelada."
        ]);
        exit;
    }

    // Actualizar: marcar como cancelada y guardar la nota
    $update = $pdo->prepare("
        UPDATE transactions 
        SET state = 0,
            cancellation_note = :note
        WHERE id = :id
    ");
    $update->execute([
        ":note" => $cancellationNote,
        ":id" => $transactionId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Transacción cancelada correctamente.",
        "data" => [
            "id" => $transactionId,
            "cancellation_note" => $cancellationNote
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al cancelar la transacción: " . $e->getMessage()
    ]);
}
