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
 * Endpoint que consulta el registro de cuadre de caja (tabla `cash_balance`)
 * por fecha (YYYY-MM-DD) e ID de caja. Si hay varios registros en el mismo día,
 * retorna el más reciente (por balance_time, created_at, id).
 *
 * Cambios (1.3.1):
 * - Se incluye el campo `frozen_box` en el SELECT y en la respuesta.
 *
 * Versión actual: 1.3.1
 * Fecha última revisión: 11-Ago-2025
 * -----------------------------------------------------------------------------
 */

date_default_timezone_set('America/Bogota');

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../db.php"; // Debe exponer $pdo (PDO)

function fail($msg, $code = 400, $extra = null)
{
    http_response_code($code);
    $out = ["success" => false, "message" => $msg];
    if ($extra !== null)
        $out["data"] = $extra;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    fail("Método no permitido. Usa GET.", 405);
}

// Validar conexión PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    fail("Conexión a base de datos no inicializada (\$pdo). Verifica db.php.", 500);
}

// Parámetros
$id_cash = isset($_GET["id_cash"]) ? intval($_GET["id_cash"]) : 0;
$date = isset($_GET["date"]) ? trim($_GET["date"]) : date("Y-m-d"); // YYYY-MM-DD

if ($id_cash <= 0) {
    fail("Falta el parámetro obligatorio id_cash (entero > 0).");
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fail("El parámetro date debe tener formato YYYY-MM-DD.");
}

try {
    // Contar registros del día (diagnóstico)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM cash_balance
        WHERE cash_id = :cash_id AND balance_date = :balance_date
    ");
    $countStmt->execute([
        ":cash_id" => $id_cash,
        ":balance_date" => $date
    ]);
    $dayCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)["cnt"] ?? 0);

    // Traer el último registro de ese día (incluye frozen_box)
    $stmt = $pdo->prepare("
        SELECT
            id,
            correspondent_id,
            cash_id,
            cashier_id,
            balance_date,
            balance_time,
            details,
            total_bills,
            total_bundles,
            total_coins,
            total_effective,
            current_cash,
            diff_amount,
            diff_status,
            frozen_box,   -- 👈 NUEVO
            note,
            created_at,
            updated_at
        FROM cash_balance
        WHERE cash_id = :cash_id
          AND balance_date = :balance_date
        ORDER BY
            COALESCE(balance_time, '00:00:00') DESC,
            created_at DESC,
            id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ":cash_id" => $id_cash,
        ":balance_date" => $date
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            "success" => true,
            "message" => "Registro encontrado.",
            "data" => $row, // 'details' se retorna como string JSON (tal cual fue guardado)
            "meta" => [
                "id_cash" => $id_cash,
                "date" => $date,
                "day_count" => $dayCount
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sin registros
    echo json_encode([
        "success" => true,
        "message" => "No hay cuadre registrado para esa caja en la fecha indicada.",
        "data" => null,
        "meta" => [
            "id_cash" => $id_cash,
            "date" => $date,
            "day_count" => $dayCount
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    fail("Error del servidor: " . $e->getMessage(), 500);
}
