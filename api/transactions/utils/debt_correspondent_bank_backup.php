<?php
/**
 * Archivo: debt_correspondent_bank.php
 * Descripción: Calcula la deuda con el banco para un corresponsal, incluyendo compensaciones y detalle por caja.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha de actualización: 25-May-2025
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
    // 1. Total ingresos
    $stmt1 = $pdo->prepare("
        SELECT SUM(cost) AS total_income
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Ingresos'
        )
        AND id_correspondent = :correspondent_id
        AND state = 1
    ");
    $stmt1->execute(["correspondent_id" => $correspondentId]);
    $income = floatval($stmt1->fetchColumn() ?: 0);

    // 2. Total egresos
    $stmt2 = $pdo->prepare("
        SELECT SUM(cost) AS total_withdrawals
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Retiros'
        )
        AND id_correspondent = :correspondent_id
        AND state = 1
    ");
    $stmt2->execute(["correspondent_id" => $correspondentId]);
    $withdrawals = floatval($stmt2->fetchColumn() ?: 0);

    // 3. Total compensaciones
    $stmt3 = $pdo->prepare("
        SELECT SUM(cost) AS total_compensation
        FROM transactions
        WHERE transaction_type_id IN (
            SELECT id FROM transaction_types WHERE category = 'Compensación'
        )
        AND id_correspondent = :correspondent_id
        AND state = 1
    ");
    $stmt3->execute(["correspondent_id" => $correspondentId]);
    $compensations = floatval($stmt3->fetchColumn() ?: 0);

    // 4. Detalle de cajas con initial_amount
    $stmt4 = $pdo->prepare("
        SELECT id, name, initial_amount
        FROM cash
        WHERE correspondent_id = :correspondent_id
    ");
    $stmt4->execute(["correspondent_id" => $correspondentId]);
    $cashes = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    $netCash = 0;
    foreach ($cashes as &$cash) {
        $amount = floatval($cash["initial_amount"] ?? 0);
        $cash["initial_amount"] = $amount;
        $netCash += $amount;
    }

    // 5. Cálculo final actualizado
    $debt = ($income - $withdrawals + $netCash) - $compensations;



    echo json_encode([
        "success" => true,
        "correspondent_id" => $correspondentId,
        "data" => [
            "income" => $income,
            "withdrawals" => $withdrawals,
            "compensations" => $compensations,
            "net_cash" => $netCash,
            "debt_to_bank" => $debt,
            "cashes" => $cashes
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
