<?php
/**
 * Archivo: delete_cashier.php
 * Descripción: Elimina un cajero de la base de datos según su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 06-Abr-2025
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
require_once '../../db.php';

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Leer el cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se recibió el ID del cajero
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(["success" => false, "message" => "ID del cajero requerido"]);
    exit();
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar que el usuario tenga el rol de cajero
    $check = $conn->prepare("SELECT id FROM users WHERE id = :id AND role = 'cajero'");
    $check->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $check->execute();

    if ($check->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "El usuario no es un cajero válido o no existe."]);
        exit();
    }

    // Eliminar el cajero
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Cajero eliminado correctamente."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error al eliminar el cajero: " . $e->getMessage()]);
}
?>