<?php
/**
 * ============================================================
 * API: Descontar comisión acumulada (pago del tercero)
 * ------------------------------------------------------------
 * Endpoint: POST /api/transactions/utils/third_party_paymentcommission_table.php
 * ------------------------------------------------------------
 * Autor: Mauricio Chara / BITSCORE
 * Fecha: <?= date("Y-m-d") ?>
 * ============================================================
 */

date_default_timezone_set('America/Bogota');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight (CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Conexión base de datos
require_once "../../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

// Leer JSON
$input = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
$required = ["third_party_id", "correspondent_id", "amount"];
foreach ($required as $key) {
    if (!isset($input[$key])) {
        echo json_encode([
            "success" => false,
            "message" => "Falta el campo obligatorio: $key"
        ]);
        exit;
    }
}

// Normalizar y validar
$third_party_id = (int) $input["third_party_id"];
$correspondent_id = (int) $input["correspondent_id"];
$amount = (float) $input["amount"];

if ($third_party_id <= 0 || $correspondent_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "IDs inválidos."
    ]);
    exit;
}

if ($amount <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "El monto a descontar debe ser mayor que 0."
    ]);
    exit;
}

try {
    // 1️⃣ Buscar registro actual
    $stmt = $pdo->prepare("
        SELECT id, total_commission
        FROM third_party_commissions
        WHERE third_party_id = :t
          AND correspondent_id = :c
        LIMIT 1
    ");
    $stmt->execute([
        ":t" => $third_party_id,
        ":c" => $correspondent_id
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            "success" => false,
            "message" => "No existe registro de comisión para este tercero/corresponsal."
        ]);
        exit;
    }

    $current = (float) $row["total_commission"];

    // 2️⃣ Validar saldo disponible
    if ($current <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "No hay comisiones acumuladas para descontar."
        ]);
        exit;
    }

    if ($amount > $current) {
        echo json_encode([
            "success" => false,
            "message" => "El monto ($amount) excede la comisión acumulada actual ($current)."
        ]);
        exit;
    }

    // 3️⃣ Ejecutar UPDATE
    $newTotal = $current - $amount;

    $upd = $pdo->prepare("
        UPDATE third_party_commissions
        SET total_commission = :newTotal,
            last_update = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ":newTotal" => $newTotal,
        ":id" => $row["id"]
    ]);

    // 4️⃣ Respuesta final
    echo json_encode([
        "success" => true,
        "message" => "Comisión descontada correctamente.",
        "data" => [
            "third_party_id" => $third_party_id,
            "correspondent_id" => $correspondent_id,
            "previous_total" => $current,
            "discounted" => $amount,
            "new_total" => $newTotal,
            "timestamp" => date("Y-m-d H:i:s")
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
