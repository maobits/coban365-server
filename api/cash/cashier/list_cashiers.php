<?php
/**
 * Archivo: list_cashier.php
 * Descripción: Lista los usuarios con rol 'cajero' e incluye los nombres de los corresponsales asignados.
 *              Filtra por un ID de corresponsal recibido como parámetro GET.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.1.1
 * Fecha de actualización: 10-Abr-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once '../../db.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $correspondentId = isset($_GET['correspondent_id']) ? intval($_GET['correspondent_id']) : null;

    $sql = "SELECT 
                id, 
                email, 
                fullname, 
                phone, 
                status, 
                role, 
                permissions,
                correspondents,
                created_at, 
                updated_at 
            FROM users 
            WHERE role = 'cajero'
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filteredUsers = [];

    foreach ($users as $user) {
        $user['correspondent_data'] = [];

        if (!empty($user['correspondents'])) {
            $decoded = json_decode($user['correspondents'], true); // [{"id":3}, {"id":5}]

            // Extraer solo los IDs
            $corrIds = is_array($decoded) ? array_column($decoded, 'id') : [];

            if (count($corrIds) > 0) {
                // Filtrar por corresponsal si fue enviado
                $includeUser = is_null($correspondentId) || in_array($correspondentId, $corrIds);

                if ($includeUser) {
                    // Cargar nombres de corresponsales
                    $placeholders = implode(',', array_fill(0, count($corrIds), '?'));
                    $query = "SELECT id, name FROM correspondents WHERE id IN ($placeholders)";
                    $stmtCorr = $conn->prepare($query);
                    $stmtCorr->execute($corrIds);
                    $user['correspondent_data'] = $stmtCorr->fetchAll(PDO::FETCH_ASSOC);

                    $filteredUsers[] = $user;
                }
            }
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $filteredUsers
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en la consulta: " . $e->getMessage()
    ]);
}
