<?php
/* ============================================================
 *  third_party_commissions.php
 *  CRUD + operaciones de saldo comisión acumulada por tercero
 *  Autor: Maobits / Mauricio Chara
 * ============================================================ */

header('Content-Type: application/json; charset=utf-8');

/* =========================================
 * CONFIG DB
 * ========================================= */
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";        // <-- ajusta
$DB_NAME = "coban365"; // <-- ajusta

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "DB_CONNECT_ERROR",
        "message" => "No se pudo conectar a la base de datos",
        "details" => $mysqli->connect_error,
    ]);
    exit;
}

// Forzar charset
$mysqli->set_charset("utf8mb4");

/* =========================================
 * HELPERS
 * ========================================= */
function json_response($arr, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($arr);
    exit;
}

/**
 * Lee body JSON de las peticiones POST
 */
function read_json_body()
{
    $raw = file_get_contents("php://input");
    if (!$raw)
        return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Obtiene (o crea si no existe) el registro de comisión para un tercero+corresponsal.
 */
function ensureCommissionRow($mysqli, $third_party_id, $correspondent_id)
{
    // 1) Buscar
    $sql = "SELECT * FROM third_party_commissions 
            WHERE third_party_id = ? AND correspondent_id = ? 
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt)
        return false;
    $stmt->bind_param("ii", $third_party_id, $correspondent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return $row;
    }
    $stmt->close();

    // 2) Crear con 0
    $sqlIns = "INSERT INTO third_party_commissions 
               (third_party_id, correspondent_id, total_commission, last_update)
               VALUES (?, ?, 0, NOW())";
    $stmt2 = $mysqli->prepare($sqlIns);
    if (!$stmt2)
        return false;
    $stmt2->bind_param("ii", $third_party_id, $correspondent_id);
    if (!$stmt2->execute()) {
        $stmt2->close();
        return false;
    }
    $insertedId = $stmt2->insert_id;
    $stmt2->close();

    // 3) Leer creada
    $sql2 = "SELECT * FROM third_party_commissions WHERE id = ? LIMIT 1";
    $stmt3 = $mysqli->prepare($sql2);
    if (!$stmt3)
        return false;
    $stmt3->bind_param("i", $insertedId);
    $stmt3->execute();
    $res2 = $stmt3->get_result();
    $row2 = $res2->fetch_assoc();
    $stmt3->close();

    return $row2;
}

/**
 * Forzar valor exacto.
 */
function setCommissionAmount($mysqli, $third_party_id, $correspondent_id, $newAmount)
{
    $sql = "UPDATE third_party_commissions 
            SET total_commission = ?, last_update = NOW()
            WHERE third_party_id = ? AND correspondent_id = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt)
        return false;
    $stmt->bind_param("dii", $newAmount, $third_party_id, $correspondent_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Sumar comisión.
 */
function addCommission($mysqli, $third_party_id, $correspondent_id, $amountToAdd)
{
    $sql = "UPDATE third_party_commissions
            SET total_commission = total_commission + ?, last_update = NOW()
            WHERE third_party_id = ? AND correspondent_id = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt)
        return false;
    $stmt->bind_param("dii", $amountToAdd, $third_party_id, $correspondent_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Restar comisión si hay saldo.
 */
function subtractCommission($mysqli, $third_party_id, $correspondent_id, $amountToSub)
{
    $sqlSel = "SELECT total_commission 
               FROM third_party_commissions
               WHERE third_party_id = ? AND correspondent_id = ?
               LIMIT 1";
    $stmtSel = $mysqli->prepare($sqlSel);
    if (!$stmtSel)
        return ["success" => false, "reason" => "SQL_PREPARE_ERROR"];
    $stmtSel->bind_param("ii", $third_party_id, $correspondent_id);
    $stmtSel->execute();
    $res = $stmtSel->get_result();
    $row = $res->fetch_assoc();
    $stmtSel->close();

    if (!$row)
        return ["success" => false, "reason" => "ROW_NOT_FOUND"];

    $current = floatval($row["total_commission"]);
    $amountToSub = floatval($amountToSub);

    if ($amountToSub <= 0)
        return ["success" => false, "reason" => "INVALID_AMOUNT"];
    if ($current <= 0)
        return ["success" => false, "reason" => "NO_FUNDS"];
    if ($amountToSub > $current)
        return ["success" => false, "reason" => "INSUFFICIENT_FUNDS", "available" => $current];

    $newTotal = $current - $amountToSub;
    if ($newTotal < 0)
        return ["success" => false, "reason" => "NEGATIVE_RESULT", "available" => $current];

    $sqlUp = "UPDATE third_party_commissions
              SET total_commission = ?, last_update = NOW()
              WHERE third_party_id = ? AND correspondent_id = ?
              LIMIT 1";
    $stmtUp = $mysqli->prepare($sqlUp);
    if (!$stmtUp)
        return ["success" => false, "reason" => "SQL_PREPARE_ERROR_UPDATE"];
    $stmtUp->bind_param("dii", $newTotal, $third_party_id, $correspondent_id);
    $ok = $stmtUp->execute();
    $stmtUp->close();

    if (!$ok)
        return ["success" => false, "reason" => "SQL_UPDATE_FAIL"];

    return ["success" => true, "new_total" => $newTotal];
}

/**
 * Eliminar relación.
 */
function deleteCommissionRow($mysqli, $third_party_id, $correspondent_id)
{
    $sql = "DELETE FROM third_party_commissions
            WHERE third_party_id = ? AND correspondent_id = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt)
        return false;
    $stmt->bind_param("ii", $third_party_id, $correspondent_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/* =========================================
 * ROUTER
 * ========================================= */
$action = isset($_GET["action"]) ? $_GET["action"] : (isset($_POST["action"]) ? $_POST["action"] : null);
$body = read_json_body();

// IDs (desde query o body)
$third_party_id = isset($_GET["third_party_id"]) ? intval($_GET["third_party_id"]) :
    (isset($body["third_party_id"]) ? intval($body["third_party_id"]) : 0);

$correspondent_id = isset($_GET["correspondent_id"]) ? intval($_GET["correspondent_id"]) :
    (isset($body["correspondent_id"]) ? intval($body["correspondent_id"]) : 0);

// Valor (para add/subtract/set)
$amount = isset($_GET["amount"]) ? floatval($_GET["amount"]) :
    (isset($body["amount"]) ? floatval($body["amount"]) : 0);

/* Sin acción -> error */
if (!$action) {
    json_response([
        "success" => false,
        "error" => "NO_ACTION",
        "message" => "Debes enviar ?action=..."
    ], 400);
}

/* ============================================================
 * NUEVO: sum_total
 *  - Suma total de comisiones de TODOS los terceros de un corresponsal.
 *  - SOLO requiere correspondent_id (>0).
 *  - Debe declararse ANTES de la validación global de IDs.
 * ============================================================ */
if ($action === "sum_total") {
    if ($correspondent_id <= 0) {
        json_response([
            "success" => false,
            "error" => "INVALID_CORRESPONDENT_ID",
            "message" => "correspondent_id es obligatorio y debe ser > 0."
        ], 400);
    }

    $sql = "SELECT COALESCE(SUM(total_commission), 0) AS total_commission
            FROM third_party_commissions
            WHERE correspondent_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        json_response([
            "success" => false,
            "error" => "SQL_PREPARE_ERROR",
            "message" => "No se pudo preparar la consulta."
        ], 500);
    }
    $stmt->bind_param("i", $correspondent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    json_response([
        "success" => true,
        "data" => [
            "total_commission" => isset($row["total_commission"]) ? floatval($row["total_commission"]) : 0.0
        ]
    ]);
}

/* Validación global para las demás acciones */
if ($third_party_id <= 0 || $correspondent_id <= 0) {
    json_response([
        "success" => false,
        "error" => "INVALID_IDS",
        "message" => "third_party_id y correspondent_id son obligatorios y deben ser > 0."
    ], 400);
}

/* =========================================
 * LÓGICA POR ACCIÓN (existentes)
 * ========================================= */
switch ($action) {
    case "get":
        $row = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        if ($row === false) {
            json_response([
                "success" => false,
                "error" => "SQL_ERROR",
                "message" => "Error consultando/creando la comisión."
            ], 500);
        }
        json_response(["success" => true, "data" => $row]);
        break;

    case "add":
        if ($amount <= 0) {
            json_response([
                "success" => false,
                "error" => "INVALID_AMOUNT",
                "message" => "amount debe ser > 0 para sumar comisión."
            ], 400);
        }
        $row = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        if ($row === false) {
            json_response([
                "success" => false,
                "error" => "SQL_ERROR",
                "message" => "No se pudo asegurar registro de comisión."
            ], 500);
        }
        $ok = addCommission($mysqli, $third_party_id, $correspondent_id, $amount);
        if (!$ok) {
            json_response([
                "success" => false,
                "error" => "ADD_FAIL",
                "message" => "No se pudo incrementar la comisión."
            ], 500);
        }
        $newRow = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        json_response([
            "success" => true,
            "message" => "Comisión incrementada correctamente.",
            "data" => $newRow
        ]);
        break;

    case "subtract":
        if ($amount <= 0) {
            json_response([
                "success" => false,
                "error" => "INVALID_AMOUNT",
                "message" => "amount debe ser > 0 para descontar comisión."
            ], 400);
        }
        $row = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        if ($row === false) {
            json_response([
                "success" => false,
                "error" => "SQL_ERROR",
                "message" => "No se pudo asegurar registro de comisión."
            ], 500);
        }
        $subRes = subtractCommission($mysqli, $third_party_id, $correspondent_id, $amount);
        if (!$subRes["success"]) {
            $reason = $subRes["reason"];
            if (in_array($reason, ["NO_FUNDS", "INSUFFICIENT_FUNDS", "NEGATIVE_RESULT"], true)) {
                json_response([
                    "success" => false,
                    "error" => $reason,
                    "message" => "Fondos insuficientes en la comisión para descontar ese valor.",
                    "details" => $subRes
                ], 400);
            }
            json_response([
                "success" => false,
                "error" => $reason,
                "message" => "No se pudo descontar la comisión.",
                "details" => $subRes
            ], 500);
        }
        $newRow = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        json_response([
            "success" => true,
            "message" => "Comisión descontada correctamente.",
            "data" => $newRow
        ]);
        break;

    case "set":
        if ($amount < 0) {
            json_response([
                "success" => false,
                "error" => "INVALID_AMOUNT",
                "message" => "amount no puede ser negativo."
            ], 400);
        }
        $row = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        if ($row === false) {
            json_response([
                "success" => false,
                "error" => "SQL_ERROR",
                "message" => "No se pudo asegurar registro de comisión."
            ], 500);
        }
        $ok = setCommissionAmount($mysqli, $third_party_id, $correspondent_id, $amount);
        if (!$ok) {
            json_response([
                "success" => false,
                "error" => "SET_FAIL",
                "message" => "No se pudo actualizar la comisión."
            ], 500);
        }
        $newRow = ensureCommissionRow($mysqli, $third_party_id, $correspondent_id);
        json_response([
            "success" => true,
            "message" => "Comisión actualizada correctamente.",
            "data" => $newRow
        ]);
        break;

    case "delete":
        $ok = deleteCommissionRow($mysqli, $third_party_id, $correspondent_id);
        if (!$ok) {
            json_response([
                "success" => false,
                "error" => "DELETE_FAIL",
                "message" => "No se pudo eliminar (o no existía)."
            ], 500);
        }
        json_response([
            "success" => true,
            "message" => "Registro de comisión eliminado."
        ]);
        break;

    default:
        json_response([
            "success" => false,
            "error" => "INVALID_ACTION",
            "message" => "Acción no soportada."
        ], 400);
        break;
}
