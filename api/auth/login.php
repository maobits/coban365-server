<?php
/**
 * Archivo: login.php
 * Descripción: Servicio de autenticación de usuarios mediante correo y contraseña.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 08-Mar-2025
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

require_once "../db.php"; // Incluye la conexión a la base de datos

// Verifica si se han recibido los datos de la solicitud (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Captura los datos enviados en el cuerpo de la solicitud
    $data = json_decode(file_get_contents("php://input"), true);

    // Verifica si los datos requeridos fueron enviados
    if (!isset($data["email"]) || !isset($data["password"])) {
        echo json_encode(["success" => false, "message" => "Correo y contraseña son obligatorios."]);
        exit;
    }

    $email = trim($data["email"]); // Limpia espacios en blanco del correo
    $password = trim($data["password"]); // Limpia espacios en blanco de la contraseña

    try {
        // Consulta para obtener el usuario por su correo electrónico
        $stmt = $pdo->prepare("SELECT id, email, fullname, role, status, password FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica si el usuario existe y si la contraseña es correcta
        if ($user && password_verify($password, $user["password"])) {
            // Verifica si el usuario está activo
            if ($user["status"] == 0) {
                echo json_encode(["success" => false, "message" => "Cuenta inactiva. Contacta al administrador."]);
                exit;
            }

            // Autenticación exitosa, devuelve los datos del usuario (sin la contraseña)
            echo json_encode([
                "success" => true,
                "message" => "Inicio de sesión exitoso.",
                "user" => [
                    "id" => $user["id"],
                    "email" => $user["email"],
                    "fullname" => $user["fullname"],
                    "role" => $user["role"],
                    "status" => $user["status"]
                ]
            ]);

        } else {
            // Credenciales incorrectas
            echo json_encode(["success" => false, "message" => "Correo o contraseña incorrectos."]);
        }
    } catch (PDOException $e) {
        // Error en la consulta
        echo json_encode(["success" => false, "message" => "Error en el servidor: " . $e->getMessage()]);
    }
} else {
    // Si no es una solicitud POST, se rechaza la petición
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>