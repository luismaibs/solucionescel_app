<?php
// Recibe el status final de n8n
require_once __DIR__ . '/../../config/env_loader.php';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$tenantId = isset($_SERVER['HTTP_X_TENANT_ID']) ? (int) $_SERVER['HTTP_X_TENANT_ID'] : (is_array($data) && isset($data['tenant_id']) ? (int) $data['tenant_id'] : null);
if ($tenantId === null || $tenantId < 1) {
    $tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
}
TenantContext::setTenantId($tenantId);

$webhookSecret = getenv('WEBHOOK_SECRET');
if ($webhookSecret !== false && $webhookSecret !== '') {
    $provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ($data['secret'] ?? null);
    if (!is_string($provided) || !hash_equals($webhookSecret, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado']);
        exit;
    }
}

if (!isset($data['log_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
    exit;
}

$log_id = $data['log_id'];
$status = $data['status']; // 'enviado' o 'fallido'
$respuesta = isset($data['message']) ? (is_array($data['message']) ? json_encode($data['message']) : $data['message']) : '';

try {
    $supabase->patch('historial_mensajes', [
        'estado_envio' => $status,
        'respuesta_api' => $respuesta,
    ], [
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $log_id,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('webhook_status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al procesar la solicitud']);
}
?>