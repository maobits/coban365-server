<?php
/**
 * Archivo: get_cash_withdrawals.php
 * Descripción: Retorna la lista de retiros activos (polarity = 0) de una caja específica junto con el total y utilidad acumuladas.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.0
 * Fecha de actualización: 18-May-2025
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

if (!isset($_GET["id_cash"])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro obligatorio id_cash."
    ]);
    exit();
}

$id_cash = intval($_GET["id_cash"]);

try {
    // Obtener retiros individuales
    $sql = "
        SELECT 
            t.*,
            tt.name AS transaction_type_name,
            c.name AS correspondent_name,
            u.fullname AS cashier_name
        FROM transactions t
        LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
        LEFT JOIN correspondents c ON t.id_correspondent = c.id
        LEFT JOIN users u ON t.id_cashier = u.id
        WHERE t.id_cash = :id_cash 
          AND t.polarity = 0 
          AND t.state = 1
        ORDER BY t.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    setlocale(LC_TIME, 'es_ES.UTF-8');
    foreach ($transactions as &$tx) {
        $datetime = new DateTime($tx["created_at"]);
        $tx["formatted_date"] = $datetime->format("d") . " de " .
            strftime("%B", $datetime->getTimestamp()) . " de " .
            $datetime->format("Y") . " a las " .
            $datetime->format("h:i A");
        $tx["utility"] = isset($tx["utility"]) ? floatval($tx["utility"]) : 0;
    }

    // Calcular total de retiros y utilidad
    $sumSql = "
        SELECT 
            SUM(cost) AS total_withdrawal,
            SUM(utility) AS total_utility
        FROM transactions
        WHERE id_cash = :id_cash 
          AND polarity = 0 
          AND state = 1
    ";
    $sumStmt = $pdo->prepare($sumSql);
    $sumStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $sumStmt->execute();
    $sumResult = $sumStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "total" => floatval($sumResult["total_withdrawal"] ?? 0),
        "utility" => floatval($sumResult["total_utility"] ?? 0),
        "data" => $transactions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener los retiros: " . $e->getMessage()
    ]);
}
?>