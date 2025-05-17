<?php
/**
 * Archivo: update_cash.php
 * Descripción: Permite actualizar los datos de una caja (cash).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.2
 * Fecha de actualización: 17-May-2025
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

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Incluir conexión a la base de datos
require_once '../db.php';

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
$requiredFields = ['id', 'correspondent_id', 'cashier_id', 'capacity', 'state', 'open', 'name'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["success" => false, "message" => "El campo '$field' es obligatorio."]);
        exit();
    }
}

// Asignar y sanitizar valores
$id = intval($data['id']);
$correspondent_id = intval($data['correspondent_id']);
$cashier_id = intval($data['cashier_id']);
$capacity = floatval($data['capacity']);
$state = intval($data['state']);
$open = intval($data['open']);
$name = trim($data['name']);
$last_note = isset($data['last_note']) ? trim($data['last_note']) : null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta de actualización
    $sql = "UPDATE cash SET 
                correspondent_id = :correspondent_id,
                cashier_id = :cashier_id,
                name = :name,
                capacity = :capacity,
                state = :state,
                open = :open,
                last_note = :last_note,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':correspondent_id' => $correspondent_id,
        ':cashier_id' => $cashier_id,
        ':name' => $name,
        ':capacity' => $capacity,
        ':state' => $state,
        ':open' => $open,
        ':last_note' => $last_note,
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
?>