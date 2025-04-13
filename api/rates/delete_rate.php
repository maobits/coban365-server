<?php
/**
 * Archivo: delete_rate.php
 * Descripción: Elimina una tarifa (rate) de la base de datos según su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 12-Abr-2025
 */

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir conexión
require_once '../db.php';

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

// Obtener datos
$data = json_decode(file_get_contents("php://input"), true);

// Validar ID
if (!isset($data["id"]) || empty($data["id"])) {
    echo json_encode(["success" => false, "message" => "ID de tarifa requerido."]);
    exit();
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("DELETE FROM rates WHERE id = :id");
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Tarifa eliminada correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "La tarifa no existe o ya fue eliminada."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la eliminación: " . $e->getMessage()]);
}
?>