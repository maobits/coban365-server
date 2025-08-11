<?php
/**
 * -----------------------------------------------------------------------------
 * Personalizado por: Maobits NIT 1061740164
 * Desde: 10-Ago-2025
 *
 * Desarrollador responsable de la personalización y continuidad del proyecto:
 * Mauricio Chara (https://www.instagram.com/maobits.io)
 *
 * Contacto para soporte:
 * Correo electrónico : code@maobits.com
 * WhatsApp           : +57 3153774638
 * País               : Colombia
 *
 * Descripción del archivo:
 * Endpoint que registra el cierre de caja guardando el detalle del conteo
 * (billetes, fajos, monedas) en la tabla `cash_balance` y, adicionalmente,
 * inserta un registro simple en `cash_closing_register`.
 * Valida que la caja no haya sido cerrada hoy y marca la caja como cerrada
 * (open = 0) en la tabla `cash`.
 *
 * Versión actual: 1.3.1
 * Fecha última revisión: 11-Ago-2025
 * -----------------------------------------------------------------------------
 */

date_default_timezone_set('America/Bogota');

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../db.php"; // <- mantener tu ruta actual

function fail($msg, $code = 400, $extra = null)
{
    http_response_code($code);
    $out = ["success" => false, "message" => $msg];
    if ($extra !== null)
        $out["data"] = $extra;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fail("Método no permitido. Usa POST.", 405);
}

$raw = file_get_contents("php://input");
if (!$raw)
    fail("Body vacío.");

$input = json_decode($raw, true);
if (!is_array($input))
    fail("JSON inválido.");

/**
 * Espera (mínimo):
 *  - correspondent_id (int)
 *  - cash_id (int)
 *  - cashier_id (int)
 *  - details (objeto JSON completo del conteo)
 * Opcional:
 *  - balance_date (YYYY-MM-DD)
 *  - balance_time (HH:MM:SS)
 *  - totals.* si ya los calculaste en frontend
 *  - note (string)
 *  - frozen_box (0|1) -> por defecto 1 (congelar caja al cerrar)
 */

$correspondent_id = intval($input["correspondent_id"] ?? 0);
$cash_id = intval($input["cash_id"] ?? 0);
$cashier_id = intval($input["cashier_id"] ?? 0);
$details = $input["details"] ?? null;
$note = trim($input["note"] ?? "");

// NUEVO: flag para congelar caja (default 1)
$frozen_box = isset($input["frozen_box"]) ? (int) !!$input["frozen_box"] : 1;

if ($correspondent_id <= 0 || $cash_id <= 0 || $cashier_id <= 0) {
    fail("Faltan IDs requeridos: correspondent_id, cash_id, cashier_id.");
}
if (!is_array($details)) {
    fail("El campo 'details' debe ser un objeto JSON con header/sections/subtotals/totals.");
}

$balance_date = $input["balance_date"] ?? date("Y-m-d");
$balance_time = $input["balance_time"] ?? date("H:i:s");

// === Validación: ¿la caja ya está cerrada hoy? ===
try {
    $checkSql = "SELECT id, closing_time, closed_by, note
                 FROM cash_closing_register
                 WHERE cash_id = :cash_id AND closing_date = :closing_date
                 LIMIT 1";
    $chk = $pdo->prepare($checkSql);
    $chk->execute([
        ":cash_id" => $cash_id,
        ":closing_date" => $balance_date
    ]);
    $already = $chk->fetch(PDO::FETCH_ASSOC);

    if ($already) {
        fail(
            "La caja ya fue cerrada hoy. Operación cancelada.",
            409,
            [
                "cash_id" => $cash_id,
                "closing_register_id" => (int) $already["id"],
                "closing_date" => $balance_date,
                "closing_time" => $already["closing_time"],
                "closed_by" => (int) $already["closed_by"],
                "note" => $already["note"]
            ]
        );
    }
} catch (Throwable $e) {
    fail("Error al validar cierre previo: " . $e->getMessage(), 500);
}

// ---- Calcular totales si no vienen completos ----
$totals = $details["totals"] ?? [];
$subtotals = $details["subtotals"] ?? [];

$calcBills = 0;
$calcBundles = 0;
$calcCoins = 0;

// Bills
if (isset($details["sections"]["bills"]) && is_array($details["sections"]["bills"])) {
    foreach ($details["sections"]["bills"] as $row) {
        $den = floatval($row["denom"] ?? 0);
        $cnt = floatval($row["count"] ?? 0);
        $calcBills += ($row["subtotal"] ?? $den * $cnt);
    }
}

// Bundles (fajos)
if (isset($details["sections"]["bundles"]) && is_array($details["sections"]["bundles"])) {
    foreach ($details["sections"]["bundles"] as $row) {
        $den = floatval($row["denom"] ?? 0);
        $upb = intval($row["units_per_bundle"] ?? 100);
        $cnt = floatval($row["count"] ?? 0);
        $calcBundles += ($row["subtotal"] ?? ($den * $upb * $cnt));
    }
}

// Coins
if (isset($details["sections"]["coins"]) && is_array($details["sections"]["coins"])) {
    foreach ($details["sections"]["coins"] as $row) {
        $den = floatval($row["denom"] ?? 0);
        $cnt = floatval($row["count"] ?? 0);
        $calcCoins += ($row["subtotal"] ?? $den * $cnt);
    }
}

// Subtotales (si no venían, asignarlos)
$subtotals["bills"] = isset($subtotals["bills"]) ? floatval($subtotals["bills"]) : $calcBills;
$subtotals["bundles"] = isset($subtotals["bundles"]) ? floatval($subtotals["bundles"]) : $calcBundles;
$subtotals["coins"] = isset($subtotals["coins"]) ? floatval($subtotals["coins"]) : $calcCoins;

// Total efectivo contado
$total_effective = isset($totals["total_effective"])
    ? floatval($totals["total_effective"])
    : ($subtotals["bills"] + $subtotals["bundles"] + $subtotals["coins"]);

// Caja actual “esperada” (trae del frontend o 0)
$current_cash = isset($totals["current_cash"]) ? floatval($totals["current_cash"]) : 0.0;

// Diferencia: contado - esperado
$diff_amount = $total_effective - $current_cash;
$diff_status = "OK";
if ($diff_amount > 0.00001)
    $diff_status = "SOBRANTE";
if ($diff_amount < -0.00001)
    $diff_status = "FALTANTE";

// Actualizar el objeto details para guardar exactamente lo registrado
$details["subtotals"] = $subtotals;
$details["totals"]["total_effective"] = $total_effective;
$details["totals"]["current_cash"] = $current_cash;
$details["totals"]["balance"] = $diff_amount;
$details["totals"]["abs_diff"] = abs($diff_amount);
$details["totals"]["message"] = ($diff_status === "FALTANTE"
    ? "Faltante en caja"
    : ($diff_status === "SOBRANTE" ? "Sobrante en caja" : "Cuadre OK"));

// Serializar JSON
$detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

try {
    $pdo->beginTransaction();

    // 1) Insert en cash_balance  (✳️ incluye frozen_box)
    $sql = "INSERT INTO cash_balance
            (correspondent_id, cash_id, cashier_id,
             balance_date, balance_time,
             details,
             total_bills, total_bundles, total_coins,
             total_effective, current_cash, diff_amount, diff_status,
             frozen_box,          -- NUEVO
             note)
          VALUES
            (:correspondent_id, :cash_id, :cashier_id,
             :balance_date, :balance_time,
             :details,
             :total_bills, :total_bundles, :total_coins,
             :total_effective, :current_cash, :diff_amount, :diff_status,
             :frozen_box,         -- NUEVO
             :note)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":correspondent_id" => $correspondent_id,
        ":cash_id" => $cash_id,
        ":cashier_id" => $cashier_id,
        ":balance_date" => $balance_date,
        ":balance_time" => $balance_time,
        ":details" => $detailsJson,
        ":total_bills" => $subtotals["bills"],
        ":total_bundles" => $subtotals["bundles"],
        ":total_coins" => $subtotals["coins"],
        ":total_effective" => $total_effective,
        ":current_cash" => $current_cash,
        ":diff_amount" => $diff_amount,
        ":diff_status" => $diff_status,
        ":frozen_box" => $frozen_box, // NUEVO
        ":note" => $note ?: $details["totals"]["message"],
    ]);

    $cashBalanceId = (int) $pdo->lastInsertId();

    // 2) Insert en cash_closing_register (registro simple del cierre)
    $sql2 = "INSERT INTO cash_closing_register
                (cash_id, closing_date, closing_time, closed_by, note)
             VALUES
                (:cash_id, :closing_date, :closing_time, :closed_by, :note)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([
        ":cash_id" => $cash_id,
        ":closing_date" => $balance_date,
        ":closing_time" => $balance_time,
        ":closed_by" => $cashier_id,
        ":note" => $note ?: $details["totals"]["message"],
    ]);

    $closingRegisterId = (int) $pdo->lastInsertId();

    // 3) Marcar la caja como cerrada (open = 0) y guardar la nota
    $sql3 = "UPDATE cash
                SET open = 0,
                    last_note = :last_note,
                    updated_at = NOW()
             WHERE id = :cash_id
             LIMIT 1";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([
        ":last_note" => $note ?: $details["totals"]["message"],
        ":cash_id" => $cash_id,
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Cierre de caja registrado.",
        "data" => [
            "cash_balance_id" => $cashBalanceId,
            "cash_closing_register_id" => $closingRegisterId,
            "diff_status" => $diff_status,
            "diff_amount" => $diff_amount,
            "total_effective" => $total_effective,
            "current_cash" => $current_cash,
            "frozen_box" => $frozen_box  // devolución para UI
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    fail("Error al guardar: " . $e->getMessage(), 500);
}
