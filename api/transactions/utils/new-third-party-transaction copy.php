<?php
date_default_timezone_set('America/Bogota'); // 🕒 Hora local de Bogotá

/**
 * Archivo: new-third-party-transaction.php
 * Descripción: Registra una transacción de terceros con client_reference, polaridad automática,
 *              type_of_movement, referencia y costos (comisión bancaria, dispersión, total).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 25-May-2025 (actualizado con reference y costos)
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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ]);
    exit;
}

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
$cost = floatval($data["cost"]);                         // Valor del movimiento (positivo)
$third_party_note = trim($data["third_party_note"]);
$client_reference = intval($data["client_reference"]);               // ID del tercero
$utility = isset($data["utility"]) ? floatval($data["utility"]) : 0;
$cash_tag = isset($data["cash_tag"]) ? floatval($data["cash_tag"]) : null;
$type_of_movement = isset($data["type_of_movement"]) ? trim($data["type_of_movement"]) : null;

// 🆕 Nuevos (opcionales en payload; si no llegan, se calculan/ponen 0)
$reference = isset($data["reference"]) ? trim($data["reference"]) : null; // aparecerá como "Referencia 2"
$bank_commission = isset($data["bank_commission"]) ? floatval($data["bank_commission"]) : 0.0; // se espera negativo si es costo
$dispersion = isset($data["dispersion"]) ? floatval($data["dispersion"]) : 0.0;           // se espera negativo si es costo
$total_commission = isset($data["total_commission"]) ? floatval($data["total_commission"]) : null; // suma de costos, negativo

// Si no viene total_commission, lo computamos como suma de costos (negativa o 0)
if ($total_commission === null) {
    $total_commission = $bank_commission + $dispersion; // típicamente negativo
}

// 🆕 total del movimiento para el tercero en el reporte (cost + costos)
$total_balance_third = isset($data["total_balance_third"])
    ? floatval($data["total_balance_third"])
    : ($cost + $bank_commission + $dispersion); // ej: 800.000 + 0 + (-1.000) = 799.000

$state = 1;
$created_at = date("Y-m-d H:i:s"); // ⏰ Fecha y hora actual de Bogotá

// Determinar polaridad automáticamente según la nota especial
$expectedPolarityMap = [
    "debt_to_third_party" => 0, // pago al tercero (sale caja)
    "charge_to_third_party" => 1, // pago del tercero (entra caja)
    "loan_to_third_party" => 0, // préstamo al tercero (sale caja)
    "loan_from_third_party" => 1  // préstamo de tercero (entra caja)
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

    // 🧾 Obtener el nombre del tercero para guardarlo en 'note'
    $thirdStmt = $pdo->prepare("SELECT name FROM others WHERE id = :id");
    $thirdStmt->bindParam(":id", $client_reference, PDO::PARAM_INT);
    $thirdStmt->execute();
    $third = $thirdStmt->fetch(PDO::FETCH_ASSOC);

    $note = $third && !empty($third["name"]) ? $third["name"] : $type["name"];
    $neutral = (strtolower($type["category"]) === "otros") ? 1 : 0;

    // Insertar transacción con referencia, costos y type_of_movement
    $insert = $pdo->prepare("
        INSERT INTO transactions (
            id_cashier, id_cash, id_correspondent,
            transaction_type_id, polarity, cost,
            state, note, third_party_note,
            utility, neutral, client_reference,
            created_at, cash_tag, type_of_movement,
            reference, bank_commission, dispersion, total_commission, total_balance_third
        ) VALUES (
            :id_cashier, :id_cash, :id_correspondent,
            :transaction_type_id, :polarity, :cost,
            :state, :note, :third_party_note,
            :utility, :neutral, :client_reference,
            :created_at, :cash_tag, :type_of_movement,
            :reference, :bank_commission, :dispersion, :total_commission, :total_balance_third
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
    $insert->bindParam(":created_at", $created_at);
    $insert->bindParam(":cash_tag", $cash_tag);

    $insert->bindParam(":type_of_movement", $type_of_movement, PDO::PARAM_STR);
    $insert->bindParam(":reference", $reference, PDO::PARAM_STR);
    $insert->bindParam(":bank_commission", $bank_commission);          // normalmente negativo si es costo
    $insert->bindParam(":dispersion", $dispersion);                    // normalmente negativo si es costo
    $insert->bindParam(":total_commission", $total_commission);        // normalmente negativo
    $insert->bindParam(":total_balance_third", $total_balance_third);  // ej: 799000

    if ($insert->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Transacción registrada exitosamente con referencia y costos.",
            "timestamp" => $created_at,
            "data" => [
                "polarity" => $polarity,
                "type_of_movement" => $type_of_movement,
                "reference" => $reference,
                "bank_commission" => $bank_commission,
                "dispersion" => $dispersion,
                "total_commission" => $total_commission,
                "total_balance_third" => $total_balance_third
            ]
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
?>