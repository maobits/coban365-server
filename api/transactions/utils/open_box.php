<?php
/**
 * Abre la caja (open = 1) solo si HOY no está registrada como cerrada
 * en cash_closing_register para ese cash_id.
 */

date_default_timezone_set('America/Bogota'); // ajusta si tu servidor usa otra zona

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../../db.php";

$respond = function (array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
};

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $respond(["success" => false, "message" => "Método no permitido. Usa POST."], 405);
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!isset($data["id_cash"]) || !is_numeric($data["id_cash"])) {
        $respond(["success" => false, "message" => "Falta el parámetro id_cash."], 400);
    }

    $id_cash = (int) $data["id_cash"];

    // 1) Validar contra el registro de cierres del día (fecha del servidor)
    //    Bloquea si hay un cierre HOY para esa caja.
    $sqlCheck = "
    SELECT 1
    FROM cash_closing_register
    WHERE cash_id = :id_cash
      AND closing_date = CURDATE()  -- fecha del servidor
    LIMIT 1
  ";
    $st = $pdo->prepare($sqlCheck);
    $st->execute([":id_cash" => $id_cash]);

    if ($st->fetchColumn()) {
        $respond([
            "success" => false,
            "message" => "Apertura no permitida: la caja ya cuenta con un cierre registrado en la fecha actual del sistema."
        ], 200);
    }

    // 2) Abrir solo si estaba cerrada
    $sqlOpen = "UPDATE cash SET `open` = 1 WHERE id = :id_cash AND `open` = 0 LIMIT 1";
    $up = $pdo->prepare($sqlOpen);
    $up->execute([":id_cash" => $id_cash]);

    if ($up->rowCount() > 0) {
        $respond([
            "success" => true,
            "message" => "Caja abierta correctamente.",
            "id_cash" => $id_cash
        ], 200);
    }

    // No cambió nada: o no existe, o ya estaba abierta.
    $respond([
        "success" => false,
        "message" => "No se pudo abrir: la caja no existe o ya estaba abierta."
    ], 200);

} catch (Throwable $e) {
    $respond([
        "success" => false,
        "message" => "Error interno al abrir la caja.",
        "detail" => $e->getMessage() // útil para debug; quítalo si no quieres exponerlo
    ], 500);
}
