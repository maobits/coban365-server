<?php
date_default_timezone_set('America/Bogota'); // 🕒 Hora local de Bogotá

/**
 * Archivo: new-third-party-transaction.php
 * Descripción: Registra una transacción de terceros con client_reference y polaridad automática.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 25-May-2025 (actualizado)
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
        $data["third_party_note"],
        $data["client_reference"]
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para registrar la transacción."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $cost = floatval($data["cost"]);
    $third_party_note = $data["third_party_note"];
    $client_reference = intval($data["client_reference"]);
    $utility = isset($data["utility"]) ? floatval($data["utility"]) : 0;
    $state = 1;
    $created_at = date("Y-m-d H:i:s"); // ⏰ Fecha y hora actual de Bogotá

    // Determinar polaridad automáticamente
    $expectedPolarityMap = [
        "debt_to_third_party" => 0,
        "charge_to_third_party" => 1,
        "loan_to_third_party" => 0,
        "loan_from_third_party" => 1
    ];

    if (!array_key_exists($third_party_note, $expectedPolarityMap)) {
        echo json_encode([
            "success" => false,
            "message" => "Nota especial de tercero no válida: '{$third_party_note}'."
        ]);
        exit;
    }

    $polarity = $expectedPolarityMap[$third_party_note];

    try {
        // Consultar nombre del tipo de transacción
        $stmt = $pdo->prepare("SELECT name, category FROM transaction_types WHERE id = :id");
        $stmt->bindParam(":id", $transaction_type_id, PDO::PARAM_INT);
        $stmt->execute();
        $type = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$type) {
            echo json_encode([
                "success" => false,
                "message" => "El tipo de transacción no fue encontrado."
            ]);
            exit;
        }

        $note = $type["name"];
        $neutral = (strtolower($type["category"]) === "otros") ? 1 : 0;

        // Insertar transacción con fecha
        $insert = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, third_party_note,
                utility, neutral, client_reference,
                created_at
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, :third_party_note,
                :utility, :neutral, :client_reference,
                :created_at
            )
        ");

        $insert->bindParam(":id_cashier", $id_cashier, PDO::PARAM_INT);
        $insert->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
        $insert->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
        $insert->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
        $insert->bindParam(":polarity", $polarity, PDO::PARAM_BOOL);
        $insert->bindParam(":cost", $cost);
        $insert->bindParam(":state", $state, PDO::PARAM_INT);
        $insert->bindParam(":note", $note, PDO::PARAM_STR);
        $insert->bindParam(":third_party_note", $third_party_note, PDO::PARAM_STR);
        $insert->bindParam(":utility", $utility);
        $insert->bindParam(":neutral", $neutral, PDO::PARAM_BOOL);
        $insert->bindParam(":client_reference", $client_reference, PDO::PARAM_INT);
        $insert->bindParam(":created_at", $created_at); // 🕒

        if ($insert->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transacción registrada exitosamente con polaridad automática.",
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