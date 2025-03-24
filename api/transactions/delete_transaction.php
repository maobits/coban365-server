<?php
/**
 * Archivo: delete_transaction_type.php
 * Descripción: Elimina un tipo de transacción por su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 23-Mar-2025
 */

// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Si la solicitud es de tipo OPTIONS (preflight), responder con 200 y salir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la configuración de la base de datos
require_once '../db.php';

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Leer el JSON recibido
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se recibió el ID del tipo de transacción
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(["success" => false, "message" => "ID del tipo de transacción requerido"]);
    exit();
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para eliminar el tipo de transacción por ID
    $sql = "DELETE FROM transaction_types WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->execute();

    // Verificar si se eliminó alguna fila
    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Tipo de transacción eliminado correctamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "El tipo de transacción no existe o ya fue eliminado"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error al eliminar: " . $e->getMessage()]);
}
?>