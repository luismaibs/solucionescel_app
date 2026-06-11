<?php
// api/api_notifications.php
// Endpoint para Long Polling de notificaciones
error_reporting(0);
include __DIR__ . '/../config/auth.php';
requireLogin();
include __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_helper.php';
require_once __DIR__ . '/../src/Shared/TenantContext.php';

$lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$tenantId = TenantContext::requireTenant();

try {
    $cincoMinAtras = date('Y-m-d\TH:i:s', strtotime('-5 minutes'));
    $result = $supabase->get('notificaciones_sistema', [
        'select' => '*',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'gt.' . $lastId,
        'created_at' => 'gt.' . $cincoMinAtras,
        'order' => 'id.asc',
    ]);
    $notificaciones = ($result['ok'] && !empty($result['data'])) ? $result['data'] : [];
    jsonResponse(['notificaciones' => $notificaciones, 'timestamp' => time()], 200);
} catch (Exception $e) {
    jsonResponse(['message' => 'Error DB'], 500);
}