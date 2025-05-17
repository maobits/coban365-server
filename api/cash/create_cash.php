<?php
/**
 * Archivo: create_cash.php
 * Descripción: Permite registrar una nueva caja (cash) en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
 * Fecha de actualización: 16-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validación de campos obligatorios
    if (!isset($data["correspondent_id"], $data["cashier_id"], $data["capacity"], $data["name"])) {
        echo json_encode(["success" => false, "message" => "Faltan campos obligatorios."]);
        exit;
    }

    $correspondent_id = intval($data["correspondent_id"]);
    $cashier_id = intval($data["cashier_id"]);
    $capacity = intval($data["capacity"]);
    $name = trim($data["name"]);
    $state = isset($data["state"]) ? (bool) $data["state"] : true;
    $open = isset($data["open"]) ? (int) $data["open"] : 1;
    $last_note = isset($data["last_note"]) ? trim($data["last_note"]) : null;

    try {
        // Verificar duplicado
        $check = $pdo->prepare("SELECT id FROM cash WHERE correspondent_id = :correspondent_id AND cashier_id = :cashier_id");
        $check->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $check->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $check->execute();

        if ($check->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Ya existe una caja para este corresponsal y cajero."]);
            exit;
        }

        // Insertar nueva caja (sin campo balance)
        $stmt = $pdo->prepare("
            INSERT INTO cash (correspondent_id, cashier_id, name, capacity, state, open, last_note)
            VALUES (:correspondent_id, :cashier_id, :name, :capacity, :state, :open, :last_note)
        ");
        $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
        $stmt->bindParam(":cashier_id", $cashier_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":capacity", $capacity, PDO::PARAM_INT);
        $stmt->bindParam(":state", $state, PDO::PARAM_BOOL);
        $stmt->bindParam(":open", $open, PDO::PARAM_INT);
        $stmt->bindParam(":last_note", $last_note);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Caja registrada exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar la caja."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error BD: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
