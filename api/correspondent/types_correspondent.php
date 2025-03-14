<?php
/**
 * Archivo: types_correspondent.php
 * Descripción: Servicio API para obtener la lista de tipos de corresponsales junto con sus procesos en formato JSON.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 08-Mar-2025
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
require_once "../db.php";

// Verificar que la solicitud sea de tipo GET
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        // Preparar la consulta para obtener todos los tipos de corresponsales
        $stmt = $pdo->prepare("SELECT id, name, description, processes FROM types_correspondents");
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verificar si hay datos en la tabla
        if ($types) {
            echo json_encode(["success" => true, "data" => $types]);
        } else {
            echo json_encode(["success" => false, "message" => "No se encontraron tipos de corresponsales."]);
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