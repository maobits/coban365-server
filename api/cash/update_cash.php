<?php
/**
 * Archivo: update_cash.php
 * Descripción: Permite actualizar los datos de una caja (cash).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 31-Mar-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir conexión a la base de datos
require_once '../db.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
if (
    !isset($data['id']) ||
    !isset($data['correspondent_id']) ||
    !isset($data['cashier_id']) ||
    !isset($data['capacity']) ||
    !isset($data['state'])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit();
}

$id = intval($data['id']);
$correspondent_id = intval($data['correspondent_id']);
$cashier_id = intval($data['cashier_id']);
$capacity = floatval($data['capacity']);
$state = intval($data['state']);

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta de actualización
    $sql = "UPDATE cash SET 
                correspondent_id = :correspondent_id,
                cashier_id = :cashier_id,
                capacity = :capacity,
                state = :state,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':correspondent_id' => $correspondent_id,
        ':cashier_id' => $cashier_id,
        ':capacity' => $capacity,
        ':state' => $state,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Caja actualizada correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o la caja no existe."]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la actualización: " . $e->getMessage()
    ]);
}
