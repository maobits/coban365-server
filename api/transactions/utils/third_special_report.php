<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Reporte resumen de balance de todos los terceros de un corresponsal,
 *              agrupando cada tercero como una "caja" e incluyendo detalle por tercero.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.4.0
 * Fecha: 17-Ago-2025
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

/* ⬇️ NUEVO: parámetro opcional de cédula/id_number */
$idNumberFilter = isset($_GET["id_number"]) ? trim($_GET["id_number"]) : null;

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

    // Terceros del corresponsal (si viene id_number, filtra solo ese tercero)
    $thirdsSql = "
        SELECT id, name, credit, balance, negative_balance
        FROM others
        WHERE correspondent_id = :id
    ";
    if ($idNumberFilter !== null && $idNumberFilter !== '') {
        $thirdsSql .= " AND id_number = :id_number ";
    }

    $thirdsStmt = $pdo->prepare($thirdsSql);
    $thirdsStmt->bindParam(":id", $correspondentId, PDO::PARAM_INT);
    if ($idNumberFilter !== null && $idNumberFilter !== '') {
        $thirdsStmt->bindParam(":id_number", $idNumberFilter, PDO::PARAM_STR);
    }
    $thirdsStmt->execute();
    $thirdParties = $thirdsStmt->fetchAll();

    $summary = [];
    $tot_credit = 0.0;
    $tot_balance = 0.0;
    $tot_available = 0.0;

    // Agregados globales (magnitudes)
    $tot_third_debt = 0.0; // deuda de terceros (magnitud, se considera negativa para el neto)
    $tot_corresp_debt = 0.0; // deuda del corresponsal (magnitud, positiva para el neto)

    foreach ($thirdParties as $third) {
        $id = (int) $third["id"];
        $name = (string) $third["name"];
        $creditLimit = (float) $third["credit"];
        $rawBalance = (float) $third["balance"];
        $isNegative = ((int) $third["negative_balance"] === 1);

        // Normalizar balance (si negative_balance=1, el saldo almacenado es negativo)
        $normalizedBalance = $isNegative ? -$rawBalance : $rawBalance;

        // -------- Detalle de movimientos (incluye polarity) --------
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
              AND t.client_reference   = :third
              AND t.state = 1
              AND t.third_party_note IN (
                  'debt_to_third_party',
                  'charge_to_third_party',
                  'loan_to_third_party',
                  'loan_from_third_party'
              )
        ";
        // ✅ ÚNICO CAMBIO: incluir todo hasta la fecha elegida o hasta HOY si no viene fecha
        if ($dateFilter) {
            $queryDetails .= " AND DATE(t.created_at) <= :filter_date";
        } else {
            $queryDetails .= " AND DATE(t.created_at) <= CURDATE()";
        }
        $queryDetails .= " ORDER BY t.created_at DESC";

        $stmtDetails = $pdo->prepare($queryDetails);
        $execParams = [":corr" => $correspondentId, ":third" => $id];
        if ($dateFilter) {
            $execParams[":filter_date"] = $dateFilter;
        }
        $stmtDetails->execute($execParams);
        $details = $stmtDetails->fetchAll();

        // Totales efectivos por tipo considerando polarity para dirección contable
        // polarity: 0 => suma deuda ; 1 => resta deuda
        $debtEff = 0.0; // pagos a tercero
        $chargeEff = 0.0; // pagos de tercero
        $loanToEff = 0.0; // préstamo a tercero
        $loanFromEff = 0.0; // préstamo de tercero

        foreach ($details as $m) {
            $amt = (float) $m["cost"];
            $sign = ((int) $m["polarity"] === 0) ? 1.0 : -1.0;

            switch ($m["third_party_note"]) {
                case "debt_to_third_party":
                    $debtEff += $sign * $amt;
                    break;
                case "charge_to_third_party":
                    $chargeEff += $sign * $amt;
                    break;
                case "loan_to_third_party":
                    $loanToEff += $sign * $amt;
                    break;
                case "loan_from_third_party":
                    $loanFromEff += $sign * $amt;
                    break;
            }
        }

        // -------- Magnitudes de deuda para sumarios --------
        // Base por saldo inicial:
        $thirdDebtBase = max(0.0, -$normalizedBalance); // tercero ya debía
        $corpDebtBase = max(0.0, $normalizedBalance);   // corresponsal ya debía

        // Reglas de magnitud:
        //  - loan_to_third_party   => aumenta deuda del tercero
        //  - charge_to_third_party => reduce deuda del tercero
        //  - debt_to_third_party   => aumenta deuda del corresponsal (y reduce la del tercero)
        //  - loan_from_third_party => aumenta deuda del corresponsal
        $thirdDebt = $thirdDebtBase
            + abs($loanToEff)
            - abs($chargeEff)
            - abs($debtEff);
        if ($thirdDebt < 0)
            $thirdDebt = 0.0;

        $correspDebt = $corpDebtBase
            + abs($loanFromEff)   // préstamo recibido del tercero => corresponsal debe más
            + abs($debtEff);      // pagos a tercero => corresponsal debe más
        if ($correspDebt < 0)
            $correspDebt = 0.0;

        // Neto con convención: (+corresp) + (−third) = correspDebt - thirdDebt
        $netBalance = $correspDebt - $thirdDebt;

        // Cupo disponible basado en el neto
        $availableCredit = ($netBalance >= 0)
            ? $creditLimit
            : max(0.0, $creditLimit - abs($netBalance));

        // Valores visuales por tipo (signos fijos para UI por renglón)
        $debtDisplay = abs($debtEff);       // Pagos a tercero (mostrar +)
        $chargeDisplay = abs($chargeEff);   // Pagos de tercero (mostrar +)
        $loanToDisplay = -abs($loanToEff);  // Préstamo a tercero (mostrar −)
        $loanFromDisplay = abs($loanFromEff); // Préstamo de tercero (mostrar +)

        // Arreglo del tercero en el resumen
        $summary[] = [
            "id" => $id,
            "name" => $name,
            "credit_limit" => $creditLimit,
            "balance" => $normalizedBalance,
            "display_balance" => abs($normalizedBalance),
            "net_balance" => $netBalance,
            "display_net_balance" => abs($netBalance),
            "available_credit" => $availableCredit,
            "debt_to_third_party" => $debtDisplay,
            "charge_to_third_party" => $chargeDisplay,
            "loan_to_third_party" => $loanToDisplay,
            "loan_from_third_party" => $loanFromDisplay,
            "third_party_debt" => $thirdDebt,
            "correspondent_debt" => $correspDebt,
            "negative_balance" => $isNegative,
            "movements" => $details
        ];

        // Totales globales
        $tot_credit += $creditLimit;
        $tot_balance += $normalizedBalance;
        $tot_available += $availableCredit;
        $tot_third_debt += $thirdDebt;     // magnitud
        $tot_corresp_debt += $correspDebt;   // magnitud
    }

    // Neto total con la misma convención
    $tot_net = $tot_corresp_debt - $tot_third_debt;

    echo json_encode([
        "success" => true,
        "message" => "Reporte de terceros generado correctamente.",
        "report" => [
            "correspondent_id" => $correspondentId,
            "generated_at" => date("Y-m-d H:i:s"),
            "filter_date" => $dateFilter,
            "total_third_parties" => count($summary),
            "total_credit_limit" => $tot_credit,
            "total_balance" => $tot_balance,
            "total_net_balance" => $tot_net,              // (+corresp) + (−third)
            "total_available_credit" => $tot_available,
            "total_third_party_debt" => $tot_third_debt,   // magnitud
            "total_correspondent_debt" => $tot_corresp_debt, // magnitud
            "third_party_summary" => $summary
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
