<?php
/**
 * Archivo: other_account_statement.php
 * Descripción: Devuelve el estado financiero de un tercero o de todos los terceros del corresponsal,
 *              incluso si client_reference es ID o número de documento.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara Hurtado
 * Versión: 1.5.0
 * Fecha: 07-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php';

$correspondentId = isset($_GET['correspondent_id']) ? intval($_GET['correspondent_id']) : null;
$idNumber = isset($_GET['id_number']) ? trim($_GET['id_number']) : null;

if (!$correspondentId && !$idNumber) {
    echo json_encode([
        "success" => false,
        "message" => "Debe proporcionar 'correspondent_id' o 'id_number'."
    ]);
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function getFinancialData($pdo, $correspondentId, $third)
    {
        $thirdId = $third['id'];
        $thirdDocument = $third['id_number'];
        $creditLimit = floatval($third["credit"]);

        // Obtener movimientos contables
        $movementsQuery = $pdo->prepare("
            SELECT id, account_receivable, account_to_pay, created_at
            FROM account_statement_others
            WHERE id_third = :thirdId AND state = 1
            ORDER BY created_at DESC
        ");
        $movementsQuery->execute([':thirdId' => $thirdId]);
        $movements = $movementsQuery->fetchAll(PDO::FETCH_ASSOC);

        $totalsQuery = $pdo->prepare("
            SELECT 
                IFNULL(SUM(account_receivable), 0) AS total_receivable,
                IFNULL(SUM(account_to_pay), 0) AS total_to_pay,
                IFNULL(SUM(account_receivable) - SUM(account_to_pay), 0) AS balance
            FROM account_statement_others
            WHERE id_third = :thirdId AND state = 1
        ");
        $totalsQuery->execute([':thirdId' => $thirdId]);
        $totals = $totalsQuery->fetch(PDO::FETCH_ASSOC);

        // Buscar transacciones con client_reference igual al ID o al número de documento
        $transQuery = $pdo->prepare("
            SELECT third_party_note, SUM(cost) AS total
            FROM transactions
            WHERE id_correspondent = :correspondentId
              AND state = 1
              AND third_party_note IN (
                  'debt_to_third_party',
                  'charge_to_third_party',
                  'loan_to_third_party',
                  'loan_from_third_party'
              )
              AND (client_reference = :thirdId OR client_reference = :idNumber)
            GROUP BY third_party_note
        ");
        $transQuery->execute([
            ':correspondentId' => $correspondentId,
            ':thirdId' => (string) $thirdId,
            ':idNumber' => $thirdDocument
        ]);
        $results = $transQuery->fetchAll(PDO::FETCH_KEY_PAIR);

        $debt = floatval($results["debt_to_third_party"] ?? 0);
        $charge = floatval($results["charge_to_third_party"] ?? 0);
        $loanTo = floatval($results["loan_to_third_party"] ?? 0);
        $loanFrom = floatval($results["loan_from_third_party"] ?? 0);

        $debtToThirdParty = $loanFrom - $debt;
        $chargeToThirdParty = $loanTo - $charge;
        $availableCredit = max(0, $creditLimit - $chargeToThirdParty);

        // Simular movimiento si no existen registros contables
        if (count($movements) === 0 && ($loanTo > 0 || $loanFrom > 0)) {
            $movements[] = [
                "created_at" => date("Y-m-d"),
                "account_receivable" => $loanTo,
                "account_to_pay" => $loanFrom,
                "description" => "Resumen financiero automático"
            ];
            $totals['total_receivable'] = $loanTo;
            $totals['total_to_pay'] = $loanFrom;
            $totals['balance'] = $loanTo - $loanFrom;
        }

        $hasData = count($movements) > 0 || $loanTo > 0 || $loanFrom > 0;

        return [
            "third" => $third,
            "balance" => floatval($totals['balance']),
            "total_receivable" => floatval($totals['total_receivable']),
            "total_to_pay" => floatval($totals['total_to_pay']),
            "movements" => $movements,
            "financial_status" => [
                "credit_limit" => $creditLimit,
                "available_credit" => $availableCredit,
                "loan_to_third_party" => $loanTo,
                "loan_from_third_party" => $loanFrom,
                "debt_to_third_party" => $debtToThirdParty,
                "charge_to_third_party" => $chargeToThirdParty
            ],
            "hasData" => $hasData
        ];
    }

    // Consulta individual
    if ($idNumber) {
        $stmt = $pdo->prepare("SELECT * FROM others WHERE id_number = :idNumber AND state = 1 LIMIT 1");
        $stmt->execute([':idNumber' => $idNumber]);
        $third = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$third) {
            echo json_encode(["success" => false, "message" => "Tercero no encontrado."]);
            exit();
        }

        $correspondentId = $third['correspondent_id'];
        $data = getFinancialData($pdo, $correspondentId, $third);

        echo json_encode(["success" => true, "data" => $data]);
        exit();
    }

    // Consulta por corresponsal
    $stmt = $pdo->prepare("SELECT * FROM others WHERE correspondent_id = :correspondentId AND state = 1");
    $stmt->execute([':correspondentId' => $correspondentId]);
    $thirds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($thirds as $third) {
        $data[] = getFinancialData($pdo, $correspondentId, $third);
    }

    echo json_encode(["success" => true, "data" => $data]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
