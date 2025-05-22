<?php
/**
 * Archivo: debt_correspondent_bank.php
 * Descripción: Calcula la deuda con el banco para un corresponsal, incluyendo detalle por caja.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 20-May-2025
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
    ");
    $stmt2->execute(["correspondent_id" => $correspondentId]);
    $withdrawals = floatval($stmt2->fetchColumn() ?: 0);

    // 3. Detalle de cajas con initial_amount
    $stmt3 = $pdo->prepare("
        SELECT id, name, initial_amount
        FROM cash
        WHERE correspondent_id = :correspondent_id
    ");
    $stmt3->execute(["correspondent_id" => $correspondentId]);
    $cajas = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $netCash = 0;
    foreach ($cajas as &$caja) {
        $amount = floatval($caja["initial_amount"] ?? 0);
        $caja["initial_amount"] = $amount;
        $netCash += $amount;
    }

    // 4. Cálculo final
    $debt = ($income - $withdrawals) + $netCash;

    echo json_encode([
        "success" => true,
        "correspondent_id" => $correspondentId,
        "data" => [
            "income" => $income,
            "withdrawals" => $withdrawals,
            "net_cash" => $netCash,
            "debt_to_bank" => $debt,
            "cashes" => $cajas
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
