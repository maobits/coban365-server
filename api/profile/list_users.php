<?php
// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *"); // Puedes cambiar * por un dominio específico
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Si la solicitud es de tipo OPTIONS (preflight), responder con 200 y salir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la configuración de la base de datos
require_once '../db.php';

header('Content-Type: application/json');

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la lista de usuarios sin incluir la contraseña
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
            ORDER BY id DESC";

    // Ejecutar la consulta
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Responder con los datos en formato JSON
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