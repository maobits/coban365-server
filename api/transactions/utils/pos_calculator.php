<?php
/**
 * POS Calculator API
 * Path: api/transactions/utils/pos_calculator.php
 *
 * Acciones soportadas:
 *  - POST {"action":"create", ...}     -> Crea un registro
 *  - POST {"action":"update", ...}     -> Actualiza por id
 *  - POST {"action":"delete", "id":N}  -> Elimina por id
 *
 *  - GET  ?id=N                        -> Obtiene un registro
 *  - GET  ?cash_id=1&from=YYYY-MM-DD&to=YYYY-MM-DD&limit=50&offset=0
 *                                      -> Lista por caja y rango de fechas (opcional)
 *  - DELETE ?id=N                      -> Elimina por id
 *
 * Requiere: api/db.php con función db():PDO o variable $pdo.
 */

declare(strict_types=1);

date_default_timezone_set('America/Bogota');
ini_set('default_charset', 'UTF-8');
// Evita que warnings rompan el JSON de respuesta
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --------- CORS ---------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --------- DB ---------
require_once __DIR__ . "/../../db.php";

function pdo_from_db(): PDO
{
    // 1) Si existe función db()
    if (function_exists('db')) {
        $pdo = db();
        if ($pdo instanceof PDO) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET NAMES utf8mb4");
            return $pdo;
        }
    }
    // 2) Si hay $pdo global
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $GLOBALS['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['pdo']->exec("SET NAMES utf8mb4");
        return $GLOBALS['pdo'];
    }
    throw new RuntimeException("No se pudo obtener la conexión PDO desde db.php");
}

$pdo = pdo_from_db();

// --------- Helpers ---------
$respond = function (array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
};

function int_or_null($v)
{
    if ($v === null || $v === '' || !is_numeric($v))
        return null;
    return (int) $v;
}
function str_or_null($v)
{
    if ($v === null)
        return null;
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}
function clean_int($v): int
{
    // Limpia separadores, deja solo números y signo
    if (is_string($v))
        $v = preg_replace('/[^\d\-]/', '', $v);
    return (int) $v;
}
function now(): string
{
    return date('Y-m-d H:i:s');
}
function today(): string
{
    return date('Y-m-d');
}

// ==========================================================
//                      ROUTER
// ==========================================================
try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ------------------- GET -------------------
    if ($method === 'GET') {

        // GET ?id=N
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int) $_GET['id'];

            $sql = "SELECT *
                FROM pos_calculator
               WHERE id = :id
               LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row)
                $respond(['success' => false, 'message' => 'No encontrado'], 404);

            // Decodifica items_json si es JSON válido
            if (!empty($row['items_json'])) {
                $decoded = json_decode($row['items_json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['items'] = $decoded;
                }
            }
            $respond(['success' => true, 'data' => $row]);
        }

        // GET ?cash_id=&from=&to=&limit=&offset=
        if (isset($_GET['cash_id']) && is_numeric($_GET['cash_id'])) {
            $cashId = (int) $_GET['cash_id'];
            $from = $_GET['from'] ?? null; // YYYY-MM-DD
            $to = $_GET['to'] ?? null; // YYYY-MM-DD
            $limit = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? max(1, (int) $_GET['limit']) : 50;
            $offset = (isset($_GET['offset']) && is_numeric($_GET['offset'])) ? max(0, (int) $_GET['offset']) : 0;

            $conds = ["cash_id = :cash_id"];
            $params = [":cash_id" => $cashId];

            if ($from) {
                $conds[] = "DATE(created_at) >= :from";
                $params[':from'] = $from;
            }
            if ($to) {
                $conds[] = "DATE(created_at) <= :to";
                $params[':to'] = $to;
            }

            $where = "WHERE " . implode(" AND ", $conds);

            $sql = "SELECT SQL_CALC_FOUND_ROWS *
                FROM pos_calculator
               $where
            ORDER BY created_at DESC, id DESC
               LIMIT :limit OFFSET :offset";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v)
                $st->bindValue($k, $v);
            $st->bindValue(':limit', $limit, PDO::PARAM_INT);
            $st->bindValue(':offset', $offset, PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $total = (int) $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

            // Decodifica items_json por conveniencia
            foreach ($rows as &$r) {
                if (!empty($r['items_json'])) {
                    $decoded = json_decode($r['items_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE)
                        $r['items'] = $decoded;
                }
            }

            $respond([
                'success' => true,
                'total' => $total,
                'items' => $rows,
                'page' => (int) floor($offset / $limit) + 1,
                'limit' => $limit
            ]);
        }

        $respond(['success' => false, 'message' => 'Parámetros GET inválidos. Usa ?id= o ?cash_id='], 400);
    }

    // ------------------- DELETE -------------------
    if ($method === 'DELETE') {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
        if (!isset($qs['id']) || !is_numeric($qs['id'])) {
            $respond(['success' => false, 'message' => 'Falta id en query (?id=)'], 400);
        }
        $id = (int) $qs['id'];

        $st = $pdo->prepare("DELETE FROM pos_calculator WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);

        if ($st->rowCount() > 0)
            $respond(['success' => true, 'message' => 'Eliminado']);
        $respond(['success' => false, 'message' => 'No existe o ya fue eliminado.'], 404);
    }

    // ------------------- POST (create/update/delete) -------------------
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data))
            $respond(['success' => false, 'message' => 'JSON inválido'], 400);

        $action = strtolower((string) ($data['action'] ?? 'create'));

        // ----- CREATE -----
        if ($action === 'create') {
            $cash_id = int_or_null($data['cash_id'] ?? $data['id_cash'] ?? null);
            $correspondent_id = int_or_null($data['correspondent_id'] ?? $data['id_correspondent'] ?? null);
            if (!$cash_id || !$correspondent_id) {
                $respond(['success' => false, 'message' => 'Faltan cash_id y/o correspondent_id'], 400);
            }

            $cashier_id = int_or_null($data['cashier_id'] ?? $data['id_cashier'] ?? null);
            $customer_name = str_or_null($data['customer_name'] ?? null);
            $customer_phone = str_or_null($data['customer_phone'] ?? null);

            $subtotal = clean_int($data['subtotal'] ?? 0);
            $discount = clean_int($data['discount'] ?? 0);
            $fee = clean_int($data['fee'] ?? 0);
            $total = clean_int($data['total'] ?? ($subtotal - $discount + $fee));

            $note = str_or_null($data['note'] ?? null);

            // items puede venir como arreglo/objeto o string JSON
            $items = $data['items'] ?? ($data['items_json'] ?? []);
            $itemsJson = is_string($items) ? $items : json_encode($items, JSON_UNESCAPED_UNICODE);

            $sql = "INSERT INTO pos_calculator
                (cash_id, correspondent_id, cashier_id, customer_name, customer_phone,
                 subtotal, discount, fee, total, note, items_json, created_at, updated_at)
              VALUES
                (:cash_id, :correspondent_id, :cashier_id, :customer_name, :customer_phone,
                 :subtotal, :discount, :fee, :total, :note, :items_json, :created_at, :updated_at)";
            $st = $pdo->prepare($sql);
            $ok = $st->execute([
                ':cash_id' => $cash_id,
                ':correspondent_id' => $correspondent_id,
                ':cashier_id' => $cashier_id,
                ':customer_name' => $customer_name,
                ':customer_phone' => $customer_phone,
                ':subtotal' => $subtotal,
                ':discount' => $discount,
                ':fee' => $fee,
                ':total' => $total,
                ':note' => $note,
                ':items_json' => $itemsJson,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);

            if (!$ok)
                $respond(['success' => false, 'message' => 'No se pudo crear el registro'], 500);

            $id = (int) $pdo->lastInsertId();
            $respond(['success' => true, 'id' => $id, 'message' => 'Registro creado correctamente.']);
        }

        // ----- UPDATE -----
        if ($action === 'update') {
            $id = int_or_null($data['id'] ?? null);
            if (!$id)
                $respond(['success' => false, 'message' => 'Falta id para actualizar'], 400);

            $fields = [];
            $params = [':id' => $id, ':updated_at' => now()];

            // mapa de alias -> columna
            $map = [
                'cash_id' => 'cash_id',
                'id_cash' => 'cash_id',
                'correspondent_id' => 'correspondent_id',
                'id_correspondent' => 'correspondent_id',
                'cashier_id' => 'cashier_id',
                'id_cashier' => 'cashier_id',
                'customer_name' => 'customer_name',
                'customer_phone' => 'customer_phone',
                'subtotal' => 'subtotal',
                'discount' => 'discount',
                'fee' => 'fee',
                'total' => 'total',
                'note' => 'note',
                'items' => 'items_json',
                'items_json' => 'items_json',
            ];

            foreach ($map as $inKey => $col) {
                if (!array_key_exists($inKey, $data))
                    continue;

                if (in_array($col, ['cash_id', 'correspondent_id', 'cashier_id'])) {
                    $val = int_or_null($data[$inKey]);
                } elseif (in_array($col, ['subtotal', 'discount', 'fee', 'total'])) {
                    $val = clean_int($data[$inKey]);
                } elseif ($col === 'items_json') {
                    $val = $data[$inKey];
                    $val = is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
                } else {
                    $val = str_or_null($data[$inKey]);
                }

                $fields[] = "$col = :$col";
                $params[":$col"] = $val;
            }

            if (empty($fields))
                $respond(['success' => false, 'message' => 'No hay campos para actualizar'], 400);

            $sql = "UPDATE pos_calculator
                 SET " . implode(', ', $fields) . ", updated_at = :updated_at
               WHERE id = :id
               LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($params);

            if ($st->rowCount() === 0) {
                $respond(['success' => false, 'message' => 'No se actualizó (id inexistente o datos idénticos).'], 200);
            }
            $respond(['success' => true, 'message' => 'Actualizado correctamente.']);
        }

        // ----- DELETE (por body) -----
        if ($action === 'delete') {
            $id = int_or_null($data['id'] ?? null);
            if (!$id)
                $respond(['success' => false, 'message' => 'Falta id para eliminar'], 400);

            $st = $pdo->prepare("DELETE FROM pos_calculator WHERE id = :id LIMIT 1");
            $st->execute([':id' => $id]);

            if ($st->rowCount() > 0)
                $respond(['success' => true, 'message' => 'Eliminado']);
            $respond(['success' => false, 'message' => 'No existe o ya fue eliminado.'], 404);
        }

        // Acción no reconocida
        $respond(['success' => false, 'message' => 'Acción POST inválida. Usa create | update | delete'], 400);
    }

    // Método no permitido
    $respond(['success' => false, 'message' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    $respond([
        'success' => false,
        'message' => 'Error interno',
        'detail' => $e->getMessage()
    ], 500);
}
