<?php
/**
 * Archivo: list_cash_by_correspondent.php
 * Descripción: Retorna las cajas asociadas a un corresponsal específico.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
 * Fecha de actualización: 17-May-2025
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
            ca.id,
            ca.correspondent_id,
            ca.cashier_id,
            ca.name, -- ✅ Nombre de la caja
            ca.capacity,
            ca.state,
            ca.open,
            ca.last_note,
            ca.initial_amount, -- ✅ Campo que faltaba
            ca.created_at,
            ca.updated_at,
            co.name AS correspondent_name,
            u.fullname AS cashier_name
        FROM cash ca
        LEFT JOIN correspondents co ON ca.correspondent_id = co.id
        LEFT JOIN users u ON ca.cashier_id = u.id
        WHERE ca.correspondent_id = :correspondent_id
        ORDER BY ca.id DESC";


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
        "message" => "Error al consultar las cajas: " . $e->getMessage()
    ]);
}
