<?php
/**
 * Archivo: create_transaction_type.php
 * Descripción: Permite registrar un nuevo tipo de transacción en la base de datos, incluyendo la polaridad.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.1
 * Fecha de actualización: 27-May-2025
 */

// Permitir solicitudes desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitudes OPTIONS (Preflight Request)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Verificar que la solicitud sea de tipo POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que los campos requeridos estén presentes
    if (!isset($data["category"], $data["name"], $data["polarity"])) {
        echo json_encode([
            "success" => false,
            "message" => "Los campos 'category', 'name' y 'polarity' son obligatorios."
        ]);
        exit;
    }

    $category = trim($data["category"]);
    $name = trim($data["name"]);
    $polarity = boolval($data["polarity"]); // Asegura valor booleano

    try {
        // Verificar si ya existe
        $checkStmt = $pdo->prepare("SELECT id FROM transaction_types WHERE category = :category AND name = :name");
        $checkStmt->bindParam(":category", $category, PDO::PARAM_STR);
        $checkStmt->bindParam(":name", $name, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El tipo de transacción ya existe."]);
            exit;
        }

        // Insertar nuevo tipo de transacción
        $stmt = $pdo->prepare("
            INSERT INTO transaction_types (category, name, polarity)
            VALUES (:category, :name, :polarity)
        ");
        $stmt->bindParam(":category", $category, PDO::PARAM_STR);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":polarity", $polarity, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Tipo de transacción registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el tipo de transacción."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>