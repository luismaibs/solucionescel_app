<?php
/**
 * router.php — Router para el servidor PHP integrado
 * Emula el comportamiento de .htaccess para URLs limpias
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = ltrim($requestUri, '/');

if (!$requestUri || $requestUri === '') {
    return false;
}

// Carpetas estáticas
$staticDirs = ['api', 'assets', 'vendor', 'public', 'storage', 'downloads', 'includes', 'js', 'css', 'img'];
foreach ($staticDirs as $dir) {
    if (strpos($requestUri, $dir . '/') === 0) {
        $ext = pathinfo($requestUri, PATHINFO_EXTENSION);
        if ($ext === '') {
            break;
        }
        return false;
    }
}

$basePath = __DIR__ . DIRECTORY_SEPARATOR;
$filePath = str_replace('/', DIRECTORY_SEPARATOR, $requestUri);
$fullPath = $basePath . $filePath;

// Asegurar que la raíz del proyecto es el directorio actual (para que las rutas relativas funcionen)
chdir(__DIR__);

// Si es un archivo existente, servirlo
if (file_exists($fullPath) && is_file($fullPath)) {
    return false;
}

// Si tiene extensión estática, 404
$ext = pathinfo($requestUri, PATHINFO_EXTENSION);
$staticExts = ['php', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'eot', 'map', 'json', 'xml', 'txt'];
if ($ext && in_array(strtolower($ext), $staticExts)) {
    return false;
}

// Buscar archivo.php
$phpFile = $fullPath . '.php';
if (file_exists($phpFile)) {
    $_SERVER['REQUEST_URI'] = '/' . $requestUri . '.php';
    include $phpFile;
    return true;
}

// Fallback a index.php
$indexFile = $basePath . 'index.php';
if (file_exists($indexFile)) {
    $oldDir = getcwd();
    chdir(dirname($indexFile));
    include $indexFile;
    chdir($oldDir);
    return true;
}

http_response_code(404);
echo "404 Not Found";
return false;
