<?php
/**
 * Archivo: update_transaction.php
 * Descripción: Permite actualizar una transacción existente en la base de datos, incluyendo referencia de cliente.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 27-Abr-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejo de solicitudes OPTIONS (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de base de datos
require_once '../db.php';

// Verificar tipo de solicitud
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

// Capturar los datos enviados
$data = json_decode(file_get_contents("php://input"), true);

// Validar que existan los campos principales
if (
    !isset($data['id']) ||
    !isset($data['id_cashier']) ||
    !isset($data['id_cash']) ||
    !isset($data['id_correspondent']) ||
    !isset($data['transaction_type_id']) ||
    !isset($data['polarity']) ||
    !isset($data['cost']) ||
    !isset($data['state']) ||
    !isset($data['note']) ||
    !isset($data['client_reference']) // Nuevo campo obligatorio
) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios"]);
    exit();
}

try {
    // Crear conexión
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta SQL para actualizar
    $sql = "UPDATE transactions SET 
                id_cashier = :id_cashier,
                id_cash = :id_cash,
                id_correspondent = :id_correspondent,
                transaction_type_id = :transaction_type_id,
                polarity = :polarity,
                cost = :cost,
                state = :state,
                note = :note,
                client_reference = :client_reference,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':id' => (int) $data['id'],
        ':id_cashier' => (int) $data['id_cashier'],
        ':id_cash' => (int) $data['id_cash'],
        ':id_correspondent' => (int) $data['id_correspondent'],
        ':transaction_type_id' => (int) $data['transaction_type_id'],
        ':polarity' => $data['polarity'] ? 1 : 0,
        ':cost' => floatval($data['cost']),
        ':state' => $data['state'] ? 1 : 0,
        ':note' => trim($data['note']),
        ':client_reference' => trim($data['client_reference']),
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Transacción actualizada correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "No se realizaron cambios o la transacción no existe."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error al actualizar: " . $e->getMessage()]);
}
?>