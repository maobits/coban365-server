<?php
date_default_timezone_set('America/Bogota');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../db.php";

/**
 * ============================
 *  DELETE: elimina transacción
 *  - Parámetro requerido: transaction_id (en JSON o query)
 *  - Si la transacción es 'loan_from_third_party' y tiene comisión,
 *    descuenta ABS(total_commission) del acumulado en third_party_commissions.
 * ============================
 */
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    // Permitir id por JSON o por querystring
    $payload = json_decode(file_get_contents("php://input"), true);
    $transactionId = null;

    if (isset($payload["transaction_id"])) {
        $transactionId = (int) $payload["transaction_id"];
    } elseif (isset($_GET["transaction_id"])) {
        $transactionId = (int) $_GET["transaction_id"];
    }

    if (!$transactionId) {
        echo json_encode([
            "success" => false,
            "message" => "Falta el campo obligatorio: transaction_id"
        ]);
        exit;
    }

    try {
        // Traer info mínima para ajustar comisiones
        $sel = $pdo->prepare("
            SELECT id, id_correspondent, client_reference, third_party_note, total_commission
            FROM transactions
            WHERE id = :id
            LIMIT 1
        ");
        $sel->execute([":id" => $transactionId]);
        $tx = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            echo json_encode([
                "success" => false,
                "message" => "Transacción no encontrada."
            ]);
            exit;
        }

        $pdo->beginTransaction();

        // Si fue préstamo DE tercero (entra dinero al corresponsal) y hubo comisión,
        // restar del acumulado (en magnitud positiva)
        if (
            $tx["third_party_note"] === "loan_from_third_party" &&
            (float) $tx["total_commission"] != 0
        ) {
            $upd = $pdo->prepare("
                UPDATE third_party_commissions
                SET total_commission = GREATEST(total_commission - :amt, 0),
                    last_update = NOW()
                WHERE third_party_id   = :third
                  AND correspondent_id = :corr
                LIMIT 1
            ");
            $upd->execute([
                ":amt" => abs((float) $tx["total_commission"]),
                ":third" => (int) $tx["client_reference"],
                ":corr" => (int) $tx["id_correspondent"],
            ]);
            // Si no existe fila, no pasa nada (equivale a 0).
        }

        // Borrar la transacción
        $del = $pdo->prepare("DELETE FROM transactions WHERE id = :id LIMIT 1");
        $del->execute([":id" => $transactionId]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Transacción eliminada y comisión ajustada (si aplicaba).",
            "adjustment" => [
                "applied" => ($tx["third_party_note"] === "loan_from_third_party" && (float) $tx["total_commission"] != 0),
                "amount" => abs((float) $tx["total_commission"])
            ]
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Error en la base de datos: " . $e->getMessage()
        ]);
    }
    exit;
}

/**
 * ============================
 *  POST: (tú código de creación)
 *  — Sin cambios —
 * ============================
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset(
        $data["id_cashier"],
        $data["id_cash"],
        $data["id_correspondent"],
        $data["transaction_type_id"],
        $data["polarity"],
        $data["cost"],
        $data["cash_tag"] // ← requerido ahora
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para crear la transacción de compensación."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $polarity = boolval($data["polarity"]);
    $cost = floatval($data["cost"]);
    $cash_tag = trim($data["cash_tag"]);
    $utility = isset($data["utility"]) ? floatval($data["utility"]) : 0;
    $state = 1;
    $created_at = date("Y-m-d H:i:s");

    try {
        $typeStmt = $pdo->prepare("SELECT name, category FROM transaction_types WHERE id = :id");
        $typeStmt->bindParam(":id", $transaction_type_id, PDO::PARAM_INT);
        $typeStmt->execute();
        $type = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$type) {
            echo json_encode([
                "success" => false,
                "message" => "Tipo de transacción no encontrado."
            ]);
            exit;
        }

        $note = "offset_transaction";
        $neutral = 0;

        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, client_reference, utility,
                neutral, created_at, cash_tag
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, NULL, :utility,
                :neutral, :created_at, :cash_tag
            )
        ");

        $stmt->bindParam(":id_cashier", $id_cashier, PDO::PARAM_INT);
        $stmt->bindParam(":id_cash", $id_cash, PDO::PARAM_INT);
        $stmt->bindParam(":id_correspondent", $id_correspondent, PDO::PARAM_INT);
        $stmt->bindParam(":transaction_type_id", $transaction_type_id, PDO::PARAM_INT);
        $stmt->bindParam(":polarity", $polarity, PDO::PARAM_BOOL);
        $stmt->bindParam(":cost", $cost);
        $stmt->bindParam(":state", $state, PDO::PARAM_INT);
        $stmt->bindParam(":note", $note, PDO::PARAM_STR);
        $stmt->bindParam(":utility", $utility);
        $stmt->bindParam(":neutral", $neutral, PDO::PARAM_BOOL);
        $stmt->bindParam(":created_at", $created_at, PDO::PARAM_STR);
        $stmt->bindParam(":cash_tag", $cash_tag, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transacción de compensación registrada exitosamente.",
                "cash_tag" => $cash_tag
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar la transacción de compensación."
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error en la base de datos: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ]);
}
