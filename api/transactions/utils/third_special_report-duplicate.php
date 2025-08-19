<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Reporte resumen de balance de todos los terceros de un corresponsal,
 *              agrupando cada tercero como una "caja" e incluyendo detalle por tercero.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.4
 * Fecha: 16-Ago-2025
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
$dateFilter = isset($_GET["date"]) ? trim($_GET["date"]) : null;

// Validar formato de fecha YYYY-MM-DD si viene
if ($dateFilter) {
    $d = DateTime::createFromFormat('Y-m-d', $dateFilter);
    if (!$d || $d->format('Y-m-d') !== $dateFilter) {
        echo json_encode([
            "success" => false,
            "message" => "Formato de fecha inválido. Use YYYY-MM-DD."
        ]);
        exit();
    }
}

require_once "../../db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Terceros del corresponsal
    $thirdsStmt = $pdo->prepare("
        SELECT id, name, credit, balance, negative_balance
        FROM others
        WHERE correspondent_id = :id
    ");
    $thirdsStmt->bindParam(":id", $correspondentId, PDO::PARAM_INT);
    $thirdsStmt->execute();
    $thirdParties = $thirdsStmt->fetchAll();

    $summary = [];
    $total_credit = 0.0;
    $total_balance = 0.0;
    $total_net = 0.0;
    $total_available = 0.0;

    foreach ($thirdParties as $third) {
        $id = (int) $third["id"];
        $name = (string) $third["name"];
        $creditLimit = (float) $third["credit"];
        $rawBalance = (float) $third["balance"];
        $isNegative = ((int) $third["negative_balance"] === 1);

        // Normalizar balance según flag negative_balance
        // Si negative_balance=1, el saldo almacenado se interpreta como negativo.
        $normalizedBalance = $isNegative ? -$rawBalance : $rawBalance;

        // ========== DETALLES DE MOVIMIENTOS (con polarity) ==========
        $queryDetails = "
            SELECT 
                t.id, 
                t.transaction_type_id, 
                tt.name AS transaction_type_name,
                t.third_party_note, 
                t.type_of_movement,
                t.polarity,
                t.cost, 
                t.note, 
                t.created_at
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.id_correspondent = :corr
              AND t.client_reference = :third
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
        $details = $stmtDetails->fetchAll();

        // Totales por tipo considerando polarity
        $debtSigned = 0.0;        // debt_to_third_party
        $chargeSigned = 0.0;      // charge_to_third_party
        $loanToSigned = 0.0;      // loan_to_third_party
        $loanFromSigned = 0.0;    // loan_from_third_party

        foreach ($details as $m) {
            $amt = (float) $m["cost"];
            $sign = ((int) $m["polarity"] === 0) ? 1.0 : -1.0; // 0 suma deuda, 1 resta deuda

            switch ($m["third_party_note"]) {
                case "debt_to_third_party":
                    $debtSigned += $sign * $amt;
                    break;
                case "charge_to_third_party":
                    $chargeSigned += $sign * $amt;
                    break;
                case "loan_to_third_party":
                    $loanToSigned += $sign * $amt;
                    break;
                case "loan_from_third_party":
                    $loanFromSigned += $sign * $amt;
                    break;
            }
        }

        /**
         * Cálculos con regla solicitada:
         * - polarity=0 suma a la deuda, polarity=1 resta a la deuda.
         * - net_balance:
         *      net = normalizedBalance
         *            - loan_to_signed
         *            + charge_signed
         *            - debt_signed
         *            + loan_from_signed
         *
         *   Intuición de signos:
         *     - loan_to (préstamo a tercero) aumenta lo que el tercero te debe ⇒ hace el neto más negativo (resta).
         *     - charge_to (pago de tercero) reduce lo que te debe ⇒ neto sube (suma).
         *     - debt_to (pago a tercero) reduce lo que tú le debes ⇒ neto baja (resta).
         *     - loan_from (préstamo de tercero) aumenta lo que tú debes ⇒ neto sube (suma).
         */
        $netBalance = $normalizedBalance
            - $loanToSigned
            + $chargeSigned
            - $debtSigned
            + $loanFromSigned;

        // Cupo disponible calculado con el NETO (no solo con el balance base)
        if ($netBalance >= 0) {
            $availableCredit = $creditLimit;
        } else {
            $availableCredit = max(0.0, $creditLimit - abs($netBalance));
        }

        // Arreglo del tercero en el resumen
        $summary[] = [
            "id" => $id,
            "name" => $name,
            "credit_limit" => $creditLimit,
            "balance" => $normalizedBalance,               // con signo correcto
            "display_balance" => abs($normalizedBalance),  // absoluto
            "net_balance" => $netBalance,
            "display_net_balance" => abs($netBalance),
            "available_credit" => $availableCredit,
            // Totales por tipo reflejando polarity (netos)
            "debt_to_third_party" => $debtSigned,
            "charge_to_third_party" => $chargeSigned,
            "loan_to_third_party" => $loanToSigned,
            "loan_from_third_party" => $loanFromSigned,
            "negative_balance" => $isNegative,
            "movements" => $details
        ];

        // Totales globales
        $total_credit += $creditLimit;
        $total_balance += $normalizedBalance;
        $total_net += $netBalance;
        $total_available += $availableCredit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Reporte de terceros generado correctamente.",
        "report" => [
            "correspondent_id" => $correspondentId,
            "generated_at" => date("Y-m-d H:i:s"),
            "filter_date" => $dateFilter,
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
