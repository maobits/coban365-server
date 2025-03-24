<?php
/**
 * Archivo: create_transaction_type.php
 * Descripción: Permite registrar un nuevo tipo de transacción en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 23-Mar-2025
 */

// Permitir solicitudes desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Maneja solicitudes OPTIONS (Preflight Request)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Verificar que la solicitud sea de tipo POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capturar los datos enviados en la solicitud
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que los datos requeridos fueron enviados
    if (!isset($data["category"], $data["name"])) {
        echo json_encode(["success" => false, "message" => "Los campos 'category' y 'name' son obligatorios."]);
        exit;
    }

    $category = trim($data["category"]);
    $name = trim($data["name"]);

    try {
        // Verificar si el tipo de transacción ya existe
        $checkStmt = $pdo->prepare("SELECT id FROM transaction_types WHERE category = :category AND name = :name");
        $checkStmt->bindParam(":category", $category, PDO::PARAM_STR);
        $checkStmt->bindParam(":name", $name, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El tipo de transacción ya existe."]);
            exit;
        }

        // Insertar el nuevo tipo de transacción
        $stmt = $pdo->prepare("INSERT INTO transaction_types (category, name) VALUES (:category, :name)");
        $stmt->bindParam(":category", $category, PDO::PARAM_STR);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Tipo de transacción registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el tipo de transacción."]);
        }
    } catch (PDOException $e) {
        // Manejo de errores en la base de datos
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    // Si no es una solicitud POST, rechazar la petición
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>