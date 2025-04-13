<?php
/**
 * Archivo: list_other_correspondent_id.php
 * Descripción: Retorna los terceros asociados a un corresponsal específico.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 12-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Validar parámetro requerido
if (!isset($_GET['correspondent_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro correspondent_id"
    ]);
    exit();
}

$correspondentId = intval($_GET['correspondent_id']);

// Incluir conexión
require_once '../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT 
                id,
                correspondent_id,
                name,
                credit,
                state,
                created_at,
                updated_at
            FROM others
            WHERE correspondent_id = :correspondent_id
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":correspondent_id", $correspondentId, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $rows
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al consultar los terceros: " . $e->getMessage()
    ]);
}
?>