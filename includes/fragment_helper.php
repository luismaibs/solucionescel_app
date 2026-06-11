<?php
/**
 * Fragment Helper - Detecta requests de fragmento para navegación SPA
 *
 * Cuando el SPA router solicita solo el contenido del módulo (sin shell),
 * envía ?fragment=1 o el header X-Fragment: 1.
 */

$isFragment = isset($_GET['fragment']) && $_GET['fragment'] == '1';
if (!$isFragment) {
    $isFragment = isset($_SERVER['HTTP_X_FRAGMENT']) && $_SERVER['HTTP_X_FRAGMENT'] == '1';
}

// Base path para assets - funciona tanto en full render como en fragment
$fragment_asset_base = strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false ? '../' : './';
