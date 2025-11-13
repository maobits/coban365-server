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
$reference = isset($data["reference"]) ? trim($data["reference"]) : null; // opcional
$created_at = date("Y-m-d H:i:s");
$state = 1;

/** 1) Polarity por nota especial */
$polMap = [
    "debt_to_third_party" => 0, // Pago a tercero  => sale
    "charge_to_third_party" => 1, // Pago de tercero => entra
    "loan_to_third_party" => 0, // Préstamo a tercero => sale
    "loan_from_third_party" => 1, // Préstamo de tercero => entra
];
if (!array_key_exists($third_party_note, $polMap)) {
    echo json_encode(["success" => false, "message" => "Nota de tercero inválida."]);
    exit;
}
$polarity = $polMap[$third_party_note];

try {
    /** 2) Traer datos del tipo y nombre del tercero */
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

    /** 4) Reglas de comisión **/
    // Normalizador para detectar consignación / cosignación, etc.
    $normalize = function ($s) {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n']);
        $s = preg_replace('/[^a-z0-9\s]/u', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    };
    $mov_norm = $normalize($type_of_movement);

    $triggers = [
        'consignacion en sucursal',
        'consignacion',
        'cosignacion en sucursal',
        'cosignacion',
        'cajero',
        'entrega en efectivo',
        ' cb ',
        'cb',
    ];
    $matchesTrigger = false;
    foreach ($triggers as $k) {
        if (strpos(' ' . $mov_norm . ' ', ' ' . $k . ' ') !== false) {
            $matchesTrigger = true;
            break;
        }
    }

    // Comisión fija por banco (17.000) y dispersión 0,001 SOLO en préstamo de tercero
    $bank_fixed = 0;
    if ($third_party_note === 'loan_from_third_party' && $matchesTrigger) {
        $bank_fixed = 17000;
    }
    $disp_pct = ($third_party_note === 'loan_from_third_party') ? 0.001 : 0.0;

    /** Utilidad para limpiar -0 */
    $zeroIfTiny = function ($v) {
        return (abs($v) < 1e-7) ? 0.0 : $v;
    };

    /** 5) Signos correctos para acumular bien */
    // SALE (polarity 0) => negativo; ENTRA (polarity 1) => positivo
    $signed_value = ($polarity === 0) ? -$cost : +$cost;

    // Comisión bancaria con signo contrario al valor (costo restando)
    $bank_commission_abs = (float) $bank_fixed;
    $bank_commission = ($signed_value >= 0 ? -$bank_commission_abs : +$bank_commission_abs);

    // Dispersión = ceil(valor_abs * pct al millar) con signo contrario al valor
    $disp_raw = abs($cost) * $disp_pct;
    $disp_ceil = ceil($disp_raw / 1000) * 1000;
    $dispersion = ($signed_value >= 0 ? -$disp_ceil : +$disp_ceil);

    // Si no aplican, dejarlas exactamente en 0.0
    if ($bank_fixed === 0) {
        $bank_commission = 0.0;
    }
    if ($disp_pct == 0.0) {
        $dispersion = 0.0;
    }

    $bank_commission = $zeroIfTiny($bank_commission);
    $dispersion = $zeroIfTiny($dispersion);
    $total_commission = $zeroIfTiny($bank_commission + $dispersion);

    /** 6) Nuevo saldo acumulado del tercero */
    $effect = $signed_value + $total_commission;    // si comisiones=0, effect = signed_value
    $new_total_balance = $prev_balance + $effect;   // ACUMULA (no resetea)

    /** 7) Insertar transacción */
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

    /* ============================================================
     * [NUEVO ACUMULADOR] Registrar comisión en third_party_commissions
     * SOLO si es préstamo de tercero (entra dinero al corresponsal)
     * y hubo costos de comisión (>0 en magnitud)
     * ============================================================ */
    if ($third_party_note === 'loan_from_third_party') {
        // Magnitud positiva a sumar al acumulador:
        $commissionMagnitude = abs($total_commission); // ej: -18000 -> 18000
        if ($commissionMagnitude > 0) {
            // 1. Asegurar fila (third_party_id, correspondent_id) exista
            $check = $pdo->prepare("
                SELECT id, total_commission
                FROM third_party_commissions
                WHERE third_party_id = :t
                  AND correspondent_id = :c
                LIMIT 1
            ");
            $check->execute([
                ":t" => $client_reference,
                ":c" => $id_correspondent
            ]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // 2.a Ya existe → UPDATE sumando
                $upd = $pdo->prepare("
                    UPDATE third_party_commissions
                    SET total_commission = total_commission + :amt,
                        last_update = NOW()
                    WHERE id = :id
                    LIMIT 1
                ");
                $upd->execute([
                    ":amt" => $commissionMagnitude,
                    ":id" => $row["id"]
                ]);
            } else {
                // 2.b No existe → INSERT con ese monto inicial
                $ins2 = $pdo->prepare("
                    INSERT INTO third_party_commissions
                      (third_party_id, correspondent_id, total_commission, last_update)
                    VALUES
                      (:t, :c, :amt, NOW())
                ");
                $ins2->execute([
                    ":t" => $client_reference,
                    ":c" => $id_correspondent,
                    ":amt" => $commissionMagnitude
                ]);
            }
        }
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
