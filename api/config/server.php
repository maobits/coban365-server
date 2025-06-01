<?php
/**
 * Archivo: server.php
 * Descripción: Configuración de entorno y rutas base del proyecto COBAN365.
 */

// Cambia manualmente este valor: true para desarrollo, false para producción
$isDevelopment = true;

// Ruta base de la API (backend)
$baseUrlApi = $isDevelopment
    ? "http://localhost:8080/coban365/api"       // Desarrollo
    : "https://coban365.maobits.com/api";        // Producción

// Ruta base del frontend (HTML)
$baseUrlFront = $isDevelopment
    ? "http://localhost:1234/login"           // Desarrollo
    : "https://coban365.maobits.com";            // Producción

// Constantes para usar en el frontend PHP
define("BASE_URL_API", $baseUrlApi);
define("BASE_URL_FRONT", $baseUrlFront);

// (Opcional) Debug en CLI
if (php_sapi_name() === 'cli') {
    echo "Modo desarrollo: " . ($isDevelopment ? "Sí" : "No") . PHP_EOL;
    echo "BASE_URL_API: " . BASE_URL_API . PHP_EOL;
    echo "BASE_URL_FRONT: " . BASE_URL_FRONT . PHP_EOL;
}
?>