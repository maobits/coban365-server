<?php
date_default_timezone_set('America/Bogota'); // Hora local de Bogotá

/**
 * Archivo: new_transaction.php
 * Descripción: Registra una nueva transacción con utilidad, nombre de tipo, valor opcional client_reference y cash_tag.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.2
 * Fecha de actualización: 07-Ago-2025
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
        $data["polarity"],
        $data["cost"]
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para crear la transacción."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $polarity = boolval($data["polarity"]);
    $cost = floatval($data["cost"]);
    $utility = isset($data["utility"]) ? floatval($data["utility"]) : 0;
    $client_reference = isset($data["client_reference"]) ? $data["client_reference"] : null;
    $cash_tag = isset($data["cash_tag"]) ? floatval($data["cash_tag"]) : null; // 🆕 nuevo campo
    $state = 1;
    $created_at = date("Y-m-d H:i:s"); // 🕒 Fecha actual con zona horaria Bogotá

    try {
        // Obtener el nombre y categoría del tipo de transacción
        $typeStmt = $pdo->prepare("SELECT name, category FROM transaction_types WHERE id = :id");
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

        $note = "-";
        $neutral = (strtolower($type["category"]) === "otros") ? 1 : 0;

        // Insertar transacción con fecha y cash_tag
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, client_reference, utility, neutral, created_at, cash_tag
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, :client_reference, :utility, :neutral, :created_at, :cash_tag
            )
        ");

        $stmt->bindParam(":id_cashier", $id_cashier, PDO::PARAM_INT);
        $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
        $stmt->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
        $stmt->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
        $stmt->bindParam(":polarity", $polarity, PDO::PARAM_BOOL);
        $stmt->bindParam(":cost", $cost);
        $stmt->bindParam(":state", $state, PDO::PARAM_INT);
        $stmt->bindParam(":note", $note, PDO::PARAM_STR);
        $stmt->bindParam(":client_reference", $client_reference, PDO::PARAM_STR);
        $stmt->bindParam(":utility", $utility);
        $stmt->bindParam(":neutral", $neutral, PDO::PARAM_BOOL);
        $stmt->bindParam(":created_at", $created_at);
        $stmt->bindParam(":cash_tag", $cash_tag);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transacción registrada exitosamente.",
                "timestamp" => $created_at
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar la transacción."
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
?>