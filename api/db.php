<?php
/**
 * Archivo: db.php
 * Descripción: Configuración de conexión a la base de datos MySQL.
 * Proyecto: COBAN365
 * Desarrollador: Mauricio Chara
 * Versión: 1.0.0
 * Fecha de creación: 08-Mar-2025
 */

$host = "localhost"; // Servidor de la base de datos
$dbname = "coban365"; // Nombre de la base de datos
$username = "root"; // Usuario de la base de datos
$password = ""; // Contraseña de la base de datos (cambiar en producción)

// Production

//$host = "195.35.61.14"; // Servidor de la base de datos
//$dbname = "u429495711_coban365"; // Nombre de la base de datos
//$username = "u429495711_coban365"; // Usuario de la base de datos
//$password = "Coban365@"; // Contraseña de la base de datos (cambiar en producción)


try {
    // Crear conexión con PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    // Configurar PDO para que lance excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si hay un error en la conexión, mostrar mensaje de error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>