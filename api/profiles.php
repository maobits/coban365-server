<?php
/**
 * Archivo: profiles.php
 * Descripción: Obtiene la lista de todos los usuarios registrados sin incluir la contraseña.
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
    try {
        // Preparar la consulta para obtener todos los usuarios sin la contraseña
        $stmt = $pdo->prepare("SELECT id, email, fullname, phone, status, role, permissions, created_at, updated_at FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verificar si hay usuarios registrados
        if ($users) {
            echo json_encode(["success" => true, "users" => $users]);
        } else {
            echo json_encode(["success" => false, "message" => "No se encontraron usuarios registrados."]);
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