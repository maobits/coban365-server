<?php
/**
 * Archivo: list_cash.php
 * Descripción: Retorna las cajas registradas filtradas por el corresponsal si se proporciona.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.1
 * Fecha de actualización: 04-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Conexión a base de datos
require_once '../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener el ID del corresponsal (GET o POST)
    $correspondent_id = $_GET['correspondent_id'] ?? $_POST['correspondent_id'] ?? null;

    // Construir consulta SQL con o sin filtro
    $sql = "SELECT 
                ca.id,
                ca.correspondent_id,
                ca.cashier_id,
                ca.capacity,
                ca.state,
                ca.created_at,
                ca.updated_at,
                co.name AS correspondent_name,
                u.fullname AS cashier_name
            FROM cash ca
            LEFT JOIN correspondents co ON ca.correspondent_id = co.id
            LEFT JOIN users u ON ca.cashier_id = u.id";

    if ($correspondent_id !== null) {
        $sql .= " WHERE ca.correspondent_id = :correspondent_id";
    }

    $sql .= " ORDER BY ca.id DESC";

    $stmt = $conn->prepare($sql);

    // Asignar valor si corresponde
    if ($correspondent_id !== null) {
        $stmt->bindParam(':correspondent_id', $correspondent_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $cajas
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
