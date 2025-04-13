<?php
/**
 * Archivo: create_rate.php
 * Descripción: Registra una nueva tarifa (rate) en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 12-Abr-2025
 */

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// OPTIONS request (preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir base de datos
require_once "../db.php";

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

// Obtener datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
if (!isset($data["transaction_type_id"], $data["price"], $data["correspondent_id"])) {
    echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
    exit;
}

$transaction_type_id = intval($data["transaction_type_id"]);
$price = floatval($data["price"]);
$correspondent_id = intval($data["correspondent_id"]);

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("INSERT INTO rates (transaction_type_id, price, correspondent_id) 
                            VALUES (:transaction_type_id, :price, :correspondent_id)");

    $stmt->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
    $stmt->bindParam(":price", $price);
    $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Tarifa registrada exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar la tarifa."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
}
?>