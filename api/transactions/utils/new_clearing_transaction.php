<?php
date_default_timezone_set('America/Bogota'); // Hora local de Bogotá

/**
 * Archivo: new_clearing_transaction.php
 * Descripción: Registra una transacción de compensación con utilidad, nota clave 'offset_transaction' y etiqueta de caja (cash_tag).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de creación: 26-May-2025
 * Fecha de modificación: 07-Ago-2025
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
        $data["cost"],
        $data["cash_tag"] // ← requerido ahora
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para crear la transacción de compensación."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $polarity = boolval($data["polarity"]);
    $cost = floatval($data["cost"]);
    $cash_tag = trim($data["cash_tag"]); // ← nuevo campo
    $utility = isset($data["utility"]) ? floatval($data["utility"]) : 0;
    $state = 1;
    $created_at = date("Y-m-d H:i:s"); // Hora actual Bogotá

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

        // Nota fija para compensaciones
        $note = "offset_transaction";
        $neutral = 0; // Siempre se marca como neutral

        // Insertar transacción con cash_tag
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, client_reference, utility,
                neutral, created_at, cash_tag
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, NULL, :utility,
                :neutral, :created_at, :cash_tag
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
        $stmt->bindParam(":utility", $utility);
        $stmt->bindParam(":neutral", $neutral, PDO::PARAM_BOOL);
        $stmt->bindParam(":created_at", $created_at, PDO::PARAM_STR);
        $stmt->bindParam(":cash_tag", $cash_tag, PDO::PARAM_STR); // ← nuevo campo

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transacción de compensación registrada exitosamente.",
                "cash_tag" => $cash_tag
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar la transacción de compensación."
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
