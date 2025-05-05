<?php
/**
 * Archivo: get_transactions.php
 * Descripción: Devuelve todas las transacciones registradas, incluyendo referencia del cliente y detalles.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.2.1
 * Fecha de actualización: 27-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes de tipo OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de la base de datos
require_once '../db.php';

header('Content-Type: application/json');

try {
    // Conexión
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para traer las transacciones
    $sql = "SELECT 
                id,
                id_cashier,
                id_cash,
                id_correspondent,
                transaction_type_id,
                polarity,
                cost,
                state,
                note,
                client_reference,
                created_at,
                updated_at
            FROM transactions
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Responder
    echo json_encode([
        "success" => true,
        "data" => $transactions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>