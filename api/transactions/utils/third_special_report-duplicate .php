<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Reporte resumen de balance de todos los terceros de un corresponsal,
 *              agrupando cada tercero como una "caja" e incluyendo detalle por tercero.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.1
 * Fecha: 27-Jul-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

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

$correspondentId = intval($_GET["correspondent_id"]);
$dateFilter = isset($_GET["date"]) ? $_GET["date"] : null;

require_once "../../db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $thirdsStmt = $pdo->prepare("
        SELECT id, name, credit, balance, negative_balance
        FROM others
        WHERE correspondent_id = :id
    ");
    $thirdsStmt->bindParam(":id", $correspondentId, PDO::PARAM_INT);
    $thirdsStmt->execute();
    $thirdParties = $thirdsStmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [];
    $total_credit = 0;
    $total_balance = 0;
    $total_net = 0;
    $total_available = 0;

    foreach ($thirdParties as $third) {
        $id = intval($third["id"]);
        $name = $third["name"];
        $creditLimit = floatval($third["credit"]);
        $balance = floatval($third["balance"]);
        $isNegative = intval($third["negative_balance"]) === 1;

        // 1️⃣ Totales por tipo de movimiento (filtrados por fecha si aplica)
        $queryTotals = "
            SELECT third_party_note, SUM(cost) AS total
            FROM transactions
            WHERE id_correspondent = :corr AND client_reference = :third
              AND state = 1
              AND third_party_note IN (
                  'debt_to_third_party',
                  'charge_to_third_party',
                  'loan_to_third_party',
                  'loan_from_third_party'
              )
        ";
        if ($dateFilter) {
            $queryTotals .= " AND DATE(created_at) = :filter_date";
        }
        $queryTotals .= " GROUP BY third_party_note";

        $stmtTotals = $pdo->prepare($queryTotals);
        $params = [":corr" => $correspondentId, ":third" => $id];
        if ($dateFilter) {
            $params[":filter_date"] = $dateFilter;
        }
        $stmtTotals->execute($params);
        $results = $stmtTotals->fetchAll(PDO::FETCH_KEY_PAIR);

        $debt = floatval($results["debt_to_third_party"] ?? 0);
        $charge = floatval($results["charge_to_third_party"] ?? 0);
        $loanTo = floatval($results["loan_to_third_party"] ?? 0);
        $loanFrom = floatval($results["loan_from_third_party"] ?? 0);

        // 2️⃣ Detalle de movimientos (sin filtro de fecha)
        $queryDetails = "
            SELECT 
                t.id, 
                t.transaction_type_id, 
                tt.name AS transaction_type_name,
                t.third_party_note, 
                t.cost, 
                t.note, 
                t.created_at
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.id_correspondent = :corr AND t.client_reference = :third
              AND t.state = 1
              AND t.third_party_note IN (
                  'debt_to_third_party',
                  'charge_to_third_party',
                  'loan_to_third_party',
                  'loan_from_third_party'
              )
            ORDER BY t.created_at DESC
        ";

        $stmtDetails = $pdo->prepare($queryDetails);
        $stmtDetails->execute([
            ":corr" => $correspondentId,
            ":third" => $id
        ]);
        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        // 3️⃣ Cálculo de saldos
        $netBalance = ($isNegative ? $balance : -$balance) + $loanTo + $debt - $charge - $loanFrom;
        $availableCredit = $netBalance >= 0 ? max(0, $creditLimit - $netBalance) : $creditLimit;

        // 4️⃣ Construcción del resumen por tercero
        $summary[] = [
            "id" => $id,
            "name" => $name,
            "credit_limit" => $creditLimit,
            "balance" => $isNegative ? -$balance : $balance,
            "display_balance" => $isNegative ? -abs($balance) : abs($balance),
            "net_balance" => $netBalance,
            "display_net_balance" => abs($netBalance),
            "available_credit" => $availableCredit,
            "debt_to_third_party" => $debt,
            "charge_to_third_party" => $charge,
            "loan_to_third_party" => $loanTo,
            "loan_from_third_party" => $loanFrom,
            "negative_balance" => $isNegative,
            "movements" => $details
        ];


        $total_credit += $creditLimit;
        $total_balance += ($isNegative ? -$balance : $balance);
        $total_net += $netBalance;
        $total_available += $availableCredit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Reporte de terceros generado correctamente.",
        "report" => [
            "correspondent_id" => $correspondentId,
            "generated_at" => date("Y-m-d H:i:s"),
            "total_third_parties" => count($summary),
            "total_credit_limit" => $total_credit,
            "total_balance" => $total_balance,
            "total_net_balance" => $total_net,
            "total_available_credit" => $total_available,
            "third_party_summary" => $summary
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
