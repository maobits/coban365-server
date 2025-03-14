<?php
// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitudes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de la base de datos
require_once '../db.php';

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Leer el JSON recibido
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Guardar en un archivo log para depuración
file_put_contents("debug.log", print_r($data, true));

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["success" => false, "message" => "Error en formato JSON: " . json_last_error_msg()]);
    exit();
}

// Validar que se recibieron los campos necesarios
$required_fields = ['id', 'fullname', 'email', 'phone', 'role', 'permissions'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["success" => false, "message" => "Falta el campo obligatorio: $field"]);
        exit();
    }
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Convertir permisos a formato JSON para almacenarlos en la base de datos
    $permissionsJSON = json_encode($data["permissions"], JSON_UNESCAPED_UNICODE);

    // Preparar la consulta SQL para actualizar el usuario y los permisos
    $sql = "UPDATE users SET 
                fullname = :fullname, 
                email = :email, 
                phone = :phone, 
                role = :role,
                permissions = :permissions, 
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    // Ejecutar la consulta con los valores recibidos
    $stmt->execute([
        ":id" => $data["id"],
        ":fullname" => $data["fullname"],
        ":email" => $data["email"],
        ":phone" => $data["phone"],
        ":role" => $data["role"],
        ":permissions" => $permissionsJSON, // 🔹 Guardar los permisos como JSON en la base de datos
    ]);

    // Verificar si se actualizó al menos una fila
    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Usuario actualizado exitosamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o el usuario no existe"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la actualización: " . $e->getMessage()]);
}
