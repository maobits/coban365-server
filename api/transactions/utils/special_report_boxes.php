<?php
/**
 * Archivo: special_report_boxes.php
 * Descripción: Reporte especial agrupado por cada caja de un corresponsal.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha: 23-Jul-2025
 */

date_default_timezone_set('America/Bogota');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../../db.php";

$data = json_decode(file_get_contents("php://input"), true);
$dateFilter = isset($data["date"]) ? $data["date"] : date("Y-m-d");

if (!isset($data["id_correspondent"])) {
    echo json_encode(["success" => false, "message" => "Falta el campo id_correspondent."]);
    exit();
}

$id_correspondent = intval($data["id_correspondent"]);

$now = new DateTime("now", new DateTimeZone("America/Bogota"));
$meses = [
    "01" => "Enero",
    "02" => "Febrero",
    "03" => "Marzo",
    "04" => "Abril",
    "05" => "Mayo",
    "06" => "Junio",
    "07" => "Julio",
    "08" => "Agosto",
    "09" => "Septiembre",
    "10" => "Octubre",
    "11" => "Noviembre",
    "12" => "Diciembre"
];
$fechaBonita = $now->format("d") . " de " . $meses[$now->format("m")] . " de " . $now->format("Y") . " - " . $now->format("h:i a");

$response = [
    "success" => true,
    "report_date" => $now->format("Y-m-d H:i:s"),
    "report_date_pretty" => $fechaBonita,
    "boxes" => [],
    "totals" => [
        "initial_total" => 0,
        "total_transactions" => 0,
        "effective_total" => 0
    ]
];

try {
    // Obtener todas las cajas del corresponsal
    $stmt = $pdo->prepare("
        SELECT id, name, initial_amount FROM cash
        WHERE correspondent_id = :id_correspondent
    ");
    $stmt->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
    $stmt->execute();
    $boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($boxes as $box) {
        $id_cash = intval($box["id"]);
        $initial = floatval($box["initial_amount"]);
        $cash_name = $box["name"];

        // --- Calcular saldo anterior a la fecha
        $pastStmt = $pdo->prepare("
            SELECT cost, polarity, neutral, is_transfer, transfer_status, id_cash, box_reference
            FROM transactions
            WHERE state = 1 AND (id_cash = :id OR box_reference = :id)
            AND DATE(created_at) < DATE(:date)
        ");
        $pastStmt->execute([
            ":id" => $id_cash,
            ":date" => $dateFilter
        ]);
        $pastTx = $pastStmt->fetchAll(PDO::FETCH_ASSOC);

        $accumulated = $initial;

        foreach ($pastTx as $pt) {
            if (intval($pt["neutral"]) === 1)
                continue;

            $isTransfer = intval($pt["is_transfer"]) === 1;
            $isAccepted = intval($pt["transfer_status"]) === 1;
            $isOrigin = intval($pt["id_cash"]) === $id_cash;
            $isDest = intval($pt["box_reference"]) === $id_cash;
            $polarity = intval($pt["polarity"]);

            if ($isTransfer && $isAccepted) {
                if ($isDest && !$isOrigin)
                    $polarity = 1;
                elseif ($isOrigin && !$isDest)
                    $polarity = 0;
            }

            $amount = floatval($pt["cost"]);
            $accumulated += ($polarity === 0) ? -$amount : $amount;
        }

        $initial_box = round($accumulated);
        // --- Obtener transacciones del día
        $txStmt = $pdo->prepare("
            SELECT 
                t.*, tt.name AS transaction_type_name, tt.polarity,
                ca.name AS cash_name,
                ca2.name AS destination_cash_name,
                o.name AS client_reference_name
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            LEFT JOIN cash ca ON t.id_cash = ca.id
            LEFT JOIN cash ca2 ON t.box_reference = ca2.id
            LEFT JOIN others o ON t.client_reference = o.id
            WHERE t.state = 1 AND (t.id_cash = :id_cash OR t.box_reference = :id_cash)
            AND DATE(t.created_at) = DATE(:date)
            ORDER BY t.created_at DESC
        ");
        $txStmt->execute([
            ":id_cash" => $id_cash,
            ":date" => $dateFilter
        ]);
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

        $summaryByType = [];
        $counted = 0;

        foreach ($transactions as &$tx) {
            if (intval($tx["neutral"]) === 1)
                continue;

            $isTransfer = intval($tx["is_transfer"]) === 1;
            $isAccepted = intval($tx["transfer_status"]) === 1;
            $isPending = intval($tx["transfer_status"]) === 0;
            $isOrigin = intval($tx["id_cash"]) === $id_cash;
            $isDest = intval($tx["box_reference"]) === $id_cash;

            if ($isTransfer && $isDest && ($isAccepted || $isPending) && !$isOrigin) {
                $tx["polarity"] = 1;
            }

            $type = $tx["transaction_type_name"];
            $cost = floatval($tx["cost"]);
            $polarity = intval($tx["polarity"]);

            if ($isTransfer && $isAccepted) {
                if ($isDest && !$isOrigin)
                    $polarity = 1;
                elseif ($isOrigin && !$isDest)
                    $polarity = 0;
            }

            $subtotal = ($polarity === 0) ? -$cost : $cost;

            if (!isset($summaryByType[$type])) {
                $summaryByType[$type] = ["type" => $type, "count" => 0, "subtotal" => 0];
            }

            $summaryByType[$type]["count"] += 1;
            $summaryByType[$type]["subtotal"] += $subtotal;

            $counted++;
        }

        $summaryList = array_values(array_map(function ($item) {
            return [
                "type" => $item["type"],
                "count" => $item["count"],
                "subtotal" => round($item["subtotal"])
            ];
        }, $summaryByType));

        $cashBalance = $initial_box;
        foreach ($summaryList as $s) {
            $cashBalance += $s["subtotal"];
        }

        // Guardar reporte de esta caja
        $response["boxes"][] = [
            "id" => $id_cash,
            "name" => $cash_name,
            "initial_box" => $initial_box,
            "transactions" => [
                "total" => $counted,
                "summary" => $summaryList,
                "cash_balance" => round($cashBalance)
            ]
        ];

        // Acumular en totales globales
        $response["totals"]["initial_total"] += $initial_box;
        $response["totals"]["total_transactions"] += $counted;
        $response["totals"]["effective_total"] += $cashBalance;
    }

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
