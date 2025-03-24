<?php
/**
 * Archivo: update_transaction_type.php
 * Descripción: Actualiza un tipo de transacción en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 23-Mar-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de solicitudes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de la base de datos
require_once '../db.php';

// Verificar tipo de solicitud
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Capturar y decodificar los datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
if (
    !isset($data['id']) ||
    !isset($data['name']) ||
    !isset($data['category'])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

try {
    // Conexión
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta SQL para actualizar el tipo de transacción (sin descripción)
    $sql = "UPDATE transaction_types SET 
                name = :name,
                category = :category,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":id" => $data["id"],
        ":name" => trim($data["name"]),
        ":category" => trim($data["category"]),
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Tipo de transacción actualizado exitosamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o el registro no existe"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la actualización: " . $e->getMessage()]);
}
?>