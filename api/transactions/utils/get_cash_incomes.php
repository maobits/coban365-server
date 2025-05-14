<?php
/**
 * Archivo: get_cash_incomes.php
 * Descripción: Retorna la lista de ingresos activos (polarity = 1) de una caja específica junto con el total acumulado.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 11-May-2025
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
    // Obtener ingresos individuales
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
          AND t.polarity = 1 
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
        $tx["formatted_date"] = $datetime->format("d") . " de " . strftime("%B", $datetime->getTimestamp()) . " de " . $datetime->format("Y") . " a las " . $datetime->format("h:i A");
    }

    // Calcular total de ingresos
    $sumSql = "
        SELECT SUM(cost) AS total_income
        FROM transactions
        WHERE id_cash = :id_cash 
          AND polarity = 1 
          AND state = 1
    ";
    $sumStmt = $pdo->prepare($sumSql);
    $sumStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $sumStmt->execute();
    $sumResult = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $total_income = $sumResult["total_income"] ?? 0;

    echo json_encode([
        "success" => true,
        "total" => floatval($total_income),
        "data" => $transactions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener los ingresos: " . $e->getMessage()
    ]);
}
