<?php
/**
 * Archivo: get_transaction_types.php
 * Descripción: Devuelve todos los tipos de transacción disponibles.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de actualización: 27-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejo de solicitudes preflight (OPTIONS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir la conexión a la base de datos
require_once "../db.php";

// Establecer cabecera de contenido JSON
header("Content-Type: application/json");

try {
    // Preparar y ejecutar la consulta
    $stmt = $pdo->prepare("SELECT id, category, name, polarity FROM transaction_types ORDER BY name ASC");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Devolver resultado exitoso
    echo json_encode([
        "success" => true,
        "data" => $types
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener tipos de transacción: " . $e->getMessage()
    ]);
}
?>