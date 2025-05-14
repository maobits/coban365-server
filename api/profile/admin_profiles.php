<?php
/**
 * Archivo: profiles.php
 * Descripción: Obtiene la lista de todos los usuarios con rol "admin", sin incluir la contraseña.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
 * Fecha de actualización: 14-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        // Obtener solo usuarios con rol "admin" (excluyendo "cajero" y "superadmin")
        $stmt = $pdo->prepare("
            SELECT id, email, fullname, phone, status, role, permissions, created_at, updated_at
            FROM users
            WHERE role = 'admin'
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($users) {
            echo json_encode(["success" => true, "users" => $users]);
        } else {
            echo json_encode(["success" => false, "message" => "No se encontraron usuarios administradores."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Error en la consulta: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>