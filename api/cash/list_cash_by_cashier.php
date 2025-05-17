<?php
/**
 * Archivo: list_cash_by_cashier.php
 * Descripción: Retorna las cajas asociadas a un cajero específico.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 11-May-2025
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
if (!isset($_GET['cashier_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro cashier_id"
    ]);
    exit();
}

$cashierId = intval($_GET['cashier_id']);

// Incluir conexión
require_once '../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT 
                ca.id,
                ca.name,
                ca.correspondent_id,
                ca.cashier_id,
                ca.capacity,
                ca.state,
                ca.open,
                ca.last_note,
                ca.created_at,
                ca.updated_at,
                co.name AS correspondent_name,
                u.fullname AS cashier_name
            FROM cash ca
            LEFT JOIN correspondents co ON ca.correspondent_id = co.id
            LEFT JOIN users u ON ca.cashier_id = u.id
            WHERE ca.cashier_id = :cashier_id
            ORDER BY ca.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":cashier_id", $cashierId, PDO::PARAM_INT);
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
?>