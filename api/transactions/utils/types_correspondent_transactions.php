<?php
/**
 * Archivo: types_correspondent_transactions.php
 * Descripción: Retorna los tipos de transacciones permitidos para un corresponsal desde el campo JSON 'transactions',
 *              agregando las propiedades 'polarity' desde 'transaction_types' y 'rate' desde 'rates'.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.2
 * Fecha de actualización: 03-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

if (!isset($_GET['correspondent_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro correspondent_id"
    ]);
    exit();
}

$correspondentId = intval($_GET['correspondent_id']);

require_once '../../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener transacciones del corresponsal
    $sql = "SELECT transactions FROM correspondents WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $correspondentId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['transactions'])) {
        $transactions = json_decode($result['transactions'], true);

        foreach ($transactions as &$t) {
            $typeId = $t['id'];

            // Obtener polarity
            $stmtPolarity = $conn->prepare("SELECT polarity FROM transaction_types WHERE id = :id");
            $stmtPolarity->bindParam(':id', $typeId, PDO::PARAM_INT);
            $stmtPolarity->execute();
            $typeData = $stmtPolarity->fetch(PDO::FETCH_ASSOC);
            $t['polarity'] = isset($typeData['polarity']) ? intval($typeData['polarity']) : null;

            // Obtener rate
            $stmtRate = $conn->prepare("SELECT price FROM rates WHERE correspondent_id = :cid AND transaction_type_id = :tid");
            $stmtRate->bindParam(':cid', $correspondentId, PDO::PARAM_INT);
            $stmtRate->bindParam(':tid', $typeId, PDO::PARAM_INT);
            $stmtRate->execute();
            $rateData = $stmtRate->fetch(PDO::FETCH_ASSOC);
            $t['rate'] = isset($rateData['price']) ? floatval($rateData['price']) : null;
        }

        echo json_encode([
            "success" => true,
            "data" => $transactions
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el corresponsal o no tiene transacciones asignadas."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al consultar transacciones: " . $e->getMessage()
    ]);
}
