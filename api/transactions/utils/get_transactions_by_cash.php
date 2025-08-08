<?php
/**
 * Archivo: get_transactions_by_cash.php
 * Descripción: Retorna transacciones paginadas de una caja específica con todos los detalles.
 * Incluye también transferencias entrantes donde box_reference = id_cash.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.4.2
 * Fecha: 21-Jul-2025 (Actualizado 08-Ago-2025 para incluir cash_tag)
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
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$perPage = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 20;
$offset = ($page - 1) * $perPage;
$category = isset($_GET["category"]) ? trim($_GET["category"]) : null;
$dateFilter = isset($_GET["date"]) ? trim($_GET["date"]) : null;

try {
    // Total combinando transacciones salientes y transferencias entrantes
    $countSql = "
        SELECT COUNT(*) FROM transactions t
        LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
        WHERE t.state = 1 AND (t.id_cash = :id_cash OR t.box_reference = :id_cash)
    ";
    if ($category) {
        $countSql .= " AND tt.category = :category";
    }
    if ($dateFilter) {
        $countSql .= " AND DATE(t.created_at) = :date";
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    if ($category) {
        $countStmt->bindParam(":category", $category, PDO::PARAM_STR);
    }
    if ($dateFilter) {
        $countStmt->bindParam(":date", $dateFilter);
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

    // Obtener transacciones propias y entrantes (incluyendo cash_tag)
    $sql = "
        SELECT 
            t.*,
            t.cash_tag, /* 👈 Incluido explícitamente */
            tt.name AS transaction_type_name,
            c.name AS correspondent_name,
            ca.name AS cash_name,
            ca.capacity AS cash_capacity,
            o.name AS client_reference_name,
            ca2.name AS destination_cash_name
        FROM transactions t
        LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
        LEFT JOIN correspondents c ON t.id_correspondent = c.id
        LEFT JOIN cash ca ON t.id_cash = ca.id
        LEFT JOIN cash ca2 ON t.box_reference = ca2.id
        LEFT JOIN others o ON t.client_reference = o.id
        WHERE t.state = 1 AND (t.id_cash = :id_cash OR t.box_reference = :id_cash)
    ";
    if ($category) {
        $sql .= " AND tt.category = :category";
    }
    if ($dateFilter) {
        $sql .= " AND DATE(t.created_at) = :date";
    }
    $sql .= " ORDER BY t.id DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    if ($category) {
        $stmt->bindParam(":category", $category, PDO::PARAM_STR);
    }
    if ($dateFilter) {
        $stmt->bindParam(":date", $dateFilter);
    }
    $stmt->bindParam(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear fechas y ajustar nota según rol de la caja en la transferencia
    setlocale(LC_TIME, 'es_ES.UTF-8');
    foreach ($transactions as &$tx) {
        $isTransfer = intval($tx["is_transfer"]) === 1;
        $isAccepted = intval($tx["transfer_status"]) === 1;
        $isPending = intval($tx["transfer_status"]) === 0;
        $isOrigin = intval($tx["id_cash"]) === $id_cash;
        $isDestination = intval($tx["box_reference"]) === $id_cash;
        $fromCash = $tx["cash_name"] ?? "Caja origen";
        $toCash = $tx["destination_cash_name"] ?? "Caja destino";

        if ($isTransfer && $isDestination && $isAccepted && !$isOrigin) {
            $tx["polarity"] = 1;
            $tx["note"] = "Recibido de " . $fromCash;
        }

        if ($isTransfer && $isDestination && $isPending && !$isOrigin) {
            $tx["polarity"] = 1;
            $tx["note"] = "Pendiente de recibir desde " . $fromCash;
        }

        if ($isTransfer && $isOrigin && $isAccepted && !$isDestination) {
            $tx["note"] = "Transferencia a " . $toCash;
        }

        if ($isTransfer && $isOrigin && $isPending && !$isDestination) {
            $tx["note"] = "Transfiriendo a " . $toCash . "...";
        }

        $datetime = new DateTime($tx["created_at"]);
        $tx["formatted_date"] = $datetime->format("d-m-Y h:i a");
    }

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
