<?php
/**
 * Archivo: update_other.php
 * Descripción: Permite actualizar los datos de un tercero (other).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 16-May-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir conexión
require_once "../db.php";

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

// Obtener datos
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
if (
    !isset($data["id"], $data["correspondent_id"], $data["name"], $data["credit"], $data["state"])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit();
}

$id = intval($data["id"]);
$correspondent_id = intval($data["correspondent_id"]);
$name = trim($data["name"]);
$credit = floatval($data["credit"]);
$state = intval($data["state"]);

// Nuevos campos opcionales
$id_type = isset($data["id_type"]) ? trim($data["id_type"]) : null;
$id_number = isset($data["id_number"]) ? trim($data["id_number"]) : null;
$email = isset($data["email"]) ? trim($data["email"]) : null;
$phone = isset($data["phone"]) ? trim($data["phone"]) : null;
$address = isset($data["address"]) ? trim($data["address"]) : null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE others SET 
                correspondent_id = :correspondent_id,
                name = :name,
                credit = :credit,
                state = :state,
                id_type = :id_type,
                id_number = :id_number,
                email = :email,
                phone = :phone,
                address = :address,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ":id" => $id,
        ":correspondent_id" => $correspondent_id,
        ":name" => $name,
        ":credit" => $credit,
        ":state" => $state,
        ":id_type" => $id_type,
        ":id_number" => $id_number,
        ":email" => $email,
        ":phone" => $phone,
        ":address" => $address,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Tercero actualizado correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o el tercero no existe."]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la actualización: " . $e->getMessage()
    ]);
}
?>