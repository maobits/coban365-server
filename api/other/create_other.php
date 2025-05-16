<?php
/**
 * Archivo: create_other.php
 * Descripción: Permite registrar un nuevo tercero en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 15-May-2025
 */

// Permitir solicitudes desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitudes OPTIONS (Preflight)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Verificar que la solicitud sea de tipo POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar campos obligatorios mínimos
    if (!isset($data["correspondent_id"], $data["name"], $data["credit"], $data["id_type"], $data["id_number"])) {
        echo json_encode(["success" => false, "message" => "Faltan campos obligatorios."]);
        exit;
    }

    // Sanitización y validación básica
    $correspondent_id = intval($data["correspondent_id"]);
    $name = trim($data["name"]);
    $credit = floatval($data["credit"]);
    $state = isset($data["state"]) ? intval($data["state"]) : 1;

    $id_type = trim($data["id_type"]);
    $id_number = trim($data["id_number"]);
    $email = isset($data["email"]) ? trim($data["email"]) : null;
    $phone = isset($data["phone"]) ? trim($data["phone"]) : null;
    $address = isset($data["address"]) ? trim($data["address"]) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO others (
                correspondent_id, name, id_type, id_number, email, phone, address, credit, state
            ) VALUES (
                :correspondent_id, :name, :id_type, :id_number, :email, :phone, :address, :credit, :state
            )
        ");

        $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":id_type", $id_type, PDO::PARAM_STR);
        $stmt->bindParam(":id_number", $id_number, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
        $stmt->bindParam(":address", $address, PDO::PARAM_STR);
        $stmt->bindParam(":credit", $credit);
        $stmt->bindParam(":state", $state, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Tercero registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el tercero."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>