<?php
/**
 * API Vista 360° del Cliente
 * GET ?id=X                — Datos del cliente + estadísticas + equipos
 * GET ?id=X&timeline=1     — Timeline paginado
 */
header('Content-Type: application/json');
include '../config/auth.php';
requireLogin();
include '../config/db.php';

$clienteRepo = new ClienteRepository($supabase);
$clienteService = new ClienteService($clienteRepo);

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

        $timelineService = new EventoTimelineService($supabase);
        $eventos = $timelineService->getTimelineClienteSoloAcciones($id, $offset, $perPage, $total);

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

    $datos = $clienteService->obtenerDatos360($id);

    if (!$datos) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => $datos]);

} catch (Throwable $e) {
    error_log('api_cliente_360: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al cargar datos del cliente']);
}
