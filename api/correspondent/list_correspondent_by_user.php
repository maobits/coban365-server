<?php
/**
 * Archivo: list_my_correspondents.php
 * Descripción: Devuelve los corresponsales asociados a un operador específico (usuario de la sesión actual).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.1
 * Fecha de actualización: 03-Jun-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Validar parámetro requerido
if (!isset($_GET['operator_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro operator_id"
    ]);
    exit();
}

$operatorId = intval($_GET['operator_id']);

// Configuración de la base de datos
require_once '../db.php';

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta SQL con campo premium agregado
    $sql = "SELECT 
                c.id, 
                c.code, 
                c.name, 
                c.location, 
                c.transactions,
                c.state, 
                c.created_at, 
                c.updated_at,
                c.credit_limit,
                c.premium, -- ✅ Campo agregado
                t.id AS type_id, 
                t.name AS type_name, 
                t.description AS type_description, 
                t.processes AS type_processes,
                u.id AS operator_id, 
                u.fullname AS operator_name, 
                u.email AS operator_email, 
                u.phone AS operator_phone
            FROM correspondents c
            LEFT JOIN types_correspondents t ON c.type_id = t.id
            LEFT JOIN users u ON c.operator_id = u.id
            WHERE c.operator_id = :operator_id
            ORDER BY c.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":operator_id", $operatorId, PDO::PARAM_INT);
    $stmt->execute();
    $correspondents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $correspondents
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>