<?php
/**
 * Archivo: get_transactions_by_cash.php
 * Descripción: Retorna transacciones paginadas de una caja específica con todos los detalles.
 * Incluye también transferencias entrantes donde box_reference = id_cash.
 * Además, si se recibe `date=YYYY-MM-DD`, verifica si la caja está cerrada en esa fecha
 * (tabla cash_closing_register) e incluye el registro de cash_balance de esa fecha.
 * Proyecto: COBAN365
 * Versión: 1.4.6
 * Fecha: 11-Ago-2025
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
$rawCat = isset($_GET["category"]) ? trim($_GET["category"]) : null;
$dateFilter = isset($_GET["date"]) ? trim($_GET["date"]) : null;

/** Rango de valor por query (opcional) */
$minValue = (isset($_GET["min_value"]) && $_GET["min_value"] !== '') ? floatval($_GET["min_value"]) : null;
$maxValue = (isset($_GET["max_value"]) && $_GET["max_value"] !== '') ? floatval($_GET["max_value"]) : null;

/** Subcategoría y/o RANGO dentro del mismo parámetro `category` */
$category = null;
$subcategory = null;

if ($rawCat) {
    $parts = array_map('trim', explode('::', $rawCat));
    $category = $parts[0] ?? null;

    $parseRange = function ($token) {
        if (strpos($token, 'RANGO=') === 0) {
            $payload = substr($token, 6);
            [$a, $b] = array_pad(explode('-', $payload, 2), 2, '');
            $min = ($a !== '') ? floatval($a) : null;
            $max = ($b !== '') ? floatval($b) : null;
            return [$min, $max];
        }
        return null;
    };

    if (isset($parts[1])) {
        $r1 = $parseRange($parts[1]);
        if ($r1) {
            [$minValueFromCat, $maxValueFromCat] = $r1;
            $minValue = $minValueFromCat;
            $maxValue = $maxValueFromCat;
        } else {
            $subcategory = $parts[1];
        }
    }

    if (isset($parts[2])) {
        $r2 = $parseRange($parts[2]);
        if ($r2) {
            [$minValueFromCat, $maxValueFromCat] = $r2;
            $minValue = $minValueFromCat;
            $maxValue = $maxValueFromCat;
        }
    }

    if ($category === '')
        $category = null;
    if ($subcategory === '')
        $subcategory = null;
}

try {
    // ---------- Conteo total ----------
    $countSql = "
        SELECT COUNT(*)
        FROM transactions t
        LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
        WHERE t.state = 1
          AND (t.id_cash = :id_cash OR t.box_reference = :id_cash)
    ";
    if ($category)
        $countSql .= " AND tt.category = :category";
    if ($subcategory)
        $countSql .= " AND tt.name = :subcategory";
    if ($dateFilter)
        $countSql .= " AND DATE(t.created_at) = :date";
    if ($minValue !== null)
        $countSql .= " AND t.cost >= :min_value";
    if ($maxValue !== null)
        $countSql .= " AND t.cost <= :max_value";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    if ($category)
        $countStmt->bindParam(":category", $category, PDO::PARAM_STR);
    if ($subcategory)
        $countStmt->bindParam(":subcategory", $subcategory, PDO::PARAM_STR);
    if ($dateFilter)
        $countStmt->bindParam(":date", $dateFilter);
    if ($minValue !== null)
        $countStmt->bindValue(":min_value", $minValue);
    if ($maxValue !== null)
        $countStmt->bindValue(":max_value", $maxValue);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $perPage);

    // ---------- Query de datos ----------
    $sql = "
        SELECT 
            t.*,
            t.cash_tag,
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
        WHERE t.state = 1
          AND (t.id_cash = :id_cash OR t.box_reference = :id_cash)
    ";
    if ($category)
        $sql .= " AND tt.category = :category";
    if ($subcategory)
        $sql .= " AND tt.name = :subcategory";
    if ($dateFilter)
        $sql .= " AND DATE(t.created_at) = :date";
    if ($minValue !== null)
        $sql .= " AND t.cost >= :min_value";
    if ($maxValue !== null)
        $sql .= " AND t.cost <= :max_value";
    $sql .= " ORDER BY t.id DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    if ($category)
        $stmt->bindParam(":category", $category, PDO::PARAM_STR);
    if ($subcategory)
        $stmt->bindParam(":subcategory", $subcategory, PDO::PARAM_STR);
    if ($dateFilter)
        $stmt->bindParam(":date", $dateFilter);
    if ($minValue !== null)
        $stmt->bindValue(":min_value", $minValue);
    if ($maxValue !== null)
        $stmt->bindValue(":max_value", $maxValue);
    $stmt->bindParam(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- Post-proceso ----------
    setlocale(LC_TIME, 'es_ES.UTF-8');
    foreach ($transactions as &$tx) {
        $isTransfer = (int) $tx["is_transfer"] === 1;
        $isAccepted = (int) $tx["transfer_status"] === 1;
        $isPending = (int) $tx["transfer_status"] === 0;
        $isOrigin = (int) $tx["id_cash"] === $id_cash;
        $isDestination = (int) $tx["box_reference"] === $id_cash;
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

    // ---------- Verificación de cierre y cash_balance (USANDO date del filtro) ----------
    $isClosed = false;
    $cashBalanceRow = null;

    if ($dateFilter) {
        // 1) ¿Hay cierre en cash_closing_register para esa fecha y caja?
        $closeStmt = $pdo->prepare("
            SELECT id, cash_id, closing_date, closing_time, closed_by, note, created_at
            FROM cash_closing_register
            WHERE cash_id = :cash_id AND closing_date = :closing_date
            LIMIT 1
        ");
        $closeStmt->execute([
            ":cash_id" => $id_cash,
            ":closing_date" => $dateFilter
        ]);
        $closingRow = $closeStmt->fetch(PDO::FETCH_ASSOC);
        $isClosed = $closingRow ? true : false;

        // 2) Traer el registro de cash_balance (si existe) para esa fecha y caja
        $balanceStmt = $pdo->prepare("
            SELECT *
            FROM cash_balance
            WHERE cash_id = :cash_id AND balance_date = :balance_date
            ORDER BY id DESC
            LIMIT 1
        ");
        $balanceStmt->execute([
            ":cash_id" => $id_cash,
            ":balance_date" => $dateFilter
        ]);
        $cashBalanceRow = $balanceStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "items" => $transactions,
            "total" => $total,
            "total_pages" => $totalPages,
            // 👇 agregado según lo solicitado
            "is_closed" => $isClosed,          // true si existe cierre para esa fecha en cash_closing_register
            "cash_balance" => $cashBalanceRow     // fila de cash_balance (o null si no existe)
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener las transacciones: " . $e->getMessage()
    ]);
}
