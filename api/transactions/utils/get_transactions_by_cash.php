<?php
/**
 * Archivo: get_transactions_by_cash.php
 * Descripción: Retorna transacciones paginadas de una caja específica con detalles completos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.0
 * Fecha de actualización: 26-May-2025
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

// Validar parámetro obligatorio
if (!isset($_GET["id_cash"])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro obligatorio id_cash."
    ]);
    exit();
}

$id_cash = intval($_GET["id_cash"]);
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$perPage = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 20; // ← antes era 10
$offset = ($page - 1) * $perPage;

try {
    // Obtener total de registros
    $countSql = "SELECT COUNT(*) FROM transactions WHERE id_cash = :id_cash AND state = 1";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

    // Obtener transacciones paginadas
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
        WHERE t.id_cash = :id_cash AND t.state = 1
        ORDER BY t.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $stmt->bindParam(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear fecha
    setlocale(LC_TIME, 'es_ES.UTF-8');
    foreach ($transactions as &$tx) {
        $datetime = new DateTime($tx["created_at"]);
        $tx["formatted_date"] = $datetime->format("d") . " de " . strftime("%B", $datetime->getTimestamp()) . " de " . $datetime->format("Y") . " a las " . $datetime->format("h:i A");
    }

    // Devolver respuesta paginada
    echo json_encode([
        "success" => true,
        "data" => [
            "items" => $transactions,
            "total" => intval($total),
            "total_pages" => intval($totalPages)
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener las transacciones: " . $e->getMessage()
    ]);
}
