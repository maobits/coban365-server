<?php
/**
 * Archivo: third_party_balance_sheet.php
 * Descripción: Calcula los saldos relacionados a terceros por corresponsal e ID de tercero.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 24-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Validar parámetros obligatorios
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

    // Consultar sumatoria por tipo de nota
    $query = "
        SELECT 
            third_party_note,
            SUM(CASE WHEN polarity = 1 THEN cost ELSE -cost END) as total
        FROM transactions
        WHERE id_correspondent = :correspondentId
          AND client_reference = :thirdPartyId
          AND state = 1
          AND third_party_note IN (
              'debt-to-third-party',
              'charge-a-third-party',
              'third-party-loan',
              'loan-received-from-a-third-party'
          )
        GROUP BY third_party_note
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(":correspondentId", $correspondentId, PDO::PARAM_INT);
    $stmt->bindParam(":thirdPartyId", $thirdPartyId, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        "success" => true,
        "data" => [
            "debt_to_third_party" => floatval($results["debt-to-third-party"] ?? 0),
            "charge_to_third_party" => floatval($results["charge-a-third-party"] ?? 0),
            "loan_to_third_party" => floatval($results["third-party-loan"] ?? 0),
            "loan_from_third_party" => floatval($results["loan-received-from-a-third-party"] ?? 0)
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
?>