<?php
/**
 * Archivo: list_shift.php
 * Descripción: Devuelve todos los turnos registrados para un corresponsal y caja específicos.
 * Marca como expirados los turnos con más de 3 días de antigüedad antes de la consulta.
 * Proyecto: COBAN365
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["correspondent_id"], $data["cash_id"])) {
        echo json_encode(["success" => false, "message" => "Faltan parámetros obligatorios."]);
        exit;
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $cash_id = intval($data["cash_id"]);

    // 🧹 Marcar como expirados los turnos con más de 3 días
    $stmtExpire = $pdo->prepare("
        UPDATE shifts
        SET expired = 1
        WHERE created_at < (NOW() - INTERVAL 3 DAY)
    ");
    $stmtExpire->execute();

    // 🔎 Consultar turnos actuales (no expirados) ordenados del más antiguo al más reciente
    $stmt = $pdo->prepare("
        SELECT 
            id,
            correspondent_id,
            cash_id,
            transaction_type,
            amount,
            agreement,
            reference,
            full_name,
            document_id,
            phone,
            email,
            created_at,
            state,
            expired
        FROM shifts
        WHERE correspondent_id = :correspondent_id AND cash_id = :cash_id AND expired = 0
        ORDER BY created_at ASC
    ");
    $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
    $stmt->bindParam(":cash_id", $cash_id, PDO::PARAM_INT);
    $stmt->execute();

    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $shifts,
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage(),
    ]);
}
