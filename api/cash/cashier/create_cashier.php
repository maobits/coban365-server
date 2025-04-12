<?php
/**
 * Archivo: create_cashier.php
 * Descripción: Permite registrar un nuevo cajero con sus corresponsales asignados.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 10-Abr-2025
 */

// Cabeceras CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir conexión
require_once "../../db.php";

// Solo permitir POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

// Capturar datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
if (
    !isset($data["email"], $data["fullname"], $data["password"], $data["correspondents"]) ||
    !is_array($data["correspondents"]) || empty($data["correspondents"])
) {
    echo json_encode(["success" => false, "message" => "Faltan campos obligatorios o correspondents no válido."]);
    exit;
}

// Limpiar y preparar datos
$email = trim($data["email"]);
$fullname = trim($data["fullname"]);
$password = password_hash($data["password"], PASSWORD_BCRYPT);
$phone = isset($data["phone"]) ? trim($data["phone"]) : null;
$role = isset($data["role"]) ? trim($data["role"]) : "cajero";
$status = 1;
$permissions = json_encode(["manageCash"], JSON_UNESCAPED_UNICODE);
$correspondents = json_encode($data["correspondents"], JSON_UNESCAPED_UNICODE);

try {
    // Verificar email duplicado
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->bindParam(":email", $email, PDO::PARAM_STR);
    $stmtCheck->execute();

    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "El correo ya está registrado."]);
        exit;
    }

    // Insertar cajero
    $stmt = $pdo->prepare("INSERT INTO users (
        email, fullname, phone, password, status, role, permissions, correspondents
    ) VALUES (
        :email, :fullname, :phone, :password, :status, :role, :permissions, :correspondents
    )");

    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
    $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
    $stmt->bindParam(":password", $password, PDO::PARAM_STR);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":role", $role, PDO::PARAM_STR);
    $stmt->bindParam(":permissions", $permissions, PDO::PARAM_STR);
    $stmt->bindParam(":correspondents", $correspondents, PDO::PARAM_STR);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Cajero registrado exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar el cajero."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
}
?>