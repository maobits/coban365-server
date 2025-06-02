<?php
/**
 * Archivo: list_correspondent_by_cash.php
 * Descripción: Devuelve el corresponsal asociado a una caja específica.
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
if (!isset($_GET['cash_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro cash_id"
    ]);
    exit();
}

$cashId = intval($_GET['cash_id']);

// Configuración de la base de datos
require_once '../db.php';

try {
    // Conexión
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta con campo premium incluido
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
            FROM cash ca
            INNER JOIN correspondents c ON ca.correspondent_id = c.id
            LEFT JOIN types_correspondents t ON c.type_id = t.id
            LEFT JOIN users u ON c.operator_id = u.id
            WHERE ca.id = :cash_id
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":cash_id", $cashId, PDO::PARAM_INT);
    $stmt->execute();
    $correspondent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($correspondent) {
        echo json_encode([
            "success" => true,
            "data" => $correspondent
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró corresponsal para la caja con ID $cashId"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>