<?php
/**
 * Archivo: list_cash.php
 * Descripción: Retorna todas las cajas (cash) registradas con sus detalles.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.0
 * Fecha de actualización: 16-May-2025
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

    // Consulta de cajas con detalles del corresponsal y cajero
    $sql = "SELECT 
                ca.id,
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
            ORDER BY ca.id DESC";

    $stmt = $conn->prepare($sql);
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
