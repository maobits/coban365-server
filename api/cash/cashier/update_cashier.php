<?php
/**
 * Archivo: update_cashier.php
 * Descripción: Actualiza los datos de un cajero.
 * Proyecto: COBAN365
 * Versión: 1.1.2
 */

// Habilitar CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'http://localhost:1234') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../db.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Obtener datos
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
if (
    !isset($data['id']) ||
    !isset($data['email']) ||
    !isset($data['fullname']) ||
    !isset($data['correspondents'])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit();
}

// Preparar variables
$id = intval($data['id']);
$email = trim($data['email']);
$fullname = trim($data['fullname']);
$phone = isset($data['phone']) ? trim($data['phone']) : null;
$password = isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;
$permissions = json_encode($data['permissions'] ?? ["manageCash"], JSON_UNESCAPED_UNICODE);
$correspondents = json_encode($data['correspondents'], JSON_UNESCAPED_UNICODE);

// Ejecutar actualización
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE users SET 
                email = :email,
                fullname = :fullname,
                phone = :phone,
                permissions = :permissions,
                correspondents = :correspondents,
                updated_at = NOW()";

    if ($password) {
        $sql .= ", password = :password";
    }

    $sql .= " WHERE id = :id AND role = 'cajero'";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
    $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
    $stmt->bindParam(":permissions", $permissions, PDO::PARAM_STR);
    $stmt->bindParam(":correspondents", $correspondents, PDO::PARAM_STR);
    if ($password) {
        $stmt->bindParam(":password", $password, PDO::PARAM_STR);
    }

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Cajero actualizado correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o el cajero no existe."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
