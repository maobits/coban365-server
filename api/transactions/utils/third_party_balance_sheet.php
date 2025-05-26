<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Calcula el balance financiero de un tercero vinculado a un corresponsal
 *              y valida si tiene cupo disponible para registrar préstamos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.3.0
 * Fecha de actualización: 25-May-2025
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
$thirdPartyId = $_GET["third_party_id"];

require_once "../../db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener cupo del tercero
    $creditQuery = "SELECT credit FROM others WHERE id = :thirdPartyId LIMIT 1";
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

    // Consulta agrupada por nota (sin polarity)
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
    $stmt->bindParam(":thirdPartyId", $thirdPartyId, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Obtener cada valor o 0
    $debt = floatval($results["debt_to_third_party"] ?? 0);
    $charge = floatval($results["charge_to_third_party"] ?? 0);
    $loanTo = floatval($results["loan_to_third_party"] ?? 0);
    $loanFrom = floatval($results["loan_from_third_party"] ?? 0);

    // Cálculos contables
    $debtToThirdParty = $loanFrom - $debt;
    $chargeToThirdParty = $loanTo - $charge;

    // Validar cupo disponible neto para préstamos
    $cupoDisponible = $creditLimit - $chargeToThirdParty;
    if ($cupoDisponible <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "El tercero no tiene cupo disponible para recibir un nuevo préstamo.",
            "data" => [
                "available_credit" => 0,
                "credit_limit" => $creditLimit,
                "charge_to_third_party" => $chargeToThirdParty
            ]
        ]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => "Cálculo de balance exitoso.",
        "data" => [
            "debt_to_third_party" => $debtToThirdParty,
            "charge_to_third_party" => $chargeToThirdParty,
            "loan_to_third_party" => $loanTo,
            "loan_from_third_party" => $loanFrom,
            "available_credit" => $cupoDisponible,
            "credit_limit" => $creditLimit
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
?>