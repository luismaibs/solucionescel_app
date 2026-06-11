<?php
include __DIR__ . '/../config/auth.php';
requireLogin();
include __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$repo = new ReparacionRepository($supabase);
$garantiaRepo = new GarantiaRepository($supabase);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'activos') {
        $mesAzulService = new MesAzulService($supabase, $repo, new MensajesService($supabase, ''), $garantiaRepo);
        $activos = $mesAzulService->obtenerDispositivosActivos();
        echo json_encode(['ok' => true, 'data' => $activos]);
        exit;
    }

    if ($action === 'historial') {
        $mesAzulService = new MesAzulService($supabase, $repo, new MensajesService($supabase, ''), $garantiaRepo);
        $historial = $mesAzulService->obtenerHistorial();
        echo json_encode(['ok' => true, 'data' => $historial]);
        exit;
    }

    if ($action === '90_dias') {
        $mesAzulService = new MesAzulService($supabase, $repo, new MensajesService($supabase, ''), $garantiaRepo);
        $lista90 = $mesAzulService->obtenerDispositivos90DiasOmas();
        echo json_encode(['ok' => true, 'data' => $lista90]);
        exit;
    }

    if ($action === 'ejecutar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $webhookN8n = getenv('N8N_WEBHOOK_NOTIFICAR');
        if (!$webhookN8n) {
            echo json_encode(['ok' => false, 'message' => 'N8N_WEBHOOK_NOTIFICAR no configurado']);
            exit;
        }
        $mensajes = new MensajesService($supabase, $webhookN8n);
        $mesAzulService = new MesAzulService($supabase, $repo, $mensajes, $garantiaRepo);
        $resultado = $mesAzulService->procesarMesAzulDiario();
        echo json_encode([
            'ok' => true,
            'message' => 'Proceso Mes Azul ejecutado',
            'inicio_enviados' => $resultado['inicio_enviados'],
            'final_enviados' => $resultado['final_enviados'],
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
