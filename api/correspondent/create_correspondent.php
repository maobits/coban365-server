<?php
/**
 * Archivo: create_correspondent.php
 * Descripción: Permite registrar un nuevo corresponsal en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.1
 * Fecha de actualización: 14-May-2025
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
    if (!isset($data["type_id"], $data["code"], $data["operator_id"], $data["name"], $data["location"])) {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
        exit;
    }

    $type_id = intval($data["type_id"]);
    $code = trim($data["code"]);
    $operator_id = intval($data["operator_id"]);
    $name = trim($data["name"]);
    $location = json_encode($data["location"], JSON_UNESCAPED_UNICODE); // Convertir ubicación a JSON
    $transactions = isset($data["transactions"]) ? json_encode($data["transactions"], JSON_UNESCAPED_UNICODE) : json_encode([]); // Transacciones como JSON
    $credit_limit = isset($data["credit_limit"]) ? intval($data["credit_limit"]) : 0;

    try {
        // Verificar si el código ya existe
        $checkStmt = $pdo->prepare("SELECT id FROM correspondents WHERE code = :code");
        $checkStmt->bindParam(":code", $code, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El código del corresponsal ya existe."]);
            exit;
        }

        // Insertar el nuevo corresponsal incluyendo credit_limit
        $stmt = $pdo->prepare("INSERT INTO correspondents (type_id, code, operator_id, name, location, transactions, credit_limit) 
                               VALUES (:type_id, :code, :operator_id, :name, :location, :transactions, :credit_limit)");
        $stmt->bindParam(":type_id", $type_id, PDO::PARAM_INT);
        $stmt->bindParam(":code", $code, PDO::PARAM_STR);
        $stmt->bindParam(":operator_id", $operator_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        $stmt->bindParam(":transactions", $transactions, PDO::PARAM_STR);
        $stmt->bindParam(":credit_limit", $credit_limit, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Corresponsal registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el corresponsal."]);
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