<?php
/**
 * Archivo: debt_correspondent_bank.php
 * Descripción: Calcula la deuda con el banco para un corresponsal, incluyendo compensaciones, terceros y detalle por caja.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha de actualización: 27-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

if (!isset($_GET["correspondent_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro correspondent_id"
    ]);
    exit();
}

require_once '../../db.php';

$correspondentId = intval($_GET["correspondent_id"]);

try {
    // 1. Ingresos
    $stmt1 = $pdo->prepare("
        SELECT SUM(cost) AS total_income
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Ingresos'
        ) AND id_correspondent = :correspondent_id AND state = 1
    ");
    $stmt1->execute(["correspondent_id" => $correspondentId]);
    $income = floatval($stmt1->fetchColumn() ?: 0);

    // 2. Retiros
    $stmt2 = $pdo->prepare("
        SELECT SUM(cost) AS total_withdrawals
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Retiros'
        ) AND id_correspondent = :correspondent_id AND state = 1
    ");
    $stmt2->execute(["correspondent_id" => $correspondentId]);
    $withdrawals = floatval($stmt2->fetchColumn() ?: 0);

    // 3. Compensaciones
    $stmt3 = $pdo->prepare("
        SELECT SUM(cost) AS total_compensation
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Compensación'
        ) AND id_correspondent = :correspondent_id AND state = 1
    ");
    $stmt3->execute(["correspondent_id" => $correspondentId]);
    $compensations = floatval($stmt3->fetchColumn() ?: 0);

    // 4. Cajas
    $stmt4 = $pdo->prepare("
        SELECT id, name, initial_amount
        FROM cash
        WHERE correspondent_id = :correspondent_id
    ");
    $stmt4->execute(["correspondent_id" => $correspondentId]);
    $cashes = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    $sumInitialAmounts = 0;
    foreach ($cashes as &$cash) {
        $amount = floatval($cash["initial_amount"] ?? 0);
        $cash["initial_amount"] = $amount;
        $sumInitialAmounts += $amount;
    }

    // 5. Saldo neto de terceros (como en third_party_balance_sheet, para TODOS los terceros del corresponsal)
    $stmt5 = $pdo->prepare("
        SELECT id, balance, negative_balance
        FROM others
        WHERE correspondent_id = :correspondent_id
    ");
    $stmt5->execute(["correspondent_id" => $correspondentId]);
    $thirdParties = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    $thirdPartyBalance = 0;

    foreach ($thirdParties as $third) {
        $thirdPartyId = intval($third["id"]);
        $initialBalance = floatval($third["balance"]);
        $isNegative = intval($third["negative_balance"]) === 1;
        $initialDebt = $isNegative ? $initialBalance : 0;

        // Transacciones relacionadas
        $stmtTx = $pdo->prepare("
            SELECT third_party_note, SUM(cost) AS total
            FROM transactions
            WHERE id_correspondent = :correspondent_id
              AND client_reference = :third_party_id
              AND state = 1
              AND third_party_note IN (
                  'debt_to_third_party',
                  'charge_to_third_party',
                  'loan_to_third_party',
                  'loan_from_third_party'
              )
            GROUP BY third_party_note
        ");
        $stmtTx->execute([
            "correspondent_id" => $correspondentId,
            "third_party_id" => $thirdPartyId
        ]);
        $tx = $stmtTx->fetchAll(PDO::FETCH_KEY_PAIR);

        $debt = floatval($tx["debt_to_third_party"] ?? 0);
        $charge = floatval($tx["charge_to_third_party"] ?? 0);
        $loanTo = floatval($tx["loan_to_third_party"] ?? 0);
        $loanFrom = floatval($tx["loan_from_third_party"] ?? 0);

        $netBalance = $initialDebt + $loanTo + $debt - $charge - $loanFrom;
        $thirdPartyBalance += $netBalance;
    }

    // 6. Caja neta
    $netCash = $sumInitialAmounts;

    // 7. Deuda al banco
    $debtToBank = ($income - $withdrawals + $netCash) - $compensations;

    // 8. Respuesta
    echo json_encode([
        "success" => true,
        "correspondent_id" => $correspondentId,
        "data" => [
            "income" => $income,
            "withdrawals" => $withdrawals,
            "compensations" => $compensations,
            "initial_cash_total" => $sumInitialAmounts,
            "third_party_balance" => $thirdPartyBalance,
            "net_cash" => $netCash,
            "debt_to_bank" => $debtToBank,
            "cashes" => $cashes
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
