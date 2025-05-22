<?php
/**
 * Archivo: initial_box_configuration.php
 * Descripción: Actualiza el valor inicial en la caja (sin registrar transacción).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.4
 * Fecha: 19-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../../db.php";

$data = json_decode(file_get_contents("php://input"), true);

// Validación de parámetros
if (!isset($data["id_cash"]) || !isset($data["cost"])) {
    echo json_encode([
        "success" => false,
        "message" => "Faltan campos obligatorios: id_cash o cost."
    ]);
    exit();
}

$id_cash = intval($data["id_cash"]);
$cost = floatval($data["cost"]);

try {
    // Verificar si la caja existe
    $stmt = $pdo->prepare("SELECT id FROM cash WHERE id = :id_cash LIMIT 1");
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetch()) {
        echo json_encode([
            "success" => false,
            "message" => "Caja no encontrada."
        ]);
        exit();
    }

    // Actualizar el monto inicial
    $updateStmt = $pdo->prepare("UPDATE cash SET initial_amount = :cost WHERE id = :id_cash");
    $updateStmt->bindParam(":cost", $cost);
    $updateStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Monto inicial actualizado correctamente."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se pudo actualizar el monto inicial."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
?>