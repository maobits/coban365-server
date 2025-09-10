<?php
/**
 * Archivo: commissions.php
 * Descripción: Calcula comisiones por rangos para categorías Ingresos y Retiros.
 * - Si viene id_cash => calcula para esa caja.
 * - Si NO viene id_cash pero viene id_correspondent => calcula para TODAS las cajas del corresponsal.
 * Muestra: número de transacciones, valor total por rango, tarifa (min/%/tope) y total de comisión por rango.
 * Proyecto: COBAN365
 * Versión: 1.3.0
 * Fecha: 07-Sep-2025
 *
 * Parámetros (GET):
 *  - id_cash (opcional): ID de la caja (tiene prioridad si viene).
 *  - id_correspondent (opcional): ID del corresponsal (solo si no viene id_cash).
 *  - date (opcional, YYYY-MM-DD): filtra por ese día exacto (DATE(t.created_at) = date).
 *  - start_date / end_date (opcional, YYYY-MM-DD): rango de fechas. Si existe `date`, tiene prioridad.
 *  - per_correspondent (opcional, 0/1): agrega desglose por corresponsal.
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

/* ---------- Lectura de parámetros ---------- */
$id_cash = isset($_GET["id_cash"]) ? intval($_GET["id_cash"]) : null;
$id_correspondent = isset($_GET["id_correspondent"]) ? intval($_GET["id_correspondent"]) : null;
$dateFilter = isset($_GET["date"]) ? trim($_GET["date"]) : null;            // día exacto
$startDate = isset($_GET["start_date"]) ? trim($_GET["start_date"]) : null; // rango desde
$endDate = isset($_GET["end_date"]) ? trim($_GET["end_date"]) : null;     // rango hasta
$wantPerCorrespondent = isset($_GET["per_correspondent"]) ? (intval($_GET["per_correspondent"]) === 1) : false;

/* ---------- Validación flexible ---------- */
if (!$id_cash && !$id_correspondent) {
    echo json_encode([
        "success" => false,
        "message" => "Debe enviar 'id_cash' o 'id_correspondent'."
    ]);
    exit();
}

/* ---------- Utilidades ---------- */
function commission_income(float $amount): float
{
    // Ingresos: 0.20% (0.002), min 160, max 1600
    if ($amount <= 0)
        return 0.0;
    if ($amount <= 80000)
        return 160.0;
    if ($amount >= 800000)
        return 1600.0;
    return round($amount * 0.002, 0);
}
function commission_withdraw(float $amount): float
{
    // Retiros: 0.10% (0.001), min 80, max 800
    if ($amount <= 0)
        return 0.0;
    if ($amount <= 80000)
        return 80.0;
    if ($amount >= 800000)
        return 800.0;
    return round($amount * 0.001, 0);
}
function range_bucket(float $amount): string
{
    if ($amount <= 80000)
        return 'leq80';
    if ($amount >= 800000)
        return 'gte800';
    return 'between';
}
function init_bucket()
{
    return ['count' => 0, 'total_amount' => 0.0, 'total_commission' => 0.0];
}
function init_category_summary(string $category): array
{
    if ($category === 'Ingresos') {
        return [
            'tariffs' => [
                'leq80' => ['label_min' => 160, 'label_pct' => '0.20%', 'label_cap' => null],
                'between' => ['label_min' => null, 'label_pct' => '0.20%', 'label_cap' => null],
                'gte800' => ['label_min' => null, 'label_pct' => null, 'label_cap' => 1600],
            ],
            'buckets' => ['leq80' => init_bucket(), 'between' => init_bucket(), 'gte800' => init_bucket()],
            'totals' => ['count' => 0, 'total_amount' => 0.0, 'total_commission' => 0.0]
        ];
    }
    // Retiros
    return [
        'tariffs' => [
            'leq80' => ['label_min' => 80, 'label_pct' => '0.10%', 'label_cap' => null],
            'between' => ['label_min' => null, 'label_pct' => '0.10%', 'label_cap' => null],
            'gte800' => ['label_min' => null, 'label_pct' => null, 'label_cap' => 800],
        ],
        'buckets' => ['leq80' => init_bucket(), 'between' => init_bucket(), 'gte800' => init_bucket()],
        'totals' => ['count' => 0, 'total_amount' => 0.0, 'total_commission' => 0.0]
    ];
}

/* ---------- Armar conjunto de cajas objetivo ---------- */
try {
    $targetCashIds = [];

    if ($id_cash) {
        $targetCashIds = [$id_cash];
    } else {
        // Buscar todas las cajas del corresponsal
        $cstmt = $pdo->prepare("SELECT id FROM cash WHERE correspondent_id = :cid");
        $cstmt->execute([":cid" => $id_correspondent]);
        $targetCashIds = array_map(fn($r) => (int) $r["id"], $cstmt->fetchAll(PDO::FETCH_ASSOC));

        // Si no tiene cajas, devolvemos estructura válida vacía
        if (empty($targetCashIds)) {
            $response = [
                'success' => true,
                'filters' => [
                    'id_cash' => null,
                    'id_correspondent' => $id_correspondent,
                    'date' => $dateFilter,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'tariffs' => [
                    'Ingresos' => [
                        'leq80' => ['min' => 160, 'pct' => 0.002, 'cap' => null, 'label' => '$160 / 0.20% / $1.600 tope'],
                        'between' => ['min' => null, 'pct' => 0.002, 'cap' => null, 'label' => '0.20%'],
                        'gte800' => ['min' => null, 'pct' => null, 'cap' => 1600, 'label' => '$1.600'],
                    ],
                    'Retiros' => [
                        'leq80' => ['min' => 80, 'pct' => 0.001, 'cap' => null, 'label' => '$80 / 0.10% / $800 tope'],
                        'between' => ['min' => null, 'pct' => 0.001, 'cap' => null, 'label' => '0.10%'],
                        'gte800' => ['min' => null, 'pct' => null, 'cap' => 800, 'label' => '$800'],
                    ],
                ],
                'summary' => [
                    'Ingresos' => [
                        'ranges' => [
                            'leq80' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => 160, 'pct' => '0.20%', 'cap' => null], 'total_commission' => 0],
                            'between' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => null, 'pct' => '0.20%', 'cap' => null], 'total_commission' => 0],
                            'gte800' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => null, 'pct' => null, 'cap' => 1600], 'total_commission' => 0],
                        ],
                        'totals' => ['count' => 0, 'total_amount' => 0, 'total_commission' => 0],
                    ],
                    'Retiros' => [
                        'ranges' => [
                            'leq80' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => 80, 'pct' => '0.10%', 'cap' => null], 'total_commission' => 0],
                            'between' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => null, 'pct' => '0.10%', 'cap' => null], 'total_commission' => 0],
                            'gte800' => ['transactions' => 0, 'total_amount' => 0, 'tariff' => ['min' => null, 'pct' => null, 'cap' => 800], 'total_commission' => 0],
                        ],
                        'totals' => ['count' => 0, 'total_amount' => 0, 'total_commission' => 0],
                    ],
                ],
                'grand_total' => [
                    'detail' => [
                        ['label' => 'TRANSACCIONES DE ENTRADA', 'movements' => 0, 'commission' => 0],
                        ['label' => 'TRANSACCIONES DE SALIDA', 'movements' => 0, 'commission' => 0],
                    ],
                    'totals' => ['movements' => 0, 'commission' => 0]
                ],
            ];
            echo json_encode($response);
            exit();
        }
    }

    /* ---------- Construir placeholders únicos para ambos IN ---------- */
    // Para evitar reutilizar el mismo placeholder dos veces en la query.
    $inCash = [];
    $inBoxRef = [];
    $params = [];
    foreach ($targetCashIds as $i => $cid) {
        $phA = ":cid_a_$i";
        $phB = ":cid_b_$i";
        $inCash[] = $phA;
        $inBoxRef[] = $phB;
        $params[$phA] = $cid;
        $params[$phB] = $cid;
    }
    $inClauseCash = implode(',', $inCash);
    $inClauseBoxRef = implode(',', $inBoxRef);

    /* ---------- Consulta de datos (solo Ingresos/Retiros) ---------- */
    $sql = "
        SELECT 
            t.id, t.id_cash, t.box_reference, t.transaction_type_id, t.polarity,
            t.cost, t.cash_tag, t.state, t.created_at,
            tt.category AS type_category, tt.name AS type_name,
            c.name AS correspondent_name
        FROM transactions t
        LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
        LEFT JOIN correspondents c ON t.id_correspondent = c.id
        WHERE t.state = 1
          AND (t.id_cash IN ($inClauseCash) OR t.box_reference IN ($inClauseBoxRef))
          AND tt.category IN ('Ingresos', 'Retiros')
    ";

    // Filtro de fechas:
    // - Si viene "date" => día exacto (tiene prioridad)
    // - Si no, usar start_date / end_date si vienen
    if ($dateFilter) {
        $sql .= " AND DATE(t.created_at) = :dt ";
    } else {
        if ($startDate && $endDate) {
            $sql .= " AND DATE(t.created_at) BETWEEN :ds AND :de ";
        } elseif ($startDate) {
            $sql .= " AND DATE(t.created_at) >= :ds ";
        } elseif ($endDate) {
            $sql .= " AND DATE(t.created_at) <= :de ";
        }
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    if ($dateFilter) {
        $stmt->bindParam(':dt', $dateFilter);
    } else {
        if ($startDate)
            $stmt->bindParam(':ds', $startDate);
        if ($endDate)
            $stmt->bindParam(':de', $endDate);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estructuras de acumulación
    $summary = [
        'Ingresos' => init_category_summary('Ingresos'),
        'Retiros' => init_category_summary('Retiros'),
    ];
    $perCorrespondent = [];

    foreach ($rows as $r) {
        $category = $r['type_category']; // 'Ingresos' o 'Retiros'
        if ($category !== 'Ingresos' && $category !== 'Retiros')
            continue;

        // Monto base (si cost=0 usar cash_tag)
        $amount = isset($r['cost']) ? floatval($r['cost']) : 0.0;
        $cashTag = isset($r['cash_tag']) ? floatval($r['cash_tag']) : 0.0;
        if ($amount <= 0 && $cashTag > 0)
            $amount = $cashTag;

        $bucket = range_bucket($amount);
        $fee = ($category === 'Ingresos') ? commission_income($amount) : commission_withdraw($amount);

        // Acumular
        $summary[$category]['buckets'][$bucket]['count'] += 1;
        $summary[$category]['buckets'][$bucket]['total_amount'] += $amount;
        $summary[$category]['buckets'][$bucket]['total_commission'] += $fee;

        $summary[$category]['totals']['count'] += 1;
        $summary[$category]['totals']['total_amount'] += $amount;
        $summary[$category]['totals']['total_commission'] += $fee;

        if ($wantPerCorrespondent) {
            $corr = $r['correspondent_name'] ?? 'Sin corresponsal';
            if (!isset($perCorrespondent[$category]))
                $perCorrespondent[$category] = [];
            if (!isset($perCorrespondent[$category][$corr])) {
                $perCorrespondent[$category][$corr] = [
                    'count' => 0,
                    'total_amount' => 0.0,
                    'total_commission' => 0.0,
                ];
            }
            $perCorrespondent[$category][$corr]['count'] += 1;
            $perCorrespondent[$category][$corr]['total_amount'] += $amount;
            $perCorrespondent[$category][$corr]['total_commission'] += $fee;
        }
    }

    // Totales generales
    $grand = [
        'total_movements' => $summary['Ingresos']['totals']['count'] + $summary['Retiros']['totals']['count'],
        'commission_incomes' => $summary['Ingresos']['totals']['total_commission'],
        'commission_withdrawals' => $summary['Retiros']['totals']['total_commission'],
        'commission_total' => $summary['Ingresos']['totals']['total_commission'] + $summary['Retiros']['totals']['total_commission'],
    ];

    // Respuesta
    $response = [
        'success' => true,
        'filters' => [
            'id_cash' => $id_cash,
            'id_correspondent' => $id_correspondent,
            'date' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ],
        'tariffs' => [
            'Ingresos' => [
                'leq80' => ['min' => 160, 'pct' => 0.002, 'cap' => null, 'label' => '$160 / 0.20% / $1.600 tope'],
                'between' => ['min' => null, 'pct' => 0.002, 'cap' => null, 'label' => '0.20%'],
                'gte800' => ['min' => null, 'pct' => null, 'cap' => 1600, 'label' => '$1.600'],
            ],
            'Retiros' => [
                'leq80' => ['min' => 80, 'pct' => 0.001, 'cap' => null, 'label' => '$80 / 0.10% / $800 tope'],
                'between' => ['min' => null, 'pct' => 0.001, 'cap' => null, 'label' => '0.10%'],
                'gte800' => ['min' => null, 'pct' => null, 'cap' => 800, 'label' => '$800'],
            ],
        ],
        'summary' => [
            'Ingresos' => [
                'ranges' => [
                    'leq80' => [
                        'transactions' => $summary['Ingresos']['buckets']['leq80']['count'],
                        'total_amount' => $summary['Ingresos']['buckets']['leq80']['total_amount'],
                        'tariff' => ['min' => 160, 'pct' => '0.20%', 'cap' => null],
                        'total_commission' => $summary['Ingresos']['buckets']['leq80']['total_commission'],
                    ],
                    'between' => [
                        'transactions' => $summary['Ingresos']['buckets']['between']['count'],
                        'total_amount' => $summary['Ingresos']['buckets']['between']['total_amount'],
                        'tariff' => ['min' => null, 'pct' => '0.20%', 'cap' => null],
                        'total_commission' => $summary['Ingresos']['buckets']['between']['total_commission'],
                    ],
                    'gte800' => [
                        'transactions' => $summary['Ingresos']['buckets']['gte800']['count'],
                        'total_amount' => $summary['Ingresos']['buckets']['gte800']['total_amount'],
                        'tariff' => ['min' => null, 'pct' => null, 'cap' => 1600],
                        'total_commission' => $summary['Ingresos']['buckets']['gte800']['total_commission'],
                    ],
                ],
                'totals' => $summary['Ingresos']['totals'],
            ],
            'Retiros' => [
                'ranges' => [
                    'leq80' => [
                        'transactions' => $summary['Retiros']['buckets']['leq80']['count'],
                        'total_amount' => $summary['Retiros']['buckets']['leq80']['total_amount'],
                        'tariff' => ['min' => 80, 'pct' => '0.10%', 'cap' => null],
                        'total_commission' => $summary['Retiros']['buckets']['leq80']['total_commission'],
                    ],
                    'between' => [
                        'transactions' => $summary['Retiros']['buckets']['between']['count'],
                        'total_amount' => $summary['Retiros']['buckets']['between']['total_amount'],
                        'tariff' => ['min' => null, 'pct' => '0.10%', 'cap' => null],
                        'total_commission' => $summary['Retiros']['buckets']['between']['total_commission'],
                    ],
                    'gte800' => [
                        'transactions' => $summary['Retiros']['buckets']['gte800']['count'],
                        'total_amount' => $summary['Retiros']['buckets']['gte800']['total_amount'],
                        'tariff' => ['min' => null, 'pct' => null, 'cap' => 800],
                        'total_commission' => $summary['Retiros']['buckets']['gte800']['total_commission'],
                    ],
                ],
                'totals' => $summary['Retiros']['totals'],
            ],
        ],
        'grand_total' => [
            'detail' => [
                ['label' => 'TRANSACCIONES DE ENTRADA', 'movements' => $summary['Ingresos']['totals']['count'], 'commission' => $summary['Ingresos']['totals']['total_commission']],
                ['label' => 'TRANSACCIONES DE SALIDA', 'movements' => $summary['Retiros']['totals']['count'], 'commission' => $summary['Retiros']['totals']['total_commission']],
            ],
            'totals' => [
                'movements' => $grand['total_movements'],
                'commission' => $grand['commission_total']
            ]
        ],
    ];

    if ($wantPerCorrespondent) {
        $response['per_correspondent'] = $perCorrespondent;
    }

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al calcular comisiones: " . $e->getMessage()
    ]);
}
