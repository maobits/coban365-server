<?php
/**
 * Archivo: report_general_figures.php
 * Descripción: Devuelve cifras generales del sistema (corresponsales, usuarios, terceros y transacciones activas).
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha: 06-Jun-2025
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once '../db.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $figures = [];

    // Total corresponsales
    $stmt = $pdo->query("SELECT COUNT(*) FROM correspondents");
    $figures["total_correspondents"] = (int) $stmt->fetchColumn();

    // Total usuarios
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $figures["total_users"] = (int) $stmt->fetchColumn();

    // Total terceros
    $stmt = $pdo->query("SELECT COUNT(*) FROM others");
    $figures["total_others"] = (int) $stmt->fetchColumn();

    // Total transacciones activas
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE state = 1");
    $figures["total_transactions_active"] = (int) $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "data" => $figures
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error en base de datos: " . $e->getMessage()
    ]);
}
