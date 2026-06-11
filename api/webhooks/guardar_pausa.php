<?php
require_once __DIR__ . '/../../config/env_loader.php';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
if (!is_array($data)) {
    $data = [];
}

$tenantId = isset($_SERVER['HTTP_X_TENANT_ID']) ? (int) $_SERVER['HTTP_X_TENANT_ID'] : (isset($data['tenant_id']) ? (int) $data['tenant_id'] : null);
if ($tenantId === null || $tenantId < 1) {
    $tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
}
TenantContext::setTenantId($tenantId);

$webhookSecret = getenv('WEBHOOK_SECRET');
if ($webhookSecret !== false && $webhookSecret !== '') {
    $provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $data['secret'] ?? null;
    if (!is_string($provided) || !hash_equals($webhookSecret, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado']);
        exit;
    }
}

$soporteRepo = new SoporteRepository($supabase);
$soporteService = new SoporteService($soporteRepo);

$respuesta = $soporteService->registrarPausaBot($data);
$code = ($respuesta['success'] ?? false) ? 200 : 400;
http_response_code($code);
echo json_encode($respuesta);
exit;
