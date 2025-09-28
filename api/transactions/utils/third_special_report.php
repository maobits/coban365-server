<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Reporte resumen de balance de todos los terceros de un corresponsal,
 *              agrupando cada tercero como una "caja" e incluyendo detalle por tercero.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.6.2
 * Fecha: 27-Sep-2025
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
$idNumberFilter = isset($_GET["id_number"]) ? trim($_GET["id_number"]) : null;

/* Rango de fechas opcional */
$dateFrom = isset($_GET["date_from"]) ? trim($_GET["date_from"]) : null;
$dateTo = isset($_GET["date_to"]) ? trim($_GET["date_to"]) : null;

/* Validar formato de fechas YYYY-MM-DD */
$checkDate = function (?string $s) {
    if ($s === null || $s === '')
        return true;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s);
};

if (!$checkDate($dateFilter)) {
    echo json_encode(["success" => false, "message" => "Formato de fecha inválido en 'date'. Use YYYY-MM-DD."]);
    exit();
}
if (!$checkDate($dateFrom)) {
    echo json_encode(["success" => false, "message" => "Formato de fecha inválido en 'date_from'. Use YYYY-MM-DD."]);
    exit();
}
if (!$checkDate($dateTo)) {
    echo json_encode(["success" => false, "message" => "Formato de fecha inválido en 'date_to'. Use YYYY-MM-DD."]);
    exit();
}

/* Si vienen ambos extremos, validar que from <= to */
if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    echo json_encode([
        "success" => false,
        "message" => "'date_from' no puede ser mayor que 'date_to'."
    ]);
    exit();
}

require_once "../../db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    /* Terceros del corresponsal (filtro opcional por id_number) */
    $thirdsSql = "
        SELECT 
            id, 
            name, 
            COALESCE(credit, 0)  AS credit, 
            COALESCE(balance, 0) AS balance, 
            COALESCE(negative_balance, 0) AS negative_balance
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

    /* Agregados globales (magnitudes) */
    $tot_third_debt = 0.0;
    $tot_corresp_debt = 0.0;

    /* Agregados de comisiones + nuevo campo */
    $tot_bank_commission = 0.0;
    $tot_dispersion = 0.0;
    $tot_total_commission = 0.0;
    $tot_paid_total_commission = 0.0;
    $tot_count_commissions_paid = 0;
    $tot_total_balance_third = 0.0; // NUEVO

    /* Construir cláusula temporal priorizando rango -> date -> hoy */
    $appliedDate = [
        "mode" => null,  // 'range' | 'lte' | 'today'
        "date" => null,
        "date_from" => null,
        "date_to" => null
    ];

    foreach ($thirdParties as $third) {
        $id = (int) $third["id"];
        $name = (string) $third["name"];
        $creditLimit = (float) $third["credit"];
        $rawBalance = (float) $third["balance"];
        $isNegative = ((int) $third["negative_balance"] === 1);

        /* Normalizar balance (si negative_balance=1, el saldo almacenado es negativo) */
        $normalizedBalance = $isNegative ? -$rawBalance : $rawBalance;

        /* Detalle de movimientos (incluye polarity) */
        $queryDetails = "
            SELECT 
                t.id, 
                t.transaction_type_id, 
                tt.name AS transaction_type_name,
                t.third_party_note, 
                t.type_of_movement,
                t.polarity,
                COALESCE(t.cost, 0) AS cost, 
                t.note, 
                t.created_at,
                t.reference,
                COALESCE(t.bank_commission, 0) AS bank_commission,
                COALESCE(t.dispersion, 0) AS dispersion,
                COALESCE(t.total_commission, 0) AS total_commission,
                COALESCE(t.commission_paid, 0) AS commission_paid,
                COALESCE(t.total_balance_third, 0) AS total_balance_third
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

        /* Prioridad de filtros temporales */
        $timeClause = "";
        $timeParams = [];
        if ($dateFrom || $dateTo) {
            if ($dateFrom && $dateTo) {
                $timeClause = " AND DATE(t.created_at) BETWEEN :date_from AND :date_to";
                $timeParams[":date_from"] = $dateFrom;
                $timeParams[":date_to"] = $dateTo;
                $appliedDate = ["mode" => "range", "date" => null, "date_from" => $dateFrom, "date_to" => $dateTo];
            } elseif ($dateFrom) {
                $timeClause = " AND DATE(t.created_at) >= :date_from";
                $timeParams[":date_from"] = $dateFrom;
                $appliedDate = ["mode" => "range", "date" => null, "date_from" => $dateFrom, "date_to" => null];
            } else { // solo date_to
                $timeClause = " AND DATE(t.created_at) <= :date_to";
                $timeParams[":date_to"] = $dateTo;
                $appliedDate = ["mode" => "range", "date" => null, "date_from" => null, "date_to" => $dateTo];
            }
        } elseif ($dateFilter) {
            $timeClause = " AND DATE(t.created_at) <= :filter_date";
            $timeParams[":filter_date"] = $dateFilter;
            $appliedDate = ["mode" => "lte", "date" => $dateFilter, "date_from" => null, "date_to" => null];
        } else {
            $timeClause = " AND DATE(t.created_at) <= CURDATE()";
            $appliedDate = ["mode" => "today", "date" => date("Y-m-d"), "date_from" => null, "date_to" => null];
        }

        $queryDetails .= $timeClause . " ORDER BY t.created_at DESC";

        $stmtDetails = $pdo->prepare($queryDetails);
        $execParams = [":corr" => $correspondentId, ":third" => $id] + $timeParams;
        $stmtDetails->execute($execParams);
        $details = $stmtDetails->fetchAll();

        /* Totales efectivos por tipo considerando polarity */
        $debtEff = 0.0; // pagos a tercero
        $chargeEff = 0.0; // pagos de tercero
        $loanToEff = 0.0; // préstamo a tercero
        $loanFromEff = 0.0; // préstamo de tercero

        /* Sumatorios por tercero (comisiones + nuevo campo) */
        $sumBankCommission = 0.0;
        $sumDispersion = 0.0;
        $sumTotalCommission = 0.0;
        $sumPaidTotalCommission = 0.0;
        $countCommissionsPaid = 0;
        $sumTotalBalanceThird = 0.0; // NUEVO

        foreach ($details as &$m) {
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

            $bankCom = (float) $m["bank_commission"];
            $disp = (float) $m["dispersion"];
            $totCom = (float) $m["total_commission"];
            $paid = (int) $m["commission_paid"];
            $totBalThr = (float) $m["total_balance_third"];

            /* Sumar magnitudes */
            $sumBankCommission += abs($bankCom);
            $sumDispersion += abs($disp);
            $sumTotalCommission += abs($totCom);
            $sumTotalBalanceThird += abs($totBalThr);

            if ($paid === 1) {
                $sumPaidTotalCommission += abs($totCom);
                $countCommissionsPaid += 1;
            }

            /* Normalizar tipos (ya vienen casteados en el SELECT) */
            $m["commission_paid"] = $paid === 1 ? 1 : 0;
        }
        unset($m);

        /* Magnitudes de deuda para sumarios */
        $thirdDebtBase = max(0.0, -$normalizedBalance);
        $corpDebtBase = max(0.0, $normalizedBalance);

        $thirdDebt = $thirdDebtBase
            + abs($loanToEff)
            - abs($chargeEff)
            - abs($debtEff);
        if ($thirdDebt < 0)
            $thirdDebt = 0.0;

        $correspDebt = $corpDebtBase
            + abs($loanFromEff)
            + abs($debtEff);
        if ($correspDebt < 0)
            $correspDebt = 0.0;

        $netBalance = $correspDebt - $thirdDebt;

        /* Cupo disponible basado en el neto */
        $availableCredit = ($netBalance >= 0)
            ? $creditLimit
            : max(0.0, $creditLimit - abs($netBalance));

        /* Valores visuales por tipo (signos fijos para UI) */
        $debtDisplay = abs($debtEff);
        $chargeDisplay = abs($chargeEff);
        $loanToDisplay = -abs($loanToEff);
        $loanFromDisplay = abs($loanFromEff);

        /* Resumen por tercero */
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

            /* Sumatorios por tercero */
            "sum_bank_commission" => $sumBankCommission,
            "sum_dispersion" => $sumDispersion,
            "sum_total_commission" => $sumTotalCommission,
            "sum_paid_total_commission" => $sumPaidTotalCommission,
            "count_commissions_paid" => $countCommissionsPaid,
            "sum_total_balance_third" => $sumTotalBalanceThird, // NUEVO

            "movements" => $details
        ];

        /* Totales globales */
        $tot_credit += $creditLimit;
        $tot_balance += $normalizedBalance;
        $tot_available += $availableCredit;
        $tot_third_debt += $thirdDebt;
        $tot_corresp_debt += $correspDebt;
        $tot_bank_commission += $sumBankCommission;
        $tot_dispersion += $sumDispersion;
        $tot_total_commission += $sumTotalCommission;
        $tot_paid_total_commission += $sumPaidTotalCommission;
        $tot_count_commissions_paid += $countCommissionsPaid;
        $tot_total_balance_third += $sumTotalBalanceThird; // NUEVO
    }

    $tot_net = $tot_corresp_debt - $tot_third_debt;

    echo json_encode([
        "success" => true,
        "message" => "Reporte de terceros generado correctamente.",
        "report" => [
            "correspondent_id" => $correspondentId,
            "generated_at" => date("Y-m-d H:i:s"),
            "filter_date" => $dateFilter,
            "applied_date_filter" => $appliedDate, // información útil para auditoría/UI
            "total_third_parties" => count($summary),
            "total_credit_limit" => $tot_credit,
            "total_balance" => $tot_balance,
            "total_net_balance" => $tot_net,
            "total_available_credit" => $tot_available,
            "total_third_party_debt" => $tot_third_debt,
            "total_correspondent_debt" => $tot_corresp_debt,

            /* Totales globales comisiones + nuevo campo */
            "total_bank_commission" => $tot_bank_commission,
            "total_dispersion" => $tot_dispersion,
            "total_total_commission" => $tot_total_commission,
            "total_paid_total_commission" => $tot_paid_total_commission,
            "total_count_commissions_paid" => $tot_count_commissions_paid,
            "total_total_balance_third" => $tot_total_balance_third, // NUEVO

            "third_party_summary" => $summary
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
