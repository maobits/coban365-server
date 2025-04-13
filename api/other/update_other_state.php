<?php
/**
 * Archivo: update_other_state.php
 * Descripción: Actualiza el estado lógico (activo/inactivo) de un tercero (others).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 12-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejo de preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir conexión a la base de datos
require_once "../db.php";

header("Content-Type: application/json");

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
if (!isset($data["id"]) || !isset($data["state"])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data["id"]);
$state = intval($data["state"]); // 0 o 1

try {
    $stmt = $pdo->prepare("UPDATE others SET state = :state, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":state", $state, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Estado del tercero actualizado correctamente"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el tercero o el estado ya estaba asignado"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
