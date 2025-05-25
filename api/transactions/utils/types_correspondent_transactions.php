<?php
/**
 * Archivo: types_correspondent_transactions.php
 * Descripción: Devuelve los tipos de transacción válidos según el tipo de movimiento y el corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Fecha: 18-May-2025
 */

// Habilitar CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Validar parámetros
if (!isset($_GET['correspondent_id']) || !isset($_GET['movement_type'])) {
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros requeridos: correspondent_id o movement_type"
    ]);
    exit();
}

$correspondentId = intval($_GET['correspondent_id']);
$movementType = $_GET['movement_type'];

// Mapear tipo de movimiento → categoría
$movementMap = [
    "deposits" => "Ingresos",
    "withdrawals" => "Retiros",
    "others" => "Otros",
    "third_parties" => "Terceros",
    "compensation" => "Compensación",
    "transfer" => "Transferencia",
];

$category = $movementMap[$movementType] ?? null;
if (!$category) {
    echo json_encode([
        "success" => false,
        "message" => "Tipo de movimiento inválido: $movementType"
    ]);
    exit();
}

// Conexión a base de datos
require_once "../../db.php";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener lista de transacciones permitidas desde correspondents
    $stmt = $conn->prepare("SELECT transactions FROM correspondents WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $correspondentId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "message" => "Corresponsal no encontrado."]);
        exit();
    }

    $transactionList = json_decode($row['transactions'], true);
    if (!is_array($transactionList)) {
        echo json_encode(["success" => false, "message" => "Campo 'transactions' inválido."]);
        exit();
    }

    $allowedIds = array_column($transactionList, 'id');
    if (empty($allowedIds)) {
        echo json_encode(["success" => false, "message" => "No hay transacciones permitidas para este corresponsal."]);
        exit();
    }

    // Consulta de transaction_types
    $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
    $sql2 = "SELECT id, name, category, polarity, created_at, updated_at 
    FROM transaction_types 
    WHERE id IN ($placeholders) AND LOWER(category) = LOWER(?)
    ORDER BY name ASC";


    $stmt2 = $conn->prepare($sql2);

    $i = 1;
    foreach ($allowedIds as $id) {
        $stmt2->bindValue($i++, $id, PDO::PARAM_INT);
    }
    $stmt2->bindValue($i, $category, PDO::PARAM_STR);
    $stmt2->execute();

    $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $results
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
?>