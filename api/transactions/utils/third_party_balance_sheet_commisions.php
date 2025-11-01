<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Calcula el balance financiero de un tercero vinculado a un corresponsal
 *              y valida si tiene cupo disponible para registrar préstamos.
 *              Ajusta el net_balance restando la comisión pendiente.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.4.1
 * Fecha de actualización: 01-Nov-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

if (!isset($_GET["correspondent_id"]) || !isset($_GET["third_party_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros requeridos: correspondent_id y third_party_id"
    ]);
    exit();
}

$correspondentId = intval($_GET["correspondent_id"]);
$thirdPartyId = intval($_GET["third_party_id"]);

require_once "../../db.php";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // 1) Cupo, balance almacenado y si el saldo se guarda como negativo
    $creditStmt = $pdo->prepare("
        SELECT credit, balance, negative_balance
        FROM others
        WHERE id = :thirdPartyId
        LIMIT 1
    ");
    $creditStmt->execute([":thirdPartyId" => $thirdPartyId]);
    $creditData = $creditStmt->fetch();

    if (!$creditData) {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el tercero con el ID especificado."
        ]);
        exit();
    }

    $creditLimit = (float) $creditData["credit"];
    $balance = (float) $creditData["balance"];
    $isNegative = ((int) $creditData["negative_balance"] === 1);

    // 2) Sumas por tipo de nota (cost)
    $stmt = $pdo->prepare("
        SELECT third_party_note, SUM(cost) AS total
        FROM transactions
        WHERE id_correspondent = :corr
          AND client_reference = :third
          AND state = 1
          AND third_party_note IN (
              'debt_to_third_party',
              'charge_to_third_party',
              'loan_to_third_party',
              'loan_from_third_party'
          )
        GROUP BY third_party_note
    ");
    $stmt->execute([
        ":corr" => $correspondentId,
        ":third" => $thirdPartyId
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $debt = (float) ($results["debt_to_third_party"] ?? 0.0);
    $charge = (float) ($results["charge_to_third_party"] ?? 0.0);
    $loanTo = (float) ($results["loan_to_third_party"] ?? 0.0);
    $loanFrom = (float) ($results["loan_from_third_party"] ?? 0.0);

    // 3) Comisiones por transacción
    $feeStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(bank_commission), 0)       AS bank_commission_signed,
            COALESCE(SUM(dispersion), 0)            AS dispersion_signed,
            COALESCE(SUM(total_commission), 0)      AS total_commission_signed,
            COALESCE(SUM(ABS(bank_commission)), 0)  AS bank_commission_abs,
            COALESCE(SUM(ABS(dispersion)), 0)       AS dispersion_abs,
            COALESCE(SUM(ABS(total_commission)), 0) AS total_commission_abs
        FROM transactions
        WHERE id_correspondent = :corr
          AND client_reference = :third
          AND state = 1
          AND third_party_note IN (
              'debt_to_third_party',
              'charge_to_third_party',
              'loan_to_third_party',
              'loan_from_third_party'
          )
    ");
    $feeStmt->execute([
        ":corr" => $correspondentId,
        ":third" => $thirdPartyId
    ]);
    $fees = $feeStmt->fetch() ?: [
        "bank_commission_signed" => 0,
        "dispersion_signed" => 0,
        "total_commission_signed" => 0,
        "bank_commission_abs" => 0,
        "dispersion_abs" => 0,
        "total_commission_abs" => 0,
    ];

    // --- cálculo neto base sin ajustar por comisión acumulada ---
    // Nota: si negative_balance=1 significa que en BD se guarda "lo que el CB debe al tercero" como positivo
    //       entonces para unificar perspectiva "cuánto debe el tercero al CB"
    //       usamos (isNegative ? balance : -balance)
    $netBalanceRaw = ($isNegative ? $balance : -$balance)
        + $loanTo
        + $debt
        - $charge
        - $loanFrom;

    // aquí viene tu ajuste:
    // restar total_commission_signed al net_balance
    // OJO: en tu ejemplo total_commission_signed = -1000
    // netBalanceRaw (-1000) - (-1000) = 0 ✔️
    $commissionToSubtract = (float) $fees["total_commission_signed"];
    $netBalance = $netBalanceRaw - $commissionToSubtract;

    // 5) Cupo disponible con el netBalance ya ajustado
    $availableCredit = $netBalance >= 0
        ? max(0, $creditLimit - $netBalance)
        : $creditLimit;

    // 6) Acción semántica
    $correspondentAction = $netBalance > 0
        ? "cobra"
        : ($netBalance < 0 ? "paga" : "sin_saldo");

    // 7) Respuesta
    $data = [
        // movimientos
        "debt_to_third_party" => $debt,
        "charge_to_third_party" => $charge,
        "loan_to_third_party" => $loanTo,
        "loan_from_third_party" => $loanFrom,

        // comisiones
        "bank_commission" => (float) $fees["bank_commission_signed"],
        "dispersion" => (float) $fees["dispersion_signed"],
        "total_commission" => (float) $fees["total_commission_signed"],
        "sum_bank_commission" => (float) $fees["bank_commission_abs"],
        "sum_dispersion" => (float) $fees["dispersion_abs"],
        "sum_total_commission" => (float) $fees["total_commission_abs"],

        // saldos
        "available_credit" => $availableCredit,
        "credit_limit" => $creditLimit,
        "balance" => $isNegative ? -$balance : $balance,

        // incluimos ambos para auditoría
        "net_balance_raw" => $netBalanceRaw,      // antes de restar comisión
        "net_balance" => $netBalance,         // después de restar comisión (el que usas en la app)

        "negative_balance" => $isNegative,
        "correspondent_action" => $correspondentAction,
    ];

    // 8) Validación de cupo
    if ($availableCredit <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "El tercero no tiene cupo disponible para recibir un nuevo préstamo.",
            "data" => $data
        ]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => "Cálculo de balance exitoso.",
        "data" => $data
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
