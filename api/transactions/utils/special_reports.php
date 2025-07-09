<?php
/**
 * Archivo: special_reports.php
 * Descripción: Reportes especiales personalizados para una caja específica.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.5
 * Fecha: 09-Jul-2025
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

if (!isset($data["id_cash"]) || !isset($data["id_correspondent"])) {
    echo json_encode([
        "success" => false,
        "message" => "Faltan campos obligatorios: id_cash o id_correspondent."
    ]);
    exit();
}

$id_cash = intval($data["id_cash"]);
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
    "report" => [
        "report_date" => $now->format("Y-m-d H:i:s"),
        "report_date_pretty" => $fechaBonita
    ]
];

try {
    // Obtener datos de caja y corresponsal
    $stmt = $pdo->prepare("
        SELECT 
            cash.initial_amount, cash.id AS cash_id, cash.name AS cash_name,
            correspondents.code AS correspondent_code, correspondents.name AS correspondent_name
        FROM cash
        INNER JOIN correspondents ON cash.correspondent_id = correspondents.id
        WHERE cash.id = :id_cash AND correspondent_id = :id_correspondent
        LIMIT 1
    ");
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $stmt->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $response["report"]["initial_box"] = floatval($result["initial_amount"]);
        $response["report"]["cash"] = [
            "id" => intval($result["cash_id"]),
            "name" => $result["cash_name"]
        ];
        $response["report"]["correspondent"] = [
            "code" => $result["correspondent_code"],
            "name" => $result["correspondent_name"]
        ];
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró la caja con el corresponsal especificado."
        ]);
        exit();
    }

    // Consultar transacciones
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
        ORDER BY t.created_at DESC
    ");
    $txStmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $txStmt->execute();
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    // Resumen por tipo
    $summaryByType = [];
    $countedTransactions = 0;

    foreach ($transactions as &$tx) {
        // OMITIR si es neutral
        if (intval($tx["neutral"]) === 1) {
            continue;
        }

        $isTransfer = intval($tx["is_transfer"]) === 1;
        $isAccepted = intval($tx["transfer_status"]) === 1;
        $isPending = intval($tx["transfer_status"]) === 0;
        $isOrigin = intval($tx["id_cash"]) === $id_cash;
        $isDestination = intval($tx["box_reference"]) === $id_cash;

        if ($isTransfer && $isDestination && $isAccepted && !$isOrigin) {
            $tx["polarity"] = 1;
        } elseif ($isTransfer && $isDestination && $isPending && !$isOrigin) {
            $tx["polarity"] = 1;
        }

        $type = $tx["transaction_type_name"];
        $cost = floatval($tx["cost"]);
        $polarity = intval($tx["polarity"]);

        if ($isTransfer && $isAccepted) {
            if ($isDestination && !$isOrigin)
                $polarity = 1;
            elseif ($isOrigin && !$isDestination)
                $polarity = 0;
        }

        $subtotal = ($polarity === 0) ? -$cost : $cost;

        if (!isset($summaryByType[$type])) {
            $summaryByType[$type] = [
                "type" => $type,
                "count" => 0,
                "subtotal" => 0
            ];
        }

        $summaryByType[$type]["count"] += 1;
        $summaryByType[$type]["subtotal"] += $subtotal;

        $countedTransactions++; // solo si no es neutral
    }

    $summaryList = array_values(array_map(function ($item) {
        return [
            "type" => $item["type"],
            "count" => $item["count"],
            "subtotal" => round($item["subtotal"])
        ];
    }, $summaryByType));

    // Calcular saldo
    $cashBalance = $response["report"]["initial_box"];
    foreach ($summaryList as $s) {
        $cashBalance += $s["subtotal"];
    }

    // Incluir resumen en respuesta
    $response["report"]["transactions"] = [
        "total" => $countedTransactions,
        "summary" => $summaryList,
        "cash_balance" => round($cashBalance)
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
?>