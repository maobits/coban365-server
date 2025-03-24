<?php
/**
 * Archivo: get_transaction_types.php
 * Descripción: Devuelve todos los tipos de transacciones registrados.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 23-Mar-2025
 */

// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *"); // Puedes cambiar * por un dominio específico
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Si la solicitud es de tipo OPTIONS (preflight), responder con 200 y salir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de la base de datos
require_once '../db.php';

header('Content-Type: application/json');

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener los tipos de transacción
    $sql = "SELECT 
            id,
            name,
            category,
            created_at,
            updated_at
        FROM transaction_types
        ORDER BY id DESC";


    // Ejecutar la consulta
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $transactionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Responder con los datos
    echo json_encode([
        "success" => true,
        "data" => $transactionTypes
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>