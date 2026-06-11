<?php
// Autoload de clases en src/ (Composer)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

function loadEnv($path)
{
    if (!file_exists($path)) {
        return; // Archivo .env no encontrado, continuar sin error
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return; // No se pudo leer el archivo
    }

    foreach ($lines as $line) {
        // Saltar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Saltar líneas vacías o sin '='
        if (strpos($line, '=') === false) {
            continue;
        }

        // Separar nombre y valor
        $parts = explode('=', $line, 2);

        // Verificar que existan ambas partes
        if (count($parts) !== 2) {
            continue;
        }

        list($name, $value) = $parts;

        // Limpiar espacios
        $name = trim($name);
        $value = trim($value);

        // Validar que el nombre no esté vacío
        if (empty($name)) {
            continue;
        }

        // Remover comillas del valor si existen
        if (
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }

        // Establecer la variable de entorno solo si no existe
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar el .env automáticamente al incluir este archivo
// Como estamos en config/, subimos un nivel con dirname(__DIR__)
loadEnv(dirname(__DIR__) . '/.env');

// Base path de la app (usar en redirectTo y otros)
if (!defined('APP_BASE_PATH')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $inSubdir = strpos($scriptName, '/modules/') !== false
             || strpos($scriptName, '/api/') !== false
             || strpos($scriptName, '/cron/') !== false;
    define('APP_BASE_PATH', $inSubdir ? '../' : './');
}
?>