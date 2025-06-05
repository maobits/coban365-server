<?php
/**
 * Archivo: box_report.php
 * Descripción: Devuelve el total agrupado por tipo de transacción, por caja o por todas las cajas de un corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.1
 * Fecha: 06-Jun-2025
 */

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

    // Obtener nombre del corresponsal
    $nameStmt = $pdo->prepare("SELECT name FROM correspondents WHERE id = :id");
    $nameStmt->execute([":id" => $correspondentId]);
    $correspondent = $nameStmt->fetch(PDO::FETCH_ASSOC);
    $correspondentName = $correspondent ? $correspondent["name"] : "Corresponsal desconocido";

    // Determinar las cajas a consultar
    if ($cashId) {
        $boxes = [["id" => $cashId, "name" => "Caja seleccionada"]]; // Puedes consultar el nombre si lo deseas
    } else {
        $boxQuery = "SELECT id, name FROM cash WHERE correspondent_id = :id_correspondent";
        $boxStmt = $pdo->prepare($boxQuery);
        $boxStmt->execute([":id_correspondent" => $correspondentId]);
        $boxes = $boxStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $reportes = [];
    $totalIngresos = 0;
    $totalEgresos = 0;

    foreach ($boxes as $box) {
        $params = [":box_id" => $box["id"]];
        $dateCondition = "";

        if ($startDate && $endDate) {
            $dateCondition = "AND t.created_at BETWEEN :start_date AND :end_date";
            $params[":start_date"] = $startDate . " 00:00:00";
            $params[":end_date"] = $endDate . " 23:59:59";
        }

        $stmt = $pdo->prepare("
            SELECT 
                t.transaction_type_id,
                tt.name AS transaction_type_name,
                tt.category,
                tt.polarity,
                SUM(t.cost) AS total
            FROM transactions t
            INNER JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.id_cash = :box_id
            $dateCondition
            GROUP BY t.transaction_type_id, tt.name, tt.category, tt.polarity
            ORDER BY tt.category, tt.name
        ");
        $stmt->execute($params);
        $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        $ingresos = 0;
        $egresos = 0;

        foreach ($rawResults as $row) {
            $typeId = $row['transaction_type_id'];
            $amount = floatval($row['total']);

            if (!isset($grouped[$typeId])) {
                $grouped[$typeId] = [
                    "transaction_type_id" => $typeId,
                    "transaction_type_name" => $row["transaction_type_name"],
                    "category" => $row["category"],
                    "ingresos" => 0,
                    "egresos" => 0,
                ];
            }

            if ($row["polarity"] == 1) {
                $grouped[$typeId]["ingresos"] += $amount;
                $ingresos += $amount;
            } else {
                $grouped[$typeId]["egresos"] += $amount;
                $egresos += $amount;
            }
        }

        foreach ($grouped as &$g) {
            $g["saldo_por_tipo"] = round($g["ingresos"] - $g["egresos"], 2);
        }

        $reportes[] = [
            "cash_id" => $box["id"],
            "cash_name" => $box["name"],
            "resumen" => array_values($grouped),
            "totales" => [
                "ingresos" => round($ingresos, 2),
                "egresos" => round($egresos, 2),
                "saldo" => round($ingresos - $egresos, 2),
            ]
        ];

        $totalIngresos += $ingresos;
        $totalEgresos += $egresos;
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "correspondent_name" => $correspondentName,
            "reportes" => $reportes,
            "totales_globales" => [
                "ingresos" => round($totalIngresos, 2),
                "egresos" => round($totalEgresos, 2),
                "saldo" => round($totalIngresos - $totalEgresos, 2),
            ],
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
