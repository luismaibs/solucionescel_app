<?php
/**
 * api_guard.php — Bootstrap único para todos los endpoints de API.
 *
 * Incluir al inicio de cada api/*.php en lugar de las combinaciones sueltas
 * de auth.php + db.php + api_helper.php.
 *
 * Garantiza:
 *   - Content-Type JSON siempre establecido
 *   - Error reporting según APP_DEBUG
 *   - requireLogin() ejecutado (devuelve 401 JSON en requests XHR si la sesión expiró)
 *   - $supabase disponible (singleton via db.php)
 *   - jsonResponse() disponible (vía api_helper.php)
 *   - Soporte CORS preflight (OPTIONS)
 */

// Marca este request como llamada de API pura para que redirectTo() devuelva
// 401 JSON en vez de un Location redirect (incluso sin X-Fragment header).
if (!defined('IS_API_REQUEST')) {
    define('IS_API_REQUEST', true);
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_helper.php';

// ─── CORS preflight ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Debug / error visibility ─────────────────────────────────────────────────
$_appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($_appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
unset($_appDebug);

// ─── Content-Type ─────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ─── Autenticación ────────────────────────────────────────────────────────────
// requireLogin() está definida en auth.php. Para peticiones XHR (fetch desde JS)
// que llegan sin sesión válida devuelve 401 JSON automáticamente gracias a
// isFragmentRequest() que detecta la cabecera X-Fragment o ?fragment=1.
// Para peticiones de API puras (sin X-Fragment) forzamos el mismo comportamiento
// aquí antes de ejecutar cualquier lógica.
requireLogin();
