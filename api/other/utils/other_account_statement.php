<?php
/**
 * Archivo: other_account_statement.php
 * Descripción: Retorna una colección de terceros (others) asociados a un corresponsal,
 *              incluyendo su balance financiero (SUM(account_receivable) - SUM(account_to_pay))
 *              y todos los movimientos registrados.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara Hurtado
 * Versión: 1.0.5
 * Fecha de actualización: 04-May-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

if (!isset($_GET['correspondent_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Falta el parámetro correspondent_id"
    ]);
    exit();
}

$correspondentId = intval($_GET['correspondent_id']);

require_once __DIR__ . '/../../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener los terceros activos del corresponsal
    $sqlThirds = "SELECT * FROM others WHERE correspondent_id = :correspondent_id AND state = 1";
    $stmtThirds = $conn->prepare($sqlThirds);
    $stmtThirds->bindParam(':correspondent_id', $correspondentId, PDO::PARAM_INT);
    $stmtThirds->execute();
    $thirds = $stmtThirds->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($thirds as $third) {
        $idThird = $third['id'];

        // Balance financiero
        $sqlBalance = "
            SELECT 
                IFNULL(SUM(account_receivable), 0) AS total_receivable,
                IFNULL(SUM(account_to_pay), 0) AS total_to_pay,
                IFNULL(SUM(account_receivable), 0) - IFNULL(SUM(account_to_pay), 0) AS balance
            FROM account_statement_others
            WHERE id_third = :id_third AND state = 1
        ";
        $stmtBalance = $conn->prepare($sqlBalance);
        $stmtBalance->bindParam(':id_third', $idThird, PDO::PARAM_INT);
        $stmtBalance->execute();
        $balanceData = $stmtBalance->fetch(PDO::FETCH_ASSOC);

        // Movimientos
        $sqlMovements = "
            SELECT * FROM account_statement_others
            WHERE id_third = :id_third AND state = 1
            ORDER BY created_at DESC
        ";
        $stmtMovements = $conn->prepare($sqlMovements);
        $stmtMovements->bindParam(':id_third', $idThird, PDO::PARAM_INT);
        $stmtMovements->execute();
        $movements = $stmtMovements->fetchAll(PDO::FETCH_ASSOC);

        $result[] = [
            "third" => $third,
            "balance" => floatval($balanceData['balance']),
            "total_receivable" => floatval($balanceData['total_receivable']),
            "total_to_pay" => floatval($balanceData['total_to_pay']),
            "movements" => $movements
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $result
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
