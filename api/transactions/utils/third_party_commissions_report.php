<?php
/* ============================================================
 *  third_party_commissions_report.php
 *  Reporte: comisiones acumuladas por tercero en un corresponsal
 *  y total acumulado del corresponsal.
 *  Respuesta JSON con lista + total + metadatos.
 *  Autor: Maobits / Mauricio Chara
 * ============================================================ */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

/* =========================================
 * Cargar conexión PDO
 * ========================================= */
require_once __DIR__ . "/../../db.php";

function json_response($arr, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($arr);
    exit;
}

/* =========================================
 * Parámetros
 * ========================================= */
$correspondentId = isset($_GET["correspondent_id"]) ? intval($_GET["correspondent_id"]) : 0;

/* Filtros opcionales */
$thirdPartyId = isset($_GET["third_party_id"]) ? intval($_GET["third_party_id"]) : null; // ver uno en particular
$minTotal = isset($_GET["min_total"]) ? floatval($_GET["min_total"]) : null; // saldo mínimo
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";   // buscar por nombre de tercero

/* Paginación opcional */
$limit = isset($_GET["limit"]) ? max(1, intval($_GET["limit"])) : 100;
$offset = isset($_GET["offset"]) ? max(0, intval($_GET["offset"])) : 0;

if ($correspondentId <= 0) {
    json_response([
        "success" => false,
        "error" => "INVALID_PARAMS",
        "message" => "Debes enviar correspondent_id > 0."
    ], 400);
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    /* =========================================
     * Nombre del corresponsal (metadato)
     * ========================================= */
    $stmtName = $pdo->prepare("SELECT name FROM correspondents WHERE id = :id LIMIT 1");
    $stmtName->execute([":id" => $correspondentId]);
    $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
    $correspondentName = $rowName ? $rowName["name"] : "Corresponsal #" . $correspondentId;

    /* =========================================
     * Construir filtros base
     * third_party_commissions: (id, third_party_id, correspondent_id, total_commission, last_update)
     * others: terceros (id, correspondent_id, name, ...)
     * ========================================= */
    $where = ["tpc.correspondent_id = :cid"];
    $params = [":cid" => $correspondentId];

    if (!empty($thirdPartyId)) {
        $where[] = "tpc.third_party_id = :tid";
        $params[":tid"] = $thirdPartyId;
    }

    if ($minTotal !== null) {
        $where[] = "tpc.total_commission >= :minTotal";
        $params[":minTotal"] = $minTotal;
    }

    if ($search !== "") {
        // búsqueda por nombre de tercero en tabla `others`
        $where[] = "LOWER(o.name) LIKE :search";
        $params[":search"] = "%" . mb_strtolower($search, "UTF-8") . "%";
    }

    $whereSQL = implode(" AND ", $where);

    /* =========================================
     * Total general (suma de todas las comisiones del corresponsal)
     * (no necesita JOIN)
     * ========================================= */
    $stmtSum = $pdo->prepare("
        SELECT COALESCE(SUM(tpc.total_commission), 0) AS grand_total,
               COUNT(*) AS third_count
        FROM third_party_commissions tpc
        WHERE tpc.correspondent_id = :cid
    ");
    $stmtSum->execute([":cid" => $correspondentId]);
    $sumRow = $stmtSum->fetch(PDO::FETCH_ASSOC);
    $grandTotal = $sumRow ? (float) $sumRow["grand_total"] : 0.0;
    $thirdCount = $sumRow ? (int) $sumRow["third_count"] : 0;

    /* =========================================
     * Listado detallado (con nombre del tercero) + paginación
     * ========================================= */
    $sqlList = "
        SELECT 
            tpc.id,
            tpc.third_party_id,
            tpc.correspondent_id,
            tpc.total_commission,
            tpc.last_update,
            o.name AS third_party_name
        FROM third_party_commissions tpc
        LEFT JOIN others o 
            ON o.id = tpc.third_party_id
           AND o.correspondent_id = tpc.correspondent_id
        WHERE $whereSQL
        ORDER BY tpc.total_commission DESC, o.name ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmtList = $pdo->prepare($sqlList);

    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmtList->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmtList->execute();
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    /* =========================================
     * Total de filas que satisfacen el filtro (para paginación)
     * ========================================= */
    $sqlCount = "
        SELECT COUNT(*) AS total_rows
        FROM third_party_commissions tpc
        LEFT JOIN others o 
            ON o.id = tpc.third_party_id
           AND o.correspondent_id = tpc.correspondent_id
        WHERE $whereSQL
    ";

    $stmtCount = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $filteredCount = $countRow ? (int) $countRow["total_rows"] : 0;

    /* =========================================
     * Respuesta
     * ========================================= */
    json_response([
        "success" => true,
        "data" => [
            "correspondent" => [
                "id" => $correspondentId,
                "name" => $correspondentName,
            ],
            "summary" => [
                "third_parties_with_commission" => $thirdCount,
                "grand_total_commission" => round($grandTotal, 2),
            ],
            "pagination" => [
                "limit" => $limit,
                "offset" => $offset,
                "count" => $filteredCount,
                "has_more" => ($offset + $limit) < $filteredCount,
            ],
            "rows" => array_map(function ($r) {
                return [
                    "id" => (int) $r["id"],
                    "third_party_id" => (int) $r["third_party_id"],
                    "third_party_name" => $r["third_party_name"] ?? null,
                    "correspondent_id" => (int) $r["correspondent_id"],
                    "total_commission" => round((float) $r["total_commission"], 2),
                    "last_update" => $r["last_update"],
                ];
            }, $rows),
        ],
    ]);

} catch (Throwable $e) {
    json_response([
        "success" => false,
        "error" => "DB_ERROR",
        "message" => "Error de base de datos.",
        "details" => $e->getMessage(),
    ], 500);
}
