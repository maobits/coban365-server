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
 * Registra un cierre de caja en la tabla `cash_closing_register` con fecha,
 * hora, id de caja, usuario y nota opcional.
 *
 * Versión actual: 1.0.0
 * Fecha última revisión: 10-Ago-2025
 * -----------------------------------------------------------------------------
 */

date_default_timezone_set('America/Bogota');

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// OPTIONS para preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "./../../db.php"; // Ajusta la ruta según tu estructura

function fail($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $msg], JSON_UNESCAPED_UNICODE);
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

// Validar campos requeridos
$cash_id = intval($input["cash_id"] ?? 0);
$closed_by = intval($input["closed_by"] ?? 0);
$note = trim($input["note"] ?? "");

if ($cash_id <= 0) {
    fail("Falta el parámetro obligatorio: cash_id.");
}
if ($closed_by <= 0) {
    fail("Falta el parámetro obligatorio: closed_by (usuario que cierra la caja).");
}

// Fecha y hora actuales (puedes permitir que vengan en el JSON si lo deseas)
$closing_date = date("Y-m-d");
$closing_time = date("H:i:s");

try {
    $sql = "INSERT INTO cash_closing_register 
                (cash_id, closing_date, closing_time, closed_by, note) 
            VALUES 
                (:cash_id, :closing_date, :closing_time, :closed_by, :note)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":cash_id" => $cash_id,
        ":closing_date" => $closing_date,
        ":closing_time" => $closing_time,
        ":closed_by" => $closed_by,
        ":note" => $note ?: null
    ]);

    $insertId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Cierre de caja registrado correctamente.",
        "data" => [
            "id" => intval($insertId),
            "cash_id" => $cash_id,
            "closing_date" => $closing_date,
            "closing_time" => $closing_time,
            "closed_by" => $closed_by,
            "note" => $note
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    fail("Error al guardar: " . $e->getMessage(), 500);
}
