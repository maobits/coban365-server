<?php
/**
 * Archivo: delete_other.php
 * Descripción: Elimina un tercero (other) de la base de datos según su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 12-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitud OPTIONS (preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Validar que sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido"
    ]);
    exit();
}

// Leer los datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Verificar que se recibió el ID
if (!isset($data["id"]) || empty($data["id"])) {
    echo json_encode([
        "success" => false,
        "message" => "ID de tercero requerido"
    ]);
    exit();
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("DELETE FROM others WHERE id = :id");
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Tercero eliminado correctamente"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "El tercero no existe o ya fue eliminado"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al eliminar: " . $e->getMessage()
    ]);
}
?>