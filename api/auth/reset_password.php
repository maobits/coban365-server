<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["email"], $data["token"], $data["new_password"])) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit;
}

$email = trim($data["email"]);
$token = trim($data["token"]);
$newPassword = trim($data["new_password"]);

try {
    // Obtener user_id desde el correo
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        echo json_encode(["success" => false, "message" => "Correo no encontrado."]);
        exit;
    }

    // Buscar token válido y no expirado
    $stmt = $pdo->prepare("SELECT token_hash, expires_at FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(["success" => false, "message" => "Token no encontrado."]);
        exit;
    }

    if (strtotime($reset["expires_at"]) < time()) {
        echo json_encode(["success" => false, "message" => "El token ha expirado."]);
        exit;
    }

    if (!password_verify($token, $reset["token_hash"])) {
        echo json_encode(["success" => false, "message" => "Token inválido."]);
        exit;
    }

    // Hashear nueva contraseña y actualizar
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);

    // Eliminar el token usado
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$userId]);

    echo json_encode(["success" => true, "message" => "Contraseña actualizada correctamente."]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error en el servidor: " . $e->getMessage()]);
}
