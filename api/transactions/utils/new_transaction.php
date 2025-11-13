<?php
date_default_timezone_set('America/Bogota'); // Hora local de Bogotá

/**
 * Archivo: new_transaction.php
 * Descripción: Registra una nueva transacción con utilidad, nombre de tipo, valor opcional client_reference y cash_tag.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.4.0
 * Fecha de actualización: 07-Sep-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../db.php";

/* ======================= Utilidades de cálculo ======================= */

/** Normaliza: minúsculas, sin tildes, sin dobles espacios. */
function normalize_str(?string $s): string
{
    if ($s === null)
        return '';
    if (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($tmp !== false)
            $s = $tmp;
    } else {
        $s = strtr($s, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N'
        ]);
    }
    $s = mb_strtolower($s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s ?: '';
}

/**
 * Regla general “Ingresos” (Depósito, Recaudos, Abono TC, Pago crédito, Entrega en efectivo, etc.)
 *  - 0.20% del valor (0.002)
 *  - Mínimo $160
 *  - Máximo $1.600
 */
function calcUtilityIncomes(float $amount): float
{
    if ($amount <= 0)
        return 0.0;
    if ($amount <= 80000)
        return 160.0;
    if ($amount >= 800000)
        return 1600.0;
    return round($amount * 0.002, 0);
}

/**
 * Regla “Retiros” (Retiro, Retiro con tarjeta, Retiro Nequi):
 *  - 1 .. 80.000  => $80
 *  - >= 800.000   => $800
 *  - (80.000, 800.000) => 0.10% (0.001)
 */
function calcUtilityWithdrawal(float $amount): float
{
    if ($amount <= 0)
        return 0.0;
    if ($amount <= 80000)
        return 80.0;
    if ($amount >= 800000)
        return 800.0;
    return round($amount * 0.001, 0);
}

/**
 * Regla fallback (por si cae en otra categoría distinta a Ingresos/Retiros):
 *  - 0.10%, Mín 80, Máx 800
 */
function calcUtilityFallback(float $amount): float
{
    if ($amount <= 0)
        return 0.0;
    if ($amount <= 80000)
        return 80.0;
    if ($amount >= 800000)
        return 800.0;
    return round($amount * 0.001, 0);
}

/**
 * Determina la utilidad a partir de:
 * 1) Category/name que contengan “ingreso(s)” → reglas de Ingresos (0.20%, min 160, max 1600)
 * 2) Category/name que contengan “retiro(s)” → reglas de Retiros (0.10%, min 80, max 800)
 * 3) Si no se puede determinar, aplica fallback 0.10%, min 80, max 800.
 *
 * Devuelve [float $utility, string $bucket] donde $bucket ∈ {incomes, withdrawals, fallback}
 */
function resolveUtilityByType(?string $typeName, ?string $category, float $amount): array
{
    $nName = normalize_str($typeName);
    $nCat = normalize_str($category);

    // Palabras clave (sin tildes) para decidir el “bucket”
    $incomeKeys = ['ingreso', 'ingresos', 'deposito', 'recaudo', 'recaudos', 'abono a tarjeta', 'abono tarjeta', 'pago de credito', 'pago credito', 'entrega en efectivo'];
    $withdrawKeys = ['retiro', 'retiros', 'retiro con tarjeta', 'retiro nequi', 'salida', 'salidas'];

    $containsAny = function (string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false)
                return true;
        }
        return false;
    };

    // 1) Decisión por categoría primero (más robusto si la BD clasifica bien)
    if ($nCat !== '') {
        if ($containsAny($nCat, $withdrawKeys)) {
            return [calcUtilityWithdrawal($amount), 'withdrawals'];
        }
        if ($containsAny($nCat, $incomeKeys) || $nCat === 'ingresos' || $nCat === 'ingreso') {
            return [calcUtilityIncomes($amount), 'incomes'];
        }
    }

    // 2) Decisión por nombre del tipo
    if ($nName !== '') {
        if ($containsAny($nName, $withdrawKeys)) {
            return [calcUtilityWithdrawal($amount), 'withdrawals'];
        }
        if ($containsAny($nName, $incomeKeys)) {
            return [calcUtilityIncomes($amount), 'incomes'];
        }
    }

    // 3) Fallback
    return [calcUtilityFallback($amount), 'fallback'];
}
/* ===================== Fin utilidades de cálculo ===================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset(
        $data["id_cashier"],
        $data["id_cash"],
        $data["id_correspondent"],
        $data["transaction_type_id"],
        $data["polarity"],
        $data["cost"]
    )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios para crear la transacción."
        ]);
        exit;
    }

    $id_cashier = intval($data["id_cashier"]);
    $id_cash = intval($data["id_cash"]);
    $id_correspondent = intval($data["id_correspondent"]);
    $transaction_type_id = intval($data["transaction_type_id"]);
    $polarity = boolval($data["polarity"]);
    $cost = floatval($data["cost"]); // puede venir 0
    $client_reference = isset($data["client_reference"]) ? $data["client_reference"] : null;
    $cash_tag = isset($data["cash_tag"]) ? floatval($data["cash_tag"]) : null; // 🆕 nuevo campo
    $state = 1;
    $created_at = date("Y-m-d H:i:s"); // Bogotá

    try {
        // Obtener el nombre y categoría del tipo de transacción
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

        /* ======================================================
         *  REGLA ESPECIAL: SOLO PARA "Retiro comisiones"
         *  - Se valida SOLO contra cost
         *  - Si no hay comisiones acumuladas (suma = 0) -> error
         *  - Si cost > suma acumulada -> error
         *  - Si pasa, se descuenta del pool third_party_commissions
         * ====================================================== */
        $normalizedTypeName = normalize_str($type["name"] ?? '');
        $isCommissionWithdrawal = ($normalizedTypeName === 'retiro comisiones');

        if ($isCommissionWithdrawal) {
            // 1) cost debe ser > 0
            if ($cost <= 0) {
                echo json_encode([
                    "success" => false,
                    "error" => "INVALID_COMMISSION_COST",
                    "message" => "El valor del retiro de comisiones debe ser mayor a 0."
                ]);
                exit;
            }

            // 2) Consultar total de comisiones acumuladas del corresponsal
            $commStmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_commission), 0) AS total_commission
                FROM third_party_commissions
                WHERE correspondent_id = :cid
            ");
            $commStmt->bindParam(":cid", $id_correspondent, PDO::PARAM_INT);
            $commStmt->execute();
            $commRow = $commStmt->fetch(PDO::FETCH_ASSOC);

            $availableCommission = isset($commRow["total_commission"])
                ? floatval($commRow["total_commission"])
                : 0.0;

            // 3) Si no hay comisiones acumuladas
            if ($availableCommission <= 0) {
                echo json_encode([
                    "success" => false,
                    "error" => "NO_COMMISSION_AVAILABLE",
                    "message" => "Este corresponsal no tiene comisiones acumuladas para retirar.",
                    "available_commission" => $availableCommission,
                    "requested_cost" => $cost,
                    "id_correspondent" => $id_correspondent
                ]);
                exit;
            }

            // 4) Si intentan retirar más de lo acumulado
            if ($cost > $availableCommission) {
                echo json_encode([
                    "success" => false,
                    "error" => "COMMISSION_WITHDRAWAL_EXCEEDS_TOTAL",
                    "message" => "No es posible registrar el retiro de comisiones porque el valor solicitado supera las comisiones acumuladas.",
                    "available_commission" => $availableCommission,
                    "requested_cost" => $cost,
                    "id_correspondent" => $id_correspondent
                ]);
                exit;
            }

            // ⚠️ Aquí ya NO se descuenta en PHP.
            // El descuento real se hace en el trigger AFTER INSERT de MySQL.
        }

        // === Monto base para la comisión ===
        // Si cost <= 0 pero cash_tag viene con el valor real, úsalo.
        $amountForFee = $cost;
        if ($amountForFee <= 0 && $cash_tag !== null && $cash_tag > 0) {
            $amountForFee = $cash_tag;
        }

        // Calcular utilidad con nueva lógica de Ingresos/Retiros
        [$utility, $bucket] = resolveUtilityByType($type["name"] ?? null, $type["category"] ?? null, $amountForFee);

        $note = "-";
        $neutral = (normalize_str($type["category"] ?? '') === 'otros') ? 1 : 0;

        // Insertar transacción
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id_cashier, id_cash, id_correspondent,
                transaction_type_id, polarity, cost,
                state, note, client_reference, utility, neutral, created_at, cash_tag
            ) VALUES (
                :id_cashier, :id_cash, :id_correspondent,
                :transaction_type_id, :polarity, :cost,
                :state, :note, :client_reference, :utility, :neutral, :created_at, :cash_tag
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
        $stmt->bindParam(":client_reference", $client_reference, PDO::PARAM_STR);
        $stmt->bindParam(":utility", $utility);
        $stmt->bindParam(":neutral", $neutral, PDO::PARAM_BOOL);
        $stmt->bindParam(":created_at", $created_at);
        $stmt->bindParam(":cash_tag", $cash_tag);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Transacción registrada exitosamente.",
                "timestamp" => $created_at,
                "calculated_utility" => $utility,
                "type_name" => $type["name"],
                "type_category" => $type["category"],
                "amount_used_for_fee" => $amountForFee,
                "rule_bucket" => $bucket
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al registrar la transacción."
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
?>