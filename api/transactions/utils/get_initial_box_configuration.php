<?php
/**
 * Archivo: get_initial_box_configuration.php
 * Descripción: Obtiene el monto inicial configurado para una caja.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha: 19-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "../../db.php";

// Validar parámetro
if (!isset($_GET["id_cash"])) {
    echo json_encode([
        "success" => false,
        "message" => "Parámetro 'id_cash' es obligatorio."
    ]);
    exit();
}

$id_cash = intval($_GET["id_cash"]);

try {
    $stmt = $pdo->prepare("SELECT initial_amount FROM cash WHERE id = :id_cash LIMIT 1");
    $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode([
            "success" => false,
            "message" => "Caja no encontrada."
        ]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "initial_amount" => floatval($result["initial_amount"])
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
}
