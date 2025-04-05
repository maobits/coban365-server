<?php
/**
 * Archivo: delete_cash.php
 * Descripción: Elimina una caja (cash) de la base de datos según su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 31-Mar-2025
 */

// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitud OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de la base de datos
require_once '../db.php';

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Leer el cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se recibió el ID de la caja
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(["success" => false, "message" => "ID de caja requerido"]);
    exit();
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Eliminar la caja por ID
    $sql = "DELETE FROM cash WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Caja eliminada correctamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "La caja no existe o ya fue eliminada"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error al eliminar: " . $e->getMessage()]);
}
?>