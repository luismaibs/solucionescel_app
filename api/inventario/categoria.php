<?php

/**
 * API — Inventario por Categoría (read + delete)
 *
 * GET   ?categoria=servicios|baterias|pantallas|accesorios&page=1&per_page=50
 *        → Listado paginado de la categoría indicada
 *
 * POST  action=delete&categoria=X&id=Y
 *        → Soft delete del registro en la tabla correspondiente
 */

include __DIR__ . '/../../config/auth.php';
requireLogin();
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/embedding_helper.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $repo = new InventarioCategoriaRepository($supabase);
} catch (Throwable $e) {
    error_log('InventarioCategoriaRepository init error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error interno de inventario.']);
    exit;
}

$categoriasValidas = ['servicios', 'baterias', 'pantallas', 'accesorios'];

try {
    /* ───── POST: Soft delete ───── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action    = $_POST['action'] ?? '';
        $categoria = $_POST['categoria'] ?? '';
        $id        = (int) ($_POST['id'] ?? 0);

        if ($action !== 'delete') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Acción no reconocida.']);
            exit;
        }

        if (!in_array($categoria, $categoriasValidas, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Categoría no válida.']);
            exit;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID no válido.']);
            exit;
        }

        $deleted = $repo->softDeleteByCategoria($categoria, $id);

        if ($deleted) {
            $tenantId = TenantContext::requireTenant();
            desindexarEmbeddingSiDisponible($tenantId, $categoria, $id);
        }

        echo json_encode([
            'ok'      => $deleted,
            'message' => $deleted ? 'Registro eliminado.' : 'No se encontró el registro.',
        ]);
        exit;
    }

    /* ───── GET: Listado paginado ───── */
    $categoria = $_GET['categoria'] ?? '';

    if (!in_array($categoria, $categoriasValidas, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Categoría no válida.']);
        exit;
    }

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(5, min(200, (int) ($_GET['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;
    $total   = 0;

    switch ($categoria) {
        case 'servicios':
            $items = $repo->findServiciosPaginado($offset, $perPage, $total);
            break;
        case 'baterias':
            $items = $repo->findBateriasPaginado($offset, $perPage, $total);
            break;
        case 'pantallas':
            $items = $repo->findPantallasPaginado($offset, $perPage, $total);
            break;
        case 'accesorios':
            $items = $repo->findAccesoriosPaginado($offset, $perPage, $total);
            break;
    }

    echo json_encode([
        'ok'       => true,
        'categoria' => $categoria,
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'items'    => $items,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('inventario categoria error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error de base de datos.']);
} catch (Throwable $e) {
    error_log('inventario categoria throwable: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error interno.']);
}
