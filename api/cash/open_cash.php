<?php
/**
 * Archivo: open_cash.php
 * Descripción: Abre una caja específica asignándole un balance inicial.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 11-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar solicitud OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Incluir conexión
require_once '../db.php';

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

// Validaciones
if (!isset($input['cash_id'], $input['balance'])) {
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros requeridos: 'cash_id' y 'balance'."
    ]);
    exit();
}

$cashId = intval($input['cash_id']);
$balance = floatval($input['balance']);
$lastNote = isset($input['last_note']) ? trim($input['last_note']) : null;

try {
    // Conectar
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Actualizar la caja
    $sql = "UPDATE cash 
            SET open = 1, balance = :balance, last_note = :last_note, updated_at = NOW()
            WHERE id = :cash_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':balance', $balance);
    $stmt->bindParam(':last_note', $lastNote);
    $stmt->bindParam(':cash_id', $cashId, PDO::PARAM_INT);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Caja abierta exitosamente."
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al abrir la caja: " . $e->getMessage()
    ]);
}
