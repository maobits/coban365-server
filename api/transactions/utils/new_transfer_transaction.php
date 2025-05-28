<?php
date_default_timezone_set('America/Bogota'); // Hora local de Bogotá

/**
 * Archivo: new_transfer_transaction.php
 * Descripción: Registra una nueva transferencia entre cajas.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha: 27-May-2025
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset(
        $data["id_cashier"],
        $data["id_cash"],
        $data["id_correspondent"],
        $data["transaction_type_id"],
        $data["cost"],
        $data["box_reference"]
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para registrar la transferencia."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $cost = floatval($data["cost"]);
    $box_reference = intval($data["box_reference"]);

    // Valores por defecto para transferencia
    $polarity = 0;
    $neutral = 1;
    $utility = 0;
    $state = 1;
    $is_transfer = 1;
    $transfer_status = 0; // Requiere aprobación

    try {
        // Obtener nombre del tipo de transacción
        $typeStmt = $pdo->prepare("SELECT name FROM transaction_types WHERE id = :id");
        $typeStmt->bindParam(":id", $transaction_type_id, PDO::PARAM_INT);
        $typeStmt->execute();
        $type = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$type) {
            echo json_encode([
                "success" => false,
                "message" => "Tipo de transacción no encontrado."
            ]);
            exit;
        }

        $note = "Transferencia: " . $type["name"];

        // Insertar transacción
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, utility, neutral,
                is_transfer, box_reference, transfer_status
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, :utility, :neutral,
                :is_transfer, :box_reference, :transfer_status
            )
        ");

        $stmt->bindParam(":id_cashier", $id_cashier, PDO::PARAM_INT);
        $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
        $stmt->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
        $stmt->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
        $stmt->bindParam(":polarity", $polarity, PDO::PARAM_INT);
        $stmt->bindParam(":cost", $cost);
        $stmt->bindParam(":state", $state, PDO::PARAM_INT);
        $stmt->bindParam(":note", $note, PDO::PARAM_STR);
        $stmt->bindParam(":utility", $utility);
        $stmt->bindParam(":neutral", $neutral, PDO::PARAM_INT);
        $stmt->bindParam(":is_transfer", $is_transfer, PDO::PARAM_INT);
        $stmt->bindParam(":box_reference", $box_reference, PDO::PARAM_INT);
        $stmt->bindParam(":transfer_status", $transfer_status, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transferencia registrada exitosamente."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar la transferencia."
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error en la base de datos: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ]);
}
