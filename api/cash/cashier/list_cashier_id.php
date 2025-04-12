<?php
/**
 * Archivo: list_cashier_id.php
 * Descripción: Retorna los datos de un cajero específico a partir de su ID.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 06-Abr-2025
 */

// Configuración de encabezados para CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de solicitud OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir archivo de conexión a base de datos
require_once '../../db.php';

// Validar parámetro GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Se requiere el ID del cajero"
    ]);
    exit();
}

$id = intval($_GET['id']);

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener los datos del cajero con el ID proporcionado
    $sql = "SELECT 
                id, 
                email, 
                fullname, 
                phone, 
                status, 
                role, 
                permissions, 
                boxes,
                created_at, 
                updated_at 
            FROM users 
            WHERE id = :id AND role = 'cajero'
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $cashier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cashier) {
        echo json_encode([
            "success" => true,
            "data" => $cashier
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Cajero no encontrado o no válido"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>