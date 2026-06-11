<?php
/**
 * API — Busqueda semantica de inventario (RAG)
 *
 * GET  api/inventario/buscar.php?q=texto&limite=10
 *
 * Soporta autenticacion por JWT (usuario web) o API key (n8n).
 * Sin autenticacion, usa TENANT_ID_DEFAULT.
 */
require_once __DIR__ . '/../../config/env_loader.php';

$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

// ─── Obtener query ───
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = trim($_GET['q'] ?? '');
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = trim($input['q'] ?? '');
}

if ($query === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametro q requerido']);
    exit;
}

if (mb_strlen($query) > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Query demasiado larga (max 500 caracteres)']);
    exit;
}

$limit = min((int)($_GET['limite'] ?? $_GET['limit'] ?? 10), 30);

// ─── Autenticacion / Tenant ───
require_once __DIR__ . '/../../config/auth_jwt.php';
require_once __DIR__ . '/../../src/Shared/SupabaseClient.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';
require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingRepository.php';
require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingService.php';

$supabase = new SupabaseClient();

$tenantId = null;

// 1. Intentar JWT de usuario web
$jwt = getJwtFromRequest();
if ($jwt && jwtIsValid($jwt)) {
    $claims = jwtDecode($jwt);
    $tenantId = (int)($claims['app_metadata']['tenant_id'] ?? 0);
}

// 2. Intentar API key (para n8n)
if (!$tenantId || $tenantId < 1) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $configuredKey = getenv('API_INVENTARIO_KEY') ?: '';
    if ($apiKey !== '' && $configuredKey !== '' && hash_equals($configuredKey, $apiKey)) {
        $tenantId = (int)(getenv('TENANT_ID_DEFAULT') ?: 1);
    }
}

if (!$tenantId || $tenantId < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Autenticacion requerida']);
    exit;
}

TenantContext::setTenantId($tenantId);

// ─── API Key DeepSeek ───
$deepseekApiKey = getenv('DEEPSEEK_API_KEY');
if (empty($deepseekApiKey)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'API Key de IA no configurada']);
    exit;
}

// ─── Ejecutar busqueda ───
try {
    $embeddingRepo = new InventarioEmbeddingRepository($supabase);
    $embeddingService = new InventarioEmbeddingService($embeddingRepo, $deepseekApiKey);

    $resultado = $embeddingService->buscar($tenantId, $query, $limit);

    if (!$resultado['ok']) {
        http_response_code(500);
        echo json_encode($resultado);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'query' => $query,
        'total' => count($resultado['resultados']),
        'resultados' => $resultado['resultados'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('buscar inventario RAG: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno de busqueda']);
}
