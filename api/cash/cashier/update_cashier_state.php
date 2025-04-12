<?php
/**
 * Archivo: update_cashier_state.php
 * Descripción: Actualiza el estado (activo/inactivo) de un cajero en la tabla `users`.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 10-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitud preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la conexión a la base de datos
require_once '../../db.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar datos requeridos
if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data['id']);
$status = intval($data['status']); // 0 o 1

try {
    // Actualizar estado solo si el usuario es cajero
    $stmt = $pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id AND role = 'cajero'");
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Estado del cajero actualizado correctamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "No se encontró el cajero o el estado ya estaba asignado"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
