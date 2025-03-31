<?php
// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Responder a preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la conexión a la base de datos
require_once '../db.php';

header('Content-Type: application/json');

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener los datos enviados por el cliente
$data = json_decode(file_get_contents("php://input"), true);

// Validar que el ID y el nuevo estado estén presentes
if (!isset($data['id']) || !isset($data['state'])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

$id = intval($data['id']);
$state = intval($data['state']); // 0 o 1

try {
    // Preparar y ejecutar la consulta de actualización
    $stmt = $pdo->prepare("UPDATE correspondents SET state = :state, updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(":state", $state, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Estado actualizado correctamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "No se encontró el corresponsal o el estado ya estaba asignado"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
?>