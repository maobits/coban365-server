<?php
/**
 * Archivo: box_report.php
 * Descripción: Reporte agrupado por tipo de transacción, con ingresos simulados por transferencias recibidas.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.7
 * Fecha: 07-Jun-2025
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

    $nameStmt = $pdo->prepare("SELECT name FROM correspondents WHERE id = :id");
    $nameStmt->execute([":id" => $correspondentId]);
    $correspondent = $nameStmt->fetch(PDO::FETCH_ASSOC);
    $correspondentName = $correspondent ? $correspondent["name"] : "Corresponsal desconocido";

    if ($cashId) {
        $boxes = [["id" => $cashId, "name" => "Caja seleccionada"]];
    } else {
        $boxStmt = $pdo->prepare("SELECT id, name FROM cash WHERE correspondent_id = :id_correspondent");
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

        // Consulta mejorada incluyendo is_transfer y transfer_status
        $stmt = $pdo->prepare("SELECT 
                t.transaction_type_id,
                tt.name AS transaction_type_name,
                tt.category,
                tt.polarity,
                t.is_transfer,
                t.transfer_status,
                SUM(t.cost) AS total
            FROM transactions t
            INNER JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.id_cash = :box_id
            $dateCondition
            GROUP BY t.transaction_type_id, tt.name, tt.category, tt.polarity, t.is_transfer, t.transfer_status
            ORDER BY tt.category, tt.name");
        $stmt->execute($params);
        $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $initStmt = $pdo->prepare("SELECT initial_amount FROM cash WHERE id = :id_cash LIMIT 1");
        $initStmt->execute([':id_cash' => $box['id']]);
        $initAmountRow = $initStmt->fetch(PDO::FETCH_ASSOC);
        $initialAmount = $initAmountRow ? floatval($initAmountRow['initial_amount']) : 0;

        $transferInStmt = $pdo->prepare("SELECT cost, created_at FROM transactions 
            WHERE box_reference = :box_id 
              AND id_cash != :box_id
              AND is_transfer = 1 
              AND transfer_status = 1 
              $dateCondition");
        $transferInStmt->execute($params);
        $transferIns = $transferInStmt->fetchAll(PDO::FETCH_ASSOC);

        $transferOutStmt = $pdo->prepare("SELECT cost, created_at FROM transactions 
            WHERE id_cash = :box_id 
              AND is_transfer = 1 
              AND transfer_status = 1 
              $dateCondition");
        $transferOutStmt->execute($params);
        $transferOuts = $transferOutStmt->fetchAll(PDO::FETCH_ASSOC);

        $simulated = [
            [
                "created_at" => "0000-00-00 00:00:00",
                "transaction_type_name" => "Monto inicial",
                "category" => "Inicial",
                "ingresos" => $initialAmount,
                "egresos" => 0,
                "saldo_por_tipo" => $initialAmount
            ]
        ];

        foreach ($transferIns as $t) {
            $simulated[] = [
                "created_at" => $t['created_at'],
                "transaction_type_name" => "Transferencias recibidas (simulado)",
                "category" => "Transferencia",
                "ingresos" => floatval($t['cost']),
                "egresos" => 0,
                "saldo_por_tipo" => floatval($t['cost'])
            ];
        }



        $grouped = [];
        $ingresos = 0;
        $egresos = 0;

        foreach ($rawResults as $row) {
            if (
                $row["category"] === "Transferencia" &&
                $row["is_transfer"] == 1 &&
                $row["transfer_status"] == 1
            )
                continue;

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

            if ($polarity == 1) {
                $grouped[$typeId]["ingresos"] += $amount;
                $ingresos += $amount;
            } else {
                $grouped[$typeId]["egresos"] += $amount;
                $egresos += $amount;
            }
        }

        foreach ($grouped as $g) {
            $simulated[] = [
                "created_at" => "9999-12-31 23:59:59",
                "transaction_type_name" => $g["transaction_type_name"],
                "category" => $g["category"],
                "ingresos" => $g["ingresos"],
                "egresos" => $g["egresos"],
                "saldo_por_tipo" => round($g["ingresos"] - $g["egresos"], 2)
            ];
        }

        usort($simulated, function ($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        foreach ($simulated as $s) {
            $ingresos += $s['ingresos'];
            $egresos += $s['egresos'];
        }

        $reportes[] = [
            "cash_id" => $box["id"],
            "cash_name" => $box["name"],
            "resumen" => $simulated,
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
