<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once '../db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["correspondent_id"])) {
    echo json_encode(["success" => false, "message" => "Falta el ID del corresponsal"]);
    exit();
}

$correspondentId = intval($data["correspondent_id"]);
$cashId = isset($data["cash_id"]) ? intval($data["cash_id"]) : null;
$startDate = isset($data["start_date"]) ? $data["start_date"] : null;
$endDate = isset($data["end_date"]) ? $data["end_date"] : null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Nombre del corresponsal
    $nameStmt = $pdo->prepare("SELECT name FROM correspondents WHERE id = :id");
    $nameStmt->execute([":id" => $correspondentId]);
    $correspondent = $nameStmt->fetch(PDO::FETCH_ASSOC);
    $correspondentName = $correspondent ? $correspondent["name"] : "Corresponsal desconocido";

    // Filtros
    $params = [":id_correspondent" => $correspondentId];
    $conditions = ["t.id_correspondent = :id_correspondent"];

    if ($cashId) {
        $conditions[] = "t.id_cash = :id_cash";
        $params[":id_cash"] = $cashId;
    }

    if ($startDate && $endDate) {
        $conditions[] = "t.created_at BETWEEN :start_date AND :end_date";
        $params[":start_date"] = $startDate . " 00:00:00";
        $params[":end_date"] = $endDate . " 23:59:59";
    }

    $whereClause = implode(" AND ", $conditions);

    // Consultar transacciones agrupadas
    $stmt = $pdo->prepare("
        SELECT 
            t.transaction_type_id,
            tt.name AS transaction_type_name,
            tt.category,
            tt.polarity,
            SUM(t.cost) AS total
        FROM transactions t
        INNER JOIN transaction_types tt ON t.transaction_type_id = tt.id
        WHERE $whereClause
        GROUP BY t.transaction_type_id, tt.name, tt.category, tt.polarity
        ORDER BY tt.category, tt.name
    ");
    $stmt->execute($params);
    $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consultar monto inicial total de todas las cajas del corresponsal
    $initStmt = $pdo->prepare("SELECT SUM(initial_amount) AS total_initial FROM cash WHERE correspondent_id = :correspondent_id");
    $initStmt->execute([":correspondent_id" => $correspondentId]);
    $initResult = $initStmt->fetch(PDO::FETCH_ASSOC);
    $initialAmountTotal = $initResult ? floatval($initResult["total_initial"]) : 0;

    $grouped = [];
    $ingresos = $initialAmountTotal;
    $egresos = 0;

    foreach ($rawResults as $row) {
        $typeId = $row['transaction_type_id'];
        $amount = floatval($row['total']);
        $category = $row["category"];
        $polarity = $row["polarity"];

        if (!isset($grouped[$typeId])) {
            $grouped[$typeId] = [
                "transaction_type_id" => $typeId,
                "transaction_type_name" => $row["transaction_type_name"],
                "category" => $category,
                "ingresos" => 0,
                "egresos" => 0,
            ];
        }

        if (strtolower($category) === "otros") {
            $grouped[$typeId]["ingresos"] = 0;
            $grouped[$typeId]["egresos"] = 0;
            continue;
        }

        if ($polarity == 1) {
            $grouped[$typeId]["ingresos"] += $amount;
            $ingresos += $amount;
        } else {
            $grouped[$typeId]["egresos"] += $amount;
            $egresos += $amount;
        }
    }

    // Agregar monto inicial como transacción simulada
    $grouped = array_values($grouped); // convertir a lista para respuesta
    array_unshift($grouped, [
        "transaction_type_id" => 0,
        "transaction_type_name" => "Monto inicial",
        "category" => "Inicial",
        "ingresos" => $initialAmountTotal,
        "egresos" => 0,
        "saldo_por_tipo" => $initialAmountTotal
    ]);

    foreach ($grouped as &$g) {
        if (!isset($g["saldo_por_tipo"])) {
            $g["saldo_por_tipo"] = round($g["ingresos"] - $g["egresos"], 2);
        }
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "correspondent_name" => $correspondentName,
            "resumen" => $grouped,
            "totales" => [
                "ingresos" => round($ingresos, 2),
                "egresos" => round($egresos, 2),
                "saldo" => round($ingresos - $egresos, 2),
            ],
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
