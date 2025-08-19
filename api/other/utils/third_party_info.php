<?php
/**
 * Archivo: third_party_info.php
 * Descripción: Retorna información detallada de UN tercero (others),
 *              buscándolo por third_id o id_number. correspondent_id es OPCIONAL:
 *              si viene, se usa como filtro; si no, se ignora.
 *
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara Hurtado
 * Versión: 1.1.0
 * Fecha: 18-Ago-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

/* -------- Parámetros de entrada (raw) -------- */
$raw_correspondent_id = $_GET['correspondent_id'] ?? null; // OPCIONAL
$raw_third_id = $_GET['third_id'] ?? null; // OPCIONAL
$raw_id_number = $_GET['id_number'] ?? null; // OPCIONAL (pero se exige third_id o id_number)
$raw_date_from = $_GET['date_from'] ?? null; // OPCIONAL
$raw_date_to = $_GET['date_to'] ?? null; // OPCIONAL
$raw_limit = $_GET['limit'] ?? null; // OPCIONAL

/* -------- Helper de error con echo de parámetros -------- */
$respondError = function (string $message, int $httpCode = 400) use ($raw_correspondent_id, $raw_third_id, $raw_id_number, $raw_date_from, $raw_date_to, $raw_limit) {
    http_response_code($httpCode);
    echo json_encode([
        "success" => false,
        "message" => $message,
        "received" => [
                "correspondent_id" => $raw_correspondent_id,
                "third_id" => $raw_third_id,
                "id_number" => $raw_id_number,
                "date_from" => $raw_date_from,
                "date_to" => $raw_date_to,
                "limit" => $raw_limit,
            ],
    ]);
    exit();
};

/* -------- Validaciones -------- */
// correspondent_id YA NO ES OBLIGATORIO

$correspondentId = isset($_GET['correspondent_id']) ? (int) $_GET['correspondent_id'] : null;
$thirdId = isset($_GET['third_id']) ? (int) $_GET['third_id'] : null;
$idNumberInput = isset($_GET['id_number']) ? trim($_GET['id_number']) : null;

if (!$thirdId && $idNumberInput === null) {
    $respondError("Debes enviar third_id o id_number");
}

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null; // YYYY-MM-DD
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null; // YYYY-MM-DD
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50;

$validateDate = function ($d) {
    if (!$d)
        return true;
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
};
if (!$validateDate($dateFrom) || !$validateDate($dateTo)) {
    $respondError("Formato de fecha inválido. Use YYYY-MM-DD para date_from/date_to");
}

require_once __DIR__ . '/../../db.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    /* -------- 1) Localizar al tercero -------- */
    // Base: activo
    $where = "state = 1";
    $params = [];

    // Filtro opcional por corresponsal
    if ($correspondentId) {
        $where .= " AND correspondent_id = :cid";
        $params[":cid"] = $correspondentId;
    }

    if ($thirdId) {
        $where .= " AND id = :tid";
        $params[":tid"] = $thirdId;
    } else {
        $where .= " AND id_number = :idn";
        $params[":idn"] = $idNumberInput;
    }

    $sqlThird = "SELECT
                    id,
                    correspondent_id,
                    name,
                    id_type,
                    id_number,
                    email,
                    phone,
                    address,
                    credit,
                    balance,
                    negative_balance,
                    state,
                    created_at,
                    updated_at
                 FROM others
                 WHERE {$where}
                 ORDER BY id DESC
                 LIMIT 1";

    $st = $pdo->prepare($sqlThird);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();

    $third = $st->fetch();
    if (!$third) {
        $respondError("No se encontró el tercero con los parámetros suministrados.", 404);
    }

    $idThird = (int) $third['id'];

    /* -------- 2) Agregados del estado de cuenta -------- */
    $sqlAgg = "
        SELECT
            IFNULL(SUM(account_receivable), 0) AS total_receivable,
            IFNULL(SUM(account_to_pay), 0)     AS total_to_pay,
            (IFNULL(SUM(account_receivable),0) - IFNULL(SUM(account_to_pay),0)) AS balance
        FROM account_statement_others
        WHERE id_third = :id_third AND state = 1
    ";
    $stAgg = $pdo->prepare($sqlAgg);
    $stAgg->bindValue(":id_third", $idThird, PDO::PARAM_INT);
    $stAgg->execute();
    $agg = $stAgg->fetch() ?: [
        "total_receivable" => 0,
        "total_to_pay" => 0,
        "balance" => 0,
    ];

    /* -------- 3) Movimientos (opcional, filtrables) --------
       En tu DB no existe `description`. Retornamos NULL AS description. */
    $sqlMov = "
        SELECT
            id,
            id_third,
            account_receivable,
            account_to_pay,
            NULL AS description,
            created_at,
            state
        FROM account_statement_others
        WHERE id_third = :id_third AND state = 1
    ";

    $paramsMov = [":id_third" => $idThird];
    if ($dateFrom) {
        $sqlMov .= " AND DATE(created_at) >= :df";
        $paramsMov[":df"] = $dateFrom;
    }
    if ($dateTo) {
        $sqlMov .= " AND DATE(created_at) <= :dt";
        $paramsMov[":dt"] = $dateTo;
    }

    $sqlMov .= " ORDER BY created_at DESC LIMIT {$limit}";

    $stMov = $pdo->prepare($sqlMov);
    foreach ($paramsMov as $k => $v) {
        $stMov->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stMov->execute();
    $movements = $stMov->fetchAll();

    /* -------- 4) Respuesta -------- */
    echo json_encode([
        "success" => true,
        "data" => [
            "third" => [
                "id" => (int) $third['id'],
                "correspondent_id" => (int) $third['correspondent_id'],
                "name" => $third['name'],
                "id_type" => $third['id_type'],
                "id_number" => $third['id_number'],
                "email" => $third['email'],
                "phone" => $third['phone'],
                "address" => $third['address'],
                "credit" => (float) $third['credit'],
                "balance_local" => (float) $third['balance'],
                "negative_balance" => (int) $third['negative_balance'],
                "state" => (int) $third['state'],
                "created_at" => $third['created_at'],
                "updated_at" => $third['updated_at'],
            ],
            "statement_totals" => [
                "total_receivable" => (float) $agg['total_receivable'],
                "total_to_pay" => (float) $agg['total_to_pay'],
                "balance" => (float) $agg['balance'],
            ],
            "movements" => $movements,
            "filters" => [
                "by" => $thirdId ? "third_id" : "id_number",
                "third_id" => $idThird,
                "id_number" => $third['id_number'],
                "date_from" => $dateFrom,
                "date_to" => $dateTo,
                "limit" => $limit,
                // echo opcional del filtro de corresponsal si vino
                "correspondent_id" => $correspondentId,
            ],
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage(),
        "received" => [
            "correspondent_id" => $raw_correspondent_id,
            "third_id" => $raw_third_id,
            "id_number" => $raw_id_number,
            "date_from" => $raw_date_from,
            "date_to" => $raw_date_to,
            "limit" => $raw_limit,
        ],
    ]);
}
