<?php
/**
 * API Vista 360° del Equipo
 * GET ?id=X              — Datos del equipo + cliente asociado
 * GET ?id=X&timeline=1   — Timeline paginado del equipo
 */
header('Content-Type: application/json');
include '../config/auth.php';
requireLogin();
include '../config/db.php';
require_once __DIR__ . '/../src/Shared/TenantContext.php';

$reparacionRepo = new ReparacionRepository($supabase);
$clienteRepo = new ClienteRepository($supabase);
$timelineService = new EventoTimelineService($supabase);

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    if (!empty($_GET['timeline'])) {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 30)));
        $offset = ($page - 1) * $perPage;
        $total = 0;

        $eventos = $timelineService->getTimelineEquipo($id, $offset, $perPage, $total);

        $items = array_map(function ($e) {
            $config = EventoTimelineService::getEventoConfig($e['tipo']);
            $e['icon'] = $config['icon'];
            $e['color'] = $config['color'];
            $e['bg'] = $config['bg'];
            $e['label'] = $config['label'];
            $e['fecha_fmt'] = date('d M Y h:i A', strtotime($e['fecha']));
            $e['metadata'] = $e['metadata'] ? json_decode($e['metadata'], true) : null;
            return $e;
        }, $eventos);

        echo json_encode([
            'ok' => true,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'items' => $items,
        ]);
        exit;
    }

    $equipo = $reparacionRepo->findById($id);
    if (!$equipo) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Equipo no encontrado']);
        exit;
    }

    $equipo['modelo_completo'] = trim(($equipo['equipo_marca'] ?? '') . ' ' . ($equipo['equipo_modelo'] ?? ''));

    $cliente = null;
    if (!empty($equipo['cliente_id'])) {
        $cliente = $clienteRepo->findById((int) $equipo['cliente_id']);
    }

    // Resolver "Ingresado por" (nombre del usuario que creo la reparacion)
    $equipo['ingresado_por'] = '—';
    if (!empty($equipo['created_by_user_id'])) {
        $userResult = $supabase->get('usuarios', [
            'select' => 'nombre_completo',
            'tenant_id' => 'eq.' . TenantContext::requireTenant(),
            'id' => 'eq.' . $equipo['created_by_user_id'],
            'deleted_at' => 'is.null',
            'limit' => '1',
        ]);
        if ($userResult['ok'] && !empty($userResult['data'])) {
            $equipo['ingresado_por'] = $userResult['data'][0]['nombre_completo'] ?? '—';
        }
    }

    // Historial de mensajes
    $tenantId = TenantContext::requireTenant();
    $msgResult = $supabase->get('historial_mensajes', [
        'select' => '*',
        'tenant_id' => 'eq.' . $tenantId,
        'reparacion_id' => 'eq.' . $id,
        'order' => 'created_at.desc',
    ]);
    $mensajes = $msgResult['ok'] ? ($msgResult['data'] ?? []) : [];
    foreach ($mensajes as &$m) {
        $m['fecha_envio'] = $m['created_at'] ?? null;
    }
    unset($m);

    echo json_encode([
        'ok' => true,
        'data' => [
            'equipo' => $equipo,
            'cliente' => $cliente,
            'mensajes' => $mensajes,
        ],
    ]);

} catch (Throwable $e) {
    error_log('api_equipo_360: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al cargar datos del equipo']);
}
