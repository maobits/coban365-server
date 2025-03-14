<?php
/**
 * Archivo: create_user.php
 * Descripción: Permite registrar un nuevo usuario en la base de datos.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 09-Mar-2025
 */

// Permitir solicitudes desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Maneja solicitudes OPTIONS (Preflight Request)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Verificar que la solicitud sea de tipo POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capturar los datos enviados en la solicitud
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que los datos requeridos fueron enviados
    if (!isset($data["email"], $data["fullname"], $data["password"], $data["role"])) {
        echo json_encode(["success" => false, "message" => "Todos los campos obligatorios deben ser proporcionados."]);
        exit;
    }

    $email = trim($data["email"]);
    $fullname = trim($data["fullname"]);
    $phone = isset($data["phone"]) ? trim($data["phone"]) : null; // Campo opcional
    $password = password_hash($data["password"], PASSWORD_BCRYPT); // Encriptar la contraseña con bcrypt
    $status = 1; // Por defecto, el usuario está activo
    $role = trim($data["role"]);
    $permissions = isset($data["permissions"]) ? json_encode($data["permissions"], JSON_UNESCAPED_UNICODE) : null;

    try {
        // Verificar si el correo ya está registrado
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->bindParam(":email", $email, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "El correo electrónico ya está registrado."]);
            exit;
        }

        // Insertar el nuevo usuario
        $stmt = $pdo->prepare("INSERT INTO users (email, fullname, phone, password, status, role, permissions) 
                               VALUES (:email, :fullname, :phone, :password, :status, :role, :permissions)");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
        $stmt->bindParam(":password", $password, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
        $stmt->bindParam(":role", $role, PDO::PARAM_STR);
        $stmt->bindParam(":permissions", $permissions, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Usuario registrado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al registrar el usuario."]);
        }
    } catch (PDOException $e) {
        // Manejo de errores en la base de datos
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} else {
    // Si no es una solicitud POST, rechazar la petición
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>