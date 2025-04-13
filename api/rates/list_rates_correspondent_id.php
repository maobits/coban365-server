<?php
/**
 * Archivo: list_rates_correspondent_id.php
 * Descripción: Lista las tarifas registradas filtradas por el ID del corresponsal.
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

// Preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir configuración base de datos
require_once '../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener ID del corresponsal desde GET o POST
    $correspondent_id = $_GET["correspondent_id"] ?? $_POST["correspondent_id"] ?? null;

    if ($correspondent_id === null) {
        echo json_encode([
            "success" => false,
            "message" => "Se requiere el parámetro 'correspondent_id'."
        ]);
        exit;
    }

    // Consulta SQL
    $sql = "SELECT 
                r.id,
                r.transaction_type_id,
                r.correspondent_id,
                r.price,
                r.created_at,
                r.updated_at,
                tt.name AS transaction_type_name,
                tt.category AS transaction_category
            FROM rates r
            LEFT JOIN transaction_types tt ON r.transaction_type_id = tt.id
            WHERE r.correspondent_id = :correspondent_id
            ORDER BY r.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":correspondent_id", $correspondent_id, PDO::PARAM_INT);
    $stmt->execute();

    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $rates
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al consultar tarifas: " . $e->getMessage()
    ]);
}
?>