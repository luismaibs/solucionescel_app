<?php

/**
 * API — Servicios Generales (CRUD)
 *
 * POST  action=create  → Crea servicio + acciones (transacción)
 * POST  action=delete  → Soft delete
 */

include __DIR__ . '/../../config/auth.php';
requireLogin();

include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/embedding_helper.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

$repo    = new ServiciosGeneralesRepository($supabase);
$service = new ServiciosGeneralesService($repo);

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        /* ───── CREAR ───── */
        case 'create':
            $id = $service->crearServicio($_POST);

            indexarEmbeddingSiDisponible(TenantContext::requireTenant(), 'servicios', $id, [
                'subcategoria' => $_POST['subcategoria'] ?? '',
                'gama' => $_POST['gama'] ?? '',
            ]);

            echo json_encode([
                'ok'      => true,
                'message' => 'Servicio creado correctamente.',
                'id'      => $id,
            ]);
            break;

        /* ───── ELIMINAR ───── */
        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'ID no válido.']);
                exit;
            }

            $service->eliminarServicio($id);

            $tenantId = TenantContext::requireTenant();
            desindexarEmbeddingSiDisponible($tenantId, 'servicios', $id);

            echo json_encode([
                'ok'      => true,
                'message' => 'Servicio eliminado.',
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Acción no reconocida.']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'message' => $e->getMessage(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Error de base de datos.',
    ]);
}
