<?php
/**
 * Archivo: create_cash.php
 * Descripción: Permite registrar una nueva caja (cash) en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 31-Mar-2025
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

    // Validación de campos obligatorios
    if (!isset($data["correspondent_id"], $data["cashier_id"], $data["capacity"])) {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
        exit;
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $cashier_id = intval($data["cashier_id"]);
    $capacity = intval($data["capacity"]);
    $state = isset($data["state"]) ? (bool) $data["state"] : true;

    try {
        // Validar que no exista una caja para el mismo corresponsal y cajero
        $check = $pdo->prepare("SELECT id FROM cash WHERE correspondent_id = :correspondent_id AND cashier_id = :cashier_id");
        $check->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $check->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $check->execute();

        if ($check->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Ya existe una caja asignada a este corresponsal y cajero."]);
            exit;
        }

        // Insertar nueva caja
        $stmt = $pdo->prepare("INSERT INTO cash (correspondent_id, cashier_id, capacity, state) 
                               VALUES (:correspondent_id, :cashier_id, :capacity, :state)");
        $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $stmt->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $stmt->bindParam(":capacity", $capacity, PDO::PARAM_INT);
        $stmt->bindParam(":state", $state, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Caja registrada exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar la caja."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>