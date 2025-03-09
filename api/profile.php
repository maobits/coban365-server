<?php
/**
 * Archivo: get_user.php
 * Descripción: Obtiene la información de un usuario por su ID sin incluir la contraseña.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 09-Mar-2025
 */

// Permitir solicitudes desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Maneja solicitudes OPTIONS (Preflight Request)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

// Incluir la conexión a la base de datos
require_once "db.php";

// Verificar que la solicitud sea de tipo GET
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Verificar si se proporcionó el ID del usuario
    if (!isset($_GET["id"])) {
        echo json_encode(["success" => false, "message" => "El ID del usuario es obligatorio."]);
        exit;
    }

    $user_id = intval($_GET["id"]); // Sanitizar el ID recibido

    try {
        // Preparar la consulta para obtener el usuario sin la contraseña
        $stmt = $pdo->prepare("SELECT id, email, fullname, phone, status, role, permissions, created_at, updated_at FROM users WHERE id = :id");
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si el usuario existe
        if ($user) {
            // Devolver la información del usuario sin la contraseña
            echo json_encode(["success" => true, "user" => $user]);
        } else {
            echo json_encode(["success" => false, "message" => "Usuario no encontrado."]);
        }
    } catch (PDOException $e) {
        // Manejo de errores en la base de datos
        echo json_encode(["success" => false, "message" => "Error en la consulta: " . $e->getMessage()]);
    }
} else {
    // Si no es una solicitud GET, rechazar la petición
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>