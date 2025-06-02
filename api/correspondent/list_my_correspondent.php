<?php
// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *"); // Puedes cambiar * por un dominio específico
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de la base de datos
require_once '../db.php';
header('Content-Type: application/json');

try {
    // Conectar a la base de datos con codificación UTF-8
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener el parámetro id desde GET o POST
    $input = json_decode(file_get_contents("php://input"), true);
    $operatorId = $_GET['id'] ?? $input['id'] ?? null;

    if (!$operatorId) {
        echo json_encode([
            "success" => false,
            "message" => "Falta el parámetro 'id'."
        ]);
        exit();
    }

    // Consulta SQL con el nuevo campo 'premium'
    $sql = "SELECT 
                c.id, 
                c.code, 
                c.name, 
                c.location, 
                c.transactions,
                c.state,
                c.credit_limit,
                c.premium, -- ✅ Campo premium incluido
                c.created_at, 
                c.updated_at,
                t.id AS type_id, 
                t.name AS type_name, 
                t.description AS type_description, 
                t.processes AS type_processes,
                u.id AS operator_id, 
                u.fullname AS operator_name, 
                u.email AS operator_email, 
                u.phone AS operator_phone
            FROM correspondents c
            LEFT JOIN types_correspondents t ON c.type_id = t.id
            LEFT JOIN users u ON c.operator_id = u.id
            WHERE c.operator_id = :id
            ORDER BY c.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $operatorId, PDO::PARAM_INT);
    $stmt->execute();
    $correspondents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $correspondents
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>