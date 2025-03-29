<?php
// Habilitar CORS para permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Si la solicitud es de tipo OPTIONS (preflight), responder con 200 y salir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la configuración de la base de datos
require_once '../db.php';

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Leer el JSON recibido
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se recibieron los campos obligatorios
if (
    !isset($data['id']) ||
    !isset($data['type_id']) ||
    !isset($data['code']) ||
    !isset($data['operator_id']) ||
    !isset($data['name']) ||
    !isset($data['location'])
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

// Preparar valores
$id = $data['id'];
$type_id = $data['type_id'];
$code = trim($data['code']);
$operator_id = $data['operator_id'];
$name = trim($data['name']);
$location = json_encode($data['location'], JSON_UNESCAPED_UNICODE);
$transactions = isset($data['transactions']) ? json_encode($data['transactions'], JSON_UNESCAPED_UNICODE) : null;

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta con transacciones incluidas
    $sql = "UPDATE correspondents SET 
                type_id = :type_id, 
                code = :code, 
                operator_id = :operator_id, 
                name = :name, 
                location = :location,
                transactions = :transactions,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":id" => $id,
        ":type_id" => $type_id,
        ":code" => $code,
        ":operator_id" => $operator_id,
        ":name" => $name,
        ":location" => $location,
        ":transactions" => $transactions,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Corresponsal actualizado exitosamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o el corresponsal no existe"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la actualización: " . $e->getMessage()]);
}
?>