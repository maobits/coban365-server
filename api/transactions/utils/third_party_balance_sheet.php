<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Calcula el balance financiero de un tercero vinculado a un corresponsal
 *              y valida si tiene cupo disponible para registrar préstamos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.9
 * Fecha de actualización: 27-Jun-2025
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
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener cupo, balance y si es negativo
    $creditQuery = "SELECT credit, balance, negative_balance FROM others WHERE id = :thirdPartyId LIMIT 1";
    $creditStmt = $pdo->prepare($creditQuery);
    $creditStmt->bindParam(":thirdPartyId", $thirdPartyId, PDO::PARAM_INT);
    $creditStmt->execute();
    $creditData = $creditStmt->fetch(PDO::FETCH_ASSOC);

    if (!$creditData) {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el tercero con el ID especificado."
        ]);
        exit();
    }

    $creditLimit = floatval($creditData["credit"]);
    $balance = floatval($creditData["balance"]);
    $isNegative = intval($creditData["negative_balance"]) === 1;

    // Consulta agrupada por nota
    $query = "
        SELECT 
            third_party_note,
            SUM(cost) AS total
        FROM transactions
        WHERE id_correspondent = :correspondentId
          AND client_reference = :thirdPartyId
          AND state = 1
          AND third_party_note IN (
              'debt_to_third_party',
              'charge_to_third_party',
              'loan_to_third_party',
              'loan_from_third_party'
          )
        GROUP BY third_party_note
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(":correspondentId", $correspondentId, PDO::PARAM_INT);
    $stmt->bindParam(":thirdPartyId", $thirdPartyId, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Obtener cada valor o 0
    $debt = floatval($results["debt_to_third_party"] ?? 0);
    $charge = floatval($results["charge_to_third_party"] ?? 0);
    $loanTo = floatval($results["loan_to_third_party"] ?? 0);
    $loanFrom = floatval($results["loan_from_third_party"] ?? 0);

    // 2️⃣ Saldo neto
    $netBalance = ($isNegative ? $balance : -$balance) + $loanTo + $debt - $charge - $loanFrom;


    // 3️⃣ Cupo disponible = crédito - deuda actual (si está en deuda)
    $availableCredit = $netBalance >= 0
        ? max(0, $creditLimit - $netBalance)
        : $creditLimit;

    // 4️⃣ Acción semántica
    $correspondentAction = $netBalance > 0 ? "cobra" : ($netBalance < 0 ? "paga" : "sin_saldo");

    // 5️⃣ Preparar datos de salida
    $data = [
        "debt_to_third_party" => $debt,
        "charge_to_third_party" => $charge,
        "loan_to_third_party" => $loanTo,
        "loan_from_third_party" => $loanFrom,
        "available_credit" => $availableCredit,
        "credit_limit" => $creditLimit,
        "balance" => $isNegative ? -$balance : $balance,
        "net_balance" => $netBalance,
        "negative_balance" => $isNegative,
        "correspondent_action" => $correspondentAction
    ];

    // 6️⃣ Validación de cupo
    if ($availableCredit <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "El tercero no tiene cupo disponible para recibir un nuevo préstamo.",
            "data" => $data
        ]);
        exit();
    }

    // ✅ Respuesta exitosa
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
