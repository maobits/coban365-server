<?php
/**
 * Archivo: update_rate.php
 * Descripción: Permite actualizar los datos de una tarifa (rate).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 12-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Conexión a la base de datos
require_once '../db.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos del cuerpo
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos
if (
    !isset($data['id']) ||
    !isset($data['transaction_type_id']) ||
    !isset($data['price']) ||
    !isset($data['correspondent_id'])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit();
}

$id = intval($data['id']);
$transaction_type_id = intval($data['transaction_type_id']);
$price = floatval($data['price']);
$correspondent_id = intval($data['correspondent_id']);

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE rates SET 
                transaction_type_id = :transaction_type_id,
                price = :price,
                correspondent_id = :correspondent_id,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':transaction_type_id' => $transaction_type_id,
        ':price' => $price,
        ':correspondent_id' => $correspondent_id,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Tarifa actualizada correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o la tarifa no existe."]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la actualización: " . $e->getMessage()
    ]);
}
