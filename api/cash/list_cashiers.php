<?php
// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
header('Content-Type: application/json');

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Consulta filtrando solo los usuarios con rol 'cajero'
    $sql = "SELECT 
                id, 
                email, 
                fullname, 
                phone, 
                status, 
                role, 
                permissions, 
                created_at, 
                updated_at 
            FROM users 
            WHERE role = 'cajero'
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $users
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>