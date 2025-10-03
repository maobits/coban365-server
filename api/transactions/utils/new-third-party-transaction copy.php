<?php
date_default_timezone_set('America/Bogota');

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
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$required = ["id_cashier", "id_cash", "id_correspondent", "transaction_type_id", "cost", "third_party_note", "client_reference", "type_of_movement"];
foreach ($required as $k) {
    if (!isset($data[$k])) {
        echo json_encode(["success" => false, "message" => "Falta el campo obligatorio: $k"]);
        exit;
    }
}

$id_cashier = (int) $data["id_cashier"];
$id_cash = (int) $data["id_cash"];
$id_correspondent = (int) $data["id_correspondent"];
$transaction_type_id = (int) $data["transaction_type_id"];
$cost = (float) $data["cost"];
$third_party_note = trim($data["third_party_note"]);
$client_reference = (int) $data["client_reference"]; // id del tercero
$type_of_movement = trim($data["type_of_movement"]);
$utility = isset($data["utility"]) ? (float) $data["utility"] : 0.0;
$cash_tag = isset($data["cash_tag"]) ? (float) $data["cash_tag"] : null;
$reference = isset($data["reference"]) ? trim($data["reference"]) : null; // ← opcional
$created_at = date("Y-m-d H:i:s");
$state = 1;

/** 1) Polarity por nota especial (misma lógica que antes) */
$polMap = [
    "debt_to_third_party" => 0, // Pago a tercero  => valor negativo en el balance (sale plata)
    "charge_to_third_party" => 1, // Pago de tercero => valor positivo en el balance (entra plata)
    "loan_to_third_party" => 0, // Préstamo a tercero => sale
    "loan_from_third_party" => 1, // Préstamo de tercero => entra
];
if (!array_key_exists($third_party_note, $polMap)) {
    echo json_encode(["success" => false, "message" => "Nota de tercero inválida."]);
    exit;
}
$polarity = $polMap[$third_party_note];

/** 2) Traer datos del tipo y nombre del tercero */
try {
    $stmt = $pdo->prepare("SELECT name, category FROM transaction_types WHERE id = :id");
    $stmt->execute([":id" => $transaction_type_id]);
    $type = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$type) {
        echo json_encode(["success" => false, "message" => "Tipo de transacción no encontrado."]);
        exit;
    }

    $sthird = $pdo->prepare("SELECT name, balance, negative_balance FROM others WHERE id = :id");
    $sthird->execute([":id" => $client_reference]);
    $third = $sthird->fetch(PDO::FETCH_ASSOC);
    if (!$third) {
        echo json_encode(["success" => false, "message" => "Tercero no encontrado."]);
        exit;
    }

    $note = !empty($third["name"]) ? $third["name"] : $type["name"];
    $neutral = (strtolower($type["category"]) === "otros") ? 1 : 0;

    /** 3) Saldo anterior */
    $prev = $pdo->prepare("
    SELECT total_balance_third
    FROM transactions
    WHERE id_correspondent = :c AND client_reference = :t AND state = 1
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ");
    $prev->execute([":c" => $id_correspondent, ":t" => $client_reference]);
    $last = $prev->fetchColumn();

    if ($last !== false && $last !== null) {
        $prev_balance = (float) $last;
    } else {
        // sin movimientos: partir del saldo guardado en others (normalizado)
        $rawBal = (float) $third["balance"];
        $prev_balance = ((int) $third["negative_balance"] === 1) ? -$rawBal : $rawBal;
    }

    /** 4) Reglas de comisión (ejemplo genérico como tu hoja) */
    $name = mb_strtolower($type_of_movement, 'UTF-8');

    // $17.000 para consignaciones y entrega en efectivo; $0 para lo demás
    $bank_fixed = 0;
    if (preg_match("/consignaci[óo]n|cajero|cb|entrega en efectivo/i", $type_of_movement)) {
        $bank_fixed = 17000;
    }

    // Dispersión 0,001 para todos
    $disp_pct = 0.001;

    /** 5) Signos y redondeos */
    $signed_value = ($polarity === 0) ? +$cost : -$cost;

    // comisión bancaria con signo contrario al valor
    $bank_commission_abs = (float) $bank_fixed;
    $bank_commission = ($signed_value >= 0 ? -$bank_commission_abs : +$bank_commission_abs);

    // dispersión = ceil(valor_abs * pct al millar) con signo contrario al valor
    $disp_raw = abs($cost) * $disp_pct;                  // 800.000 * 0,001 = 800
    $disp_ceil = ceil($disp_raw / 1000) * 1000;          // → 1.000
    $dispersion = ($signed_value >= 0 ? -$disp_ceil : +$disp_ceil);

    $total_commission = $bank_commission + $dispersion;

    /** 6) Nuevo saldo acumulado del tercero */
    $effect = $signed_value + $total_commission;         // lo que suma/resta esta fila
    $new_total_balance = $prev_balance + $effect;

    /** 7) Insertar transacción con todos los campos */
    $sql = "
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
  ";

    $ins = $pdo->prepare($sql);
    $ok = $ins->execute([
        ":id_cashier" => $id_cashier,
        ":id_cash" => $id_cash,
        ":id_correspondent" => $id_correspondent,
        ":transaction_type_id" => $transaction_type_id,
        ":polarity" => $polarity,
        ":cost" => $cost,
        ":state" => $state,
        ":note" => $note,
        ":third_party_note" => $third_party_note,
        ":utility" => $utility,
        ":neutral" => $neutral,
        ":client_reference" => $client_reference,
        ":created_at" => $created_at,
        ":cash_tag" => $cash_tag,
        ":type_of_movement" => $type_of_movement,
        ":reference" => $reference,
        ":bank_commission" => $bank_commission,
        ":dispersion" => $dispersion,
        ":total_commission" => $total_commission,
        ":total_balance_third" => $new_total_balance,
    ]);

    if ($ok) {
        echo json_encode([
            "success" => true,
            "message" => "Transacción registrada.",
            "calc" => [
                "prev_balance" => $prev_balance,
                "signed_value" => $signed_value,
                "bank_commission" => $bank_commission,
                "dispersion" => $dispersion,
                "total_commission" => $total_commission,
                "effect" => $effect,
                "new_total_balance" => $new_total_balance
            ],
            "timestamp" => $created_at
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar la transacción."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
