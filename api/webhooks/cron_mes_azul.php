<?php
/**
 * Webhook para que n8n (u otro agente) ejecute el proceso Mes Azul diariamente.
 * Requiere WEBHOOK_SECRET en query (?secret=...) o header (X-Webhook-Secret).
 * No requiere sesión de usuario.
 */
require_once __DIR__ . '/../../config/env_loader.php';
header('Content-Type: application/json; charset=utf-8');

$secret = getenv('WEBHOOK_SECRET');
if ($secret !== false && $secret !== '') {
    $provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ($_GET['secret'] ?? $_POST['secret'] ?? null);
    if (!is_string($provided) || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado']);
        exit;
    }
}

require_once __DIR__ . '/../../config/db.php';

$webhookN8n = getenv('N8N_WEBHOOK_NOTIFICAR') ?: '';
$repo = new ReparacionRepository($supabase);
$garantiaRepo = new GarantiaRepository($supabase);
$mensajes = new MensajesService($supabase, $webhookN8n);
$mesAzulService = new MesAzulService($supabase, $repo, $mensajes, $garantiaRepo);

try {
    $resultado = $mesAzulService->procesarMesAzulDiario();
    echo json_encode([
        'ok' => true,
        'message' => 'Proceso Mes Azul ejecutado',
        'inicio_enviados' => $resultado['inicio_enviados'],
        'final_enviados' => $resultado['final_enviados'],
        'executed_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Mes Azul webhook error: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
