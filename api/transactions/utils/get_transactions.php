<?php
/**
 * Archivo: get_transactions.php
 * Descripción: Retorna todas las transacciones del cajero activo con datos completos, incluyendo la hora y el nombre de la caja.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.3
 * Fecha de actualización: 05-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../../db.php";

if (!isset($_GET["id_cashier"])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro obligatorio id_cashier."
    ]);
    exit();
}

$id_cashier = intval($_GET["id_cashier"]);

try {
    $sql = "
    SELECT 
        t.*,
        tt.name AS transaction_type_name,
        c.name AS correspondent_name,
        ca.capacity AS cash_capacity,
        ca.name AS cash_name,
        o.name AS client_reference_name
    FROM transactions t
    LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
    LEFT JOIN correspondents c ON t.id_correspondent = c.id
    LEFT JOIN cash ca ON t.id_cash = ca.id
    LEFT JOIN others o ON t.client_reference = o.id
    WHERE t.id_cashier = :id_cashier AND t.state = 1
    ORDER BY t.created_at DESC
";


    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id_cashier", $id_cashier, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    setlocale(LC_TIME, 'es_ES.UTF-8');

    foreach ($transactions as &$tx) {
        $datetime = new DateTime($tx["created_at"]);
        $tx["formatted_date"] = $datetime->format("d") . " de " . strftime("%B", $datetime->getTimestamp()) . " de " . $datetime->format("Y") . " a las " . $datetime->format("h:i A");
    }

    echo json_encode([
        "success" => true,
        "data" => $transactions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener las transacciones: " . $e->getMessage()
    ]);
}
